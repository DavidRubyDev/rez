<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\GetSession;

use Rez\Domain\Session\SessionId;

final class GetSessionRequest
{
    public function __construct(
        public readonly SessionId $sessionId,
    ) {
    }
}
