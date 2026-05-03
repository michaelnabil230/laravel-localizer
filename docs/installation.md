# Installation

## Requirements

- **PHP** 8.2 / 8.3 / 8.4
- **Laravel** 9, 10, 11, 12, or 13

## Install via Composer

```bash
composer require niels-numbers/laravel-localizer
```

The service provider auto-registers via package discovery. Three steps
to finish setup:

## 1. Set your supported locales

Make sure your default is set in `config/app.php`:

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

## 2. Register the middleware

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

::: tip Safe to mix with unlocalized routes
Both middlewares only act on routes registered through
`Route::localize()` / `Route::translate()` (detected via a `locale_type`
action attribute the macros set). Plain routes in the same `web` group
- `/admin`, `/api/health`, anything outside the macros - pass through
untouched: no redirect, no `App::setLocale()` side effect. See
[Caveats & Recipes](/caveats-and-recipes#mixing-localized-and-unlocalized-routes).
:::

## 3. Wrap your routes in `Route::localize()`

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});
```

This single definition produces:

- `/about` - default locale (e.g. English), prefix hidden
- `/de/about` - German
- `/fr/about` - French (and so on for every configured locale)

Under the hood, two static routes are registered per definition. `php artisan route:list` shows:

```
  GET|HEAD  about ............................. without_locale.about › AboutController
  GET|HEAD  {locale}/about ........................ with_locale.about › AboutController
```

In your application code, `route('about')` always picks the right
variant for the current request, both server-side and (with the
[JS adapter](/javascript-route-helpers)) client-side.

::: info You don't pass the locale to the `route` helper
The `SetLocale` middleware sets it as a default URL parameter via
Laravel's [`URL::defaults()`](https://laravel.com/docs/urls#default-values),
so Laravel fills the `{locale}` placeholder automatically.
:::

## Next steps

- [Multitenancy](/multitenancy) - per-tenant locale subsets and default locale
- [Caveats & Recipes](/caveats-and-recipes) - edge cases and integration patterns
- [Migrating from `mcamara/laravel-localization`](/migrating-from-laravel-localization)
