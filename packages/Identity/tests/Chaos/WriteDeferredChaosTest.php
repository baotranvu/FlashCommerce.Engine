<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Chaos;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use FlashCommerce\Identity\Http\Middleware\EnsureEmailVerifiedFastPath;
use FlashCommerce\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use FlashCommerce\Identity\Infrastructure\Verification\Stage3WriteDeferredVerificationStrategy;
use FlashCommerce\Identity\Tests\Integration\IdentityIntegrationTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

final class WriteDeferredChaosTest extends IdentityIntegrationTestCase
{
    private UserRepositoryInterface $userRepo;
    private Stage3WriteDeferredVerificationStrategy $stage3Strategy;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
        $this->userRepo = app(UserRepositoryInterface::class);
        $this->stage3Strategy = app(Stage3WriteDeferredVerificationStrategy::class);
    }

    private function createUnverifiedUser(string $email): UserEntity
    {
        return $this->userRepo->create([
            'name' => 'ChaosUser',
            'email' => $email,
            'password' => 'secret123456',
        ]);
    }

    public function test_chaos_fm02_poison_pill_routes_corrupt_payload_to_dlq_while_valid_users_commit(): void
    {
        $user1 = $this->createUnverifiedUser('valid1_' . uniqid() . '@test.com');
        $user2 = $this->createUnverifiedUser('valid2_' . uniqid() . '@test.com');

        $stream = 'stream:user_verified';

        // 1. Valid User 1
        Redis::xadd($stream, '*', ['payload' => json_encode([
            'user_id' => $user1->id,
            'email' => $user1->email,
            'verified_at' => now()->toIso8601String(),
        ])]);

        // 2. Poison Pill: Corrupt payload
        Redis::xadd($stream, '*', ['payload' => '{"corrupted_json_without_closing_bracket']);

        // 3. Valid User 2
        Redis::xadd($stream, '*', ['payload' => json_encode([
            'user_id' => $user2->id,
            'email' => $user2->email,
            'verified_at' => now()->toIso8601String(),
        ])]);

        // Run Micro-Batching Daemon
        $exitCode = Artisan::call('auth:flush-verified', [
            '--batch' => 10,
            '--stream' => $stream,
            '--dlq' => 'verified_dlq',
        ]);

        $this->assertSame(0, $exitCode);

        // Assert Valid Users are committed to MySQL
        $this->assertNotNull(UserModel::find($user1->id)->email_verified_at);
        $this->assertNotNull(UserModel::find($user2->id)->email_verified_at);

        // Assert Poison Pill was captured in DLQ
        $dlqItems = Redis::lrange('verified_dlq', 0, -1);
        $this->assertCount(1, $dlqItems);
        $this->assertStringContainsString('Corrupted JSON', (string) $dlqItems[0]);
    }

    public function test_chaos_fm03_duplicate_replay_is_strictly_idempotent(): void
    {
        $user = $this->createUnverifiedUser('idempotent_' . uniqid() . '@test.com');
        $stream = 'stream:user_verified';

        $payload = json_encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => now()->toIso8601String(),
        ]);

        // First Execution
        Redis::xadd($stream, '*', ['payload' => $payload]);
        Artisan::call('auth:flush-verified', ['--stream' => $stream]);

        $firstVerifiedAt = UserModel::find($user->id)->email_verified_at->toIso8601String();
        $this->assertNotNull($firstVerifiedAt);

        // Replay Duplicate Entry
        Redis::xadd($stream, '*', ['payload' => $payload]);
        Artisan::call('auth:flush-verified', ['--stream' => $stream]);

        $secondVerifiedAt = UserModel::find($user->id)->email_verified_at->toIso8601String();
        $this->assertSame($firstVerifiedAt, $secondVerifiedAt);
    }

    public function test_chaos_fm04_email_mutation_race_condition_neutralized_by_dual_key_binding(): void
    {
        $originalEmail = 'original_' . uniqid() . '@test.com';
        $user = $this->createUnverifiedUser($originalEmail);
        $stream = 'stream:user_verified';

        // Ingress creates payload tied to originalEmail
        Redis::xadd($stream, '*', ['payload' => json_encode([
            'user_id' => $user->id,
            'email' => $originalEmail,
            'verified_at' => now()->toIso8601String(),
        ])]);

        // Race Condition Exploit: Attacker mutates email in DB during delta-t window
        DB::table('users')->where('id', $user->id)->update(['email' => 'attacker_hacked_' . uniqid() . '@evil.com']);

        // Worker flushes batch
        Artisan::call('auth:flush-verified', ['--stream' => $stream]);

        // Assert Dual-Key Binding prevented verification of the mutated email!
        $dbUser = DB::table('users')->where('id', $user->id)->first();
        $this->assertNull($dbUser->email_verified_at, 'Mutated email must NOT be marked as verified');
    }

    public function test_chaos_fm05_timestamp_preserves_exact_ingress_epoch_rather_than_now(): void
    {
        $user = $this->createUnverifiedUser('timestamp_' . uniqid() . '@test.com');
        $stream = 'stream:user_verified';
        $ingressTime = '2026-10-25T10:15:30+00:00';

        Redis::xadd($stream, '*', ['payload' => json_encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => $ingressTime,
        ])]);

        Artisan::call('auth:flush-verified', ['--stream' => $stream]);

        $dbUser = UserModel::find($user->id);
        $this->assertSame('2026-10-25 10:15:30', $dbUser->email_verified_at->format('Y-m-d H:i:s'));
    }

    public function test_chaos_fm07_read_through_guard_returns_verified_status_instantly_during_consistency_window(): void
    {
        $user = $this->createUnverifiedUser('fastpath_' . uniqid() . '@test.com');

        // MySQL is still unverified (email_verified_at is null)
        $this->assertNull(UserModel::find($user->id)->email_verified_at);

        // Redis Fast-Path key is set by Lua script
        Redis::setex("user_verified:{$user->id}", 86400, '1');

        $middleware = new EnsureEmailVerifiedFastPath();
        $request = Request::create('/api/v1/user/checkout', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, function ($req) {
            return new Response('Passed Fast Path', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Passed Fast Path', $response->getContent());
    }
}
