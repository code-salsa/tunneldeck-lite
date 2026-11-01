<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Rebuild SQLite in-memory tables from schema when migrations are pruned.
     */
    protected function afterRefreshingDatabase()
    {
        $schemaFile = database_path('schema/sqlite-schema.sql');
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            DB::connection()->getPdo()->exec($sql);
        }
    }
}
