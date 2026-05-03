# Comparison & Background

## When to use this package

Use this package if you want:

- automatic locale detection from the request (e.g. from the browser)
- automatic redirects to localized routes
- the option to hide the default locale in the URL
- fully translatable routes (e.g. `/en/humans`, `/de/menschen`)

You **don't** need it if you're fine with only:

- `example.com/de/blog`
- `example.com/en/blog`

and don't need `example.com/blog` or locale detection from the browser.

## Comparison to other packages

### [`mcamara/laravel-localization`](https://github.com/mcamara/laravel-localization)

The original package this one is the official successor to. It remains
actively maintained by
[@jordyvanderhaegen](https://github.com/jordyvanderhaegen) for
Laravel/PHP compatibility, security and small bug fixes. The original
was the first to tackle the routing problem; it generates routes
dynamically at runtime, which means `route:cache` doesn't work out of
the box and parts of the Laravel ecosystem aren't fully compatible:
several classes of long-standing bugs follow from that model.

In contrast, this package registers **two static routes** per
definition (one with a `{locale}` placeholder and one without), making
it fully cache-safe and compatible with most modern Laravel packages.
See the [migration guide](/migrating-from-laravel-localization) for a
step-by-step swap and the full list of long-standing issues this
rewrite addresses.

### [`codezero-be/laravel-localized-routes`](https://github.com/codezero-be/laravel-localized-routes) (deprecated)

An alternative to *laravel-localization*, using a **route-per-locale**
approach (N× routes, one per language). While that package is no
longer maintained, many of its design ideas influenced this one. Here,
only **two routes** per definition are created, striking a balance
between performance, maintainability, and flexibility.

### [`spatie/laravel-translatable`](https://github.com/spatie/laravel-translatable)

Serves a different purpose: translating **Eloquent model fields**, not
routes. It works perfectly alongside this package if you want
translatable slugs.

## Background

This package is the official successor to
[mcamara/laravel-localization](https://github.com/mcamara/laravel-localization),
which has a very long legacy in the Laravel ecosystem. I (Adam Nielsen)
was a collaborator on the original package; since @mcamara has moved on
from Laravel,
[@jordyvanderhaegen](https://github.com/jordyvanderhaegen) continues to
maintain it for Laravel/PHP compatibility, security and small bug
fixes. This rewrite addresses the long-standing limitations that follow
from the original's architecture, chiefly that `route:cache` doesn't
work out of the box and parts of the Laravel ecosystem aren't fully
compatible.

The [original package](https://github.com/mcamara/laravel-localization)
generated **dynamic routes**, which led to cache and compatibility
issues.
[laravel-localized-routes](https://github.com/codezero-be/laravel-localized-routes)
solved this by generating **static routes for each locale** (N× per
definition).

This package takes a **middle path**: each route is registered
**twice**, once with a `{locale}` placeholder, and once without. This
avoids dynamic routing issues while keeping the number of routes
manageable.

## Credits

- [@mcamara](https://github.com/mcamara): original creator of
  [laravel-localization](https://github.com/mcamara/laravel-localization).
- [@codezero-be](https://github.com/codezero-be): developed a static
  route-per-locale approach (e.g. `en.index`, `de.index`, `es.index`).
  While this package follows a different routing strategy (two routes
  per definition: one with `{locale}` and one without), many classes
  and much of the implementation style are adapted from
  [laravel-localized-routes](https://github.com/codezero-be/laravel-localized-routes).
- [@jordyvanderhaegen](https://github.com/jordyvanderhaegen):
  co-maintainer of
  [laravel-localization](https://github.com/mcamara/laravel-localization);
  his issue
  [mcamara/laravel-localization#921](https://github.com/mcamara/laravel-localization/issues/921)
  was the motivation for writing this package.

Since [@codezero-be](https://github.com/codezero-be) is no longer with
us, I want to acknowledge his great work and influence on this package.
Many of his ideas live on here, and I hope this helps to keep his
contributions useful to the Laravel community for years to come.
