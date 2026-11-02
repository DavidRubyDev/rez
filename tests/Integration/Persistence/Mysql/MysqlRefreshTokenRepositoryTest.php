<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Infrastructure\Mapper\UserRoleMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlRefreshTokenRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlUserRepository;

class MysqlRefreshTokenRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlRefreshTokenRepository $repository;
    private MysqlUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository     = new MysqlRefreshTokenRepository($this->pdo(), new NullLogger());
        $this->userRepository = new MysqlUserRepository($this->pdo(), new UserRoleMapper(), new NullLogger());
    }

    private function insertUser(string $email = 'john@example.com'): UserId
    {
        $user = User::create(UserId::generate(), 'John Doe', $email, HashedPassword::fromPlainText('correct-horse-battery-staple'));
        $this->userRepository->save($user);

        return $user->id;
    }

    private function issue(UserId $userId, string $rawToken, ?RefreshTokenFamilyId $familyId = null): RefreshToken
    {
        $token = RefreshToken::issue($familyId ?? RefreshTokenFamilyId::generate(), $userId, $rawToken, Utc::now()->modify('+30 days'));
        $this->repository->save($token);

        return $token;
    }

    public function testSaveAndFindByTokenHashRoundtrip(): void
    {
        $userId = $this->insertUser();
        $token  = $this->issue($userId, 'raw-token');

        $found = $this->repository->findByTokenHash(RefreshToken::hashOf('raw-token'));

        $this->assertTrue($token->id->equals($found->id));
        $this->assertTrue($token->familyId->equals($found->familyId));
        $this->assertTrue($userId->equals($found->userId));
        $this->assertSame($token->tokenHash, $found->tokenHash);
        $this->assertSame($token->expiresAt->format('Y-m-d H:i:s'), $found->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame($token->createdAt->format('Y-m-d H:i:s'), $found->createdAt->format('Y-m-d H:i:s'));
        $this->assertNull($found->usedAt);
        $this->assertNull($found->revokedAt);
    }

    public function testFindByTokenHashThrowsWhenNotFound(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash(RefreshToken::hashOf('unknown'));
    }

    public function testSavePersistsUsedAtOnAnExistingToken(): void
    {
        $token  = $this->issue($this->insertUser(), 'raw-token');
        $usedAt = Utc::now();

        $this->repository->save($token->markUsed($usedAt));

        $found = $this->repository->findByTokenHash($token->tokenHash);
        $this->assertSame($usedAt->format('Y-m-d H:i:s'), $found->usedAt?->format('Y-m-d H:i:s'));
    }

    public function testRevokeFamilyRevokesEveryTokenInThatFamilyOnly(): void
    {
        $userId      = $this->insertUser();
        $familyId    = RefreshTokenFamilyId::generate();
        $first       = $this->issue($userId, 'first', $familyId);
        $second      = $this->issue($userId, 'second', $familyId);
        $otherFamily = $this->issue($userId, 'other');

        $this->repository->revokeFamily($familyId, Utc::now());

        $this->assertTrue($this->repository->findByTokenHash($first->tokenHash)->isRevoked());
        $this->assertTrue($this->repository->findByTokenHash($second->tokenHash)->isRevoked());
        $this->assertFalse($this->repository->findByTokenHash($otherFamily->tokenHash)->isRevoked());
    }

    public function testRevokeAllForUserRevokesOnlyThatUsersTokens(): void
    {
        $userId      = $this->insertUser('john@example.com');
        $otherUserId = $this->insertUser('jane@example.com');
        $first       = $this->issue($userId, 'first');
        $second      = $this->issue($userId, 'second');
        $otherUsers  = $this->issue($otherUserId, 'other');

        $this->repository->revokeAllForUser($userId, Utc::now());

        $this->assertTrue($this->repository->findByTokenHash($first->tokenHash)->isRevoked());
        $this->assertTrue($this->repository->findByTokenHash($second->tokenHash)->isRevoked());
        $this->assertFalse($this->repository->findByTokenHash($otherUsers->tokenHash)->isRevoked());
    }

    public function testRevokingAgainKeepsTheOriginalRevocationTime(): void
    {
        $token       = $this->issue($this->insertUser(), 'raw-token');
        $firstRevoke = Utc::now()->modify('-1 hour');

        $this->repository->revokeFamily($token->familyId, $firstRevoke);
        $this->repository->revokeFamily($token->familyId, Utc::now());

        $found = $this->repository->findByTokenHash($token->tokenHash);
        $this->assertSame($firstRevoke->format('Y-m-d H:i:s'), $found->revokedAt?->format('Y-m-d H:i:s'));
    }

    public function testDeletingTheUserDeletesTheirTokens(): void
    {
        $userId = $this->insertUser();
        $token  = $this->issue($userId, 'raw-token');

        $this->userRepository->delete($userId);

        $this->expectException(InvalidTokenException::class);
        $this->repository->findByTokenHash($token->tokenHash);
    }
}
