# Language Switcher

A switcher uses **`Route::localizedSwitcherUrl()`** so each link points
to the **current page** in the target locale. Click triggers a normal
navigation: the URL carries the new locale, `SetLocale` reads it on the
next request and persists it to session/cookie.

::: tip Why a different helper than `localizedUrl()`?
`localizedUrl()` returns canonical URLs (no prefix for the hidden
default), correct for hreflang and sitemaps.

A switcher needs the prefix even for the default locale - it's the
only way the URL itself can tell `SetLocale` which language to switch
to. `localizedSwitcherUrl()` always emits the prefixed form;
`RedirectLocale` strips it on the follow-up, so the user lands on the
canonical URL anyway, with one invisible 302 hop.
:::

## Blade

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

Render per-locale URLs server-side and ship them as shared props:

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

Then build a SPA component (Vue example, React analogous):

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

A plain `<a>` triggers a full reload, which is what you usually want
when switching languages: the HTML `lang`, shared props and cached
translations all need to refresh.

## Caveats

For routes with per-locale model bindings (translated slugs), some
links may build URLs that 404 on follow. Render switcher items
conditionally or add a fallback in `resolveRouteBinding()`. See
[Caveats](/caveats-and-recipes#route-model-binding-with-translated-slugs).
