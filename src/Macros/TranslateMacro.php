<?php

namespace NielsNumbers\LaravelLocalizer\Macros;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Traits\Localizable;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;
use NielsNumbers\LaravelLocalizer\Services\UriTranslator;

class TranslateMacro
{
    use Localizable;

    public function __construct(
        protected UriTranslator $translator
    ) {}

    /**
     * Register translated routes for all supported locales.
     *
     * For each supported locale:
     * - prefixes the route with the locale (e.g. /de/about)
     * - uses translated URIs (the user's closure calls Localizer::uri('...'),
     *   which reads App::getLocale() — hence the per-iteration withLocale)
     * - creates a "without locale" route for the fallback locale when
     *   hide_default_locale is on
     *
     * Each group sets a `locale` action attribute carrying the registered
     * locale. Translated routes use literal prefixes (/de, /fr), so they
     * carry no {locale} URL parameter; SetLocale reads getAction('locale')
     * to recover the locale from the route — same pattern as locale_type
     * in LocalizeMacro.
     */
    public function register(Closure $routes): void
    {
        $supported = Localizer::supportedLocales();
        $default = Config::get('app.fallback_locale');
        $hide = Localizer::hideDefaultLocale();

        foreach ($supported as $locale) {
            $this->withLocale($locale, function () use ($routes, $locale, $default, $hide) {
                Route::group([
                    'prefix' => $locale,
                    'as' => "translated_$locale.",
                    'locale' => $locale,
                ], $routes);

                // For the default locale: register a no-prefix variant.
                // The route name namespace ('without_locale.') prevents a
                // collision with the LocalizeMacro's own without-locale
                // routes.
                if ($locale === $default && $hide) {
                    Route::group([
                        'as' => 'without_locale.',
                        'locale' => $locale,
                    ], $routes);
                }
            });
        }
    }
}
