<?php

namespace FlashCommerce\Identity\Infrastructure\Persistence\Eloquent;

use FlashCommerce\Identity\Domain\Entities\UserEntity;
use FlashCommerce\Identity\Domain\Exceptions\DatabaseUnavailableException;
use FlashCommerce\Identity\Domain\Exceptions\UserAlreadyExistsException;
use FlashCommerce\Identity\Domain\Repositories\UserRepositoryInterface;
use Illuminate\Database\QueryException;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(array $attributes): UserEntity
    {
        try {
            $model = UserModel::create([
                'name' => $attributes['name'] ?? null,
                'email' => strtolower(trim($attributes['email'])),
                'password' => $attributes['password'], // UserModel cast handles automatic hashing
            ]);

            return $this->toEntity($model);
        } catch (QueryException $e) {
            // 🛡️ ISOLATE DB EXCEPTIONS: Convert MySQL 1062 into Domain UserAlreadyExistsException
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
                throw new UserAlreadyExistsException();
            }

            // Connection / server down errors (MySQL 2002, 2003)
            if (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [2002, 2003], true)) {
                throw new DatabaseUnavailableException();
            }

            throw $e;
        }
    }

    public function findByEmail(string $email): ?UserEntity
    {
        try {
            $model = UserModel::where('email', strtolower(trim($email)))->first();

            return $model ? $this->toEntity($model) : null;
        } catch (QueryException $e) {
            if (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [2002, 2003], true)) {
                throw new DatabaseUnavailableException();
            }

            throw $e;
        }
    }

    public function findById(int $id): ?UserEntity
    {
        try {
            $model = UserModel::find($id);

            return $model ? $this->toEntity($model) : null;
        } catch (QueryException $e) {
            if (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [2002, 2003], true)) {
                throw new DatabaseUnavailableException();
            }

            throw $e;
        }
    }

    public function markEmailAsVerified(int $userId): bool
    {
        try {
            return (bool) UserModel::where('id', $userId)
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => now()]);
        } catch (QueryException $e) {
            if (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [2002, 2003], true)) {
                throw new DatabaseUnavailableException();
            }

            throw $e;
        }
    }

    private function toEntity(UserModel $model): UserEntity
    {
        return new UserEntity(
            id: (int) $model->id,
            name: $model->name,
            email: $model->email,
            createdAt: $model->created_at?->toIso8601String(),
            emailVerifiedAt: $model->email_verified_at?->toIso8601String()
        );
    }
}
