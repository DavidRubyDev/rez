<?php

declare(strict_types=1);

namespace Rez\Domain\User;

use Rez\Domain\Shared\Email;
use Rez\Domain\Shared\Utc;

final class User
{
    private function __construct(
        public readonly UserId $id,
        public readonly string $name,
        public readonly string $email,
        public readonly HashedPassword $password,
        public readonly UserRole $role,
        public readonly bool $newsletterOptIn,
        public readonly ?string $stripeCustomerId,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function create(
        UserId $id,
        string $name,
        string $email,
        HashedPassword $password,
        UserRole $role = UserRole::Customer,
        bool $newsletterOptIn = false,
        ?string $stripeCustomerId = null,
    ): self {
        self::validateName($name);
        self::validateEmail($email);

        return new self($id, $name, $email, $password, $role, $newsletterOptIn, $stripeCustomerId, Utc::now());
    }

    public static function reconstruct(
        UserId $id,
        string $name,
        string $email,
        HashedPassword $password,
        UserRole $role,
        bool $newsletterOptIn,
        ?string $stripeCustomerId,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $name, $email, $password, $role, $newsletterOptIn, $stripeCustomerId, $createdAt);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function withName(string $name): self
    {
        self::validateName($name);

        return new self($this->id, $name, $this->email, $this->password, $this->role, $this->newsletterOptIn, $this->stripeCustomerId, $this->createdAt);
    }

    public function withNewsletterOptIn(bool $optIn): self
    {
        return new self($this->id, $this->name, $this->email, $this->password, $this->role, $optIn, $this->stripeCustomerId, $this->createdAt);
    }

    public function withStripeCustomerId(string $stripeCustomerId): self
    {
        return new self($this->id, $this->name, $this->email, $this->password, $this->role, $this->newsletterOptIn, $stripeCustomerId, $this->createdAt);
    }

    public function withPassword(HashedPassword $password): self
    {
        return new self($this->id, $this->name, $this->email, $password, $this->role, $this->newsletterOptIn, $this->stripeCustomerId, $this->createdAt);
    }

    public function withRole(UserRole $role): self
    {
        return new self($this->id, $this->name, $this->email, $this->password, $role, $this->newsletterOptIn, $this->stripeCustomerId, $this->createdAt);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function validateName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('User name must not be empty.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function validateEmail(string $email): void
    {
        if (!Email::isValid($email)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $email));
        }
    }
}
