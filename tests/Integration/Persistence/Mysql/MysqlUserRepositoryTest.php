<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;
use Rez\Infrastructure\Mapper\UserRoleMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlUserRepository;

class MysqlUserRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlUserRepository($this->pdo(), new UserRoleMapper(), new NullLogger());
    }

    private function makeUser(string $email = 'john@example.com'): User
    {
        return User::create(
            UserId::generate(),
            'John Doe',
            $email,
            HashedPassword::fromPlainText('correct-horse-battery-staple'),
            UserRole::Admin,
            true,
            'cus_123',
        );
    }

    public function testSaveAndFindByIdRoundtrip(): void
    {
        $user = $this->makeUser();
        $this->repository->save($user);

        $found = $this->repository->findById($user->id);

        $this->assertTrue($user->id->equals($found->id));
        $this->assertSame('John Doe', $found->name);
        $this->assertSame('john@example.com', $found->email);
        $this->assertSame(UserRole::Admin, $found->role);
        $this->assertTrue($found->newsletterOptIn);
        $this->assertSame('cus_123', $found->stripeCustomerId);
        $this->assertTrue($found->password->verify('correct-horse-battery-staple'));
    }

    public function testFindByIdThrowsWhenNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->repository->findById(UserId::generate());
    }

    public function testFindByEmailFindsAfterSave(): void
    {
        $user = $this->makeUser();
        $this->repository->save($user);

        $found = $this->repository->findByEmail('john@example.com');

        $this->assertTrue($user->id->equals($found->id));
    }

    public function testFindByEmailThrowsWhenNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->repository->findByEmail('unknown@example.com');
    }

    public function testSaveUpdatesExistingUser(): void
    {
        $user = $this->makeUser();
        $this->repository->save($user);

        $renamed = $user->withName('Jane Doe')->withNewsletterOptIn(false);
        $this->repository->save($renamed);

        $found = $this->repository->findById($user->id);
        $this->assertSame('Jane Doe', $found->name);
        $this->assertFalse($found->newsletterOptIn);
    }

    public function testFindAllReturnsAllUsers(): void
    {
        $this->repository->save($this->makeUser('a@example.com'));
        $this->repository->save($this->makeUser('b@example.com'));
        $this->repository->save($this->makeUser('c@example.com'));

        $this->assertCount(3, $this->repository->findAll());
    }

    public function testDeleteRemovesUser(): void
    {
        $user = $this->makeUser();
        $this->repository->save($user);
        $this->repository->delete($user->id);

        $this->expectException(UserNotFoundException::class);
        $this->repository->findById($user->id);
    }
}
