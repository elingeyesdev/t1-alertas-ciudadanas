<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();

        if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'alertas_test') {
            throw new RuntimeException('Las pruebas requieren PostgreSQL y la base aislada alertas_test.');
        }

        return $app;
    }
}
