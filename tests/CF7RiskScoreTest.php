<?php
use PHPUnit\Framework\TestCase;

class WPDB_Mock
{
    public $prefix = 'wp_';
    private $ipAttempts;
    private $emailAttempts;
    public function __construct($ipAttempts, $emailAttempts)
    {
        $this->ipAttempts = $ipAttempts;
        $this->emailAttempts = $emailAttempts;
    }
    public function get_var($query)
    {
        if (stripos($query, 'ip_address') !== false) {
            return $this->ipAttempts;
        }
        if (stripos($query, 'email') !== false) {
            return $this->emailAttempts;
        }
        return 0;
    }
    public function prepare($query, $param)
    {
        return sprintf($query, $param);
    }
}

class CF7RiskScoreTest extends TestCase
{
    private function calculate($ipAttempts, $emailAttempts, $email = 'a@example.com')
    {
        global $wpdb;
        $wpdb = new WPDB_Mock($ipAttempts, $emailAttempts);

        $ref = new ReflectionClass('CF7_Advanced_Honeypot');
        $instance = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('calculate_risk_score');
        $method->setAccessible(true);
        return $method->invoke($instance, '127.0.0.1', $email);
    }

    public function testScoreLimits()
    {
        $this->assertSame(0, $this->calculate(0, 0));
        $this->assertSame(30, $this->calculate(6, 0));
        $this->assertSame(60, $this->calculate(12, 0));
        $this->assertSame(20, $this->calculate(0, 4));
        $this->assertSame(40, $this->calculate(0, 8));
        $this->assertSame(100, $this->calculate(12, 8));
    }

    public function testMaximumScore()
    {
        $this->assertLessThanOrEqual(100, $this->calculate(50, 50));
    }

    public function testScoreWithEmptyEmail()
    {
        $this->assertSame(0, $this->calculate(0, 0, ''));
        $this->assertSame(30, $this->calculate(6, 0, ''));
    }
}
