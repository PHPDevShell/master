<?php

// --------------------------------------------------------------
// Notes:
//  Testing Sessions using PHPUnit and the CLI PHP command line
//  interface is a tricky affair since the CLI interface isn't
//  really designed to handle sessions properly. Manual testing
//  of the Session classes is highly recommended.

$testing_dir = "/var/www/websentinel/";

require_once $testing_dir."tests/config/config.php";
require_once $testing_dir."lib/sessionintf.php";
require_once $testing_dir."lib/mcsession.php";

// Global Text translation function
function __s($text) {
    return $text;
}

// Global debug function
function __debug($text) {
    echo 'DEBUG: ' . $text . "<br>";
}

class MCSessionTest extends PHPUnit_Framework_TestCase {
    protected $session;

    protected function setUp() {
    }

    protected function tearDown() {
        if (isset($this->session)) {
            if ($this->session->started) {
                $this->session->flush(true);
            }
            unset($this->session);
        }
    }

    /**
     * @expectedException EMCSNotStartedException
     * @group session
     * @group mcsession
     */
    public function testMCSessionNotStartedException1() {
        $config = array(
            'session_enabled' => true,
            'session_root_dir' => "/var/www/websentinel/",
            'session_write_dir' => "write/sessions/",
            'session_protect' => true,
            'session_gc_probability' => 1,
            'session_gc_divisor' => 100,
            'session_gc_maxlifetime' => 1440,
            'session_mc_server' => "localhost",
            'session_mc_port' => 11211
        );

        $this->session = new MCSession($config);
        $this->session->start();
        $this->session->started = false;
        try {
            $this->session->set('not-started', 'will throw error');
        } catch (Exception $e) {}
        $this->session->get('not-started', 'will throw error');
    }

    /**
     * @group session
     * @group mcsession
     */
    public function testMCSessionDisabled() {
        // Test with disabled cache
        $config = array('session_enabled' => False);
        $this->session = new MCSession($config);
        $this->assertTrue($this->session->start());

        $this->session->set('some_string', 'This will not be stored.');
        $actual = $this->session->get('some_string');
        $this->assertNull($actual);
        $this->session->started = true;
        $this->session->flush(true);
    }

    /**
     * @group session
     * @group mcsession
     */
    public function testMCSessionFull() {
        // Test with correct configuration settings
        $config = array(
            'session_enabled' => true,
            'session_root_dir' => "/var/www/websentinel/",
            'session_write_dir' => "write/sessions/",
            'session_protect' => false,
            'session_gc_probability' => 1,
            'session_gc_divisor' => 100,
            'session_gc_maxlifetime' => 1440,
            'session_write_mode' => 0,
            'session_mc_server' => "localhost",
            'session_mc_port' => 11211
        );
        $this->session = new MCSession($config);
        //$this->session->flush(true);
        $this->assertTrue($this->session->start());

        // Test the starting of a session that was already started
        $this->assertTrue($this->session->start());

        $this->runSessionTests();
    }

