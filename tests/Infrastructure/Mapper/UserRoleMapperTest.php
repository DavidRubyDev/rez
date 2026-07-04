<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\User\UserRole;
use Rez\Infrastructure\Mapper\UserRoleMapper;

class UserRoleMapperTest extends TestCase
{
    private UserRoleMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new UserRoleMapper();
    }

    public function testCustomerMapsToString(): void
    {
        $this->assertSame('customer', $this->mapper->toString(UserRole::Customer));
    }

    public function testAdminMapsToString(): void
    {
        $this->assertSame('admin', $this->mapper->toString(UserRole::Admin));
    }

    public function testStringMapsToCustomer(): void
    {
        $this->assertSame(UserRole::Customer, $this->mapper->fromString('customer'));
    }

    public function testStringMapsToAdmin(): void
    {
        $this->assertSame(UserRole::Admin, $this->mapper->fromString('admin'));
    }

    public function testUnknownStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('unknown');
    }
}
