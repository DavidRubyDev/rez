<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings;

final class UpdateReservationSettingsRequest
{
    public function __construct(
        public readonly ?bool $autoConfirm = null,
        public readonly ?bool $autoSendReservationCreated = null,
        public readonly ?bool $autoSendReservationConfirmed = null,
        public readonly ?bool $autoSendReservationCancelled = null,
    ) {
    }
}
