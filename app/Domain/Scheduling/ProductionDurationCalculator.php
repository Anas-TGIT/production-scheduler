<?php

namespace App\Domain\Scheduling;

use InvalidArgumentException;

/**
 * Converts quantities into whole production minutes.
 *
 * Rule: minutes = ceil(sum(quantities) * 60 / unitsPerHour), rounded UP once at
 * order level (never per line). Integer arithmetic avoids float rounding errors.
 */
final class ProductionDurationCalculator
{
    /**
     * @param  list<int>  $quantities  quantities of one order (all items share the same product type)
     */
    public function minutesFor(array $quantities, int $unitsPerHour): int
    {
        if ($unitsPerHour <= 0) {
            throw new InvalidArgumentException("Production rate must be positive, got {$unitsPerHour}.");
        }

        if ($quantities === []) {
            throw new InvalidArgumentException('An order needs at least one item to have a duration.');
        }

        $totalUnits = 0;
        foreach ($quantities as $quantity) {
            if ($quantity <= 0) {
                throw new InvalidArgumentException("Quantities must be positive, got {$quantity}.");
            }
            $totalUnits += $quantity;
        }

        return intdiv($totalUnits * 60 + $unitsPerHour - 1, $unitsPerHour);
    }
}
