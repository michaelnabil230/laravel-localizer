# Livewire

Livewire 3 works with Laravel Localizer out of the box. No
`setUpdateRoute()` recipe, no `/livewire/update` URL rewriting, no
JavaScript fetch interception.

## Why it works

Livewire 3 ships with a built-in `SupportLocales` feature hook
(`Livewire\Features\SupportLocales\SupportLocales`) that snapshots the
application locale on render and restores it on update:

- **On render** (`dehydrate`): writes `App::getLocale()` into the
  component's `memo.locale`.
- **On update** (`hydrate`): reads `memo.locale` and calls
  `App::setLocale(...)` before the action method runs.

So as long as `SetLocale` has set the right locale **at render time**,
every subsequent update for that component instance runs with that
same locale - even though the POST goes to the unprefixed
`/livewire/update` route. The locale travels inside the snapshot, not
the URL.

## What that means in practice

Render your Livewire components from routes wrapped in
`Route::localize()` (or `Route::translate()`):

```php
Route::middleware([SetLocale::class, RedirectLocale::class])->group(function () {
    Route::localize(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});
```

The `SetLocale` middleware runs before the controller, sets
`App::getLocale()` to the active locale (`de` for `/de/dashboard`),
the controller renders the view, Livewire mounts the component, and
`SupportLocales::dehydrate()` freezes that locale into the snapshot.

Updates POST to `/livewire/update` without a locale prefix, the
snapshot carries `memo.locale: "de"`, and the action runs with
`App::getLocale() === 'de'`.

This is verified end-to-end in `tests/Integration/Livewire/`.

## Caveat: don't first-render components on unlocalized routes

`SetLocale` only runs on routes registered through `Route::localize()`
or `Route::translate()`. Routes outside those macros pass through
without locale resolution - `App::getLocale()` stays at the configured
`app.locale` (typically your default). Whatever value is active at
**first render** gets frozen into the component's `memo.locale` for
the rest of its lifetime.

So a Livewire component that initially renders on an unlocalized
route - say a plain `/admin` - will keep using your default locale on
every subsequent update, regardless of what the user's browser,
session or any localized route says. No Livewire setup can recover
from that, because the component's view of "current locale" is fixed
at mount time.

If you need locale-aware components in an unlocalized area, wrap that
area in `Route::localize()` too, or set the locale explicitly in the
component's `mount()`:

```php
public function mount(): void
{
    app()->setLocale(auth()->user()->preferred_locale);
}
```

The `dehydrate` hook will pick that up and snapshot it.

## Volt, Filament, Flux

Volt components and Filament panels are Livewire components under the
hood, so the same mechanism applies. Render them from routes inside
`Route::localize()` / `Route::translate()` and the locale flows
through automatically. Filament's panel routes are typically registered
unprefixed under `/admin`; if you need locale-scoped admin panels, see
the [multitenancy](/multitenancy) doc for how to set the active locale
per tenant outside the URL.
