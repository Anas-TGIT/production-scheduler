<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ProductionDurationCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductionDurationCalculatorTest extends TestCase
{
    private ProductionDurationCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ProductionDurationCalculator;
    }

    #[Test]
    public function type_1_produces_1430_units_in_120_minutes(): void
    {
        $this->assertSame(120, $this->calculator->minutesFor([1430], 715));
    }

    #[Test]
    public function type_2_produces_770_units_in_60_minutes(): void
    {
        $this->assertSame(60, $this->calculator->minutesFor([770], 770));
    }

    #[Test]
    public function type_3_produces_1000_units_in_60_minutes(): void
    {
        $this->assertSame(60, $this->calculator->minutesFor([1000], 1000));
    }

    #[Test]
    public function partial_minutes_are_rounded_up(): void
    {
        // 500 * 60 / 715 = 41.96 minutes
        $this->assertSame(42, $this->calculator->minutesFor([500], 715));
        // 1 unit still takes a whole minute
        $this->assertSame(1, $this->calculator->minutesFor([1], 1000));
    }

    #[Test]
    public function multiple_items_are_summed_before_rounding_once(): void
    {
        // Per line: ceil(6.29) + ceil(6.29) = 14; summed first: ceil(12.59) = 13.
        $this->assertSame(13, $this->calculator->minutesFor([75, 75], 715));
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_input(array $quantities, int $rate): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->minutesFor($quantities, $rate);
    }

    public static function invalidInputs(): array
    {
        return [
            'zero rate' => [[100], 0],
            'negative rate' => [[100], -715],
            'no items' => [[], 715],
            'zero quantity' => [[0], 715],
            'negative quantity' => [[100, -5], 715],
        ];
    }
}
