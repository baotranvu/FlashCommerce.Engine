<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\E2E;

final class RegistrationRaceConditionTest extends IdentityE2ETestCase
{
    public function test_concurrent_registration_guarantees_exactly_one_winner_and_one_conflict(): void
    {
        $targetUrl = "{$this->baseUrl}/api/v1/auth/register";
        $email = 'race_phpunit_' . time() . '_' . random_int(1000, 9999) . '@test.com';

        $mh = curl_multi_init();
        $handles = [];

        for ($i = 1; $i <= 2; $i++) {
            $ch = curl_init($targetUrl);
            $payload = json_encode([
                'name' => "Racer {$i}",
                'email' => $email,
                'password' => 'secret123456',
            ]);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        $statuses = [];
        $bodies = [];

        foreach ($handles as $ch) {
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $bodyStr = (string) curl_multi_getcontent($ch);
            $statuses[] = $status;
            $bodies[] = json_decode($bodyStr, true);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        // Strict Invariants: Exactly one 201 Created and exactly one 409 Conflict
        $this->assertContains(201, $statuses, 'Expected exactly one registration request to succeed with HTTP 201');
        $this->assertContains(409, $statuses, 'Expected the conflicting registration request to fail with HTTP 409');

        // Verify the 409 response strictly complies with RFC 7807 Problem Details
        $conflictBody = $statuses[0] === 409 ? $bodies[0] : $bodies[1];
        $this->assertSame(409, $conflictBody['status']);
        $this->assertSame('Conflict', $conflictBody['title']);
        $this->assertSame('USER_ALREADY_EXISTS', $conflictBody['code']);
        $this->assertSame('/api/v1/auth/register', $conflictBody['instance']);
    }
}
