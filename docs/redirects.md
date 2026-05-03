# Redirects

If `redirect_enabled` is set to `true` (the default), the package
automatically redirects between localized and non-localized URLs.

## Behavior

1. If `hide_default_locale` is `true` and the current locale is the
   **fallback_locale**, requests to `/en/about` will redirect to `/about`.

   This prevents SEO duplicate content (both `/about` and `/en/about`
   pointing to the same page).

2. If the current locale is **not** the fallback_locale and the route
   has **no locale prefix**, the request will be redirected to the
   localized version. For example, if the user's session locale is `de`
   and they open `/about`, it will redirect to `/de/about`.

## Safe-method only

Non-safe methods (POST/PUT/PATCH/DELETE) are **not** redirected.
Browsers downgrade 302 from non-GET methods to GET, dropping the
request body, a submitted form would silently lose its payload. The
matched route handler runs on whatever URL the client actually hit
(`with_locale` or `without_locale` variant); both register the same
controller, so behavior is identical bar the URL itself.

## Disabling redirects

```php
// config/localizer.php
'redirect_enabled' => false,
```

::: warning Disabling is strongly discouraged for normal web apps
Without redirects, the application may display the wrong locale or
produce duplicate URLs. This option is primarily for headless APIs or
advanced SPA setups where the client handles locale switching itself.
:::
