<?php

class TestHelper
{
    private static $passed = 0;
    private static $failed = 0;
    private static $skipped = 0;

    public static function assertTrue($condition, $name)
    {
        if ($condition) {
            self::$passed++;
            echo "[PASS] $name\n";
        } else {
            self::$failed++;
            echo "[FAIL] $name\n";
        }
    }

    public static function assertFalse($condition, $name)
    {
        if (!$condition) {
            self::$passed++;
            echo "[PASS] $name\n";
        } else {
            self::$failed++;
            echo "[FAIL] $name\n";
        }
    }

    public static function assertEqual($expected, $actual, $name)
    {
        if ($expected === $actual) {
            self::$passed++;
            echo "[PASS] $name\n";
        } else {
            self::$failed++;
            echo "[FAIL] $name (Expected: '$expected', Got: '$actual')\n";
        }
    }

    public static function assertNull($value, $name)
    {
        if ($value === null) {
            self::$passed++;
            echo "[PASS] $name\n";
        } else {
            self::$failed++;
            echo "[FAIL] $name (Expected null, Got something else)\n";
        }
    }

    public static function assertNotEmpty($value, $name)
    {
        if (!empty($value)) {
            self::$passed++;
            echo "[PASS] $name\n";
        } else {
            self::$failed++;
            echo "[FAIL] $name (Expected not empty)\n";
        }
    }

    public static function skip($name)
    {
        self::$skipped++;
        echo "[SKIP] $name\n";
    }

    public static function finish()
    {
        echo "\nTests Finished: " . self::$passed . " passed, " . self::$failed . " failed, " . self::$skipped . " skipped.\n";
        if (self::$failed > 0) {
            exit(1);
        }
        exit(0);
    }
}
