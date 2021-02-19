<?php

namespace ACFBentveld\XML\Tests;

use ACFBentveld\XML\XMLFacade;
use ACFBentveld\XML\XMLServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Snapshots\MatchesSnapshots;

abstract class TestCase extends Orchestra
{
    use MatchesSnapshots;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('view:clear');
    }

    protected function getPackageProviders($app)
    {
        return [
            XMLServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'XML' => XMLFacade::class,
        ];
    }
}
