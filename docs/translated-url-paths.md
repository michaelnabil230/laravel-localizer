# Translated URL Paths

`Route::localize()` keeps the same path in every language. If you need
*truly* localized paths (`/about` vs `/de/ueber` vs `/fr/a-propos`), use
`Route::translate()` together with `Localizer::url()`, which looks up
the URI from your language files:

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

::: warning Lookup keys must match the full URI
`routes.about` translates the path `/about`. For nested paths use the
full path as the key: `'blog/post/{slug}' => 'artikel/{slug}'`. The
translator does not split paths into segments; that would cause
unintended hits when the same word appears in different contexts (e.g.
`routes.about` translating `/blog/about/team` -> `/blog/ueber/team`).
:::

::: warning The closure runs N+1 times
Once per supported locale, plus an additional time for the
`without_locale.` variant when the locale is the default and
`hide_default_locale` is on. Same side-effect rules apply as for
`Route::localize()`.
:::

## Multi-tenant caveat

`Route::translate()` does not work correctly when tenants have
different default locales. The `without_locale.*` variant gets baked
against the boot-time default and can't be re-rewritten by
`setActiveDefaultLocale()` at request time. See
[Multitenancy: Route::translate caveat](/multitenancy#caveat-route-translate-and-per-tenant-defaults)
for the timing explanation and the recommended workaround.
