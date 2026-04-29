<?php

namespace NielsNumbers\LaravelLocalizer\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use NielsNumbers\LaravelLocalizer\Localizer;
use Symfony\Component\HttpFoundation\Response;

class RedirectLocale
{
    public function __construct(protected Localizer $localizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Config::get('localizer.redirect_enabled', true)) {
            return $next($request);
        }

        $locale = App::getLocale();
        $default = Config::get('app.fallback_locale');
        $hideDefault = Config::get('localizer.hide_default_locale', true);

        $path = ltrim($request->path(), '/');
        // We can't use $request->route('locale'): only LocalizeMacro routes
        // (/{locale}/about) declare it as a parameter. TranslateMacro routes
        // (/de/ueber), directly registered routes (Route::get('/de/foo')) and
        // without_locale.* routes don't — yet they all need this middleware.
        // So we look at the raw path and split off the first segment ourselves.
        [$prefix, $rest] = array_pad(explode('/', $path, 2), 2, '');

        // The first path segment counts as a locale only if it's whitelisted —
        // matching by regex would either miss multi-character tags (zh-CN, pt-BR)
        // or treat any two-letter prefix as a locale, redirecting nonsense paths.
        $hasLocalePrefix = $this->localizer->isActive($prefix);

        // Locale prefix matches default + default should be hidden → strip it
        if ($hasLocalePrefix && $prefix === $default && $hideDefault) {
            return $this->redirectTo($request, $rest);
        }

        // No locale prefix + app is in a non-default language → add prefix
        if (! $hasLocalePrefix && $locale !== $default) {
            return $this->redirectTo($request, $locale . '/' . $path);
        }

        return $next($request);
    }

    protected function redirectTo(Request $request, string $path): Response
    {
        $url = url('/' . ltrim($path, '/'));
        $query = $request->getQueryString();

        return redirect($query ? "{$url}?{$query}" : $url);
    }
}
