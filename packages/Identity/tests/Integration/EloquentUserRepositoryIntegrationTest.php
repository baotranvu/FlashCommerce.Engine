<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\Integration;

use FlashCommerce\Identity\Domain\Exceptions\UserAlreadyExistsException;
use FlashCommerce\Identity\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * TC-INT-01, TC-INT-02, TC-INT-03: EloquentUserRepository Integration Tests
 * 
 * Runs against real MySQL 8.0 in Docker with DatabaseTransactions rollback.
 */
final class EloquentUserRepositoryIntegrationTest extends IdentityIntegrationTestCase
{
    use DatabaseTransactions;

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository();
    }

    /**
     * TC-INT-01: Persist new user entity to MySQL 8.0.
     */
    public function test_it_persists_user_to_real_mysql_and_maps_back_to_entity(): void
    {
        $uniqueEmail = 'int_test_' . uniqid() . '@example.com';

        $entity = $this->repository->create([
            'name' => 'Integration Tester',
            'email' => $uniqueEmail,
            'password' => 'SecureSecret123!',
        ]);

        $this->assertNotNull($entity->id);
        $this->assertSame($uniqueEmail, $entity->email);
        $this->assertSame('Integration Tester', $entity->name);
        $this->assertNull($entity->emailVerifiedAt);

        // Verify retrieval by ID and by Email
        $foundById = $this->repository->findById($entity->id);
        $this->assertNotNull($foundById);
        $this->assertSame($entity->id, $foundById->id);

        $foundByEmail = $this->repository->findByEmail($uniqueEmail);
        $this->assertNotNull($foundByEmail);
        $this->assertSame($entity->id, $foundByEmail->id);
    }

    /**
     * TC-INT-02: Catch MySQL 1062 duplicate entry and convert to UserAlreadyExistsException.
     */
    public function test_it_catches_mysql_unique_constraint_collision_and_throws_domain_exception(): void
    {
        $duplicateEmail = 'duplicate_' . uniqid() . '@example.com';

        // First insert succeeds
        $this->repository->create([
            'name' => 'First User',
            'email' => $duplicateEmail,
            'password' => 'Password123!',
        ]);

        // Second insert with identical email must throw domain exception
        $this->expectException(UserAlreadyExistsException::class);

        $this->repository->create([
            'name' => 'Second User',
            'email' => $duplicateEmail,
            'password' => 'Password456!',
        ]);
    }

    /**
     * TC-INT-03: Mark email as verified updates database row.
     */
    public function test_it_marks_email_as_verified_in_mysql(): void
    {
        $email = 'verify_' . uniqid() . '@example.com';

        $entity = $this->repository->create([
            'name' => 'To Verify',
            'email' => $email,
            'password' => 'Password123!',
        ]);

        $updated = $this->repository->markEmailAsVerified($entity->id);
        $this->assertTrue($updated);

        $refreshed = $this->repository->findById($entity->id);
        $this->assertNotNull($refreshed?->emailVerifiedAt);
    }
}
