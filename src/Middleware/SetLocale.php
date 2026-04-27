<?php

namespace NielsNumbers\LocaleRouting\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use NielsNumbers\LocaleRouting\Contracts\DetectorInterface;
use NielsNumbers\LocaleRouting\Localizer;

class SetLocale
{
    public function __construct(protected Localizer $localizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request) ?? config('app.fallback_locale');

        if ($this->localizer->storesInSession()) {
            Session::put('locale', $locale);
        }

        if ($this->localizer->storesInCookie()) {
            Cookie::queue('locale', $locale, 60 * 24 * 30);
        }

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }

    protected function detectLocale(Request $request): ?string
    {
        $routeLocale = $request->route('locale');
        if ($this->localizer->isSupported($routeLocale)) {
            return $routeLocale;
        }

        if ($this->localizer->storesInSession()) {
            $sessionLocale = Session::get('locale');
            if ($this->localizer->isSupported($sessionLocale)) {
                return $sessionLocale;
            }
        }

        if ($this->localizer->storesInCookie()) {
            $cookieLocale = $request->cookie('locale');
            if ($this->localizer->isSupported($cookieLocale)) {
                return $cookieLocale;
            }
        }

        return $this->detectLocaleFromDetectors($request);
    }

    protected function detectLocaleFromDetectors(Request $request): ?string
    {
        foreach ($this->localizer->detectors() as $detectorClass) {
            $detector = app($detectorClass);
            if (! $detector instanceof DetectorInterface) {
                continue;
            }

            $result = $detector->detect($request);

            foreach ((array) $result as $candidate) {
                if ($this->localizer->isSupported($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
