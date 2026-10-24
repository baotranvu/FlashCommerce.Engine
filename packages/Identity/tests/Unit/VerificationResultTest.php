<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Domain\Verification\VerificationResult;
use PHPUnit\Framework\TestCase;

class VerificationResultTest extends TestCase
{
    public function test_success_result_instantiation(): void
    {
        $result = VerificationResult::success('Operation succeeded.');

        $this->assertTrue($result->success);
        $this->assertSame('Operation succeeded.', $result->message);
        $this->assertNull($result->errorCode);
    }

    public function test_failure_result_instantiation(): void
    {
        $result = VerificationResult::failure('Invalid OTP code.', 'INVALID_OTP');

        $this->assertFalse($result->success);
        $this->assertSame('Invalid OTP code.', $result->message);
        $this->assertSame('INVALID_OTP', $result->errorCode);
    }
}
