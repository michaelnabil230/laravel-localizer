# Caveats and Recipes

A grab bag of edge cases and patterns. Skim the headings; jump in when
you hit the symptom.

## Route names must be unique across both macros

Each route name should be defined **once**. Defining the same name through
both `Route::localize()` and `Route::translate()` causes the second
registration to silently overwrite the first's `without_locale.{name}`
variant (Laravel's route registration is last-write-wins). Pick one macro
per route and stick with it.

## Empty `supported_locales` is a silent no-op

If `config('localizer.supported_locales')` is empty, `Route::translate()`
iterates zero locales, the closure never runs, and no routes get
registered. There is no warning at boot; you'll discover it when
`route('about')` raises `RouteNotFoundException` at request time. Make
sure your config is in place before any service provider that defines
translated routes runs.

## `app.locale` vs `app.fallback_locale`

- `config('app.fallback_locale')` is the package's reference for the
  default locale, used by `hide_default_locale` and as the base
  language for missing translations. Set it in `config/app.php`.
- `config('app.locale')` is updated at runtime by the `SetLocale`
  middleware via `App::setLocale()`. Its initial value in
  `config/app.php` has no lasting effect once the middleware runs.

In multi-tenant apps where the default locale varies per tenant, prefer
`Localizer::setActiveDefaultLocale()` over mutating
`app.fallback_locale` - see [docs/multitenancy.md](multitenancy.md).

## Mixing localized and unlocalized routes

You can register routes outside `Route::localize()` / `Route::translate()`
in the same middleware group - they won't be touched. Both `SetLocale`
and `RedirectLocale` look for a `locale_type` action attribute on the
matched route, which the macros set automatically; routes registered
without the macros simply have no `locale_type` and pass through:

```php
$middleware->web(append: [SetLocale::class, RedirectLocale::class]);

// In routes/web.php:
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});

// Plain unlocalized route - no redirect, no App::setLocale() - works fine.
Route::get('/admin', AdminController::class)->name('admin');
```

Without this, an authenticated user with `session.locale = de` hitting
`/admin` would get a 302 to `/de/admin` (which doesn't exist → 404).
Now `/admin` is reached directly.

## Don't add `$locale` as a controller argument

The `{locale}` URI segment is consumed by `SetLocale` and stripped from
the route parameter bag, so it is **not** passed positionally to your
controller. Write your controllers as if the locale weren't in the URI:

```php
// Route::localize(fn() => Route::get('/users/{country?}', [UsersController::class, 'index']));

// Correct:
public function index(Request $request, ?string $country = null) { … }

// Wrong - $locale will receive the country, not the locale:
public function index(Request $request, string $locale, ?string $country = null) { … }
```

Read the active locale via `App::getLocale()` if you need it.

## Middleware order with translated route bindings

If your localized routes use route model bindings with **per-locale slugs**
(`/de/blog/{post:slug}` resolving a German slug, `/en/blog/{post:slug}` the
English one - see recipe below), `SetLocale` must run **before** Laravel's
`SubstituteBindings` middleware. Otherwise `resolveRouteBinding()` reads
the fallback locale instead of the request's locale.

The recommended setup (`web(append: [SetLocale, RedirectLocale])`) handles
this automatically - both middlewares become part of the `web` group,
which runs before `SubstituteBindings`. If you register them elsewhere
(e.g. as global middleware after the routing pipeline), verify the order.

## Route Model Binding with translated slugs

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

## Closures in `Route::translate()` / `Route::localize()` must be pure

The closure runs more than once because both variants are registered
from the same definition:

- `Route::localize()`: closure runs **twice** (one prefixed, one
  unprefixed variant).
- `Route::translate()`: closure runs **N+1 times** (one per supported
  locale, plus once for `without_locale.` when the locale is the default
  and `hide_default_locale` is on).

Side effects inside the closure (logging, DB writes, third-party API
calls) will execute that many times. Treat it as a pure route definition.
