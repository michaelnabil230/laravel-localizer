# Language Switcher

Use a single switcher component anywhere in your layout. It picks the
right URLs from **`Route::localizedSwitcherUrl()`** so each link points
to the **current page** in the target locale. Clicking a link triggers a
normal navigation: the URL carries the new locale, `SetLocale` reads it
on the next request and persists it to session/cookie.

::: tip Why a different helper than `localizedUrl()`?
`localizedUrl()` returns the **canonical** URL (no `/en` prefix when
English is the hidden default), correct for `<link rel="alternate">`
and sitemaps.

But a switcher link to the default locale needs the prefix: it's the
only way the URL itself can tell `SetLocale` which language to switch
to. Without it, a stale session locale would win and `RedirectLocale`
would bounce the visitor back. `localizedSwitcherUrl()` always emits
the prefixed form; `RedirectLocale` then strips it on the follow-up
request, so the browser ends up on the canonical URL anyway, one
invisible 302 hop.
:::

## Blade

Define once as a component, include anywhere:

```blade
{{-- resources/views/components/language-switcher.blade.php --}}
@foreach (config('localizer.supported_locales') as $locale)
    <a href="{{ Route::localizedSwitcherUrl($locale) }}"
       @class(['active' => app()->getLocale() === $locale])>
        {{ strtoupper($locale) }}
    </a>
@endforeach
```

```blade
<x-language-switcher />
```

## Inertia (Vue / React)

The Inertia bridge (Ziggy or Wayfinder underneath) doesn't see
`Route::localizedUrl()` directly. Render the per-locale URLs
server-side and ship them as shared props:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'locale'        => app()->getLocale(),
        'localizedUrls' => fn () => collect(config('localizer.supported_locales'))
            ->mapWithKeys(fn ($l) => [$l => Route::localizedSwitcherUrl($l)])
            ->all(),
    ]);
}
```

Then build a SPA component (Vue example; React works analogously):

```vue
<!-- resources/js/Components/LanguageSwitcher.vue -->
<script setup>
import { usePage } from '@inertiajs/vue3';
const { localizedUrls, locale } = usePage().props;
</script>

<template>
  <a v-for="(url, code) in localizedUrls" :key="code"
     :href="url" :class="{ active: code === locale }">
    {{ code.toUpperCase() }}
  </a>
</template>
```

A plain `<a>` triggers a full-page reload, which is typically what you
want when switching languages: the HTML `lang` attribute, shared props
and any cached translations all need to refresh.

::: info SPA language switch via `<Link>`
Has a few extra moving parts (Ziggy as a shared prop, `route()`
reactive to `usePage()`, `<html lang>` updates, prefixed switcher URLs).
See [Inertia SPA Switcher](/inertia-spa-language-switch) for a working
sketch, marked **experimental**, not yet verified end-to-end. Full
reload remains the recommended default.
:::

## Caveats

For routes with per-locale model bindings (translated slugs), some
links may build URLs that 404 on follow. Render switcher items
conditionally or add a fallback in `resolveRouteBinding()`. See
[Caveats & Recipes](/caveats-and-recipes#route-model-binding-with-translated-slugs).
