<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Direct migrator avoids adding Mockery solely for artisan's test wrapper.
        $migrator = $this->app->make('migrator');
        if (!$migrator->repositoryExists()) {
            $migrator->getRepository()->createRepository();
        }
        $migrator->run(database_path('migrations'));
        $this->app->make('db')->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            $this->app->make('db')->rollBack();
        }
        parent::tearDown();
    }
}
