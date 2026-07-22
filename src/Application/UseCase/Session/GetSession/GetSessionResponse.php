<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\GetSession;

use Rez\Domain\Session\Session;

final class GetSessionResponse
{
    public function __construct(
        public readonly Session $session,
    ) {
    }
}
