<?php

declare(strict_types=1);

namespace Rez\Application\Port;

interface DatabaseSeederInterface
{
    /**
     * @throws \RuntimeException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function executeFile(string $filePath): void;
}
