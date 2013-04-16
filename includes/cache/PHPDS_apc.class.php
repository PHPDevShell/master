<?php
/**
 * Delphex Web Framework Core
 *
 * @link http://www.delphexonline.com
 * @copyright Copyright (C) 2012 Delphex Technologies CC, All rights reserved.
 * @author Don Schoeman
 *
 * Copyright notice: See readme/notice
 * By using DWF you agree to notice and license, if you dont agree to this notice/license you are not allowed to use DWF.
 *
 */

class EAPCNotAvailableException extends Exception {}
class EAPCNotStartedException extends Exception {
    public function __construct($message = "", $code = 0 , $previous = null) {
        parent::__construct($message, $code, $previous);
        $message = __s("Cache system not started properly. APCCache::start() not called or failed to start cache properly.");
    }
};

// Implements an APC Cache wrapper
class PHPDS_apc {
    public $config;
    public $enabled = False;
    public $lifetime = 1440;
    public $test_mode = false;
    public $started = false;

    /**
    * Class constructor.
    * @param array $config Possible Options:
    *   config['cache_enabled']: default = False
    *   config['cache_apc_lifetime']: default = 1440
    */
    public function __construct($config = array()) {
        $this->config = $config;
    }

    /**
    * Starts the cache. Will throw an exception if an invalid configuration was specified.
    *
    * @return bool Returns true if all ok.
    */
    public function start() {
        $result = false;

        // Is session handling enabled?
        $this->enabled = !isset($this->config['cache_enabled']) ? True: $this->config['cache_enabled'];

        if ($this->enabled) {
            // Set the default lifetime
            $this->lifetime = empty($this->config['cache_apc_lifetime']) ? 1440: $this->config['cache_apc_lifetime'];

            if (extension_loaded('apc') && ini_get('apc.enabled') && !$this->test_mode) {
                $result = true;
                $this->started = true;
            } else {
                throw new EAPCNotAvailableException(__s("Unable to start cache system. APC caching is not installed or is currently disabled."));
            }
        } else {
            $result = true;
            $this->started = true;
        }

        return $result;
    }

    /**
    * Writes a value to the cache. Note that the key will be converted to a valid key if the function finds any invalid characters.
    * Values with empty key's will not be written to the cache.
    *
    * @param $key string Name of the key
    * @param $value mixed Value to store
    * @param $lifetime int The lifetime of the value in seconds, will be cleared once the lifetime is reached. If lifetime
    *                      is not specified the default will be used (as per configuration).
    * @return bool True if the data was written successfully
    */
    public function set($key, $value, $lifetime = null) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            // Converts the key to a valid memcached key. This is very important since certain characters
            // such as spaces will hang the PHP client!!! :O
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                if (isset($lifetime)) {
                    return apc_store($key, $value, $lifetime);
                } else {
                    return apc_store($key, $value, $this->lifetime);
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
    * Writes multiple values to the cache. Note that the keys will be converted to valid keys if the function finds any invalid characters.
    * Values with empty key's will not be written to the cache.
    *
    * @param $items array Values to store
    * @param $lifetime int The lifetime of the value in seconds, will be cleared once the lifetime is reached. If lifetime
    *                      is not specified the default will be used (as per configuration).
    * @return bool True if the data was written successfully
    */
    public function setMulti($items, $lifetime = null) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            $tmp_items = array();
            foreach ($items as $key => $value) {
                $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
                if (!empty($key)) {
                    $tmp_items[$key] = $value;
                }
            }

            // Now store the items
            if (count($tmp_items) > 0) {
                if (isset($lifetime)) {
                    return apc_store($tmp_items, null, $lifetime);
                } else {
                    return apc_store($tmp_items, null, $this->lifetime);
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
    * Reads a value from the cache. Note that the key will be converted to a valid key if the function finds any invalid characters.
    * Values with empty key's will not be written to the cache.
    *
    * @param $key string Name of the key
    * @param $default mixed Default value if the key is not set
    * @return mixed Returned data
    */
    public function get($key, $default = null) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                $success = false;
                $result = apc_fetch($key, $success);
                if ($success) {
                    return $result;
                } else {
                    return $default;
                }
            } else {
                return $default;
            }
        } else {
            return $default;
        }
    }

    /**
    * Reads multiple values from the cache. Note that the keys will be converted to valid keys if the function finds any invalid characters.
    *
    * @param $items array Name of the keys and their default values in the format array('key' => 'default')
    * @return array Returned data, False if failure
    */
    public function getMulti($items) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            $result = array();
            foreach ($items as $key => $default) {
                $fixed_key = preg_replace("/[^A-Za-z0-9]/", "", $key);
                if (!empty($fixed_key)) {
                    $success = false;
                    $value = apc_fetch($fixed_key, $success);
                    if ($success && isset($value)) {
                        $result[$key] = $value;
                    } else {
                        $result[$key] = $default;
                    }
                }
            }

            return $result;
        } else {
            return $items;
        }
    }

    /**
    * Increments an integer value in the cache.
    *
    * @param $key string Name of the key
    * @param $increment_by int By how much to increment the number (default = 1)
    * @return int New value of the incremented number
    */
    public function increment($key, $increment_by = 1) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                // APC does not automatically create the key if it doesn't exist. We could use apc_exists() to check
                // if it exists first, but apc_exists() is only available for PECL APC v3.1.4 and onwards. So we instead
                // check for the success flag.
                $success = false;
                $result = apc_inc($key, $increment_by, $success);
                if ($success) {
                    return $result;
                } else {
                    $this->set($key, $increment_by);
                    return $increment_by;
                }
            } else{
                return 0;
            }
        } else {
            return 0;
        }
    }

    /**
    * Decrements an integer value in the cache.  For consistency with other cache systems, the value
    * can not be decremented to a negative value and will return 0 when attempting to do so.
    *
    * @param $key string Name of the key
    * @param $decrement_by int By how much to decrement the number (default = 1)
    * @return int New value of the decremented number
    */
    public function decrement($key, $decrement_by = 1) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                $result = $this->get($key, 0);
                if ($result - $decrement_by < 0) {
                    $this->set($key, 0);
                    return 0;
                } else {
                    // APC does not automatically create the key if it doesn't exist. We could use apc_exists() to check
                    // if it exists first, but apc_exists() is only available for PECL APC v3.1.4 and onwards. So we instead
                    // check for the success flag.
                    $this->set($key, $result - $decrement_by);
                    return $result - $decrement_by;
                }
            } else{
                return 0;
            }
        } else {
            return 0;
        }
    }

    /**
    * Deletes a value from the cache.
    *
    * @param $key string Name of the key
    * @return bool True if successfull
    */
    public function delete($key) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                $result = apc_delete($key);
                return $result;
            } else{
                return false;
            }
        } else {
            return true;
        }
    }

    /**
    * Completely clears the cache.
    *
    * @param $delay int Time to wait for the cache is flushed. (Ignored)
    * @return bool True if successfull
    */
    public function flush($delay = 0) {
        if (!$this->started) throw new EAPCNotStartedException();

        if ($this->enabled) {
            apc_clear_cache();
            apc_clear_cache('user');
            apc_clear_cache('opcode');
        } else {
            return true;
        }
    }
}
