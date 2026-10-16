<?php

namespace App\Domain\Scheduling\Strategies;

use App\Domain\Scheduling\Contracts\SchedulingStrategy;
use App\Domain\Scheduling\TimelineSimulator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Optional heuristic: best-improvement local search starting from the EDD sequence.
 *
 * Neighbourhood: every pairwise swap and every single-order relocation (O(n^2) candidates), each scored by the
 * shared TimelineSimulator (O(n)). Candidates are compared lexicographically by
 * (late orders, total tardiness, setup minutes, makespan); only strict improvements are accepted and neighbours
 * are scanned in a fixed order, so results are deterministic.
 *
 * Cost is bounded by a work budget: the total number of order placements the simulator may compute
 * (one simulation of n orders costs n). One placement takes a few microseconds, so the budget caps the response
 * time whatever the number of orders. When the budget runs out the best sequence found so far is returned;
 * the result is never worse than EDD.
 *
 * This finds a local optimum (or stops earlier), not a guaranteed global optimum.
 */
final class ChangeoverAwareStrategy implements SchedulingStrategy
{
    public const KEY = 'changeover';

    public function __construct(
        private readonly EarliestDueDateStrategy $baseline,
        private readonly TimelineSimulator $simulator,
        private readonly int $maxSimulatedOrders = 200_000,
    ) {
        if ($maxSimulatedOrders < 0) {
            throw new InvalidArgumentException('Heuristic search budget cannot be negative.');
        }
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Changeover-aware local search (heuristic)';
    }

    public function sequence(array $orders, CarbonImmutable $startAt): array
    {
        $best = $this->baseline->sequence($orders, $startAt);
        $count = count($best);
        $budget = $this->maxSimulatedOrders - $count;

        // Nothing to improve, or not enough budget to evaluate a single candidate.
        if ($count < 2 || $budget < $count) {
            return $best;
        }

        $bestResult = $this->simulator->simulate($best, $startAt);

        do {
            $improved = false;
            $exhausted = false;
            $bestCandidate = $best;

            foreach ($this->neighbours($best) as $candidate) {
                if ($budget < $count) {
                    $exhausted = true;
                    break;
                }

                $budget -= $count;
                $result = $this->simulator->simulate($candidate, $startAt);

                if ($result->isBetterThan($bestResult)) {
                    [$bestCandidate, $bestResult, $improved] = [$candidate, $result, true];
                }
            }

            $best = $bestCandidate;
        } while ($improved && ! $exhausted);

        return $best;
    }

    /**
     * @template T
     *
     * @param  list<T>  $sequence
     * @return iterable<list<T>>
     */
    private function neighbours(array $sequence): iterable
    {
        $count = count($sequence);

        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $swapped = $sequence;
                [$swapped[$i], $swapped[$j]] = [$swapped[$j], $swapped[$i]];

                yield $swapped;
            }
        }

        for ($from = 0; $from < $count; $from++) {
            for ($to = 0; $to < $count; $to++) {
                // Adjacent relocations are identical to swaps already generated.
                if (abs($from - $to) <= 1) {
                    continue;
                }

                $moved = $sequence;
                $order = array_splice($moved, $from, 1);
                array_splice($moved, $to, 0, $order);

                yield $moved;
            }
        }
    }
}
