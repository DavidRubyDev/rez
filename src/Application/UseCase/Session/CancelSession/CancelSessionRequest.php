<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CancelSession;

use Rez\Domain\Session\SessionId;

final class CancelSessionRequest
{
    public function __construct(
        public readonly SessionId $sessionId,
    ) {
    }
}
