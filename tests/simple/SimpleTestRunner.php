<?php
/**
 * Test Runner Simple pour SocialFlow
 * Alternative à PHPUnit pour les tests de base
 */

class SimpleTestRunner {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    private $results = [];
    
    public function addTest($name, $callback) {
        $this->tests[$name] = $callback;
    }
    
    public function runAll() {
        echo "=== SocialFlow - Tests Simples ===\n";
        echo "Démarrage des tests...\n\n";
        
        foreach ($this->tests as $name => $callback) {
            echo "Test: $name\n";
            try {
                $result = $callback();
                if ($result) {
                    echo "✅ PASSED\n";
                    $this->passed++;
                    $this->results[$name] = 'PASSED';
                } else {
                    echo "❌ FAILED\n";
                    $this->failed++;
                    $this->results[$name] = 'FAILED';
                }
            } catch (Exception $e) {
                echo "❌ ERROR: " . $e->getMessage() . "\n";
                $this->failed++;
                $this->results[$name] = 'ERROR: ' . $e->getMessage();
            }
            echo "\n";
        }
        
        $this->showSummary();
    }
    
    private function showSummary() {
        echo "=== RÉSUMÉ DES TESTS ===\n";
        echo "Tests réussis: {$this->passed}\n";
        echo "Tests échoués: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        echo "Taux de réussite: " . round(($this->passed / ($this->passed + $this->failed)) * 100, 2) . "%\n\n";
        
        if ($this->failed > 0) {
            echo "=== DÉTAILS DES ÉCHECS ===\n";
            foreach ($this->results as $test => $result) {
                if ($result !== 'PASSED') {
                    echo "- $test: $result\n";
                }
            }
        }
    }
    
    // Méthodes d'assertion
    public static function assertTrue($condition, $message = '') {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
        return true;
    }
    
    public static function assertFalse($condition, $message = '') {
        if ($condition) {
            throw new Exception("Assertion failed: $message");
        }
        return true;
    }
    
    public static function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new Exception("Assertion failed: Expected '$expected', got '$actual'. $message");
        }
        return true;
    }
    
    public static function assertNotEquals($expected, $actual, $message = '') {
        if ($expected === $actual) {
            throw new Exception("Assertion failed: Expected different values. $message");
        }
        return true;
    }
    
    public static function assertNotEmpty($value, $message = '') {
        if (empty($value)) {
            throw new Exception("Assertion failed: Value is empty. $message");
        }
        return true;
    }
    
    public static function assertNull($value, $message = '') {
        if ($value !== null) {
            throw new Exception("Assertion failed: Expected null, got '$value'. $message");
        }
        return true;
    }
    
    public static function assertNotNull($value, $message = '') {
        if ($value === null) {
            throw new Exception("Assertion failed: Expected non-null value. $message");
        }
        return true;
    }
}
?>
