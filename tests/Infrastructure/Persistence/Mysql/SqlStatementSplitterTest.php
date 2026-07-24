<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PHPUnit\Framework\TestCase;
use Rez\Infrastructure\Persistence\Mysql\SqlStatementSplitter;

class SqlStatementSplitterTest extends TestCase
{
    public function testSplitsMultipleStatements(): void
    {
        $sql = 'SELECT 1; SELECT 2';

        $this->assertSame(['SELECT 1', 'SELECT 2'], SqlStatementSplitter::split($sql));
    }

    public function testTrimsWhitespaceAroundStatements(): void
    {
        $sql = "  SELECT 1;  \n  SELECT 2  ";

        $this->assertSame(['SELECT 1', 'SELECT 2'], SqlStatementSplitter::split($sql));
    }

    public function testDropsEmptyStatements(): void
    {
        $sql = 'SELECT 1;;SELECT 2;';

        $this->assertSame(['SELECT 1', 'SELECT 2'], SqlStatementSplitter::split($sql));
    }

    public function testSingleStatementWithNoTrailingSemicolon(): void
    {
        $this->assertSame(['SELECT 1'], SqlStatementSplitter::split('SELECT 1'));
    }

    public function testEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], SqlStatementSplitter::split(''));
    }

    public function testWhitespaceOnlyReturnsEmptyArray(): void
    {
        $this->assertSame([], SqlStatementSplitter::split("  \n  "));
    }
}
