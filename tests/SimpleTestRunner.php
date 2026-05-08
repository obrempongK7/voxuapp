<?php

class SimpleTestRunner {
    private $passes = 0;
    private $fails = 0;
    private $testFile;

    public function __construct($testFile) {
        $this->testFile = $testFile;
    }

    public function run() {
        require_once $this->testFile;
        $className = pathinfo($this->testFile, PATHINFO_FILENAME);
        $testObject = new $className();

        echo "Running tests in $className...\n";

        foreach (get_class_methods($testObject) as $method) {
            if (strpos($method, 'test') === 0) {
                try {
                    $testObject->$method();
                    echo "  [PASS] $method\n";
                    $this->passes++;
                } catch (Exception $e) {
                    echo "  [FAIL] $method: " . $e->getMessage() . "\n";
                    $this->fails++;
                }
            }
        }

        echo "\nSummary: {$this->passes} passes, {$this->fails} fails\n";
        return $this->fails === 0;
    }

    public static function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new Exception($message ?: "Expected " . var_export($expected, true) . " but got " . var_export($actual, true));
        }
    }

    public static function assertTrue($condition, $message = '') {
        if (!$condition) {
            throw new Exception($message ?: "Expected true but got false");
        }
    }

    public static function assertFalse($condition, $message = '') {
        if ($condition) {
            throw new Exception($message ?: "Expected false but got true");
        }
    }

    public static function assertCount($expectedCount, $haystack, $message = '') {
        if (count($haystack) !== $expectedCount) {
            throw new Exception($message ?: "Expected count $expectedCount but got " . count($haystack));
        }
    }
}
