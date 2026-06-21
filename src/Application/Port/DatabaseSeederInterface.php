<?php

declare(strict_types=1);

namespace Rez\Application\Port;

interface DatabaseSeederInterface
{
    public function executeFile(string $filePath): void;
}
