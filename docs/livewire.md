# Livewire

Livewire 3 and Livewire 4 both work with Laravel Localizer out of the
box. No `setUpdateRoute()` recipe, no URL rewriting, no JavaScript
fetch interception. Render your component from a localized route -
done.

Both major versions are covered by an integration test matrix in CI
(`tests/Integration/Livewire3/` and `tests/Integration/Livewire4/`).

## Why it works

Livewire ships with a built-in `SupportLocales` feature hook
(`Livewire\Features\SupportLocales\SupportLocales`) that snapshots the
application locale on render and restores it on update:

- **On render** (`dehydrate`): writes `App::getLocale()` into the
  component's `memo.locale`.
- **On update** (`hydrate`): reads `memo.locale` and calls
  `App::setLocale(...)` before the action method runs.

So as long as `SetLocale` has set the right locale **at render time**,
every subsequent update for that component instance runs with that
same locale - even though the update POST has no locale prefix in its
URL. The locale travels inside the snapshot, not the URL.

The hook is byte-identical between Livewire 3 and 4; the mechanism
hasn't changed.

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

Subsequent updates restore that locale before the action runs - the
component method sees `App::getLocale() === 'de'` regardless of what
URL the update POST went to.

## Differences between Livewire 3 and 4

For application code there is none. The only thing that changed is
where Livewire posts updates to:

- **Livewire 3** posts to a fixed `/livewire/update`.
- **Livewire 4** randomizes the path per `app.key`
  (`/livewire-{hash}/update`) and adds an `X-Livewire` header guard
  middleware. This is anti-scanner hardening internal to Livewire and
  has nothing to do with localization.

Either way, `SupportLocales` carries the locale inside the snapshot,
so the path and the header guard don't affect locale handling.

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
