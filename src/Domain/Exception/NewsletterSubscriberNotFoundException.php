<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class NewsletterSubscriberNotFoundException extends DomainException
{
    public function __construct(private readonly string $email)
    {
        parent::__construct("Newsletter subscriber with email '{$email}' not found.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NewsletterSubscriberNotFound;
    }

    public function errorParams(): array
    {
        return ['email' => $this->email];
    }
}
