# Defining Routes

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

## Adding middleware, prefixes, or other attributes

Define them **inside** the `Route::localize()` closure as you would in
any other group. `Route::localize()` is itself a group, so nested groups
compose the way Laravel groups normally compose:

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');

    Route::middleware('auth')->prefix('account')->group(function () {
        Route::get('/profile', ProfileController::class)->name('profile');
    });
});
```

::: warning The closure runs twice
Once per route variant (prefixed and unprefixed). Keep it side-effect-free:
no logging, no DB writes, no external calls. Treat it as a pure route
definition.
:::

::: info Need per-locale paths?
Routes like `/about`, `/de/ueber`, `/fr/a-propos` (instead of just locale
prefixes) are supported via [Translated URL Paths](/translated-url-paths).
:::

## URL Generation Is Context-Dependent

`route('about')` resolves to a different URL depending on the current
`App::getLocale()`. The same call inside an HTTP request, a queued job, or a
mailable can yield different results. That's the whole point: you keep using
`route('about')` everywhere and the package picks the right variant.

```php
App::setLocale('en');
route('about'); // -> /about      (default locale, hidden via hide_default_locale)

App::setLocale('de');
route('about'); // -> /de/about

route('about', ['locale' => 'en']); // -> /about (explicit override wins)
```

This is **fully compatible with `php artisan route:cache`**. The cache
serializes the *route definitions* (`with_locale.about` -> `/{locale}/about`,
`without_locale.about` -> `/about`); those are static and deterministic. The
locale-aware *selection* between them happens at runtime in the URL generator,
which is unaffected by the cache. URL-translated routes built by
[`Route::translate()`](/translated-url-paths) are likewise baked into static
URIs at registration time, so the cache covers them too.
