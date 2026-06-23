<?php

declare(strict_types=1);

namespace Rez\Domain\Newsletter;

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

    public static function create(
        NewsletterSubscriberId $id,
        string $email,
        ?string $name,
        SubscriberSource $source,
    ): self {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $email));
        }

        return new self($id, $email, $name, $source, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }


}
