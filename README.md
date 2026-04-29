# Laravel Localizer

Detect a visitor's preferred language and serve the right localized URL.
Optionally hide the default locale or translate URI paths, and keep
using `route(...)` as usual, fully compatible with `php artisan
route:cache`, with adapters available for Ziggy / Wayfinder.

## Table of Contents

- [Example](#example)
- [Installation](#installation)
- [Defining Routes](#defining-routes)
- [Template Helpers](#template-helpers)
- [JavaScript Route Helpers](#javascript-route-helpers)
- [Language Switcher](#language-switcher)
- [Configuration](#configuration)
- [Detectors](#detectors)
- [Redirects](#redirects)
- [Locale in Jobs, Mailables and Notifications](#locale-in-jobs-mailables-and-notifications)
- [Translated URL Paths](#translated-url-paths)
- [Caveats and Recipes](#caveats-and-recipes)
- [When to use this package](#when-to-use-this-package)
- [Comparison to other packages](#comparison-to-other-packages)
- [Background](#background)
- [Testing](#testing)
- [Credits](#credits)

## Example

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});
```

This single definition produces:

- `/about`: default locale (e.g. English), prefix hidden
- `/de/about`: German
- `/fr/about`: French (and so on for every configured locale)

In your application code, `route('about')` always picks the right
variant for the current request, both server-side and (with the
[JS adapter](#javascript-route-helpers)) client-side.

When a visitor first lands on `example.com`, the package detects their
browser language and redirects to the matching locale. The choice is
persisted in the session and a cookie for follow-up requests.

## Installation

```bash
composer require niels-numbers/laravel-localizer
```

The service provider auto-registers via package discovery. Three steps
to finish setup:

**1. Set your supported locales.** Make sure your default is set in
`config/app.php`:

```php
'fallback_locale' => 'en',
```

Then publish and edit the package config:

```bash
php artisan vendor:publish --provider="NielsNumbers\\LaravelLocalizer\\ServiceProvider" --tag=config
```

```php
// config/localizer.php
return [
    'supported_locales' => ['en', 'de', 'fr'],
    // ...
];
```

**2. Register the middleware.**

For Laravel 11+, in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \NielsNumbers\LaravelLocalizer\Middleware\SetLocale::class,
        \NielsNumbers\LaravelLocalizer\Middleware\RedirectLocale::class,
    ]);
})
```

For Laravel 9 / 10, add both classes to the `web` group in
`app/Http/Kernel.php`.

**3. Wrap your routes** in `Route::localize()`. See [Defining Routes](#defining-routes).

## Defining Routes

Wrap your routes with `Route::localize()` to register them in both their
prefixed and unprefixed form:

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});
```

This generates `/about`, `/de/about`, `/fr/about` (etc.) from a single
definition. In your application code, keep using `route('about')`; the
package picks the right variant based on the current locale.

To attach middleware, prefixes, or other route attributes, define them
**inside** the `Route::localize()` closure as you would in any other group —
`Route::localize()` is itself a group, so nested groups compose the way
Laravel groups normally compose:

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');

    Route::middleware('auth')->prefix('account')->group(function () {
        Route::get('/profile', ProfileController::class)->name('profile');
    });
});
```

> **The closure runs twice**, once per route variant. Keep it side-effect-free:
> no logging, no DB writes, no external calls. Treat it as a pure route
> definition.

> Need per-locale paths like `/about`, `/de/ueber`, `/fr/a-propos` instead
> of just locale prefixes? See [Translated URL Paths](#translated-url-paths).

### URL Generation Is Context-Dependent

`route('about')` resolves to a different URL depending on the current
`App::getLocale()`. The same call inside an HTTP request, a queued job, or a
mailable can yield different results. That's the whole point: you keep using
`route('about')` everywhere and the package picks the right variant.

```php
App::setLocale('en');
route('about'); // → /about      (default locale, hidden via hide_default_locale)

App::setLocale('de');
route('about'); // → /de/about

route('about', ['locale' => 'en']); // → /about (explicit override wins)
```

This is **fully compatible with `php artisan route:cache`**. The cache
serializes the *route definitions* (`with_locale.about` → `/{locale}/about`,
`without_locale.about` → `/about`); those are static and deterministic. The
locale-aware *selection* between them happens at runtime in the URL generator,
which is unaffected by the cache. URL-translated routes built by
[`Route::translate()`](#translated-url-paths) are likewise baked into static
URIs at registration time, so the cache covers them too.

## Template Helpers

Three additional macros are available on the `Route` facade for use in
controllers, Blade templates, and middleware.

### `Route::localizedUrl($locale, $absolute = true)`

Returns the **current** request's URL in another locale. Primary use is
`<link rel="alternate" hreflang="...">` tags, canonical URLs and
sitemaps:

```blade
@foreach (config('localizer.supported_locales') as $locale)
    <link rel="alternate"
          hreflang="{{ $locale }}"
          href="{{ Route::localizedUrl($locale) }}" />
@endforeach
```

The returned URL is the **canonical** form. Switching to the default locale
with `hide_default_locale` enabled yields `/about` directly, not
`/en/about` followed by a 301. Suitable for hreflang attributes that crawlers
read literally. For an **in-page language switcher** use the sibling helper
`Route::localizedSwitcherUrl($locale)` — it always emits the prefixed form,
which is what carries the locale signal across the click. See
[Language Switcher](#language-switcher).

| Current route | Behavior |
|---|---|
| Named (recommended) | Resolved through `route()`; works for both macros. |
| Unnamed `Route::localize()` | Falls back to a URI prefix swap on the request path. |
| Unnamed `Route::translate()` | Throws `LogicException`; the translated URI can't be reversed. Add `->name()`. |
| Called outside a request | Throws `LogicException`. |

### `Route::hasLocalized($name)`

Returns `true` if a route with the given name was registered through
`Route::localize()` or `Route::translate()`:

```blade
@if (Route::hasLocalized('about'))
    <a href="{{ route('about') }}">{{ __('About') }}</a>
@endif
```

Checks `with_locale.{name}`, `without_locale.{name}` and
`translated_{$locale}.{name}` for every supported locale.

### `Route::isLocalized()`

Returns `true` if the **current** request was matched to a localizer-managed
route. Convenient for showing a language switcher only on localized pages:

```blade
@if (Route::isLocalized())
    @include('partials.language-switcher')
@endif
```

## JavaScript Route Helpers

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

### Ziggy: `LocalizerZiggy` adapter

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

### Wayfinder: `localizedRoute()` helper

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

### Cross-locale URLs and SEO

Both adapters above optimize for **the current request's locale**, ideal
for in-page links. For `hreflang` tags, canonical URLs and sitemaps you
want all locales at once and a guaranteed canonical form (no 301
round-trip on the default locale). Render those server-side via
`Route::localizedUrl($locale)` regardless of which JS helper you use.
See [Template Helpers](#template-helpers).

## Language Switcher

Use a single switcher component anywhere in your layout. It picks the
right URLs from **`Route::localizedSwitcherUrl()`** so each link points
to the **current page** in the target locale. Clicking a link triggers a
normal navigation: the URL carries the new locale, `SetLocale` reads it
on the next request and persists it to session/cookie.

> **Why a different helper than `localizedUrl()`?** `localizedUrl()`
> returns the **canonical** URL (no `/en` prefix when English is the
> hidden default) — correct for `<link rel="alternate">` and sitemaps.
> But a switcher link to the default locale needs the prefix: it's the
> only way the URL itself can tell `SetLocale` which language to switch
> to. Without it, a stale session locale would win and `RedirectLocale`
> would bounce the visitor back. `localizedSwitcherUrl()` always emits
> the prefixed form; `RedirectLocale` then strips it on the follow-up
> request, so the browser ends up on the canonical URL anyway — one
> invisible 302 hop.

### Blade

Define once as a component, include anywhere:

```blade
{{-- resources/views/components/language-switcher.blade.php --}}
@foreach (config('localizer.supported_locales') as $locale)
    <a href="{{ Route::localizedSwitcherUrl($locale) }}"
       @class(['active' => app()->getLocale() === $locale])>
        {{ strtoupper($locale) }}
    </a>
@endforeach
```

```blade
<x-language-switcher />
```

### Inertia (Vue / React)

The Inertia bridge (Ziggy or Wayfinder underneath) doesn't see
`Route::localizedUrl()` directly. Render the per-locale URLs
server-side and ship them as shared props:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'locale'        => app()->getLocale(),
        'localizedUrls' => fn () => collect(config('localizer.supported_locales'))
            ->mapWithKeys(fn ($l) => [$l => Route::localizedSwitcherUrl($l)])
            ->all(),
    ]);
}
```

Then build a SPA component (Vue example; React works analogously):

```vue
<!-- resources/js/Components/LanguageSwitcher.vue -->
<script setup>
import { usePage } from '@inertiajs/vue3';
const { localizedUrls, locale } = usePage().props;
</script>

<template>
  <a v-for="(url, code) in localizedUrls" :key="code"
     :href="url" :class="{ active: code === locale }">
    {{ code.toUpperCase() }}
  </a>
</template>
```

A plain `<a>` triggers a full-page reload, which is typically what you
want when switching languages: the HTML `lang` attribute, shared props
and any cached translations all need to refresh.

> **SPA language switch via `<Link>`** has a few extra moving parts (Ziggy as
> a shared prop, `route()` reactive to `usePage()`, `<html lang>`
> updates, prefixed switcher URLs). See
> [docs/inertia-spa-language-switch.md](docs/inertia-spa-language-switch.md)
> for a working sketch — marked **experimental**, not yet verified
> end-to-end. Full reload remains the recommended default.

### Caveats

For routes with per-locale model bindings (translated slugs), some
links may build URLs that 404 on follow. Render switcher items
conditionally or add a fallback in `resolveRouteBinding()`.

## Configuration

Publish the config file with:

```bash
php artisan vendor:publish --provider="NielsNumbers\\LaravelLocalizer\\ServiceProvider" --tag=config
```

This creates `config/localizer.php`.

| Key | Type | Default | Description |
|-----|------|----------|--------------|
| `supported_locales` | `array` | `[]` | List of all available locales. Example: `['en', 'de']`. |
| `hide_default_locale` | `bool` | `true` | If `true`, URLs using the **default (fallback)** locale will be redirected to the version **without** a locale prefix. Example: `/en/about` → `/about`. |
| `persist_locale.session` | `bool` | `true` | If `true`, the detected locale is stored in the session. |
| `persist_locale.cookie` | `bool` | `true` | If `true`, the detected locale is stored in a browser cookie. |
| `detectors` | `array` | `[UserDetector::class, BrowserDetector::class]` | Ordered list of classes used to detect a user's locale when no locale is found in the URL, session, or cookie. See [Detectors](#detectors). |
| `redirect_enabled` | `bool` | `true` | Enables or disables automatic redirects between prefixed and unprefixed routes. See [Redirects](#redirects). |

> The package's reference for the **default locale** is
> `config('app.fallback_locale')` (in `config/app.php`), not a localizer
> config of its own. It's the base for `hide_default_locale` and the
> fallback language for missing translations.

## Detectors

### Locale Resolution Order

`SetLocale` walks through the following sources, in order, and uses the
first one that yields a supported locale:

1. **URL** — the `{locale}` segment of a `with_locale.*` route (`/de/about` → `de`).
2. **Session** — the locale stored on a previous request.
3. **Cookie** — the locale persisted client-side.
4. **Detectors** — see below (auth user preference, `Accept-Language`, custom).
5. **`fallback_locale`** from `config/app.php`.

Only the **URL** can override an existing session or cookie. If the URL
carries no `{locale}` segment (the request came in as `/about` rather
than `/de/about`), `SetLocale` keeps using the session/cookie value —
that's deliberate, so a user who once picked German isn't reset to
English every time they hit an unprefixed link, and `RedirectLocale`
can send them to the prefixed variant.

This is also the reason the language switcher uses
`Route::localizedSwitcherUrl()` rather than `Route::localizedUrl()`:
the switcher always emits the prefixed form (`/en/about`, even for the
hidden default locale), so the URL itself flips the active locale on
click. `RedirectLocale` then strips the prefix on the follow-up to
restore the canonical form. See [Language Switcher](#language-switcher).

### Available Detectors

Detectors run only when steps 1–3 above produced nothing — typically a
first visit with no session and no cookie. Each implements a simple
interface that returns a locale string or `null`.

By default, two detectors are provided:

1. **UserDetector**: reads the locale from the authenticated user model (if available).
2. **BrowserDetector**: detects the preferred language from the `Accept-Language` HTTP header.

You can register your own detectors by adding them to the `detectors` array in the configuration.
They are executed in the order they appear; the first one returning a locale stops the chain.

Example:
```php
'detectors' => [
    \App\Locale\CustomDetector::class,
    \NielsNumbers\LaravelLocalizer\Detectors\UserDetector::class,
    \NielsNumbers\LaravelLocalizer\Detectors\BrowserDetector::class,
],
```

## Redirects

If `redirect_enabled` is set to `true`, the package automatically redirects between localized and non-localized URLs.

### Behavior

1. If `hide_default_locale` is `true` and the current locale is the
   **fallback_locale**, requests to `/en/about` will redirect to `/about`.

   This prevents SEO duplicate content (both `/about` and `/en/about` pointing to the same page).

2. If the current locale is **not** the fallback_locale and the route has **no locale prefix**,
   the request will be redirected to the localized version.
   For example, if the user's session locale is `de` and they open `/about`,
   it will redirect to `/de/about`.

To disable redirects entirely, set:
```php
'redirect_enabled' => false,
```

> **Note:** Disabling redirects is strongly discouraged for normal web apps.
> Without redirects, the application may display the wrong locale or produce duplicate URLs.
> This option is primarily for headless APIs or advanced SPA setups.

## Locale in Jobs, Mailables and Notifications

The `SetLocale` middleware only runs during HTTP requests. Anywhere else
(queued jobs, mailables, notifications, console commands), the application's
locale is whatever the worker process has set globally, typically your
`fallback_locale`.

This affects **everything that reads `App::getLocale()`**, not just URLs:

- `route('about')`: picks the wrong locale variant
- `__('messages.welcome')` / `@lang(...)` / `trans_choice(...)`: wrong language
- Validation messages
- `Carbon` / date formatting (`$date->translatedFormat(...)`, locale-aware diffs)
- Number / currency formatting via `Number::currency()`

Scoping the locale once at the right boundary fixes all of these together.
Laravel handles this for you in two of the three common cases:

### Mailables: automatic via `Mail::to()->locale()`

Pass the recipient's locale to the pending mail; Laravel wraps the entire
build and send in `withLocale($locale, ...)` (see
[laravel/framework#23178](https://github.com/laravel/framework/pull/23178)),
so any `route(...)` call inside your mailable's `build()`/`content()`
resolves with the correct locale.

```php
Mail::to($user)
    ->locale($user->locale)
    ->send(new InvoiceMail($invoice));
```

### Notifications: automatic via the notifiable's preferred locale

If your notifiable model implements `HasLocalePreference`, Laravel's
`NotificationSender` wraps each delivery in `withLocale(...)` for you.

```php
class User extends Model implements HasLocalePreference
{
    public function preferredLocale(): string
    {
        return $this->locale;
    }
}
```

### Plain queued jobs: manual

There is **no** built-in propagation for arbitrary queued jobs (see
[laravel/ideas#394](https://github.com/laravel/ideas/issues/394), closed
without a fix). You have to scope the locale yourself; easiest by adding
the `Localizable` trait to your job and wrapping the locale-sensitive work
in `$this->withLocale(...)`. URLs, translations, validation, dates etc.
inside the closure all see the scoped locale:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Traits\Localizable;

class SendReminder implements ShouldQueue
{
    use Dispatchable, Queueable, Localizable;

    public function __construct(public User $user) {}

    public function handle(): void
    {
        $this->withLocale($this->user->locale, function () {
            $url     = route('dashboard');
            $subject = __('reminders.subject');
            // …send the reminder using $url and $subject
        });
    }
}
```

If your job's only job is to send a mail or notification, you don't need
this trait; `Mail::to()->locale()` and `HasLocalePreference` already wrap
the relevant code in `withLocale(...)` for you.

## Translated URL Paths

`Route::localize()` keeps the same path in every language. If you need
*truly* localized paths (`/about` vs `/de/ueber` vs `/fr/a-propos`), use
`Route::translate()` together with `Localizer::url()`, which looks
up the URI from your language files:

```php
use NielsNumbers\LaravelLocalizer\Facades\Localizer;

Route::translate(function () {
    Route::get(Localizer::url('about'), AboutController::class)->name('about');
});
```

Define the translations in `lang/{locale}/routes.php`:

```php
// lang/en/routes.php
return [
    'about' => 'about',
];

// lang/de/routes.php
return [
    'about' => 'ueber',
];
```

This registers one route per supported locale (`/en/about`, `/de/ueber`),
plus a no-prefix variant for the default locale when `hide_default_locale`
is on. From your application code, keep using `route('about')`; the
package selects the right URI.

> **Lookup keys must match the full URI.** `routes.about` translates the
> path `/about`. For nested paths use the full path as the key:
> `'blog/post/{slug}' => 'artikel/{slug}'`. The translator does not split
> paths into segments; that would cause unintended hits when the same
> word appears in different contexts (e.g. `routes.about` translating
> `/blog/about/team` → `/blog/ueber/team`).

> **The closure runs N+1 times**: once per supported locale, plus an
> additional time for the `without_locale.` variant when the locale is
> the default and `hide_default_locale` is on. Same side-effect rules
> apply as for `Route::localize()`.

## Caveats and Recipes

### Route names must be unique across both macros

Each route name should be defined **once**. Defining the same name through
both `Route::localize()` and `Route::translate()` causes the second
registration to silently overwrite the first's `without_locale.{name}`
variant (Laravel's route registration is last-write-wins). Pick one macro
per route and stick with it.

### Empty `supported_locales` is a silent no-op

If `config('localizer.supported_locales')` is empty, `Route::translate()`
iterates zero locales, the closure never runs, and no routes get
registered. There is no warning at boot; you'll discover it when
`route('about')` raises `RouteNotFoundException` at request time. Make
sure your config is in place before any service provider that defines
translated routes runs.

### `app.locale` vs `app.fallback_locale`

- `config('app.fallback_locale')` is the package's reference for the
  default locale, used by `hide_default_locale` and as the base
  language for missing translations. Set it in `config/app.php`.
- `config('app.locale')` is updated at runtime by the `SetLocale`
  middleware via `App::setLocale()`. Its initial value in
  `config/app.php` has no lasting effect once the middleware runs.

### Route Model Binding with translated slugs

If your models have per-locale slugs and you want `/de/blog/{post:slug}` to
resolve the German slug while `/en/blog/{post:slug}` resolves the English
one, combine this package with
[spatie/laravel-translatable](https://github.com/spatie/laravel-translatable)
and override `resolveRouteBinding()`:

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Post extends Model
{
    use HasTranslations;

    public $translatable = ['slug'];

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        if ($field === 'slug') {
            return $this->where("slug->" . app()->getLocale(), $value)->firstOrFail();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
```

Reading `app()->getLocale()` here is reliable: route model binding runs
after the `SetLocale` middleware, so the recipient's locale is already in
place.

### Closures in `Route::translate()` / `Route::localize()` must be pure

Already mentioned in [Defining Routes](#defining-routes), repeated here
because it's the most common surprise:

- `Route::localize()`: closure runs **twice** (one prefixed, one
  unprefixed variant).
- `Route::translate()`: closure runs **N+1 times** (one per supported
  locale, plus once for `without_locale.` when the locale is the default
  and `hide_default_locale` is on).

Side effects inside the closure (logging, DB writes, third-party API
calls) will execute that many times. Treat it as a pure route definition.

## When to use this package

Use this package if you want:

- automatic locale detection from the request (e.g. from the browser)
- automatic redirects to localized routes
- the option to hide the default locale in the URL
- fully translatable routes (e.g. `/en/humans`, `/de/menschen`)

You **don't** need it if you're fine with only:

- `example.com/de/blog`
- `example.com/en/blog`

and don't need `example.com/blog` or locale detection from the browser.

## Comparison to other packages

- **[mcamara/laravel-localization](https://github.com/mcamara/laravel-localization) (deprecated)**
  This package is the modern successor to *laravel-localization*, which
  is no longer maintained. The original package was the first to tackle
  the routing problem; it generated routes dynamically at runtime,
  making it incompatible with `php artisan route:cache` and several
  Laravel packages. In contrast, this package registers **two static
  routes** per definition (one with a `{locale}` placeholder and one
  without), making it fully cache-safe and compatible with most modern
  Laravel packages.

- **[codezero-be/laravel-localized-routes](https://github.com/codezero-be/laravel-localized-routes) (deprecated)**
  An alternative to *laravel-localization*, using a **route-per-locale**
  approach (N× routes, one per language). While that package is no
  longer maintained, many of its design ideas influenced this one. Here,
  only **two routes** per definition are created, striking a balance
  between performance, maintainability, and flexibility.

- **[spatie/laravel-translatable](https://github.com/spatie/laravel-translatable)**
  This package serves a different purpose: translating **Eloquent model
  fields**, not routes. It works perfectly alongside this package if you
  want translatable slugs.

## Background

This package is the maintained continuation of [mcamara/laravel-localization](https://github.com/mcamara/laravel-localization).
I (Adam Nielsen) was a collaborator on the original package, and since
@mcamara has moved on from Laravel, I am now maintaining the route
localization package. The original package from mcamara has a very long
legacy.

The [original package](https://github.com/mcamara/laravel-localization)
generated **dynamic routes**, which led to cache and compatibility
issues. [laravel-localized-routes](https://github.com/codezero-be/laravel-localized-routes)
solved this by generating **static routes for each locale** (N× per
definition).

This package takes a **middle path**: each route is registered **twice**,
once with a `{locale}` placeholder, and once without. This avoids
dynamic routing issues while keeping the number of routes manageable.

## Testing

This package includes a Docker setup for consistent testing across environments.

### Prerequisites
- Docker
- Docker Compose
- GNU Make (optional, but recommended)

### Usage with Make

The following will first build the docker image,
then install dependencies via composer and then run phpunit.

```bash
make build    # Build the Docker image
make install  # Install Composer dependencies inside the container
make test     # Run PHPUnit tests (tests are in /tests, using Orchestra Testbench)
```

### Usage without Make

If you don't have `make`, you can run the commands manually:

```bash
docker compose build
UID=$(id -u) GID=$(id -g) docker compose run --rm test composer install
UID=$(id -u) GID=$(id -g) docker compose run --rm test vendor/bin/phpunit
```

## Credits

- [@mcamara](https://github.com/mcamara): original creator of [laravel-localization](https://github.com/mcamara/laravel-localization).
- [@codezero-be](https://github.com/codezero-be): developed a static route-per-locale approach
  (e.g. `en.index`, `de.index`, `es.index`). While this package follows a different routing strategy
  (two routes per definition: one with `{locale}` and one without), many classes and much of the
  implementation style are adapted from [laravel-localized-routes](https://github.com/codezero-be/laravel-localized-routes).

Since [@codezero-be](https://github.com/codezero-be) is no longer with us,
I want to acknowledge his great work and influence on this package.
Many of his ideas live on here, and I hope this helps to keep his contributions
useful to the Laravel community for years to come.
