<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs after the app boots and before RefreshDatabase. The containers
     * point DB_* at the demo's MariaDB, which RefreshDatabase would wipe, so
     * only an in-memory SQLite database is accepted.
     */
    protected function setUpTraits()
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Refusing to run tests against '.config('database.default').'. Check the <server> entries in phpunit.xml.');
        }

        return parent::setUpTraits();
    }
}
