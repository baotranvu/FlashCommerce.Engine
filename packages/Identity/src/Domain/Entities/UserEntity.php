<?php

namespace FlashCommerce\Identity\Domain\Entities;

readonly class UserEntity
{
    public function __construct(
        public int $id,
        public ?string $name,
        public string $email,
        public ?string $createdAt = null,
        public ?string $emailVerifiedAt = null
    ) {}

    public function isVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_verified' => $this->isVerified(),
            'created_at' => $this->createdAt,
            'email_verified_at' => $this->emailVerifiedAt,
        ];
    }
}
