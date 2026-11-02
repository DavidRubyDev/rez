<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Auth;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Auth\RefreshTokenId;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\UserId;

class RefreshTokenTest extends TestCase
{
    private RefreshTokenFamilyId $familyId;
    private UserId $userId;

    protected function setUp(): void
    {
        $this->familyId = RefreshTokenFamilyId::generate();
        $this->userId   = UserId::generate();
    }

    private function issue(string $rawToken = 'raw-token', string $expiresIn = '+30 days'): RefreshToken
    {
        return RefreshToken::issue($this->familyId, $this->userId, $rawToken, Utc::now()->modify($expiresIn));
    }

    public function testIssueStoresOnlyTheHashOfTheRawToken(): void
    {
        $token = $this->issue('raw-token');

        $this->assertSame(hash('sha256', 'raw-token'), $token->tokenHash);
        $this->assertSame(RefreshToken::hashOf('raw-token'), $token->tokenHash);
    }

    public function testIssueStoresFamilyUserAndExpiry(): void
    {
        $expiresAt = Utc::now()->modify('+30 days');

        $token = RefreshToken::issue($this->familyId, $this->userId, 'raw-token', $expiresAt);

        $this->assertTrue($this->familyId->equals($token->familyId));
        $this->assertTrue($this->userId->equals($token->userId));
        $this->assertSame($expiresAt, $token->expiresAt);
    }

    public function testIssuedTokenIsNeitherUsedNorRevoked(): void
    {
        $token = $this->issue();

        $this->assertFalse($token->isUsed());
        $this->assertFalse($token->isRevoked());
        $this->assertNull($token->usedAt);
        $this->assertNull($token->revokedAt);
    }

    public function testIssueWithEmptyRawTokenThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->issue('');
    }

    public function testIssueWithExpiryInThePastThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->issue('raw-token', '-1 second');
    }

    public function testIsExpiredAtComparesAgainstExpiry(): void
    {
        $token = $this->issue('raw-token', '+1 hour');

        $this->assertFalse($token->isExpiredAt(Utc::now()));
        $this->assertTrue($token->isExpiredAt(Utc::now()->modify('+2 hours')));
    }

    public function testMarkUsedRecordsWhenTheTokenWasRotated(): void
    {
        $token = $this->issue();
        $at    = Utc::now();

        $used = $token->markUsed($at);

        $this->assertTrue($used->isUsed());
        $this->assertSame($at, $used->usedAt);
        $this->assertFalse($token->isUsed(), 'markUsed must not mutate the original');
    }

    public function testMarkUsedTwiceThrows(): void
    {
        $used = $this->issue()->markUsed(Utc::now());

        $this->expectException(InvalidTokenException::class);
        $used->markUsed(Utc::now());
    }

    public function testWasUsedWithinIsFalseForAnUnusedToken(): void
    {
        $this->assertFalse($this->issue()->wasUsedWithin(30, Utc::now()));
    }

    public function testWasUsedWithinIsTrueInsideTheWindow(): void
    {
        $now  = Utc::now();
        $used = $this->issue()->markUsed($now->modify('-10 seconds'));

        $this->assertTrue($used->wasUsedWithin(30, $now));
    }

    public function testWasUsedWithinIsFalseOutsideTheWindow(): void
    {
        $now  = Utc::now();
        $used = $this->issue()->markUsed($now->modify('-31 seconds'));

        $this->assertFalse($used->wasUsedWithin(30, $now));
    }

    public function testReconstructRestoresEveryField(): void
    {
        $id        = RefreshTokenId::generate();
        $expiresAt = Utc::now()->modify('+1 day');
        $createdAt = Utc::now()->modify('-1 day');
        $usedAt    = Utc::now()->modify('-1 hour');
        $revokedAt = Utc::now();

        $token = RefreshToken::reconstruct($id, $this->familyId, $this->userId, 'stored-hash', $expiresAt, $createdAt, $usedAt, $revokedAt);

        $this->assertSame($id, $token->id);
        $this->assertSame('stored-hash', $token->tokenHash);
        $this->assertSame($createdAt, $token->createdAt);
        $this->assertSame($usedAt, $token->usedAt);
        $this->assertSame($revokedAt, $token->revokedAt);
        $this->assertTrue($token->isRevoked());
    }
}
