<?php

namespace NielsNumbers\LaravelLocalizer\Tests\Feature\Services;

use Illuminate\Support\Facades\Lang;
use Orchestra\Testbench\TestCase;
use NielsNumbers\LaravelLocalizer\Services\UriTranslator;

class UriTranslatorTest extends TestCase
{
    protected UriTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = new UriTranslator();

        Lang::addLines([
            'routes.hello' => 'hallo',
            'routes.world' => 'wereld',
            'routes.override/hello/world' => 'iets/heel/anders',
            'routes.hello/world/{parameter}' => 'uri/met/{parameter}',
        ], 'nl');

    }

    public function test_translates_full_uri_if_exact_match_exists()
    {
        $result = $this->translator->translate('override/hello/world', 'nl');
        $this->assertEquals('iets/heel/anders', $result);
    }

    public function test_translates_individual_segments()
    {
        $result = $this->translator->translate('hello/world', 'nl');
        $this->assertEquals('hallo/wereld', $result);
    }

    public function test_keeps_untranslated_segments()
    {
        $result = $this->translator->translate('hello/big/world', 'nl');
        $this->assertEquals('hallo/big/wereld', $result);
    }

    public function test_preserves_placeholders()
    {
        $result = $this->translator->translate('hello/{parameter}', 'nl');
        $this->assertEquals('hallo/{parameter}', $result);
    }

    public function test_translates_exact_match_with_placeholder()
    {
        $result = $this->translator->translate('hello/world/{parameter}', 'nl');
        $this->assertEquals('uri/met/{parameter}', $result);
    }
}
