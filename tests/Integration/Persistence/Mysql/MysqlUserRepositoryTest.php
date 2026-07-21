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

    private function makeUser(string $email = 'john@example.com', string $name = 'John Doe', UserRole $role = UserRole::Admin): User
    {
        return User::create(
            UserId::generate(),
            $name,
            $email,
            HashedPassword::fromPlainText('correct-horse-battery-staple'),
            $role,
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

        $this->assertSame(3, $this->repository->findAll()->count());
    }

    public function testDeleteRemovesUser(): void
    {
        $user = $this->makeUser();
        $this->repository->save($user);
        $this->repository->delete($user->id);

        $this->expectException(UserNotFoundException::class);
        $this->repository->findById($user->id);
    }

    public function testFindPageWithNoParamsMatchesFindAll(): void
    {
        $this->repository->save($this->makeUser('a@example.com'));
        $this->repository->save($this->makeUser('b@example.com'));

        $page = $this->repository->findPage();
        $all  = $this->repository->findAll();

        $this->assertSame($all->count(), $page->count());
        $this->assertSame(
            array_map(fn ($u) => $u->id->toString(), $all->toArray()),
            array_map(fn ($u) => $u->id->toString(), $page->toArray()),
        );
    }

    public function testFindPageFiltersBySearchAgainstNameOrEmail(): void
    {
        $match   = $this->makeUser('alice@example.com', 'Alice Wonderland');
        $noMatch = $this->makeUser('bob@example.com', 'Bob Builder');

        $this->repository->save($match);
        $this->repository->save($noMatch);

        $result = $this->repository->findPage(search: 'wonderland');

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($match->id));
    }

    public function testFindPageFiltersByRole(): void
    {
        $admin    = $this->makeUser('admin@example.com', role: UserRole::Admin);
        $customer = $this->makeUser('customer@example.com', role: UserRole::Customer);

        $this->repository->save($admin);
        $this->repository->save($customer);

        $result = $this->repository->findPage(role: UserRole::Customer);

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($customer->id));
    }

    public function testFindPageSortsAndPaginates(): void
    {
        $this->repository->save($this->makeUser('a@example.com', 'Alice'));
        $this->repository->save($this->makeUser('b@example.com', 'Bob'));
        $this->repository->save($this->makeUser('c@example.com', 'Carol'));

        $page = $this->repository->findPage(sortBy: 'name', sortDir: 'desc', offset: 1, limit: 1);

        $this->assertSame(1, $page->count());
        $this->assertSame('Bob', $page->toArray()[0]->name);
    }

    public function testFindPageDefaultSortIsCreatedAtAscending(): void
    {
        // created_at is a DATETIME (second precision) — explicit, distinct timestamps avoid a
        // tie that MySQL would break arbitrarily (UUID primary keys give no insertion-order guarantee).
        $utc = new \DateTimeZone('UTC');

        $first = User::reconstruct(
            UserId::generate(),
            'First',
            'first@example.com',
            HashedPassword::fromPlainText('correct-horse-battery-staple'),
            UserRole::Admin,
            false,
            null,
            new \DateTimeImmutable('2024-01-01 10:00:00', $utc),
        );
        $second = User::reconstruct(
            UserId::generate(),
            'Second',
            'second@example.com',
            HashedPassword::fromPlainText('correct-horse-battery-staple'),
            UserRole::Admin,
            false,
            null,
            new \DateTimeImmutable('2024-01-01 10:00:01', $utc),
        );

        $this->repository->save($second);
        $this->repository->save($first);

        $page = $this->repository->findPage();

        $this->assertTrue($page->toArray()[0]->id->equals($first->id));
        $this->assertTrue($page->toArray()[1]->id->equals($second->id));
    }

    public function testCountPageMatchesFilteredCount(): void
    {
        $this->repository->save($this->makeUser('admin@example.com', role: UserRole::Admin));
        $this->repository->save($this->makeUser('customer@example.com', role: UserRole::Customer));

        $this->assertSame(1, $this->repository->countPage(role: UserRole::Customer));
    }

    public function testFindAllIsUnaffectedByFindPageChanges(): void
    {
        $this->repository->save($this->makeUser('a@example.com'));
        $this->repository->save($this->makeUser('b@example.com'));

        $this->assertSame(2, $this->repository->findAll()->count());
    }
}
