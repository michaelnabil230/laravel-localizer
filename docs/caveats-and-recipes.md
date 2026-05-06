# Caveats and Recipes

A grab bag of edge cases. Skim the headings; jump in when you hit the
symptom.

## `Route::has()` returns false for localizer routes

`Route::localize()` and `Route::translate()` never register the bare
base name - they register `with_locale.{name}`,
`without_locale.{name}`, and `translated_{$locale}.{name}`. So
`Route::has('about')` is `false` even when `route('about')` works.
Use `Route::hasLocalized('about')` instead - it checks every variant.
See [Template Helpers](/template-helpers#has-localized).

## `$route->getName()` returns the prefixed variant

For the same reason, `$request->route()->getName()` returns
`with_locale.about` / `translated_de.about` etc. - so a comparison like
`$route->getName() === 'about'` in middleware or gates breaks the
moment the request hits a non-default locale. Use
`$route->baseName()` (or `Route::currentBaseName()`) - they strip the
prefix and pass foreign names through unchanged. See
[Template Helpers](/template-helpers#base-name).

## Route names must be unique across both macros

Each name once. Defining the same name through both `Route::localize()`
and `Route::translate()` makes the second registration silently
overwrite the first's `without_locale.{name}` (Laravel's route
registration is last-write-wins). Pick one macro per route.

## Empty `supported_locales` is a silent no-op

If `localizer.supported_locales` is empty, `Route::translate()`
iterates zero locales, the closure never runs, no routes get
registered. No boot warning - you'll discover it when `route('about')`
raises `RouteNotFoundException` at request time.

## `app.locale` vs `app.fallback_locale`

- `config('app.fallback_locale')`: package's default locale + Laravel
  translation fallback. Set in `config/app.php`.
- `config('app.locale')`: overridden at runtime by `SetLocale` via
  `App::setLocale()` - but only inside `Route::localize()` /
  `Route::translate()`. For plain unlocalized routes, console commands
  and jobs the initial value from `config/app.php` stays in effect.

For multi-tenant apps, prefer `Localizer::setActiveDefaultLocale()`
over mutating `app.fallback_locale` - see [Multitenancy](/multitenancy).

## Mixing localized and unlocalized routes

Routes outside `Route::localize()` / `Route::translate()` in the same
middleware group pass through untouched. Both middlewares look for a
`locale_type` action attribute the macros set; routes without it are
ignored:

```php
$middleware->web(append: [SetLocale::class, RedirectLocale::class]);

// In routes/web.php:
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});

// Plain unlocalized route - no redirect, no App::setLocale().
Route::get('/admin', AdminController::class)->name('admin');
```

## Don't add `$locale` as a controller argument

`{locale}` is consumed by `SetLocale` and stripped from the route
parameter bag, so it's **not** passed positionally. Write controllers
as if the locale weren't in the URI:

```php
// Route::localize(fn() => Route::get('/users/{country?}', [UsersController::class, 'index']));

// Correct:
public function index(Request $request, ?string $country = null) { ... }

// Wrong - $locale will receive the country:
public function index(Request $request, string $locale, ?string $country = null) { ... }
```

Read the active locale via `App::getLocale()` if you need it.

## Middleware order with translated route bindings

If your routes use per-locale slugs (`/de/blog/{post:slug}` for the
German slug, `/en/blog/{post:slug}` for the English one - see recipe
below), `SetLocale` must run **before** Laravel's `SubstituteBindings`.
Otherwise `resolveRouteBinding()` reads the fallback locale.

The recommended setup (`web(append: [SetLocale, RedirectLocale])`)
handles this automatically. If you register them elsewhere, verify
order.

## Route Model Binding with translated slugs

Combine with
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

`app()->getLocale()` is reliable here: route model binding runs after
`SetLocale`.

## Closures must be pure

The macros invoke the closure more than once:

- `Route::localize()`: **twice** (one prefixed, one unprefixed).
- `Route::translate()`: **N+1 times** (one per supported locale, plus
  one for `without_locale.` when the locale is the default and
  `hide_default_locale` is on).

Side effects (logging, DB writes, third-party calls) execute that many
times. Treat the closure as a pure route definition.
