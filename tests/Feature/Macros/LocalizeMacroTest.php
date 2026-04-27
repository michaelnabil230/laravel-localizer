<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature\Macros;

use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\ServiceProvider;
use Orchestra\Testbench\TestCase;

class LocalizeMacroTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function test_registers_the_macro()
    {
        Route::localize(function () {
            Route::get('/test', fn () => 'ok')->name('test');
        });

        $routes = collect(Route::getRoutes())->map->getName();

        $this->assertTrue($routes->contains('with_locale.test'));
        $this->assertTrue($routes->contains('without_locale.test'));
    }

    public function test_chained_middleware_propagates_into_localized_routes()
    {
        Route::middleware('auth')->localize(function () {
            Route::get('/profile', fn () => 'ok')->name('profile');
        });
        Route::getRoutes()->refreshNameLookups();

        $with = Route::getRoutes()->getByName('with_locale.profile');
        $without = Route::getRoutes()->getByName('without_locale.profile');

        $this->assertNotNull($with);
        $this->assertNotNull($without);
        $this->assertContains('auth', $with->middleware());
        $this->assertContains('auth', $without->middleware());
    }

    public function test_chained_prefix_propagates_into_localized_routes()
    {
        Route::prefix('admin')->localize(function () {
            Route::get('/dashboard', fn () => 'ok')->name('dashboard');
        });
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame(
            'admin/{locale}/dashboard',
            Route::getRoutes()->getByName('with_locale.dashboard')->uri()
        );
        $this->assertSame(
            'admin/dashboard',
            Route::getRoutes()->getByName('without_locale.dashboard')->uri()
        );
    }
}
