<?php

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/SimpleTestRunner.php';

class I18nTest {
    public function setUp() {
        // Clear globals that might affect tests
        $_SESSION = [];
        $_COOKIE = [];
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
    }

    public function testGetCurrentLang() {
        $this->setUp();

        // 1. Default
        SimpleTestRunner::assertEquals('en', getCurrentLang());

        // 2. Session preference
        $_SESSION['voxu_lang'] = 'fr';
        SimpleTestRunner::assertEquals('fr', getCurrentLang());
        $this->setUp(); // Reset

        // 3. Cookie preference
        $_COOKIE['voxu_lang'] = 'es';
        SimpleTestRunner::assertEquals('es', getCurrentLang());
        $this->setUp(); // Reset

        // 4. Browser Accept-Language
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7';
        SimpleTestRunner::assertEquals('de', getCurrentLang());
        $this->setUp(); // Reset

        // 5. Invalid values
        $_SESSION['voxu_lang'] = 'invalid_lang';
        SimpleTestRunner::assertEquals('en', getCurrentLang());
    }

    public function testTranslation() {
        $this->setUp();

        // Test English (Default)
        SimpleTestRunner::assertEquals('Home', __('home'));

        // Test Specific Language
        SimpleTestRunner::assertEquals('Accueil', __('home', 'fr'));

        // Test fallback to English when language missing
        // (Assuming a key doesn't have a translation for a language, e.g., if we added one)
        // Since all keys currently seem to have all languages, we just test normal translation.

        // Test fallback to key when key is completely missing
        SimpleTestRunner::assertEquals('missing_key', __('missing_key', 'fr'));
        SimpleTestRunner::assertEquals('missing_key', __('missing_key'));
    }

    public function testSetLanguage() {
        $this->setUp();

        // Valid language. Note: setcookie might issue a warning if headers are sent.
        // We suppress it with @ since we are in CLI.
        @setLanguage('pt');
        SimpleTestRunner::assertEquals('pt', $_SESSION['voxu_lang']);

        // Invalid language fallback to 'en'
        @setLanguage('not_a_lang');
        SimpleTestRunner::assertEquals('en', $_SESSION['voxu_lang']);
    }

    public function testIsRtl() {
        $this->setUp();

        $_SESSION['voxu_lang'] = 'ar';
        SimpleTestRunner::assertTrue(isRtl());

        $_SESSION['voxu_lang'] = 'en';
        SimpleTestRunner::assertFalse(isRtl());

        // Invalid language uses getCurrentLang fallback to en, which is not RTL
        $_SESSION['voxu_lang'] = 'invalid';
        SimpleTestRunner::assertFalse(isRtl());
    }

    public function testGetI18nStrings() {
        $this->setUp();

        $enStrings = getI18nStrings('en');
        SimpleTestRunner::assertTrue(is_array($enStrings));
        SimpleTestRunner::assertEquals('Home', $enStrings['home']);

        $frStrings = getI18nStrings('fr');
        SimpleTestRunner::assertTrue(is_array($frStrings));
        SimpleTestRunner::assertEquals('Accueil', $frStrings['home']);
    }
}
