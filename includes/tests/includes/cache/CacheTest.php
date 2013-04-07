<?php

$testing_dir = "/var/www/websentinel/";

require_once $testing_dir."tests/config/config.php";
require_once $testing_dir."lib/filecache.php";
require_once $testing_dir."lib/apccache.php";
require_once $testing_dir."lib/mccache.php";

// Global Text translation function
function __s($text) {
    return $text;
}

// Global debug function
function __debug($text) {
    echo 'DEBUG: ' . $text . "<br>";
}

class CacheTest extends PHPUnit_Framework_TestCase {
    protected $cache;

    protected function setUp() {
    }

    protected function tearDown() {
        if (isset($this->cache)) {
            if ($this->cache->started) {
                $this->cache->flush();
            }
            unset($this->cache);
        }
    }

    /**
     * FILE CACHE TESTS
     */

    /**
     * @expectedException   EFCNotStartedException
     * @group cache
     * @group filecache
     */
    public function testFileCacheNotStartedException() {
        // Test without cache_file_root_dir setting specified
        $config = array(
            'cache_enabled' => True,
            'cache_file_root_dir' => "/var/www/websentinel/",
            'cache_file_write_dir' => "write/cache/",
            'cache_file_lifetime' => 1440
        );
        $this->cache = new FileCache($config);
        $this->cache->start();
        $this->cache->started = false;
        $this->cache->set('not-started', 'will throw error');
        $this->cache->setMulti(array());
        $this->cache->get('not-started', 'will throw error');
        $this->cache->getMulti(array());
        $this->cache->increment('inc', 1);
        $this->cache->decrement('inc', 1);
        $this->cache->flush();
    }

    /**
     * @expectedException   EFCWriteableException
     * @group cache
     * @group filecache
     */
    public function testFileCacheWriteDirPermException() {
        // Test without cache_file_root_dir setting specified
        $this->cache = new FileCache(array('cache_file_write_dir' => "write/cache/"));
        $this->cache->start();
    }

    /**
     * @expectedException   EFCWriteableException
     * @group cache
     * @group filecache
     */
    public function testFileCacheInvalidConfig() {
        // Test file cache startup without any config specified
        $this->cache = new FileCache();
        $this->assertFalse($this->cache->start());
        unset($this->cache);

        // Test without cache_file_write_dir setting specified
        $this->cache = new FileCache(array('cache_file_root_dir' => "/var/www/websentinel/"));
        $this->assertFalse($this->cache->start());
    }

    /**
     * @group cache
     * @group filecache
     */
    public function testFileCacheDisabled() {
        // Test with disabled cache
        $config = array('cache_enabled' => False);
        $this->cache = new FileCache($config);
        $this->assertTrue($this->cache->start());

        $this->cache->set('some_string', 'This will not be stored.');
        $actual = $this->cache->get('some_string');
        $this->assertNull($actual);

        $this->cache->setMulti(array('key' => 'value'));
        $actual = $this->cache->getMulti(array('key' => null));
        $this->assertNull($actual['key']);

        $this->assertEquals($this->cache->increment('inc_key'), 0);
        $this->assertEquals($this->cache->decrement('dec_key'), 0);

        $this->assertTrue($this->cache->delete('any_key'));
    }

    /**
     * @group cache
     * @group filecache
     */
    public function testFileCacheFull() {
        // Test with correct configuration settings
        $config = array(
            'cache_enabled' => True,
            'cache_file_root_dir' => "/var/www/websentinel/",
            'cache_file_write_dir' => "write/cache/",
            'cache_file_lifetime' => 1440
        );
        $this->cache = new FileCache($config);
        $this->assertTrue($this->cache->start());
        $this->cache->flush();

        $this->runCacheTests();
    }

    /**
     * APC CACHE TESTS
     * Notes:
     *     APC must be installed and enabled:
     *         $ sudo apt-get install php-apc
     *     Enable apc for php client access:
     *         Add "apc.enable_cli = On" to:
     *         /etc/php5/cli/php.ini
     */

