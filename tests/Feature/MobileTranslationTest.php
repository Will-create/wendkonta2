<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileTranslationTest extends TestCase
{
    public function test_mobile_dictionary_is_an_array_in_each_supported_locale(): void
    {
        $this->assertSame(base_path('lang'), lang_path());
        foreach (['en', 'fr', 'ar'] as $locale) {
            app()->setLocale($locale);
            $strings = trans('mobile');
            $this->assertIsArray($strings);
            $this->assertNotEmpty($strings['install']);
            $this->assertSame($strings['offline'], trans('mobile.offline'));
        }
    }

    public function test_dictionary_loading_preserves_case_insensitive_database_keys(): void
    {
        app()->setLocale('fr');
        app('translator')->addLines(['db.Customer Name' => 'Nom du client'], 'fr');
        $this->assertSame('Nom du client', trans('db.customer name'));
        $this->assertIsArray(trans('mobile'));
        $this->assertSame('Nom du client', trans('db.CUSTOMER NAME'));
    }
}
