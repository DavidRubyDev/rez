<?php

declare(strict_types=1);

namespace Rez\Domain\Payment;

// CashAtCashier is the only case until online payment (Stripe) and credits land —
// every reservation today is paid in person, after booking.
enum PaymentMethod
{
    case CashAtCashier;
}
