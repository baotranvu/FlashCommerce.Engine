<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Infrastructure\Verification\VerificationStrategyFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * TC-UNIT-07: VerificationStrategyFactory Unit Tests
 * 
 * Verifies factory exception on unsupported channels. Zero-I/O.
 */
final class VerificationStrategyFactoryTest extends TestCase
{
    public function test_it_throws_exception_on_unsupported_channel(): void
    {
        $factory = new VerificationStrategyFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported verification channel [carrier_pigeon]');

        $factory->make('carrier_pigeon');
    }

    public function test_it_throws_exception_on_empty_channel_string(): void
    {
        $factory = new VerificationStrategyFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported verification channel []');

        $factory->make('');
    }
}
