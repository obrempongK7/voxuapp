<?php

require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/SimpleTestRunner.php';

class SecurityTest {
    public function testSanitize() {
        // Normal string
        SimpleTestRunner::assertEquals('Hello World', Security::sanitize('Hello World'));

        // String with tags
        SimpleTestRunner::assertEquals('alert(1)', Security::sanitize('<script>alert(1)</script>'));
        SimpleTestRunner::assertEquals('Hello World', Security::sanitize('<b>Hello</b> <i>World</i>'));

        // Padded whitespace
        SimpleTestRunner::assertEquals('Hello World', Security::sanitize('  Hello World  '));
        SimpleTestRunner::assertEquals('alert(1)', Security::sanitize('  <script>alert(1)</script>  '));

        // Integers and Floats
        SimpleTestRunner::assertEquals('123', Security::sanitize(123));
        SimpleTestRunner::assertEquals('12.34', Security::sanitize(12.34));

        // Booleans
        SimpleTestRunner::assertEquals('1', Security::sanitize(true));
        SimpleTestRunner::assertEquals('', Security::sanitize(false));

        // Null
        SimpleTestRunner::assertEquals('', Security::sanitize(null));
    }
}
