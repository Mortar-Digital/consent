<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MortarDigital\Consent\Csv;

final class CsvTest extends TestCase
{
    public function testQuotesAndEscapes(): void
    {
        self::assertSame("\"a\",\"b \"\"c\"\"\",\"1\",\"\"\r\n", Csv::row(['a', 'b "c"', 1, null]));
    }

    public function testNeutralisesFormulas(): void
    {
        self::assertSame("\"'=HYPERLINK(1)\",\"'+1\",\"'-1\",\"'@SUM(A1)\"\r\n", Csv::row(['=HYPERLINK(1)', '+1', '-1', '@SUM(A1)']));
    }
}