    /**
     * @expectedException   EAPCNotStartedException
     * @group cache
     * @group filecache
     */
    public function testAPCNotStartedException() {
        // Test without cache_file_root_dir setting specified
        $config = array(
            'cache_enabled' => True,
            'cache_apc_lifetime' => 1440
        );
        $this->cache = new APCCache($config);
        $this->cache->start();
        $this->cache->started = false;
        $this->cache->set('not-started', 'will throw error');
        $this->cache->setMulti(array());
        $this->cache->get('not-started', 'will throw error');
        $this->cache->getMulti(array());
        $this->cache->increment('inc', 1);
        $this->cache->decrement('inc', 1);
        $this->cache->flush();
    }

    /**
     * @expectedException   EAPCNotAvailableException
     * @group cache
     * @group apccache
     */
    public function testAPCNotAvailableException() {
        $this->cache = new APCCache(array());
        $this->cache->test_mode = true;
        $this->assertFalse($this->cache->start());
    }

    /**
     * @group cache
     * @group apccache
     */
    public function testAPCCacheDisabled() {
        // Test with disabled cache
        $config = array('cache_enabled' => False);
        $this->cache = new APCCache($config);
        $this->assertTrue($this->cache->start());

        $this->cache->set('some_string', 'This will not be stored.');
        $actual = $this->cache->get('some_string');
        $this->assertNull($actual);

        $this->cache->setMulti(array('key' => 'value'));
        $actual = $this->cache->getMulti(array('key' => null));
        $this->assertNull($actual['key']);

        $this->assertEquals($this->cache->increment('inc_key'), 0);
        $this->assertEquals($this->cache->decrement('dec_key'), 0);

        $this->assertTrue($this->cache->delete('any_key'));
    }

    /**
     * @group cache
     * @group apccache
     */
    public function testAPCCacheFull() {
        // Test with correct configuration settings
        $config = array(
            'cache_enabled' => True,
            'cache_apc_lifetime' => 1440
        );
        $this->cache = new APCCache($config);
        $this->assertTrue($this->cache->start());
        $this->cache->flush();

        $this->runCacheTests();
    }

    /**
     * @expectedException   EMCNotStartedException
     * @group cache
     * @group mccache
     */
    public function testMCNotStartedException() {
        $config = array(
            'cache_enabled' => True,
            'cache_mc_server' => "localhost",
            'cache_mc_port' => 11211,
            'cache_mc_lifetime' => 1440
        );
        $this->cache = new MCCache($config);
        $this->cache->start();
        $this->cache->started = false;
        $this->cache->set('not-started', 'will throw error');
        $this->cache->setMulti(array());
        $this->cache->get('not-started', 'will throw error');
        $this->cache->getMulti(array());
        $this->cache->increment('inc', 1);
        $this->cache->decrement('inc', 1);
        $this->cache->flush();
    }

    /**
     * @expectedException   EMCNotAvailableException
     * @group cache
     * @group mccache
     */
    public function testMCNotAvailableException() {
        // Test without cache_file_root_dir setting specified
        $this->cache = new MCCache(array());
        $this->cache->test_mode = true;
        $this->assertFalse($this->cache->start());
    }

    /**
     * @group cache
     * @group mccache
     */
    public function testMCDisabled() {
        // Test with disabled cache
        $config = array('cache_enabled' => False);
        $this->cache = new MCCache($config);
        $this->assertTrue($this->cache->start());

        $this->cache->set('some_string', 'This will not be stored.');
        $actual = $this->cache->get('some_string');
        $this->assertNull($actual);

        $this->cache->setMulti(array('key' => 'value'));
        $actual = $this->cache->getMulti(array('key' => null));
        $this->assertNull($actual['key']);

        $this->assertEquals($this->cache->increment('inc_key'), 0);
        $this->assertEquals($this->cache->decrement('dec_key'), 0);

        $this->assertTrue($this->cache->delete('any_key'));
    }

    /**
     * @group cache
     * @group apccache
     */
    public function testMCCacheFull() {
        // Test with correct configuration settings
        $config = array(
            'cache_enabled' => True,
            'cache_mc_server' => "localhost",
            'cache_mc_port' => 11211,
            'cache_mc_lifetime' => 1440
        );
        $this->cache = new MCCache($config);
        $this->assertTrue($this->cache->start());
        $this->cache->flush();

        $this->runCacheTests();
    }

