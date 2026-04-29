<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature\Middleware;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Orchestra\Testbench\TestCase;
use NielsNumbers\LaravelLocalizer\Middleware\SetLocale;
use NielsNumbers\LaravelLocalizer\Localizer;
use NielsNumbers\LaravelLocalizer\ServiceProvider;

class SetLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Logs create permission conflicts in docker
        \Illuminate\Support\Facades\Log::swap(new \Illuminate\Log\Logger(
            new \Monolog\Logger('null', [new \Monolog\Handler\NullHandler()])
        ));
    }

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
        Config::set('localizer.persist_locale.session', true);
        Config::set('localizer.persist_locale.cookie', true);
    }

    protected function defineRoutes($router)
    {
        // SetLocale skips routes without a `locale_type` action attribute
        // (see middleware). Tag these test routes so the middleware engages
        // — production routes get this for free via Route::localize().
        $router->group([
            'middleware' => SetLocale::class,
            'locale_type' => 'with_locale',
        ], function () use ($router) {
            $router->get('/{locale}/about', fn($locale) => $locale)->name('about.locale');
            $router->get('/about', fn() => response('about'))->name('about');
            $router->get('/{locale}', fn() => response('start'))->name('start.locale');
            $router->get('/', fn() => response('start'))->name('start');
        });
    }

    public function test_sets_locale_from_route_parameter()
    {
        $response = $this->get('/de/about');
        $this->assertEquals('de', App::getLocale());
        $response->assertOk(); // route doesn't exist, but middleware ran
    }

    public function test_stores_locale_in_session()
    {
        Config::set('localizer.use_session', true);
        Session::flush();

        $this->get('/de/about');
        $this->assertEquals('de', Session::get('locale'));
    }

    public function test_reads_locale_from_session()
    {
        Config::set('localizer.use_session', true);
        session(['locale' => 'de']);

        $this->get('/about');
        $this->assertEquals('de', Session::get('locale'));
    }

    public function test_ignores_unsupported_locale_in_url()
    {
        $this->get('/xx/about');

        $this->assertEquals('en', App::getLocale());
    }

    public function test_ignores_unsupported_locale_in_session()
    {
        session(['locale' => 'xx']);

        $this->get('/about');

        $this->assertEquals('en', App::getLocale());
    }

    public function test_detects_locale_from_accept_language_header()
    {
        $this->get('/about', ['Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8']);

        $this->assertEquals('de', App::getLocale());
    }

    public function test_skips_accept_language_when_not_supported()
    {
        $this->get('/about', ['Accept-Language' => 'fr-FR,fr;q=0.9']);

        $this->assertEquals('en', App::getLocale());
    }
}
