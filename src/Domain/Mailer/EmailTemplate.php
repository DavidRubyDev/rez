<?php

declare(strict_types=1);

namespace Rez\Domain\Mailer;

use Rez\Domain\Shared\Utc;

final class EmailTemplate
{
    private function __construct(
        public readonly EmailTemplateId $id,
        public readonly string $subject,
        public readonly string $html,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function reconstruct(
        EmailTemplateId $id,
        string $subject,
        string $html,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $subject, $html, $createdAt);
    }

    /** @throws \InvalidArgumentException */
    public static function create(EmailTemplateId $id, string $subject, string $html): self
    {
        self::validate($subject, $html);

        return new self($id, $subject, $html, Utc::now());
    }

    /** @throws \InvalidArgumentException */
    public function withContent(string $subject, string $html): self
    {
        self::validate($subject, $html);

        return new self($this->id, $subject, $html, $this->createdAt);
    }

    /** @throws \InvalidArgumentException */
    private static function validate(string $subject, string $html): void
    {
        if ($subject === '') {
            throw new \InvalidArgumentException('subject must not be empty.');
        }

        if ($html === '') {
            throw new \InvalidArgumentException('html must not be empty.');
        }
    }
}
