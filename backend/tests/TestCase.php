<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock Vite manifest for testing
        Vite::useHotFile('hot')
            ->useBuildDirectory('build')
            ->withEntryPoints(['resources/css/app.css', 'resources/js/app.js']);
    }
}
