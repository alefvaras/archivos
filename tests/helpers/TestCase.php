<?php
/**
 * Test Case Base
 *
 * Clase base para todos los tests
 */

class TestCase {

    protected $tests_passed = 0;
    protected $tests_failed = 0;
    protected $results = [];

    /**
     * Assert helper
     */
    protected function assert($condition, $test_name, $details = '') {
        if ($condition) {
            $this->tests_passed++;
            $this->results[] = ['status' => 'pass', 'name' => $test_name, 'details' => $details];
            echo "  ✅ PASS: $test_name";
            if ($details) echo " ($details)";
            echo "\n";
        } else {
            $this->tests_failed++;
            $this->results[] = ['status' => 'fail', 'name' => $test_name, 'details' => $details];
            echo "  ❌ FAIL: $test_name";
            if ($details) echo " ($details)";
            echo "\n";
        }
    }

    /**
     * Show summary
     */
    protected function showSummary() {
        $total = $this->tests_passed + $this->tests_failed;
        $percentage = $total > 0 ? round(($this->tests_passed / $total) * 100, 2) : 0;

        echo "\n\n";
        echo "================================================================================\n";
        echo str_pad("RESUMEN", 80, " ", STR_PAD_BOTH) . "\n";
        echo "================================================================================\n";
        echo "\n";

        echo "  • Total tests → $total\n";
        echo "  • Pasados → {$this->tests_passed} ✅\n";
        echo "  • Fallados → {$this->tests_failed} ❌\n";
        echo "  • Porcentaje → {$percentage}%\n";
        echo "\n";

        if ($this->tests_failed == 0) {
            echo "  ✓  ¡Todos los tests pasaron! 🎉\n";
        } else {
            echo "  ✗  Hay {$this->tests_failed} tests que fallaron\n";

            // Mostrar tests fallados
            if ($this->tests_failed > 0) {
                echo "\n  Tests fallados:\n";
                foreach ($this->results as $result) {
                    if ($result['status'] === 'fail') {
                        echo "    • {$result['name']}\n";
                    }
                }
            }
        }

        echo "\n";
    }
}
