<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\Middleware\SetLocale;
use NielsNumbers\LaravelLocalizer\ServiceProvider;
use Orchestra\Testbench\TestCase;

class RouteHelpersTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        Config::set('app.locale', 'en');
        Config::set('app.fallback_locale', 'en');
        Config::set('localizer.supported_locales', ['en', 'de']);
        Config::set('localizer.hide_default_locale', true);
        Config::set('localizer.persist_locale.session', false);
        Config::set('localizer.persist_locale.cookie', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Log::swap(new \Illuminate\Log\Logger(
            new \Monolog\Logger('null', [new \Monolog\Handler\NullHandler()])
        ));
    }

    public function test_has_localized_returns_true_for_localize_macro_routes()
    {
        Route::localize(function () {
            Route::get('/about', fn () => 'ok')->name('about');
        });

        $this->assertTrue(Route::hasLocalized('about'));
    }

    public function test_has_localized_returns_true_for_translate_macro_routes()
    {
        Lang::addLines(['routes.about' => 'ueber'], 'de');
        Lang::addLines(['routes.about' => 'about'], 'en');

        Route::translate(function () {
            Route::get(\NielsNumbers\LaravelLocalizer\Facades\Localizer::url('about'), fn () => 'ok')
                ->name('about');
        });

        $this->assertTrue(Route::hasLocalized('about'));
    }

    public function test_has_localized_returns_false_for_plain_routes()
    {
        Route::get('/contact', fn () => 'ok')->name('contact');

        $this->assertFalse(Route::hasLocalized('contact'));
    }

    public function test_has_localized_returns_false_for_unknown_names()
    {
        $this->assertFalse(Route::hasLocalized('nonexistent'));
    }

    public function test_is_localized_returns_true_inside_localize_macro_route()
    {
        Route::middleware(SetLocale::class)->group(function () {
            Route::localize(function () {
                Route::get('/about', fn () => Route::isLocalized() ? 'yes' : 'no')->name('about');
            });
        });

        $response = $this->get('/about');
        $response->assertOk();
        $response->assertSee('yes');
    }

    public function test_is_localized_returns_true_inside_translate_macro_route()
    {
        Lang::addLines(['routes.about' => 'ueber'], 'de');
        Lang::addLines(['routes.about' => 'about'], 'en');

        Route::middleware(SetLocale::class)->group(function () {
            Route::translate(function () {
                Route::get(
                    \NielsNumbers\LaravelLocalizer\Facades\Localizer::url('about'),
                    fn () => Route::isLocalized() ? 'yes' : 'no'
                )->name('about');
            });
        });

        $response = $this->get('/about');
        $response->assertOk();
        $response->assertSee('yes');
    }

    public function test_is_localized_returns_false_inside_plain_route()
    {
        Route::get('/contact', fn () => Route::isLocalized() ? 'yes' : 'no')->name('contact');

        $response = $this->get('/contact');
        $response->assertOk();
        $response->assertSee('no');
    }

    public function test_is_localized_returns_false_outside_request_context()
    {
        $this->assertFalse(Route::isLocalized());
    }
}
