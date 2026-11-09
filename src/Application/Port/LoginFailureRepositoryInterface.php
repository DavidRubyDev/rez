<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Auth\LoginThrottleKey;

interface LoginFailureRepositoryInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function record(LoginThrottleKey $key, DateTimeImmutable $at): void;

    /**
     * @return list<DateTimeImmutable> oldest first
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function failedSince(LoginThrottleKey $key, DateTimeImmutable $since): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function clear(LoginThrottleKey $key): void;
}
