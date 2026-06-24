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
        public readonly ?PaymentsConfig $payments = null,
        public readonly ?UsersConfig $users = null,
        public readonly ?CreditsConfig $credits = null,
        public readonly ?SubscriptionsConfig $subscriptions = null,
    ) {
        if ($users !== null && $payments === null) {
            throw new \InvalidArgumentException('users requires payments to be configured.');
        }

        if ($credits !== null && $payments === null) {
            throw new \InvalidArgumentException('credits requires payments to be configured.');
        }

        if ($credits !== null && $users === null) {
            throw new \InvalidArgumentException('credits requires users to be configured.');
        }

        if ($subscriptions !== null && $payments === null) {
            throw new \InvalidArgumentException('subscriptions requires payments to be configured.');
        }

        if ($subscriptions !== null && $users === null) {
            throw new \InvalidArgumentException('subscriptions requires users to be configured.');
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

    public function hasUsers(): bool
    {
        return $this->users !== null;
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
