# Template Helpers

Three additional macros are available on the `Route` facade for use in
controllers, Blade templates, and middleware.

## `Route::localizedUrl($locale, $absolute = true)`

Returns the **current** request's URL in another locale. Primary use is
`<link rel="alternate" hreflang="...">` tags, canonical URLs and
sitemaps:

```blade
@foreach (config('localizer.supported_locales') as $locale)
    <link rel="alternate"
          hreflang="{{ $locale }}"
          href="{{ Route::localizedUrl($locale) }}" />
@endforeach
```

The returned URL is the **canonical** form. Switching to the default
locale with `hide_default_locale` enabled yields `/about` directly, not
`/en/about` followed by a 301. Suitable for hreflang attributes that
crawlers read literally.

For an **in-page language switcher** use the sibling helper
`Route::localizedSwitcherUrl($locale)` instead. It always emits the
prefixed form, which is what carries the locale signal across the click.
See [Language Switcher](/language-switcher).

::: tip Canonical (`/about`) vs. always-prefixed (`/en/about`) for hreflang
Google's official guidance is to point hreflang at canonical URLs, which
is what `localizedUrl()` returns: `/about` for the hidden default
locale, `/de/about` etc. for others. This is the normal recommendation.

However, if a visitor with a non-default browser locale (or a stale
session/cookie) hits `/about`, `RedirectLocale` will 302 them to
`/de/about`. If you'd rather avoid any redirect roundtrip (at the cost
of having two URLs that resolve to English content: `/en/about` and
`/about`), use `Route::localizedSwitcherUrl($locale)` in your hreflang
tags instead. That always emits the prefixed form, even for the default
locale.
:::

### Behavior by route type

| Current route | Behavior |
|---|---|
| Named (recommended) | Resolved through `route()`; works for both macros. |
| Unnamed `Route::localize()` | Falls back to a URI prefix swap on the request path. |
| Unnamed `Route::translate()` | Throws `LogicException`; the translated URI can't be reversed. Add `->name()`. |
| Called outside a request | Throws `LogicException`. |

## `Route::hasLocalized($name)`

Returns `true` if a route with the given name was registered through
`Route::localize()` or `Route::translate()`:

```blade
@if (Route::hasLocalized('about'))
    <a href="{{ route('about') }}">{{ __('About') }}</a>
@endif
```

Checks `with_locale.{name}`, `without_locale.{name}` and
`translated_{$locale}.{name}` for every supported locale.

## `Route::isLocalized()`

Returns `true` if the **current** request was matched to a
localizer-managed route. Convenient for showing a language switcher only
on localized pages:

```blade
@if (Route::isLocalized())
    @include('partials.language-switcher')
@endif
```