    public function runSessionTests() {
        // Test null
        $this->session->set('null_value', null);
        $actual = $this->session->get('null_value');
        $this->assertNull($actual);

        // Test true
        $this->session->set('true_value', true);
        $actual = $this->session->get('true_value');
        $this->assertTrue($actual);

        // Test false
        $this->session->set('false_value', false);
        $actual = $this->session->get('false_value');
        $this->assertFalse($actual);

        // Test Setting and Getting integers
        $expected = 0;
        $this->session->set('int_zero', $expected);
        $actual = $this->session->get('int_zero');
        $this->assertEquals($expected, $actual);

        $expected = 2147483647;
        $this->session->set('32bit_int_pos', $expected);
        $actual = $this->session->get('32bit_int_pos');
        $this->assertEquals($expected, $actual);

        $expected = -2147483647;
        $this->session->set('32bit_int_neg', $expected);
        $actual = $this->session->get('32bit_int_neg');
        $this->assertEquals($expected, $actual);

        /*
        $expected = 9223372036854775807;
        $this->session->set('64bit_int_pos', $expected);
        $actual = $this->session->get('64bit_int_pos');
        $this->assertEquals($expected, $actual);

        $expected = -9223372036854775807;
        $this->session->set('64bit_int_neg', $expected);
        $actual = $this->session->get('64bit_int_neg');
        $this->assertEquals($expected, $actual);
         */

        // Test Setting and Getting floating point numbers (floats shouldn't be compared directly in normal conditions)
        $expected = 0.0;
        $this->session->set('float_zero', $expected);
        $actual = $this->session->get('float_zero');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = 1.234;
        $this->session->set('float_small_pos', $expected);
        $actual = $this->session->get('float_small_pos');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = -1.234;
        $this->session->set('float_small_neg', $expected);
        $actual = $this->session->get('float_small_neg');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = 1.2e3;
        $this->session->set('float_scientific_pos', $expected);
        $actual = $this->session->get('float_scientific_pos');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = -1.2e3;
        $this->session->set('float_scientific_neg', $expected);
        $actual = $this->session->get('float_scientific_neg');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = 7E-10;
        $this->session->set('float_exponent_pos', $expected);
        $actual = $this->session->get('float_exponent_pos');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = -7E-10;
        $this->session->set('float_exponent_neg', $expected);
        $actual = $this->session->get('float_exponent_neg');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        // Test Setting and Getting strings
        $expected = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
        $this->session->set('ascii_string', $expected);
        $actual = $this->session->get('ascii_string');
        $this->assertEquals($expected, $actual);

        // Test Setting and Getting unicode strings
        $expected = mb_convert_encoding(
            '&#x20;&#x21;&#x22;&#x23;&#x24;&#x25;&#x26;&#x27;&#x28;&#x29;&#x2a;&#x2b;&#x2c;&#x2d;&#x2e;&#x2f;&#x30;&#x31;' .
            '&#x32;&#x33;&#x34;&#x35;&#x36;&#x37;&#x38;&#x39;&#x3a;&#x3b;&#x3c;&#x3d;&#x3e;&#x3f;&#x40;&#x41;&#x42;&#x43;' .
            '&#x44;&#x45;&#x46;&#x47;&#x48;&#x49;&#x4a;&#x4b;&#x4c;&#x4d;&#x4e;&#x4f;&#x50;&#x51;&#x52;&#x53;&#x54;&#x55;' .
            '&#x56;&#x57;&#x58;&#x59;&#x5a;&#x5b;&#x5c;&#x5d;&#x5e;&#x5f;&#x60;&#x61;&#x62;&#x63;&#x64;&#x65;&#x66;&#x67;' .
            '&#x68;&#x69;&#x6a;&#x6b;&#x6c;&#x6d;&#x6e;&#x6f;&#x70;&#x71;&#x72;&#x73;&#x74;&#x75;&#x76;&#x77;&#x78;&#x79;' .
            '&#x7a;&#x7b;&#x7c;&#x7d;&#x7e;', 'UTF-8', 'HTML-ENTITIES');
        $this->session->set('unicode_string', $expected);
        $actual = $this->session->get('unicode_string');
        $this->assertEquals($expected, $actual);

        // Test Setting and Getting arrays
        $expected = array(
            'inner_array' => array(0 => 'Test Setting', '1' => 'Some string', 'inner_inner_array' => array(10, 20, 30, 40)),
            10 => 'Element 10',
            'string' => " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~"
        );
        $this->session->set('array', $expected);
        $actual = $this->session->get('array');
        $this->assertEquals($expected, $actual);

        // Test setting a weird key
        $expected = 'Some string';
        $key = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
        $this->session->set($key, $expected);
        $actual = $this->session->get($key);
        $this->assertEquals($expected, $actual);

        // Test setting a blank key
        $expected = null;
        $result = $this->session->set('', $expected);
        $actual = $this->session->get('');
        $this->assertNull($actual);
        $this->assertFalse($result);

        // Test setting and getting multiple values. Also tests whether the correct default
        // value is returned for a non existent key.
        $default_keys = array(
            'bool' => null,
            'int' => null,
            'float' => null,
            'string' => null,
            'array' => null,
            'non-existent-key' => 'default'
        );
        $set_keys = array(
            'bool' => false,
            'int' => 2147483647,
            'float' => 1.234,
            'string' => " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~",
            'array' => array(10, 20, 30, 40)
        );
        $expected = $set_keys;
        $expected['non-existent-key'] = 'default';

        // Test getting the default value if the key does not exist
        $expected = 'default';
        $actual = $this->session->get('non-existent-key', $expected);
        $this->assertEquals($expected, $actual);

        // Test saving the session
        $this->session->save();

        /*
        // Final Test: Test Flush function (Removes all stored entries)
        $this->session->flush();
        $actual = $this->session->get('ascii_string');
        $this->assertNull($actual);
         */
    }
}
