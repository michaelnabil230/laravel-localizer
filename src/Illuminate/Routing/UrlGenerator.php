<?php

namespace NielsNumbers\LocaleRouting\Illuminate\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\UrlGenerator as BaseUrlGenerator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LocaleRouting\Facades\Localizer;

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

        // If the default locale is hidden and both the URL and app locales match the default,
        // skip adding the locale to the URL. Otherwise, include it to update the app locale.
        if ($hideDefault && ($urlLocale === $defaultLocale || $urlLocale === null) && $appLocale === $defaultLocale) {
            $withoutLocaleName = 'without_locale.' . $name;
            if (Route::has($withoutLocaleName)) {
                // Need to unset locale here, otherwise it will
                // show as query paramter in url
                unset($parameters['locale']);
                return parent::route($withoutLocaleName, $parameters, $absolute);
            }
        }


        // Check for localized route, locale is part of the url as parameter {localize}
        $resolvedName = 'with_locale.' .  $name;
        if (Route::has($resolvedName)) {
            $parameters['locale'] = $locale;
            return parent::route($resolvedName, $parameters, $absolute);
        }

        return parent::route($resolvedName, $parameters, $absolute);
    }

}
