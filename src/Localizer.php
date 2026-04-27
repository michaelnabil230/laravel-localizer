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
