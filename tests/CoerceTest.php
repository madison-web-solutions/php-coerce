<?php

use MadisonSolutions\Coerce\Coerce;
use PHPUnit\Framework\TestCase;

class CoerceTest extends TestCase
{
    // Helper function which attempts to return helpful information about the
    // type and value of a variable as concisely as possible for debugging.
    protected function debugval($val)
    {
        if (is_string($val)) {
            if (strlen($val) > 20) {
                $val = substr($val, 0, 20) . '...';
            }
            return "'{$val}'";
        } elseif (is_null($val)) {
            return 'NULL';
        } elseif (is_bool($val)) {
            return $val ? 'TRUE' : 'FALSE';
        } elseif (is_int($val)) {
            return (string) $val;
        } elseif (is_float($val)) {
            if (is_nan($val)) {
                return 'NAN';
            } elseif (is_infinite($val)) {
                return ($val < 0 ? '-INF' : 'INF');
            } else {
                return (string) $val;
            }
        } elseif (is_array($val)) {
            $out = [];
            foreach ($val as $key => $subval) {
                $out[] = $this->debugval($key) . ': ' . $this->debugval($subval);
            }
            $out = implode(', ', $out);
            if (strlen($out) > 20) {
                $out = substr($out, 0, 20) . '...';
            }
            return '{' . $out . '}';
        } elseif (is_object($val)) {
            return get_class($val) . ' ' . (method_exists($val, '__toString') ? $val->__toString() : '-obj-');
        } else {
            throw new \Exception("Unexpected type " . gettype($val));
        }
    }

    protected function assertCoercionResults($method, $tests, ...$extra_args)
    {
        foreach ($tests as $test) {
            switch ($test[0]) {
                case 'pass':
                    $this->assertCoersionsSucceeds($method, $test[1], $test[2], ...$extra_args);
                    break;
                case 'fail':
                    $this->assertCoersionsFails($method, $test[1], ...$extra_args);
                    break;
                default:
                    throw new \Exception("Test result should be pass or fail but got " . $this->debugval($test[0]));
            }
        }
    }

    protected function assertCoersionsSucceeds($method, $input, $expected_output, ...$extra_args)
    {
        $result = Coerce::$method($input, $output, ...$extra_args);
        $this->assertSame(true, $result, "Could not coerce value " . $this->debugval($input) . " using method {$method}");
        $this->assertSame($expected_output, $output);
        $method_or_fail = "{$method}OrFail";
        $this->assertSame($expected_output, Coerce::$method_or_fail($input, ...$extra_args));
    }

    protected function assertCoersionsFails($method, $input, ...$extra_args)
    {
        $output = 'initial'; // Set output initially to a string so that we can verify it gets nulled by the failed coercion
        $result = Coerce::$method($input, $output, ...$extra_args);
        $this->assertSame(false, $result, "Should not have been able to coerce value " . $this->debugval($input) . " using method {$method} but was coerced to value " . $this->debugval($output));

        $method_or_fail = "{$method}OrFail";
        $thrown = false;
        $output = 'initial';
        try {
            $output = Coerce::$method_or_fail($input, ...$extra_args);
        } catch (InvalidArgumentException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, "InvalidArgumentException should have been thrown attempting to coerce value " . $this->debugval($input) . " using method {$method_or_fail} but was coerced to value " . $this->debugval($output));
    }

    public function testCoerceToString()
    {
        $foo = new class() {
            public function __toString()
            {
                return 'foo';
            }
        };

        $tests1 = [
            0  => ['pass', null, ''],
            1  => ['pass', '', ''],
            2  => ['pass', 'foo', 'foo'],
            3  => ['pass', 0 ,'0'],
            4  => ['pass', 2, '2'],
            5  => ['pass', 2.5, '2.5'],
            6  => ['pass', NAN, 'NAN'],
            7  => ['pass', true, 'true'],
            8  => ['pass', false, 'false'],
            9  => ['pass', $foo,  'foo'],
            10 => ['fail', []],
            11 => ['fail', new \stdClass()],
        ];

        $this->assertCoercionResults('toString', $tests1);

        $tests2 = $tests1;
        $tests2[0] = ['pass', null, null];
        $tests2[1] = ['pass', '', null];
        $this->assertCoercionResults('toStringOrNull', $tests2);

        $tests3 = $tests1;
        $tests3[7] = ['fail', true];
        $tests3[8] = ['fail', false];
        $this->assertCoercionResults('toString', $tests3, reject_bool: true);
    }

