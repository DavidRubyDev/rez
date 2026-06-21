<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkNoShow;

interface MarkNoShowUseCaseInterface
{
    public function execute(MarkNoShowRequest $request): MarkNoShowResponse;
}
