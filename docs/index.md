---
layout: home

hero:
  name: Laravel Localizer
  text: Locale-aware routing for Laravel
  tagline: Auto-detect, auto-redirect, and resolve `route()` per language. Static routes, `route:cache` compatible.
  actions:
    - theme: brand
      text: Get Started
      link: /installation
    - theme: alt
      text: View on GitHub
      link: https://github.com/niels-numbers/laravel-localizer

features:
  - title: route:cache ready
    details: Two static routes per definition. No dynamic registration.
  - title: Auto-detect & redirect
    details: Browser, session, cookie, or custom detector chain. Query strings preserved.
  - title: Translated URL paths
    details: /about, /de/ueber, /fr/a-propos. Resolved through Laravel's URL generator.
  - title: Multi-tenant ready
    details: Per-request overrides for active locales and default locale. Octane-safe.
---

## Example

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});
```

`route('about')` returns `/about` (default locale), `/de/about`, `/fr/about` based on the current locale.

## Successor to `mcamara/laravel-localization`

Rebuilt on **static routes**: `route:cache` works out of the box,
translated routes resolve through Laravel's own URL generator.

Migrating? See the [step-by-step migration guide](/migrating-from-laravel-localization).
