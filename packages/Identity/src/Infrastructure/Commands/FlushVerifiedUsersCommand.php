<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Infrastructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use JsonException;
use Throwable;

class FlushVerifiedUsersCommand extends Command
{
    protected $signature = 'auth:flush-verified
                            {--batch=100 : Maximum verified entries to process}
                            {--stream=stream:user_verified : Redis stream key}
                            {--group=identity_sync_group : Consumer group name}
                            {--consumer=sync_worker_1 : Consumer identifier}
                            {--dlq=verified_dlq : Dead letter queue key}';

    protected $description = 'Flush Redis verified user stream to MySQL in atomic micro-batches';

    public function handle(): int
    {
        $stream = (string) $this->option('stream');
        $group = (string) $this->option('group');
        $consumer = (string) $this->option('consumer');
        $dlq = (string) $this->option('dlq');
        $batchSize = (int) $this->option('batch');

        $this->ensureConsumerGroup($stream, $group);

        // Read up to $batchSize messages from Stream
        $messages = $this->readFromStream($stream, $group, $consumer, $batchSize);
        if (empty($messages)) {
            $this->info('No verified users pending in stream.');
            return self::SUCCESS;
        }

        $validEntries = [];
        $ackMessageIds = [];

        foreach ($messages as $messageId => $fields) {
            $rawPayload = $fields['payload'] ?? null;
            if (!$rawPayload) {
                // Toxic payload: route directly to DLQ
                $this->routeToDlq($dlq, $messageId, 'Missing payload field');
                $ackMessageIds[] = $messageId;
                continue;
            }

            try {
                $payload = json_decode((string) $rawPayload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->routeToDlq($dlq, $messageId, 'Corrupted JSON: ' . $e->getMessage(), (string) $rawPayload);
                $ackMessageIds[] = $messageId;
                continue;
            }

            if (!isset($payload['user_id'], $payload['email']) || !is_numeric($payload['user_id']) || (int) $payload['user_id'] <= 0) {
                $this->routeToDlq($dlq, $messageId, 'Invalid user ID or email', (string) $rawPayload);
                $ackMessageIds[] = $messageId;
                continue;
            }

            $validEntries[] = [
                'message_id' => $messageId,
                'user_id' => (int) $payload['user_id'],
                'email' => (string) $payload['email'],
                'verified_at' => $payload['verified_at'] ?? now()->toIso8601String(),
            ];
            $ackMessageIds[] = $messageId;
        }

        if (!empty($validEntries)) {
            // 🛡️ INVARIANT: Sort IDs ascending to eliminate InnoDB Deadlock (Error 1213)
            usort($validEntries, static fn (array $a, array $b): int => $a['user_id'] <=> $b['user_id']);

            // 🛡️ DUAL-KEY ATOMIC TRANSACTION: WHERE id = :id AND email = :email (Neutralizes Email Mutation Race)
            DB::transaction(static function () use ($validEntries) {
                foreach ($validEntries as $entry) {
                    DB::table('users')
                        ->where('id', $entry['user_id'])
                        ->where('email', $entry['email'])
                        ->whereNull('email_verified_at')
                        ->update([
                            'email_verified_at' => $entry['verified_at'],
                            'updated_at' => now(),
                        ]);
                }
            });
        }

        // Acknowledge processed stream entries
        if (!empty($ackMessageIds)) {
            Redis::xack($stream, $group, $ackMessageIds);
        }

        $this->info(sprintf(
            'Batch flush completed: %d valid users committed, %d message(s) acknowledged.',
            count($validEntries),
            count($ackMessageIds)
        ));

        return self::SUCCESS;
    }

    private function ensureConsumerGroup(string $stream, string $group): void
    {
        try {
            Redis::xgroup('CREATE', $stream, $group, '0', 'MKSTREAM');
        } catch (Throwable) {
            // Group already exists (BUSYGROUP), safe to proceed
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function readFromStream(string $stream, string $group, string $consumer, int $count): array
    {
        $raw = Redis::xreadgroup($group, $consumer, [$stream => '>'], $count);
        if (empty($raw) || !is_array($raw)) {
            return [];
        }

        $streamData = $raw[$stream] ?? reset($raw);
        if (!is_array($streamData)) {
            return [];
        }

        return $streamData;
    }

    private function routeToDlq(string $dlq, string $messageId, string $reason, ?string $raw = null): void
    {
        $record = json_encode([
            'message_id' => $messageId,
            'reason' => $reason,
            'raw_payload' => $raw,
            'failed_at' => now()->toIso8601String(),
        ]);

        Redis::rpush($dlq, (string) $record);
    }
}
