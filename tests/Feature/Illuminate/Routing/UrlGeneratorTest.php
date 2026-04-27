<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature\Illuminate\Routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Orchestra\Testbench\TestCase;
use NielsNumbers\LaravelLocalizer\ServiceProvider;
use NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator as CustomUrlGenerator;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class UrlGeneratorTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    #[Test]
    public function it_replaces_the_default_url_generator()
    {
        $url = $this->app->make(UrlGeneratorContract::class);

        $this->assertInstanceOf(CustomUrlGenerator::class, $url);
        $this->assertSame($url, app('url')); // both bindings are identical
    }

    #[Test]
    public function it_throws_exception_if_route_is_not_found()
    {
        // Route::get('/test', fn () => 'ok')->name('test');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');

        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $this->expectException(RouteNotFoundException::class);

        // Because your route() method doesn’t return yet, we just check that it’s called
        $url->route('test');
    }

    #[Test]
    public function it_works_for_standard_routes()
    {
        Route::get('/test', fn () => 'ok')->name('test');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');

        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        // Because your route() method doesn’t return yet, we just check that it’s called
        $route = $url->route('test', [], false);

        $this->assertEquals('/test', $route);
    }


    /** @test */
    public function it_works_for_route_with_locale()
    {
        Route::get('/{locale}/about', fn () => 'ok')->name('with_locale.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');

        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        // Because your route() method doesn’t return yet, we just check that it’s called
        $route = $url->route('about', ['locale' => 'de'], false);

        $this->assertEquals('/de/about', $route);
    }

     /** @test */
    public function it_works_for_route_without_locale()
    {
        // Laravel’s default locale (from config/app.php)
        config()->set('app.locale', 'en');
        app()->setLocale('en');

        // Package config: simulate localizer.php values
        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/{locale}/about', fn () => 'ok')->name('with_locale.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        // Because your route() method doesn’t return yet, we just check that it’s called
        $route = $url->route('about', ['locale' => 'en'], false);

        $this->assertEquals('/about', $route);
    }

    /** @test */
    public function it_works_for_route_without_locale_without_parameters()
    {
        // Laravel’s default locale (from config/app.php)
        config()->set('app.locale', 'en');
        app()->setLocale('en');

        // Package config: simulate localizer.php values
        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/{locale}/about', fn () => 'ok')->name('with_locale.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        // Because your route() method doesn’t return yet, we just check that it’s called
        $route = $url->route('about', [], false);

        $this->assertEquals('/about', $route);
    }

    /** @test */
    public function it_works_for_switch_to_route_without_locale()
    {
        app()->setLocale('de');

        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/{locale}/about', fn () => 'ok')->name('with_locale.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $route = $url->route('about', ['locale' => 'en'], false);

        // Set locale is set to `de`, thus we need to call `/en/about` to request
        // locale change, otherwise it would redirect to de
        $this->assertEquals('/en/about', $route);
    }

    /** @test */
    public function it_autoloads_locale_into_route()
    {
        app()->setLocale('de');

        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/{locale}/about', fn () => 'ok')->name('with_locale.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $route = $url->route('about', [], false);

        $this->assertEquals('/de/about', $route);
    }


    /** @test */
    public function it_loads_translated_de_route()
    {
        app()->setLocale('de');

        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/de/ueber', fn () => 'ok')->name('translated_de.about');
        Route::get('/en/about', fn () => 'ok')->name('translated_en.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $route = $url->route('about', [], false);

        $this->assertEquals('/de/ueber', $route);
    }

    /** @test */
    public function it_loads_translated_en_route_with_locale_in_url()
    {
        app()->setLocale('de');

        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/de/ueber', fn () => 'ok')->name('translated_de.about');
        Route::get('/en/about', fn () => 'ok')->name('translated_en.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $route = $url->route('about', ['locale' => 'en'], false);

        $this->assertEquals('/en/about', $route);
    }

    /** @test */
    public function it_loads_translated_en_route_without_locale_in_url()
    {
        app()->setLocale('en');

        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/de/ueber', fn () => 'ok')->name('translated_de.about');
        Route::get('/en/about', fn () => 'ok')->name('translated_en.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $route = $url->route('about', ['locale' => 'en'], false);

        $this->assertEquals('/about', $route);
    }

    /** @test */
    public function it_loads_translated_en_route_without_locale_in_url_without_parameter()
    {
        app()->setLocale('en');

        config()->set('localizer.supported_locales', ['en', 'de']);
        config()->set('localizer.hide_default_locale', true);

        Route::get('/de/ueber', fn () => 'ok')->name('translated_de.about');
        Route::get('/en/about', fn () => 'ok')->name('translated_en.about');
        Route::get('/about', fn () => 'ok')->name('without_locale.about');

        /** @var \NielsNumbers\LaravelLocalizer\Illuminate\Routing\UrlGenerator $url */
        $url = app('url');
        $this->assertInstanceOf(CustomUrlGenerator::class, $url);

        $route = $url->route('about', [], false);

        $this->assertEquals('/about', $route);
    }

}
