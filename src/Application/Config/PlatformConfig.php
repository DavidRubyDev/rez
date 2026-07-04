<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class PlatformConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly MailerConfig $mailer,
        public readonly UsersConfig $users,
        public readonly ?PaymentsConfig $payments = null,
        public readonly ?CreditsConfig $credits = null,
        public readonly ?SubscriptionsConfig $subscriptions = null,
    ) {
        if ($credits !== null && $payments === null) {
            throw new \InvalidArgumentException('credits requires payments to be configured.');
        }

        if ($subscriptions !== null && $payments === null) {
            throw new \InvalidArgumentException('subscriptions requires payments to be configured.');
        }
    }

    public function hasMailer(): bool
    {
        return true;
    }

    public function hasPayments(): bool
    {
        return $this->payments !== null;
    }

    public function hasCredits(): bool
    {
        return $this->credits !== null;
    }

    public function hasSubscriptions(): bool
    {
        return $this->subscriptions !== null;
    }
}
