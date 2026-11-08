<?php

namespace App\Demo;

use Closure;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;

/**
 * Runs a read that the rollup source may refuse, and hands back the refusal
 * instead of throwing, so one panel can explain it while the rest of the
 * page renders.
 *
 * Once views are pruned, the package refuses rather than undercounts two
 * kinds of reads over that range: a count by a dimension that is not in
 * `retention.rollups.dimensions`, and a count that combines two dimensions.
 */
final class Answer
{
    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T|UnsupportedBySource|ResolutionUnavailable
     */
    public static function of(Closure $read): mixed
    {
        try {
            return $read();
        } catch (UnsupportedBySource|ResolutionUnavailable $refused) {
            return $refused;
        }
    }
}
