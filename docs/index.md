---
layout: home

hero:
  name: Laravel Localizer
  text: Localized URLs for Laravel
  tagline: Detect a visitor's language, redirect to the matching locale, and let `route('about')` always pick the right URL - fully `route:cache` compatible.
  actions:
    - theme: brand
      text: Get Started
      link: /installation
    - theme: alt
      text: View on GitHub
      link: https://github.com/niels-numbers/laravel-localizer

features:
  - title: route:cache compatible
    details: Two static routes per definition (with and without a locale prefix) - no dynamic registration, full Laravel ecosystem compatibility.
  - title: Detect & redirect
    details: Browser, session, cookie, or custom detector chain. Auto-redirects between prefixed and unprefixed variants, query strings preserved.
  - title: Translated URL paths
    details: Optional truly localized paths - /about, /de/ueber, /fr/a-propos - resolved through Laravel's URL generator. Adapters for Ziggy and Wayfinder included.
  - title: Multi-tenant ready
    details: Per-request runtime overrides for active locales and default locale, without mutating Laravel's translation config or leaking across Octane workers.
---

## 30-second example

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});
```

Produces:

- `/about` - default locale (e.g. English), prefix hidden
- `/de/about` - German
- `/fr/about` - French (and so on for every configured locale)

In your code, keep using `route('about')` - the package picks the right
variant based on the current locale.

## Successor to `mcamara/laravel-localization`

This package is the official successor to
[`mcamara/laravel-localization`](https://github.com/mcamara/laravel-localization),
rebuilt on **static routes** so that `php artisan route:cache` works out
of the box and translated routes resolve through Laravel's own URL
generator - no URI parsing required.

Migrating from the original? See the
[step-by-step migration guide](/migrating-from-laravel-localization).
