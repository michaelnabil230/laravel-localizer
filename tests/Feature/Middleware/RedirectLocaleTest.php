<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature\Middleware;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\Middleware\RedirectLocale;
use Orchestra\Testbench\TestCase;
use NielsNumbers\LaravelLocalizer\ServiceProvider;

class RedirectLocaleTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.locale', 'en');

        Route::middleware(RedirectLocale::class)
            ->get('/{locale}/about', fn() => 'ok');
        Route::middleware(RedirectLocale::class)
            ->get('/about', fn() => 'ok');

        // Logs create permission conflicts in docker
        \Illuminate\Support\Facades\Log::swap(new \Illuminate\Log\Logger(
            new \Monolog\Logger('null', [new \Monolog\Handler\NullHandler()])
        ));
    }

    protected function defineEnvironment($app)
    {
        Config::set('app.fallback_locale', 'en');
        Config::set('localizer.supported_locales', ['en', 'de', 'pt-BR']);
    }

    public function test_redirects_default_locale_when_hidden()
    {
        App::setLocale('en');
        Config::set('localizer.hide_default_locale', true);

        $response = $this->get('/en/about');
        $response->assertRedirect('/about');
    }

    public function test_redirects_to_prefixed_locale_when_missing()
    {
        App::setLocale('de');
        Config::set('localizer.hide_default_locale', true);

        $response = $this->get('/about');
        $response->assertRedirect('/de/about');
    }

    public function test_no_redirect_when_disabled()
    {
        App::setLocale('en');
        Config::set('localizer.redirect_enabled', false);

        $response = $this->get('/about');
        $response->assertOk();
    }

    public function test_does_not_treat_unsupported_two_letter_prefix_as_locale()
    {
        App::setLocale('en');
        Config::set('localizer.hide_default_locale', true);

        Route::middleware(RedirectLocale::class)->get('/xx/about', fn() => 'ok');

        $response = $this->get('/xx/about');
        $response->assertOk();
    }

    public function test_handles_multi_character_locale_prefix()
    {
        App::setLocale('pt-BR');
        Config::set('app.fallback_locale', 'pt-BR');
        Config::set('localizer.hide_default_locale', true);

        $response = $this->get('/pt-BR/about');
        $response->assertRedirect('/about');
    }

    public function test_preserves_query_string_when_stripping_default_prefix()
    {
        App::setLocale('en');
        Config::set('localizer.hide_default_locale', true);

        // Symfony's getQueryString() returns parameters sorted alphabetically.
        $response = $this->get('/en/about?utm_source=newsletter&ref=foo');
        $response->assertRedirect('/about?ref=foo&utm_source=newsletter');
    }

    public function test_preserves_query_string_when_adding_prefix()
    {
        App::setLocale('de');
        Config::set('localizer.hide_default_locale', true);

        $response = $this->get('/about?utm_source=newsletter');
        $response->assertRedirect('/de/about?utm_source=newsletter');
    }
}
