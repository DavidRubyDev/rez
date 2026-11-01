<?php

declare(strict_types=1);

namespace Rez\Domain\Payment;

enum PaymentStatus
{
    case Unpaid;
    case Paid;
}
