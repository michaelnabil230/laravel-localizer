# Multitenancy

Two distinct concepts at play in a multi-tenant app:

- **Supported locales** (`config('localizer.supported_locales')`): the
  static union, evaluated at boot time. Drives route registration -
  every locale here gets a registered route variant. **Cannot change
  per request** without breaking `route:cache` compatibility.
- **Active locales** (runtime): the subset the user is allowed to reach
  in the **current request**. Defaults to the supported set. Can be
  narrowed at runtime via `Localizer::setActiveLocales([...])`.
- **Default locale** (runtime): which locale is the unprefixed one
  when `hide_default_locale` is on. Defaults to
  `config('app.fallback_locale')`. Can be overridden per request via
  `Localizer::setActiveDefaultLocale(...)`.

The classic use case: in a multi-tenant app, each tenant exposes a
different subset of the globally supported locales - and possibly a
different default language. Tenant A allows `en + de` with `en` default,
Tenant B allows `en + fr + es` with `fr` default. Configure the union of
all locales in `supported_locales`, then narrow + redefine the default
per request in middleware.

## Tenant middleware example

```php
// app/Http/Middleware/TenantLocales.php
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;

class TenantLocales
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->tenant(); // your resolver

        Localizer::setActiveLocales($tenant->supported_locales);
        Localizer::setActiveDefaultLocale($tenant->default_locale);

        try {
            return $next($request);
        } finally {
            // Reset for long-running workers (Octane, queue workers).
            // The Localizer is a container singleton; without reset
            // the override leaks into the next request on the same
            // worker process.
            Localizer::setActiveLocales(null);
            Localizer::setActiveDefaultLocale(null);
        }
    }
}
```

## Middleware order

`TenantLocales` must run **before** `SetLocale` so that `SetLocale`
validates incoming locale candidates against the narrowed subset and
falls back to the tenant's default locale (not the global one):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\TenantLocales::class,
        \NielsNumbers\LaravelLocalizer\Middleware\SetLocale::class,
        \NielsNumbers\LaravelLocalizer\Middleware\RedirectLocale::class,
    ]);
})
```

## Why not just `Config::set('app.fallback_locale', ...)`?

It's tempting to mutate the Laravel config per request to swap the
default locale. Three problems with that:

1. **`fallback_locale` is overloaded.** It's also Laravel's translation
   fallback - flipping it per tenant changes translation behavior, not
   just URL behavior. Two unrelated concepts coupled by accident.
2. **Octane / worker leaks.** The `Config` repository is a singleton
   that survives across requests. Mutating it without a reset hook
   causes Tenant A's value to leak into Tenant B's next request on the
   same worker.
3. **Boot-time consumers.** Some package internals (and userland code)
   read config at boot. Mid-request mutations don't reach them.

`setActiveDefaultLocale()` avoids all three: the override lives on the
Localizer singleton (request-scoped with the reset pattern above),
doesn't touch Laravel's translation config, and is read at request time
by every consumer that needs it.

## What changes vs. the default behavior

- A request to a route for an inactive-but-supported locale (e.g. `/fr/about`
  on Tenant A) is treated as if the prefix isn't a locale at all -
  `SetLocale` falls back to the resolution chain (session → cookie →
  detectors → tenant's default locale), and `RedirectLocale` doesn't
  strip or add the inactive prefix.
- `Route::localizedSwitcherUrl()` and friends still iterate
  `supportedLocales()`. If you build a switcher, filter against
  `Localizer::activeLocales()` yourself when rendering.
- `route('about')` resolves the same as before - the underlying routes
  for inactive locales still exist physically; the package just won't
  *route* the user there via locale detection.
- `hide_default_locale` follows the **active default locale**, not
  config. With Tenant B (`fr` default), `route('about')` for a `fr`
  request resolves to `/about` (no prefix); a `/fr/about` request gets
  302'd to `/about` by `RedirectLocale`.

## Caveat: `Route::translate()` and per-tenant defaults

> **`Route::translate()` does not work correctly when tenants have
> different default locales.** Use `Route::localize()` instead.

The reason is timing. Service providers register routes **at boot**;
your `TenantLocales` middleware sets `setActiveDefaultLocale()` **at
request time**. Boot runs first, middleware second - so when
`TranslateMacro::register()` decides which locale gets the unprefixed
`without_locale.*` variant, no override has been set yet and it falls
back to `config('app.fallback_locale')`.

```
1. BOOT (once per worker)
   routes/web.php → Route::translate(...)
   → TranslateMacro::register() runs
   → reads config('app.fallback_locale') = 'en'
   → registers:
       translated_en.about  → /en/about
       translated_de.about  → /de/ueber
       without_locale.about → /about        ← baked against 'en'

2. REQUEST (Tenant B with 'de' default)
   TenantLocales::handle()
   → Localizer::setActiveDefaultLocale('de')   ← too late!
   → routes are already registered; the unprefixed variant
     is /about (English path), not /ueber (German path)
```

The route table is fixed after boot - the runtime override has nothing
to rewrite. With `Route::localize()` this isn't a problem, because
every locale shares the *same* URI (`/about`); only the prefix differs,
and the prefix is stripped at URL-generation time, not at registration
time.

**For multi-tenant apps with different defaults, use `Route::localize()`.**
If you really need translated paths *and* per-tenant defaults, you'll
have to register per-locale `without_locale.*` routes yourself and
extend `UrlGenerator` to pick between them - see the source of
`TranslateMacro` for a starting point.

## API summary

| Method | Purpose |
|---|---|
| `Localizer::supportedLocales()` | Static union from config (boot-time). |
| `Localizer::activeLocales()` | Runtime subset; defaults to supported. |
| `Localizer::isSupported($locale)` | Membership in supported. |
| `Localizer::isActive($locale)` | Membership in active. |
| `Localizer::setActiveLocales($array\|null)` | Narrow (or reset with `null`). |
| `Localizer::defaultLocale()` | Runtime default; defaults to `app.fallback_locale`. |
| `Localizer::setActiveDefaultLocale($string\|null)` | Override (or reset with `null`). |
