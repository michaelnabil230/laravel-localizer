<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature\Macros;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use NielsNumbers\LaravelLocalizer\ServiceProvider;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;

class TranslateMacroTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.fallback_locale', 'en');

        Localizer::shouldReceive('supportedLocales')->andReturn(['en', 'de']);
        Localizer::shouldReceive('hideDefaultLocale')->andReturn(true);
    }

    public function test_registers_routes_for_each_locale()
    {
        Route::translate(function () {
            Route::get('about', fn() => 'ok')->name('about');
        });

        $routes = collect(Route::getRoutes())->pluck('action.as');

        $this->assertTrue($routes->contains('translated_en.about'));
        $this->assertTrue($routes->contains('translated_de.about'));
        $this->assertTrue($routes->contains('without_locale.about'));
    }

    public function test_restores_locale_after_registration()
    {
        App::setLocale('fr');

        Route::translate(fn() => null);

        $this->assertEquals('fr', App::getLocale());
    }

    public function test_restores_locale_when_closure_throws()
    {
        App::setLocale('fr');

        try {
            Route::translate(function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertEquals('fr', App::getLocale(),
            'App locale must be restored even when route registration throws.');
    }

    public function test_chained_middleware_propagates_into_translated_routes()
    {
        Route::middleware('auth')->translate(function () {
            Route::get('about', fn() => 'ok')->name('about');
        });
        Route::getRoutes()->refreshNameLookups();

        foreach (['translated_en.about', 'translated_de.about', 'without_locale.about'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route {$name} should be registered");
            $this->assertContains('auth', $route->middleware());
        }
    }
}
