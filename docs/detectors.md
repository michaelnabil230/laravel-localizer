# Detectors

## Locale Resolution Order

`SetLocale` walks through the following sources, in order, and uses the
first one that yields a supported locale:

1. **URL** - the `{locale}` segment of a `with_locale.*` route
   (`/de/about` -> `de`).
2. **Route action** - the `locale` action attribute of the matched route.
   `Route::translate()` registers per-locale routes with literal prefixes
   (`/de/ueber`) and no `{locale}` parameter; the macro stores the locale
   in the route action so `SetLocale` can recover it here.
3. **Session** - the locale stored on a previous request.
4. **Cookie** - the locale persisted client-side.
5. **Detectors** - see below (auth user preference, `Accept-Language`, custom).
6. **`fallback_locale`** from `config/app.php`.

Only the **URL** and **route action** can override an existing session
or cookie. If neither carries a locale signal (the request came in as
`/about` rather than `/de/about` or `/de/ueber`), `SetLocale` keeps
using the session/cookie value: that's deliberate, so a user who once
picked German isn't reset to English every time they hit an unprefixed
link, and `RedirectLocale` can send them to the prefixed variant.

This is also the reason the language switcher uses
`Route::localizedSwitcherUrl()` rather than `Route::localizedUrl()`:
the switcher always emits the prefixed form (`/en/about`, even for the
hidden default locale), so the URL itself flips the active locale on
click. `RedirectLocale` then strips the prefix on the follow-up to
restore the canonical form. See [Language Switcher](/language-switcher).

## Available Detectors

Detectors run only when steps 1-3 above produced nothing, typically a
first visit with no session and no cookie. Each implements a simple
interface that returns a locale string or `null`.

By default, two detectors are provided:

1. **`UserDetector`** - reads the locale from the authenticated user
   model (if available).
2. **`BrowserDetector`** - detects the preferred language from the
   `Accept-Language` HTTP header.

## Custom Detectors

You can register your own detectors by adding them to the `detectors`
array in the configuration. They are executed in the order they appear;
the first one returning a locale stops the chain.

```php
// config/localizer.php
'detectors' => [
    \App\Locale\CustomDetector::class,
    \NielsNumbers\LaravelLocalizer\Detectors\UserDetector::class,
    \NielsNumbers\LaravelLocalizer\Detectors\BrowserDetector::class,
],
```

A custom detector implements `DetectorInterface`:

```php
namespace App\Locale;

use Illuminate\Http\Request;
use NielsNumbers\LaravelLocalizer\Contracts\DetectorInterface;

class CustomDetector implements DetectorInterface
{
    public function detect(Request $request): ?string
    {
        // return a locale string, or null to defer to the next detector
        return $request->header('X-App-Locale');
    }
}
```
