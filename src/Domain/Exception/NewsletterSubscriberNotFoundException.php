<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class NewsletterSubscriberNotFoundException extends DomainException
{
    public function __construct(string $email)
    {
        parent::__construct("Newsletter subscriber with email '{$email}' not found.");
    }
}
