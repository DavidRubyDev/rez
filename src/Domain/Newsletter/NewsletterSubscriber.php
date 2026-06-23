<?php

declare(strict_types=1);

namespace Rez\Domain\Newsletter;

final class NewsletterSubscriber
{
    private function __construct(
        private readonly NewsletterSubscriberId $id,
        private readonly string $email,
        private readonly ?string $name,
        private readonly SubscriberSource $source,
        private readonly \DateTimeImmutable $optedInAt,
    ) {
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

    public function getId(): NewsletterSubscriberId
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSource(): SubscriberSource
    {
        return $this->source;
    }

    public function getOptedInAt(): \DateTimeImmutable
    {
        return $this->optedInAt;
    }
}
