<?php

namespace App\Viewable\Dimensions;

use App\Models\User;
use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * The plan of the signed-in reader, so the stats can split free from paying
 * readers. Guests count as `guest`.
 */
final class PlanDimension extends Dimension
{
    public function resolve(DimensionInput $input): ?string
    {
        $viewer = $input->visitor->viewer();

        return $viewer instanceof User ? $viewer->plan : 'guest';
    }
}
