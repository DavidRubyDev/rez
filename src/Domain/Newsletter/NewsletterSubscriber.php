<?php

declare(strict_types=1);

namespace Rez\Domain\Newsletter;

use Rez\Domain\Shared\Email;
use Rez\Domain\Shared\Utc;

final class NewsletterSubscriber
{
    private function __construct(
        public readonly NewsletterSubscriberId $id,
        public readonly string $email,
        public readonly ?string $name,
        public readonly SubscriberSource $source,
        public readonly \DateTimeImmutable $optedInAt,
    ) {
    }

    public static function reconstruct(
        NewsletterSubscriberId $id,
        string $email,
        ?string $name,
        SubscriberSource $source,
        \DateTimeImmutable $optedInAt,
    ): self {
        return new self($id, $email, $name, $source, $optedInAt);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function create(
        NewsletterSubscriberId $id,
        string $email,
        ?string $name,
        SubscriberSource $source,
    ): self {
        if (!Email::isValid($email)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $email));
        }

        return new self($id, $email, $name, $source, Utc::now());
    }


}
