<?php

namespace FlashCommerce\Identity\Domain\Repositories;

use FlashCommerce\Identity\Domain\Entities\UserEntity;

interface UserRepositoryInterface
{
    /**
     * Persist a new user into the storage engine.
     *
     * @param array{name?: string|null, email: string, password: string} $attributes
     */
    public function create(array $attributes): UserEntity;

    /**
     * Retrieve a user by email address.
     */
    public function findByEmail(string $email): ?UserEntity;

    /**
     * Retrieve a user by unique identifier.
     */
    public function findById(int $id): ?UserEntity;

    /**
     * Mark a user's email as verified in the database.
     */
    public function markEmailAsVerified(int $userId): bool;
}
