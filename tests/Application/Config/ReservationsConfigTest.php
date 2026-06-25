<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\ReservationsConfig;

class ReservationsConfigTest extends TestCase
{
    public function testDefaultAutoConfirmIsFalse(): void
    {
        $config = new ReservationsConfig();

        $this->assertFalse($config->autoConfirm);
    }

    public function testAutoConfirmTrueIsAccepted(): void
    {
        $config = new ReservationsConfig(autoConfirm: true);

        $this->assertTrue($config->autoConfirm);
    }
}
