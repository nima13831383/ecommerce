<?php

namespace Tests;

use App\Support\DatabaseSafetyGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        DatabaseSafetyGuard::assertTestingDatabaseIsolated();

        return $app;
    }
}
