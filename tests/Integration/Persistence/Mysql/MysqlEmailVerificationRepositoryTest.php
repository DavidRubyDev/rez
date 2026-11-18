<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Infrastructure\Mapper\UserRoleMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlEmailVerificationRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlUserRepository;

class MysqlEmailVerificationRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlEmailVerificationRepository $repository;
    private MysqlUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository     = new MysqlEmailVerificationRepository($this->pdo(), new NullLogger());
        $this->userRepository = new MysqlUserRepository($this->pdo(), new UserRoleMapper(), new NullLogger());
    }

    private function insertUser(string $email = 'john@example.com'): UserId
    {
        $user = User::create(UserId::generate(), 'John Doe', $email, HashedPassword::fromPlainText('correct-horse-battery-staple'));
        $this->userRepository->save($user);

        return $user->id;
    }

    public function testSaveAndFindByTokenHash(): void
    {
        $userId    = $this->insertUser();
        $expiresAt = Utc::now()->modify('+1 day');
        $this->repository->save($userId, 'a-token-hash', Utc::now(), $expiresAt);

        $record = $this->repository->findByTokenHash('a-token-hash');

        $this->assertTrue($userId->equals($record['user_id']));
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $record['expires_at']->format('Y-m-d H:i:s'));
    }

    public function testFindByTokenHashThrowsWhenNotFound(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('unknown-hash');
    }

    public function testSaveReplacesTheUsersEarlierToken(): void
    {
        $userId = $this->insertUser();
        $this->repository->save($userId, 'first-hash', Utc::now(), Utc::now()->modify('+1 day'));
        $this->repository->save($userId, 'second-hash', Utc::now(), Utc::now()->modify('+1 day'));

        $this->assertTrue($userId->equals($this->repository->findByTokenHash('second-hash')['user_id']));
        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('first-hash');
    }

    public function testFindIssuedAtForUser(): void
    {
        $userId   = $this->insertUser();
        $issuedAt = new \DateTimeImmutable('2026-11-18 10:00:00', new \DateTimeZone('UTC'));
        $this->repository->save($userId, 'a-token-hash', $issuedAt, $issuedAt->modify('+1 day'));

        $this->assertSame('2026-11-18 10:00:00', $this->repository->findIssuedAtForUser($userId)?->format('Y-m-d H:i:s'));
    }

    public function testFindIssuedAtForUserIsNullWithoutAToken(): void
    {
        $this->assertNull($this->repository->findIssuedAtForUser($this->insertUser()));
    }

    public function testDeleteForUserRemovesTheToken(): void
    {
        $userId = $this->insertUser();
        $this->repository->save($userId, 'a-token-hash', Utc::now(), Utc::now()->modify('+1 day'));

        $this->repository->deleteForUser($userId);

        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('a-token-hash');
    }

    public function testDeletingTheUserDeletesTheirToken(): void
    {
        $userId = $this->insertUser();
        $this->repository->save($userId, 'a-token-hash', Utc::now(), Utc::now()->modify('+1 day'));

        $this->userRepository->delete($userId);

        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash('a-token-hash');
    }
}
