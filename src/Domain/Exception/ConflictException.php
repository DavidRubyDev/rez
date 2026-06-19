<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;

class ConflictException extends DomainException
{
    public function __construct(
        private readonly TimeSlot $slot,
        private readonly Resource $resource,
    ) {
        parent::__construct('Reservation conflicts with an existing booking.');
    }

    public function slot(): TimeSlot
    {
        return $this->slot;
    }

    public function resource(): Resource
    {
        return $this->resource;
    }
}
