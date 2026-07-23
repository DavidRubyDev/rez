<?php

declare(strict_types=1);

namespace Rez\Domain\Session;

enum SessionStatus
{
    case Scheduled;
    case Cancelled;
}