    public function testCoerceToInt()
    {
        $tests1 = [
            0  => ['pass', 0 , 0],
            1  => ['pass', 2, 2],
            2  => ['pass', 2.0, 2],
            3  => ['pass', true, 1],
            4  => ['pass', false, 0],
            5  => ['pass', '0', 0],
            6  => ['pass', '2.0', 2],
            7  => ['fail', null],
            8  => ['fail', ''],
            9  => ['fail', 2.6],
            10 => ['fail', NAN],
            11 => ['fail', INF],
            12 => ['fail', 'foo'],
            13 => ['fail', '2.6'],
            14 => ['fail', []],
            15 => ['fail', new \stdClass()],
            16 => ['pass', -4, -4],
        ];

        $this->assertCoercionResults('toInt', $tests1);

        $tests2 = $tests1;
        $tests2[7] = ['pass', null, null];
        $tests2[8] = ['pass', '', null];
        $this->assertCoercionResults('toIntOrNull', $tests2);

        $tests3 = $tests1;
        $tests3[3] = ['fail', true];
        $tests3[4] = ['fail', false];
        $this->assertCoercionResults('toInt', $tests3, reject_bool: true);

        $tests4 = $tests1;
        $tests4[0] = ['fail', 0];
        $tests4[5] = ['fail', '0'];
        $tests4[4] = ['fail', false];
        $this->assertCoercionResults('toInt', $tests4, reject_zero: true);

        $tests5 = $tests1;
        $tests5[16] = ['fail', -4];
        $this->assertCoercionResults('toInt', $tests5, reject_negative: true);
    }

    public function testCoerceToFloat()
    {
        $tests1 = [
            0  => ['pass', 0, 0.0],
            1  => ['pass', 2, 2.0],
            2  => ['pass', 2.0, 2.0],
            3  => ['pass', true, 1.0],
            4  => ['pass', false, 0.0],
            5  => ['pass', 2.5, 2.5],
            6  => ['pass', '2.5', 2.5],
            7  => ['fail', null],
            8  => ['fail', ''],
            9  => ['fail', NAN],
            10 => ['fail', INF],
            11 => ['fail', 'foo'],
            12 => ['fail', []],
            13 => ['fail', new \stdClass()],
        ];

        $this->assertCoercionResults('toFloat', $tests1);

        $tests2 = $tests1;
        $tests2[7] = ['pass', null, null];
        $tests2[8] = ['pass', '', null];
        $this->assertCoercionResults('toFloatOrNull', $tests2);

        $tests3 = $tests1;
        $tests3[3] = ['fail', true];
        $tests3[4] = ['fail', false];
        $this->assertCoercionResults('toFloat', $tests3, reject_bool: true);
    }

    public function testCoerceToBool()
    {
        $tests1 = [
            0  => ['pass', 0, false],
            2  => ['pass', 1, true],
            3  => ['pass', 0.0, false],
            4  => ['pass', 1.0, true],
            5  => ['pass', true, true],
            6  => ['pass', false, false],
            7  => ['pass', 't', true],
            8  => ['pass', 'TRUE', true],
            9  => ['pass', 'no', false],
            10 => ['fail', null],
            11 => ['fail', ''],
            12 => ['fail', NAN],
            13 => ['fail', INF],
            14 => ['fail', 'foo'],
            14 => ['fail', '2.5'],
            15 => ['fail', []],
            16 => ['fail', new \stdClass()],
        ];

        $this->assertCoercionResults('toBool', $tests1);

        $tests2 = $tests1;
        $tests2[10] = ['pass', null, null];
        $tests2[11] = ['pass', '', null];
        $this->assertCoercionResults('toBoolOrNull', $tests2);
    }

    public function testCoerceToArrayKey()
    {
        $tests1 = [
            0  => ['pass', 0, 0],
            1  => ['pass', 'foo', 'foo'],
            2  => ['pass', 1.0, 1],
            3  => ['pass', 2.5, '2.5'],
            4  => ['pass', '1', 1],
            5  => ['pass', '1.0', 1],
            6  => ['pass', '2.5', '2.5'],
            7  => ['fail', null],
            8  => ['fail', ''],
            9  => ['fail', NAN],
            10 => ['fail', INF],
            11 => ['fail', true],
            12 => ['fail', []],
            13 => ['fail', new \stdClass()],
        ];

        $this->assertCoercionResults('toArrayKey', $tests1);

        $tests2 = $tests1;
        $tests2[7] = ['pass', null, null];
        $tests2[8] = ['pass', '', null];
        $this->assertCoercionResults('toArrayKeyOrNull', $tests2);
    }
}
