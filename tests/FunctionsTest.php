<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/SimpleTestRunner.php';

class FunctionsTest {
    public function testClean() {
        SimpleTestRunner::assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', clean('<script>alert(1)</script>'));
        SimpleTestRunner::assertEquals('Hello World', clean('  Hello World  '));
        SimpleTestRunner::assertEquals('&quot;quoted&quot;', clean('"quoted"'));
    }

    public function testSanitize() {
        SimpleTestRunner::assertEquals('alert(1)', sanitize('<script>alert(1)</script>'));
        SimpleTestRunner::assertEquals('Hello World', sanitize('  <b>Hello</b> <i>World</i>  '));
    }

    public function testSanitizeUrl() {
        SimpleTestRunner::assertEquals('https://example.com', sanitizeUrl(' https://example.com '));
        SimpleTestRunner::assertEquals('', sanitizeUrl('javascript:alert(1)'));
        SimpleTestRunner::assertEquals('', sanitizeUrl('not-a-url'));
        SimpleTestRunner::assertEquals('http://localhost', sanitizeUrl('http://localhost'));
    }

    public function testAvatarInitials() {
        SimpleTestRunner::assertEquals('JD', avatarInitials('John Doe'));
        SimpleTestRunner::assertEquals('JO', avatarInitials('John'));
        SimpleTestRunner::assertEquals('JD', avatarInitials('  john doe  '));
        SimpleTestRunner::assertEquals('AB', avatarInitials('A B C'));
    }

    public function testExtractHashtags() {
        $text = "Hello #world! This is #Voxu #voxu #TESTing";
        $tags = extractHashtags($text);
        SimpleTestRunner::assertCount(3, $tags);
        SimpleTestRunner::assertTrue(in_array('world', $tags));
        SimpleTestRunner::assertTrue(in_array('voxu', $tags));
        SimpleTestRunner::assertTrue(in_array('testing', $tags));
    }

    public function testTimeAgo() {
        $now = time();
        SimpleTestRunner::assertEquals('just now', timeAgo(date('Y-m-d H:i:s', $now - 30)));
        SimpleTestRunner::assertEquals('5m ago', timeAgo(date('Y-m-d H:i:s', $now - 300)));
        SimpleTestRunner::assertEquals('2h ago', timeAgo(date('Y-m-d H:i:s', $now - 7200)));
        SimpleTestRunner::assertEquals('1d ago', timeAgo(date('Y-m-d H:i:s', $now - 86400)));
    }

    public function testFormatPoints() {
        SimpleTestRunner::assertEquals('1,000 pts', formatPoints(1000));
        SimpleTestRunner::assertEquals('0 pts', formatPoints(0));
        SimpleTestRunner::assertEquals('-1,000 pts', formatPoints(-1000));
        SimpleTestRunner::assertEquals('1 pts', formatPoints(1));
        SimpleTestRunner::assertEquals('1,000,000 pts', formatPoints(1000000));
        SimpleTestRunner::assertEquals(number_format(PHP_INT_MAX) . ' pts', formatPoints(PHP_INT_MAX));
        SimpleTestRunner::assertEquals(number_format(PHP_INT_MIN) . ' pts', formatPoints(PHP_INT_MIN));
    }

    public function testFormatDuration() {
        SimpleTestRunner::assertEquals('Unlimited', formatDuration(0));
        SimpleTestRunner::assertEquals('1h 5m', formatDuration(3900));
        SimpleTestRunner::assertEquals('10m ', formatDuration(600));
        SimpleTestRunner::assertEquals('45s', formatDuration(45));
    }

    public function testPasswordHashing() {
        $password = 'secret123';
        $hash = hashPassword($password);
        SimpleTestRunner::assertTrue(verifyPassword($password, $hash));
        SimpleTestRunner::assertFalse(verifyPassword('wrong-password', $hash));
    }

    public function testGenerateToken() {
        $token1 = generateToken(16);
        $token2 = generateToken(16);
        SimpleTestRunner::assertEquals(32, strlen($token1)); // bin2hex of 16 bytes is 32 chars
        SimpleTestRunner::assertFalse($token1 === $token2);
    }
}
