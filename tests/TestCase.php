<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Uji tidak bergantung pada aset Vite yang sudah dibangun.
        $this->withoutVite();
    }
}
