<?php

namespace NielsNumbers\LaravelLocalizer;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use NielsNumbers\LaravelLocalizer\Contracts\DetectorInterface;
use NielsNumbers\LaravelLocalizer\Services\UriTranslator;

class Localizer
{
    /**
     * Runtime override for the active subset of supported locales.
     * `null` means: active === supported (no override).
     *
     * @var array<int, string>|null
     */
    protected ?array $activeLocales = null;

    /**
     * Runtime override for the default locale (the one whose URLs are
     * unprefixed when `hide_default_locale` is on). `null` means: fall
     * back to `config('app.fallback_locale')`.
     */
    protected ?string $activeDefaultLocale = null;

    public function __construct(
        protected UriTranslator $translator
    ) {
    }

    public function supportedLocales(): array
    {
        return Config::get('localizer.supported_locales', []);
    }

    public function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->supportedLocales(), true);
    }

    /**
     * Locales the user is allowed to reach in the current request.
     * Defaults to `supportedLocales()`; can be narrowed at runtime via
     * `setActiveLocales()` (e.g. per tenant in a multi-tenant app).
     *
     * @return array<int, string>
     */
    public function activeLocales(): array
    {
        return $this->activeLocales ?? $this->supportedLocales();
    }

    public function isActive(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->activeLocales(), true);
    }

    /**
     * Narrow the active locales for the current request. Pass `null` to
     * reset to `supportedLocales()` — important in long-running workers
     * (Octane, queue) where the Localizer singleton survives the request.
     *
     * @param  array<int, string>|null  $locales
     */
    public function setActiveLocales(?array $locales): void
    {
        $this->activeLocales = $locales;
    }

    /**
     * The default locale for the current request. Defaults to
     * `config('app.fallback_locale')`; can be overridden at runtime via
     * `setActiveDefaultLocale()` (e.g. per tenant in a multi-tenant app).
     *
     * Drives the unprefixed URL variant when `hide_default_locale` is on,
     * the SetLocale fallback, and the RedirectLocale prefix-strip rule.
     */
    public function defaultLocale(): string
    {
        return $this->activeDefaultLocale ?? Config::get('app.fallback_locale');
    }

    /**
     * Override the default locale for the current request. Pass `null` to
     * reset to `config('app.fallback_locale')` — important in long-running
     * workers (Octane, queue) where the Localizer singleton survives the
     * request.
     *
     * Has no effect on routes that were already registered at boot time
     * (Route::translate() bakes the without-locale variant against the
     * boot-time default). Affects URL generation, RedirectLocale and
     * SetLocale at request time.
     */
    public function setActiveDefaultLocale(?string $locale): void
    {
        $this->activeDefaultLocale = $locale;
    }


    public function hideDefaultLocale(): bool
    {
        return Config::get('localizer.hide_default_locale', true);
    }

    public function storesInSession(): bool
    {
        return Config::get('localizer.persist_locale.session', true);
    }

    public function storesInCookie(): bool
    {
        return Config::get('localizer.persist_locale.cookie', true);
    }

    public function detectors(): array
    {
        return Config::get('localizer.detectors', []);
    }

    public function url(string $uri, ?string $locale = null): string
    {
        return $this->translator->translate($uri, $locale);
    }

    public function macroRegisterName(): string
    {
        return 'localize';
    }
}
