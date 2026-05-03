# Laravel Localizer

[![Tests](https://github.com/niels-numbers/laravel-localizer/actions/workflows/tests.yml/badge.svg)](https://github.com/niels-numbers/laravel-localizer/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-9%20%7C%2010%20%7C%2011%20%7C%2012%20%7C%2013-blue?logo=laravel&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

> **Official successor to [`mcamara/laravel-localization`](https://github.com/mcamara/laravel-localization).**
> Built on static routes, `route:cache` works out of the box.

Detect a visitor's language and serve the right localized URL. Define
your routes once, get prefixed (`/de/about`) and unprefixed (`/about`)
variants for free, and let `route('about')` always pick the right one
for the current locale.

**Full documentation: [localizer.adam-nielsen.de](https://localizer.adam-nielsen.de)**

## 30-second example

```php
Route::localize(function () {
    Route::get('/about', AboutController::class)->name('about');
});
```

Produces:

- `/about` for the default locale (e.g. English), prefix hidden
- `/de/about`, `/fr/about`, ... for every other configured locale

In your application code, keep using `route('about')`; the package
picks the right variant based on the current locale.

## Install

```bash
composer require niels-numbers/laravel-localizer
```

Then publish the config and register the middleware. Full setup in the
[Installation guide](https://localizer.adam-nielsen.de/installation).

## Migrating from `mcamara/laravel-localization`?

See the [step-by-step migration guide](https://localizer.adam-nielsen.de/migrating-from-laravel-localization).
The original package remains actively maintained by
[@jordyvanderhaegen](https://github.com/jordyvanderhaegen) for
Laravel/PHP compatibility, security and small bug fixes; this package
addresses the long-standing limitations that follow from the original's
runtime route generation (chiefly that `route:cache` doesn't work out
of the box).

## Testing

```bash
make build && make install && make test
```

Or without Make: see the [contributing notes](CONTRIBUTING.md).

## License & credits

MIT licensed. Created by Adam Nielsen, building on prior work by
[@mcamara](https://github.com/mcamara) (original
`laravel-localization`),
[@codezero-be](https://github.com/codezero-be) (deprecated
`laravel-localized-routes`, whose static-route ideas inspired this
rewrite) and
[@jordyvanderhaegen](https://github.com/jordyvanderhaegen) (current
maintainer of the original, whose
[issue #921](https://github.com/mcamara/laravel-localization/issues/921)
motivated this package).
