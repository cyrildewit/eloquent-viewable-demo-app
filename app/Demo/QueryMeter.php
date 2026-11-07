<?php

namespace App\Demo;

/**
 * Counts the queries of the current request and the time the database spent
 * on them, for the footer of every page.
 */
final class QueryMeter
{
    public int $queries = 0;

    public float $milliseconds = 0.0;

    public function record(float $milliseconds): void
    {
        $this->queries++;
        $this->milliseconds += $milliseconds;
    }
}
