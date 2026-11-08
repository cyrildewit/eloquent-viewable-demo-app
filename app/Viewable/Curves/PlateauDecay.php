<?php

namespace App\Viewable\Curves;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;

/**
 * Full weight for the first hours, then exponential decay: a breaking story
 * keeps its place for a morning before it starts to fade.
 */
final readonly class PlateauDecay implements DecayCurve
{
    public function __construct(
        private CarbonInterval $plateau,
        private CarbonInterval $halfLife,
    ) {}

    public function weight(CarbonInterval $age): float
    {
        $faded = max(0, $age->totalSeconds - $this->plateau->totalSeconds);

        return 0.5 ** ($faded / $this->halfLife->totalSeconds);
    }

    public function horizon(): CarbonInterval
    {
        return $this->plateau->copy()->add($this->halfLife->copy()->times(8));
    }

    public function identity(): string
    {
        return "plateau:{$this->plateau->totalSeconds}:{$this->halfLife->totalSeconds}";
    }
}
