<?php

declare(strict_types=1);

namespace Rez\Domain\Newsletter;

enum SubscriberSource
{
    case Guest;
    case Registered;
    case Admin;
}
