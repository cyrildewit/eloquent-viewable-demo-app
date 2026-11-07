<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Adds a column for each dimension in `eloquent-viewable.dimensions.definitions`
 * that the views table lacked when `php artisan views:dimensions` wrote this.
 */
return new class extends Migration
{
    protected array $columns = ['source', 'medium', 'campaign', 'device', 'country', 'referrer_host'];

    public function up(): void
    {
        $schema = Schema::connection(config('eloquent-viewable.models.view.connection'));
        $table = config('eloquent-viewable.models.view.table_name');

        foreach ($this->columns as $column) {
            if ($schema->hasColumn($table, $column)) {
                continue;
            }

            $schema->table($table, function (Blueprint $table) use ($column) {
                $table->string($column, 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(config('eloquent-viewable.models.view.connection'));
        $table = config('eloquent-viewable.models.view.table_name');

        foreach ($this->columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                continue;
            }

            $schema->table($table, function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }
};
