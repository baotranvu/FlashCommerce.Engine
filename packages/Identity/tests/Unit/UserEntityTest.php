<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Unit;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use PHPUnit\Framework\TestCase;

class UserEntityTest extends TestCase
{
    public function test_user_entity_defaults_to_unverified(): void
    {
        $user = new UserEntity(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            createdAt: '2026-10-24T12:00:00Z'
        );

        $this->assertSame(1, $user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertNull($user->emailVerifiedAt);
        $this->assertFalse($user->isVerified());

        $array = $user->toArray();
        $this->assertFalse($array['is_verified']);
        $this->assertNull($array['email_verified_at']);
    }

    public function test_user_entity_recognizes_verified_status(): void
    {
        $verifiedAt = '2026-10-24T14:00:00Z';
        $user = new UserEntity(
            id: 2,
            name: 'Jane Doe',
            email: 'jane@example.com',
            createdAt: '2026-10-24T12:00:00Z',
            emailVerifiedAt: $verifiedAt
        );

        $this->assertTrue($user->isVerified());
        $this->assertSame($verifiedAt, $user->emailVerifiedAt);

        $array = $user->toArray();
        $this->assertTrue($array['is_verified']);
        $this->assertSame($verifiedAt, $array['email_verified_at']);
    }
}
