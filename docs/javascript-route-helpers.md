# JavaScript Route Helpers

Client-side URL builders like [Ziggy](https://github.com/tighten/ziggy)
and [Laravel Wayfinder](https://github.com/laravel/wayfinder) don't go
through this package's `UrlGenerator` override; the locale-aware variant
selection that `route('about')` does on the server doesn't happen in JS
automatically. With a small adapter per stack you get the same DX as on
the server.

| Stack | What you write in JS | What you install |
|---|---|---|
| **Ziggy** | `route('about')`, unchanged | `LocalizerZiggy` subclass + container binding |
| **Wayfinder** | `localizedRoute('about')` | TS helper module |

> Using **Inertia.js**? Inertia bundles Ziggy or Wayfinder as its route
> helper, so the same setup applies. Pick the section that matches
> your stack. The adapter rewrites the route manifest before Inertia
> ships it to the client; nothing extra to wire up.

## Ziggy: `LocalizerZiggy` adapter

The adapter subclasses Ziggy and rewrites the route manifest based on
the current `App::getLocale()` and `hide_default_locale`. After it's
installed, `route('about')` in JS resolves to the same URL that
`route('about')` would on the server:

```php
// app/Routing/LocalizerZiggy.php
namespace App\Routing;

use Illuminate\Support\Facades\App;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;
use Tighten\Ziggy\Ziggy;

class LocalizerZiggy extends Ziggy
{
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['routes'] = $this->rewriteForCurrentLocale($data['routes']);
        return $data;
    }

    protected function rewriteForCurrentLocale(array $routes): array
    {
        $appLocale     = App::getLocale();
        $defaultLocale = config('app.fallback_locale');
        $useUnprefixed = Localizer::hideDefaultLocale()
                      && $appLocale === $defaultLocale;
        $translatedPrefix = "translated_{$appLocale}.";

        $rewritten = [];

        foreach ($routes as $name => $def) {
            if (str_starts_with($name, 'with_locale.')) {
                if ($useUnprefixed) continue;          // without_locale wins
                $rewritten[substr($name, 12)] = $def;
                continue;
            }
            if (str_starts_with($name, 'without_locale.')) {
                if (! $useUnprefixed) continue;
                $rewritten[substr($name, 15)] = $def;
                continue;
            }
            if (str_starts_with($name, $translatedPrefix)) {
                $rewritten[substr($name, strlen($translatedPrefix))] = $def;
                continue;
            }
            if (str_starts_with($name, 'translated_')) continue; // other locales
            $rewritten[$name] = $def;                   // non-localized passthrough
        }

        return $rewritten;
    }
}
```

Bind it in your `AppServiceProvider::register()`:

```php
$this->app->bind(\Tighten\Ziggy\Ziggy::class, \App\Routing\LocalizerZiggy::class);
```

Now `@routes` in your Blade layout (or the Ziggy bridge that Inertia
uses) emits the locale-aware manifest. `URL::defaults(['locale' => …])`
is already set by the `SetLocale` middleware, so Ziggy fills in
`{locale}` placeholders automatically:

```js
// current locale = de
route('about');                   // '/de/about'
route('about', { locale: 'fr' }); // '/fr/about' (explicit override)

// current locale = en (= default, hide_default_locale on)
route('about');                   // '/about'
```

## Wayfinder: `localizedRoute()` helper

Wayfinder generates typed functions at build time and doesn't read
`URL::defaults`, so a build-time rewrite would break tree-shaking and
lose per-route type inference. Instead, ship a small lookup helper that
wraps the generated modules and mirrors the server-side variant pick:

```ts
// resources/js/localizedRoute.ts
import * as withLocale    from '@/routes/with_locale';
import * as withoutLocale from '@/routes/without_locale';

const DEFAULT_LOCALE = 'en';   // mirror config('app.fallback_locale')
const HIDE_DEFAULT   = true;   // mirror localizer.hide_default_locale

// Use whatever locale source you have. With Inertia, share it from the
// server: HandleInertiaRequests::share() returns ['locale' => app()->getLocale()]
// and you read usePage().props.locale here.
function currentLocale(): string {
    return document.documentElement.lang || DEFAULT_LOCALE;
}

export function localizedRoute<K extends keyof typeof withLocale>(
    name: K,
    params: Record<string, any> = {},
): string {
    const locale = params.locale ?? currentLocale();
    const { locale: _, ...rest } = params;

    if (HIDE_DEFAULT && locale === DEFAULT_LOCALE && (name in withoutLocale)) {
        return (withoutLocale as any)[name].url(rest);
    }
    return (withLocale as any)[name].url({ ...rest, locale });
}
```

```ts
import { localizedRoute } from '@/localizedRoute';

localizedRoute('about');                   // '/de/about' (current = de)
localizedRoute('about', { locale: 'fr' }); // '/fr/about'
localizedRoute('about', { locale: 'en' }); // '/about'   (= default, hide_default)
```

For `Route::translate()` routes, extend the helper with one extra branch
that imports `@/routes/translated_<locale>` and dispatches by the active
locale; same pattern.

## Cross-locale URLs and SEO

Both adapters above optimize for **the current request's locale**, ideal
for in-page links. For `hreflang` tags, canonical URLs and sitemaps you
want all locales at once and a guaranteed canonical form (no 301
round-trip on the default locale). Render those server-side via
`Route::localizedUrl($locale)` regardless of which JS helper you use.
See [Template Helpers in the README](../README.md#template-helpers).
