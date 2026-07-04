<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

enum Feature
{
    case Payments;
    case Credits;
    case Subscriptions;
}
