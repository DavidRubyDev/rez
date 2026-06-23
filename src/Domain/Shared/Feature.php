<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

enum Feature
{
    case Payments;
    case Users;
    case Credits;
    case Subscriptions;
}
