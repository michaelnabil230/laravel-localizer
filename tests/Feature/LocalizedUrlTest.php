<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\Middleware\SetLocale;
use NielsNumbers\LaravelLocalizer\ServiceProvider;
use Orchestra\Testbench\TestCase;

class LocalizedUrlTest extends TestCase
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

    public function test_named_localize_route_returns_canonical_url_for_default_locale()
    {
        Route::middleware(SetLocale::class)->group(function () {
            Route::localize(function () {
                Route::get('/about', fn () => Route::localizedUrl('en', false))->name('about');
            });
        });

        // Visit the German variant. Switching to en (default + hidden) should
        // drop the prefix entirely — the canonical URL, not /en/about.
        $response = $this->get('/de/about');

        $response->assertOk();
        $response->assertSee('/about');
        $response->assertDontSee('/en/about');
    }

    public function test_named_localize_route_returns_prefixed_url_for_non_default_locale()
    {
        Route::middleware(SetLocale::class)->group(function () {
            Route::localize(function () {
                Route::get('/about', fn () => Route::localizedUrl('de', false))->name('about');
            });
        });

        // App is in en (default), unprefixed URL. Switching to de should add
        // the prefix.
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('/de/about');
    }

    public function test_named_translate_route_resolves_to_translated_uri()
    {
        Lang::addLines(['routes.about' => 'ueber'], 'de');
        Lang::addLines(['routes.about' => 'about'], 'en');

        Route::middleware(SetLocale::class)->group(function () {
            Route::translate(function () {
                Route::get(\NielsNumbers\LaravelLocalizer\Facades\Localizer::url('about'), function () {
                    return Route::localizedUrl('de', false);
                })->name('about');
            });
        });

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('/de/ueber');
    }

    public function test_unnamed_translated_route_throws()
    {
        Lang::addLines(['routes.about' => 'ueber'], 'de');
        Lang::addLines(['routes.about' => 'about'], 'en');

        Route::middleware(SetLocale::class)->group(function () {
            Route::translate(function () {
                Route::get(\NielsNumbers\LaravelLocalizer\Facades\Localizer::url('about'), function () {
                    return Route::localizedUrl('de', false);
                });
            });
        });

        $this->withoutExceptionHandling();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('unnamed translated route');

        $this->get('/about');
    }

    public function test_unnamed_localize_route_falls_back_to_uri_prefix_swap()
    {
        Route::middleware(SetLocale::class)->group(function () {
            Route::localize(function () {
                Route::get('/about', fn () => Route::localizedUrl('de', false));
            });
        });

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('/de/about');
    }

    public function test_unnamed_localize_route_swap_preserves_query_string()
    {
        Route::middleware(SetLocale::class)->group(function () {
            Route::localize(function () {
                Route::get('/about', fn () => Route::localizedUrl('de', false));
            });
        });

        $response = $this->get('/about?ref=newsletter');

        $response->assertOk();
        $response->assertSeeText('/de/about?ref=newsletter');
    }

    public function test_throws_when_called_outside_request_context()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('active matched route');

        Route::localizedUrl('de');
    }
}
