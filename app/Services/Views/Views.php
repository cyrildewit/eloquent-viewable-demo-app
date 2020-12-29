<?php

namespace App\Services\Views;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Views as BaseViews;
use Illuminate\Container\Container;

class Views extends BaseViews
{
    protected $extraAttributes = [];

    public function extraAttributes(array $extraAttributes)
    {
        $this->extraAttributes = $extraAttributes;

        return $this;
    }

    /**
     * Create a new view instance.
     *
     * @return \CyrildeWit\EloquentViewable\Contracts\View
     */
    protected function createView(): ViewContract
    {
        $view = Container::getInstance()->make(ViewContract::class);

        return $view->create([
            'viewable_id' => $this->viewable->getKey(),
            'viewable_type' => $this->viewable->getMorphClass(),
            'extra_attributes' => $this->extraAttributes,
            'visitor' => $this->visitor->id(),
            'collection' => $this->collection,
            'viewed_at' => Carbon::now(),
        ]);
    }
}
