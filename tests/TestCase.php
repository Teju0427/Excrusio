<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The test database is migrated and filled with the demo data once per run;
     * every test then runs inside a transaction that is rolled back afterwards.
     * (Set here, not per class, because only the first class to run triggers the migration.)
     */
    protected bool $seed = true;
}
