<?php

namespace App\Demo;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Takes the secondary indexes off the views table during a bulk load and
 * puts them back afterwards. Keeping six indexes up to date row by row is
 * what slows a load of millions down; building each once from sorted data
 * at the end is several times faster.
 */
final class ViewsIndexes
{
    /**
     * Drops every index but the primary key and returns their definitions.
     *
     * @return list<array{name: string, columns: list<string>, unique: bool}>
     */
    public static function suspend(): array
    {
        $indexes = collect(Schema::getIndexes('views'))
            ->reject(fn (array $index): bool => $index['primary'])
            ->map(fn (array $index): array => ['name' => $index['name'], 'columns' => $index['columns'], 'unique' => $index['unique']])
            ->values()
            ->all();

        if ($indexes !== []) {
            Schema::table('views', function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index) {
                    $table->dropIndex($index['name']);
                }
            });
        }

        return $indexes;
    }

    /**
     * Builds the indexes again, in one statement where the database allows.
     *
     * @param  list<array{name: string, columns: list<string>, unique: bool}>  $indexes
     */
    public static function restore(array $indexes): void
    {
        if ($indexes === []) {
            return;
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $grammar = DB::connection()->getQueryGrammar();

            $clauses = array_map(fn (array $index): string => sprintf(
                'add %s %s (%s)',
                $index['unique'] ? 'unique index' : 'index',
                $grammar->wrap($index['name']),
                implode(', ', array_map($grammar->wrap(...), $index['columns'])),
            ), $indexes);

            DB::statement('alter table '.$grammar->wrapTable('views').' '.implode(', ', $clauses));

            return;
        }

        Schema::table('views', function (Blueprint $table) use ($indexes): void {
            foreach ($indexes as $index) {
                $index['unique']
                    ? $table->unique($index['columns'], $index['name'])
                    : $table->index($index['columns'], $index['name']);
            }
        });
    }
}
