<?php

namespace App\Models;

use CyrildeWit\EloquentViewable\View as BaseView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Spatie\SchemalessAttributes\SchemalessAttributes;

class View extends BaseView
{
    use HasFactory;

    public $casts = [
        'extra_attributes' => 'array',
    ];

    public function getExtraAttributesAttribute(): SchemalessAttributes
    {
        return SchemalessAttributes::createForModel($this, 'extra_attributes');
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return SchemalessAttributes::scopeWithSchemalessAttributes('extra_attributes');
    }
}