    public function runCacheTests() {
        // Test null
        $this->cache->set('null_value', null, 1440);
        $actual = $this->cache->get('null_value');
        $this->assertNull($actual);

        // Test true
        $this->cache->set('true_value', true);
        $actual = $this->cache->get('true_value');
        $this->assertTrue($actual);

        // Test false
        $this->cache->set('false_value', false);
        $actual = $this->cache->get('false_value');
        $this->assertFalse($actual);

        // Test Setting and Getting integers
        $expected = 0;
        $this->cache->set('int_zero', $expected);
        $actual = $this->cache->get('int_zero');
        $this->assertEquals($expected, $actual);

        $expected = 2147483647;
        $this->cache->set('32bit_int_pos', $expected);
        $actual = $this->cache->get('32bit_int_pos');
        $this->assertEquals($expected, $actual);

        $expected = -2147483647;
        $this->cache->set('32bit_int_neg', $expected);
        $actual = $this->cache->get('32bit_int_neg');
        $this->assertEquals($expected, $actual);

        /*
        $expected = 9223372036854775807;
        $this->cache->set('64bit_int_pos', $expected);
        $actual = $this->cache->get('64bit_int_pos');
        $this->assertEquals($expected, $actual);

        $expected = -9223372036854775807;
        $this->cache->set('64bit_int_neg', $expected);
        $actual = $this->cache->get('64bit_int_neg');
        $this->assertEquals($expected, $actual);
         */

        // Test Setting and Getting floating point numbers (floats shouldn't be compared directly in normal conditions)
        $expected = 0.0;
        $this->cache->set('float_zero', $expected);
        $actual = $this->cache->get('float_zero');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = 1.234;
        $this->cache->set('float_small_pos', $expected);
        $actual = $this->cache->get('float_small_pos');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = -1.234;
        $this->cache->set('float_small_neg', $expected);
        $actual = $this->cache->get('float_small_neg');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = 1.2e3;
        $this->cache->set('float_scientific_pos', $expected);
        $actual = $this->cache->get('float_scientific_pos');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = -1.2e3;
        $this->cache->set('float_scientific_neg', $expected);
        $actual = $this->cache->get('float_scientific_neg');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = 7E-10;
        $this->cache->set('float_exponent_pos', $expected);
        $actual = $this->cache->get('float_exponent_pos');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        $expected = -7E-10;
        $this->cache->set('float_exponent_neg', $expected);
        $actual = $this->cache->get('float_exponent_neg');
        $this->assertTrue(abs($expected-$actual) < 0.00000000001);

        // Test Setting and Getting strings
        $expected = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
        $this->cache->set('ascii_string', $expected);
        $actual = $this->cache->get('ascii_string');
        $this->assertEquals($expected, $actual);

        // Test Setting and Getting unicode strings
        $expected = mb_convert_encoding(
            '&#x20;&#x21;&#x22;&#x23;&#x24;&#x25;&#x26;&#x27;&#x28;&#x29;&#x2a;&#x2b;&#x2c;&#x2d;&#x2e;&#x2f;&#x30;&#x31;' .
            '&#x32;&#x33;&#x34;&#x35;&#x36;&#x37;&#x38;&#x39;&#x3a;&#x3b;&#x3c;&#x3d;&#x3e;&#x3f;&#x40;&#x41;&#x42;&#x43;' .
            '&#x44;&#x45;&#x46;&#x47;&#x48;&#x49;&#x4a;&#x4b;&#x4c;&#x4d;&#x4e;&#x4f;&#x50;&#x51;&#x52;&#x53;&#x54;&#x55;' .
            '&#x56;&#x57;&#x58;&#x59;&#x5a;&#x5b;&#x5c;&#x5d;&#x5e;&#x5f;&#x60;&#x61;&#x62;&#x63;&#x64;&#x65;&#x66;&#x67;' .
            '&#x68;&#x69;&#x6a;&#x6b;&#x6c;&#x6d;&#x6e;&#x6f;&#x70;&#x71;&#x72;&#x73;&#x74;&#x75;&#x76;&#x77;&#x78;&#x79;' .
            '&#x7a;&#x7b;&#x7c;&#x7d;&#x7e;', 'UTF-8', 'HTML-ENTITIES');
        $this->cache->set('unicode_string', $expected);
        $actual = $this->cache->get('unicode_string');
        $this->assertEquals($expected, $actual);

        // Test Setting and Getting arrays
        $expected = array(
            'inner_array' => array(0 => 'Test Setting', '1' => 'Some string', 'inner_inner_array' => array(10, 20, 30, 40)),
            10 => 'Element 10',
            'string' => " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~"
        );
        $this->cache->set('array', $expected);
        $actual = $this->cache->get('array');
        $this->assertEquals($expected, $actual);

        // Test setting a weird key
        $expected = 'Some string';
        $key = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
        $this->cache->set($key, $expected);
        $actual = $this->cache->get($key);
        $this->assertEquals($expected, $actual);

        // Test setting a blank key
        $expected = null;
        $result = $this->cache->set('', $expected);
        $actual = $this->cache->get('');
        $this->assertNull($actual);
        $this->assertFalse($result);

        // Test deleting a blank key
        $actual = $this->cache->delete('');
        $this->assertFalse($actual);

        // Test deleting a non existent
        $actual = $this->cache->delete('non-existent-key');
        $this->assertFalse($actual);

        // Test incrementing a blank key
        $actual = $this->cache->increment('');
        $this->assertEquals(0, $actual);

        // Test decrementing a blank key
        $actual = $this->cache->decrement('');
        $this->assertEquals(0, $actual);

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

        $this->cache->setMulti($set_keys);
        $actual = $this->cache->getMulti($default_keys);
        $this->assertEquals($expected, $actual);

        // Test using specified timeout value
        $this->cache->setMulti($set_keys, 1440);
        $actual = $this->cache->getMulti($default_keys);
        $this->assertEquals($expected, $actual);

        // Test setting multiple keys with empty key array
        $expected = false;
        $actual = $this->cache->setMulti(array());
        $this->assertEquals($expected, $actual);

        // Test trying to get multiple keys with empty key array
        $expected = array();
        $actual = $this->cache->getMulti($expected);
        $this->assertEquals($expected, $actual);

        // Test trying to get multiple keys with non existent keys only
        $expected = array('non-existent-key' => 'default');
        $actual = $this->cache->getMulti($expected);
        $this->assertEquals($expected, $actual);

        // Test incrementing an integer
        $expected = 12;
        $this->cache->increment('inc_int', 10);
        $this->cache->increment('inc_int');
        $actual = $this->cache->increment('inc_int');
        $this->assertEquals($expected, $actual);

        /*
        // Test incrementing a float (Some caching systems does not accept floats as incrementals)
        $expected = 12.5;
        $this->cache->increment('inc_float', 10.5);
        $this->cache->increment('inc_float');
        $actual = $this->cache->increment('inc_float');
        $this->assertEquals($expected, $actual);
         */

        // Test decrementing a non existing value
        $expected = 0;
        $this->cache->decrement('dec_int_a', 10);
        $actual = $this->cache->decrement('dec_int_a');
        $this->assertEquals($expected, $actual);

        // Test decrementing an existing value
        $expected = 5;
        $this->cache->set('dec_int_b', 10);
        $actual = $this->cache->decrement('dec_int_b', 5);
        $this->assertEquals($expected, $actual);

        /*
        // Test decrementing a float (Some caching systems does not accept floats as decrementals)
        $expected = -12.5;
        $this->cache->decrement('dec_float', 10.5);
        $this->cache->decrement('dec_float');
        $actual = $this->cache->decrement('dec_float');
        $this->assertEquals($expected, $actual);
         */

        // Test deleting a key
        $expected = null;
        $this->cache->set('true_value', "is true");
        $result = $this->cache->delete('true_value');
        $actual = $this->cache->get('true_value');
        $this->assertTrue($result);
        $this->assertNull($actual);

        // Test getting the default value if the key does not exist
        $expected = 'default';
        $actual = $this->cache->get('non-existent-key', $expected);
        $this->assertEquals($expected, $actual);

        // Test key expiration using global cache lifetime setting
        $expected = null;
        $lifetime = $this->cache->lifetime; // store current lifetime value
        $this->cache->lifetime = -1; // 0 Seconds
        $this->cache->set('expire-key', 'alive');
        sleep(1);
        $actual = $this->cache->get('expire-key');
        $this->assertNull($actual);
        $this->cache->lifetime = $lifetime; // Restore previous lifetime value

        // Final Test: Test Flush function (Removes all stored entries)
        $this->cache->flush();
        $actual = $this->cache->get('ascii_string');
        $this->assertNull($actual);
    }
}