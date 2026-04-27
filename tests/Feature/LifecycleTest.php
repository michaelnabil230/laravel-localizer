<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\Middleware\RedirectLocale;
use NielsNumbers\LaravelLocalizer\Middleware\SetLocale;
use NielsNumbers\LaravelLocalizer\ServiceProvider;
use Orchestra\Testbench\TestCase;

class LifecycleTest extends TestCase
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

    protected function defineRoutes($router)
    {
        $router->middleware([SetLocale::class, RedirectLocale::class])->group(function () {
            Route::localize(function () {
                Route::get('/about', fn () => route('about', [], false))->name('about');
                Route::get('/contact', fn () => route('about', ['locale' => 'en'], false))->name('contact');
            });
        });
    }

    public function test_localized_request_resolves_and_handler_generates_matching_url()
    {
        $response = $this->get('/de/about');

        $response->assertOk();
        $response->assertSee('/de/about');
    }

    public function test_default_locale_request_drops_prefix_in_generated_urls()
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('/about');
        $response->assertDontSee('/en/about');
    }

    public function test_visiting_default_locale_with_prefix_redirects_to_unprefixed()
    {
        $response = $this->get('/en/about');

        $response->assertRedirect('/about');
    }

    public function test_visiting_unprefixed_path_with_browser_in_non_default_locale_redirects()
    {
        $response = $this->get('/about', ['Accept-Language' => 'de-DE,de;q=0.9']);

        $response->assertRedirect('/de/about');
    }

    public function test_query_string_is_preserved_through_redirect_chain()
    {
        $response = $this->get('/about?utm_source=newsletter', [
            'Accept-Language' => 'de-DE,de;q=0.9',
        ]);

        $response->assertRedirect('/de/about?utm_source=newsletter');
    }

    public function test_explicit_locale_switch_keeps_prefix_for_target_default_locale()
    {
        // Visiting /de/contact while app is German — handler builds a
        // switch-link to English (the default). With hide_default_locale on,
        // the target route still needs the /en prefix at link-time so SetLocale
        // detects the switch from the URL; RedirectLocale then strips it on
        // the follow-up request.
        $response = $this->get('/de/contact');

        $response->assertOk();
        $response->assertSee('/en/about');
    }

    public function test_unsupported_path_prefix_is_not_treated_as_locale()
    {
        Route::middleware([SetLocale::class, RedirectLocale::class])
            ->get('/xx/page', fn () => 'ok');

        $response = $this->get('/xx/page');

        $response->assertOk();
        $response->assertSee('ok');
    }
}
