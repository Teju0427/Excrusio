<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_paise_with_indian_grouping(): void
    {
        $this->assertSame('₹0', Money::format(0));
        $this->assertSame('₹999', Money::format(99_900));
        $this->assertSame('₹38,500', Money::format(3_850_000));
        $this->assertSame('₹12,34,567', Money::format(123_456_700));
        $this->assertSame('₹123.45', Money::format(12_345, true));
    }
}
