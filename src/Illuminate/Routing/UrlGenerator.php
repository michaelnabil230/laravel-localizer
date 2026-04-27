<?php

namespace NielsNumbers\LaravelLocalizer\Illuminate\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\UrlGenerator as BaseUrlGenerator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;

class UrlGenerator extends BaseUrlGenerator
{
    public function route($name, $parameters = [], $absolute = true)
    {
        $urlLocale = $parameters['locale'] ?? null;
        $appLocale = App::getLocale();
        $locale = $urlLocale ?? $appLocale;

        if (Route::has($name)) {
            return parent::route($name, $parameters, $absolute);
        }

        $defaultLocale = config('app.fallback_locale');
        $hideDefault = Localizer::hideDefaultLocale();

        // Hidden default locale: when the requested locale is the default AND we're already
        // serving the default-locale page, drop the prefix (/about instead of /en/about).
        // The app-locale check is intentional: if the app is in another language and we're
        // building a switch-link to the default, we MUST keep the prefix (/en/about) so
        // SetLocale can detect the switch from the URL — RedirectLocale then strips it.
        //
        // This section covers BOTH macros (LocalizeMacro and TranslateMacro): both register
        // a `without_locale.{name}` variant for the default locale when hide_default is on,
        // so we don't need a macro-specific check here.
        if ($hideDefault && ($urlLocale === $defaultLocale || $urlLocale === null) && $appLocale === $defaultLocale) {
            $withoutLocaleName = 'without_locale.' . $name;
            if (Route::has($withoutLocaleName)) {
                // Unset locale, otherwise Laravel appends it as ?locale=xx query string
                // because the target route doesn't declare a {locale} parameter.
                unset($parameters['locale']);
                return parent::route($withoutLocaleName, $parameters, $absolute);
            }
        }

        // LocalizeMacro: the locale is a {locale} route parameter, e.g. /{locale}/about.
        $resolvedName = 'with_locale.' .  $name;
        if (Route::has($resolvedName)) {
            $parameters['locale'] = $locale;
            return parent::route($resolvedName, $parameters, $absolute);
        }

        // TranslateMacro: the locale is baked into the route name and path
        // (e.g. translated_de.about → /de/ueber). One route per supported locale.
        $resolvedName = "translated_{$locale}." .  $name;
        if (Route::has($resolvedName)) {
            // Locale is part of the route definition, not a parameter — same query-string
            // reason as above.
            unset($parameters['locale']);
            return parent::route($resolvedName, $parameters, $absolute);
        }

        // Fallback: only reached if the caller passed an unsupported locale (e.g.
        // route('about', ['locale' => 'klingon'])). We can't resolve it, so we degrade
        // to the default-locale variant and let parent::route() raise RouteNotFoundException
        // if even that doesn't exist.
        //
        // The hideDefault branch isn't a functional necessity — RedirectLocale would strip
        // /en/about → /about anyway. But generating /about directly keeps the output
        // consistent with the hide-convention and avoids an unnecessary 301 round-trip.
        $resolvedName = "translated_{$defaultLocale}." .  $name;
        if ($hideDefault) {
            $resolvedName = 'without_locale.' . $name;
        }
        return parent::route($resolvedName, $parameters, $absolute);
    }

}
