# Template Helpers

Three additional macros are available on the `Route` facade for use in
controllers, Blade templates, and middleware.

## `Route::localizedUrl($locale, $absolute = true)`

The current request's URL in another locale, in **canonical** form
(no prefix when the target is the hidden default). Use for
`<link rel="alternate" hreflang>` tags, canonical URLs, and sitemaps:

```blade
@foreach (config('localizer.supported_locales') as $locale)
    <link rel="alternate"
          hreflang="{{ $locale }}"
          href="{{ Route::localizedUrl($locale) }}" />
@endforeach
```

For an in-page **language switcher**, use
`Route::localizedSwitcherUrl($locale)` instead - it always emits the
prefixed form. See [Language Switcher](/language-switcher).

| Current route | Behavior                               |
|---|----------------------------------------|
| Named (recommended) | Resolved through `route()`.            |
| Unnamed `Route::localize()` | URI prefix swap on the request path.   |
| Unnamed `Route::translate()` | Resolved through `route()`.|
| Outside a request | Throws `LogicException`.               |

## `Route::hasLocalized($name)` {#has-localized}

True if the name was registered through `Route::localize()` or
`Route::translate()`:

```blade
@if (Route::hasLocalized('about'))
    <a href="{{ route('about') }}">{{ __('About') }}</a>
@endif
```

Use this instead of `Route::has('about')`. The macros never register
the bare base name - they register `with_locale.about`,
`without_locale.about`, and (for `Route::translate()`)
`translated_{$locale}.about`. `Route::has('about')` therefore returns
`false` even though `route('about')` resolves correctly through the
package's URL generator. `Route::hasLocalized()` checks all variants
for you.

## `Route::isLocalized()`

True if the **current** request matched a localizer-managed route.
Useful for showing a switcher only on localized pages:

```blade
@if (Route::isLocalized())
    @include('partials.language-switcher')
@endif
```
