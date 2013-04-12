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

class EMCNotAvailableException extends Exception {}
class EMCNotStartedException extends Exception {
    public function __construct($message = "", $code = 0 , $previous = null) {
        parent::__construct($message, $code, $previous);
        $message = __s("Cache system not started properly. MCCache::start() not called or failed to start cache properly.");
    }
};

// Implements a memcached wrapper
class PHPDS_memCached {
    public $config;
    public $enabled = False;
    public $lifetime = 1440;
    public $memcached = null;
    public $test_mode = false;
    public $started = false;

    /**
    * Class constructor.
    * @param array $config Possible Options:
    *   config['cache_enabled']: default = False
    *   config['cache_mc_server']: default = ''
    *   config['cache_mc_port']: default = 11211
    *   config['cache_mc_lifetime']: default = 1440
    */
    public function __construct($config = array()) {
        $this->config = $config;
    }

    /**
    * Starts the cache. Will throw an exception if memcached is not installed or enabled or if an invalid configuration was specified.
    *
    * @return bool Returns true if all ok.
    */
    public function start() {
        $result = false;

        // Is session handling enabled?
        $this->enabled = !isset($this->config['cache_enabled']) ? True: $this->config['cache_enabled'];

        if ($this->enabled && !$this->memcached) {
            // Set the default lifetime
            $this->lifetime = empty($this->config['cache_mc_lifetime']) ? 1440: $this->config['cache_mc_lifetime'];

            if (!empty($this->config['cache_mc_server']) && extension_loaded('memcached')  && !$this->test_mode) {
                $port = empty($this->config['cache_mc_port']) ? 11211: $this->config['cache_mc_port'];
                $this->memcached = new Memcached();
                $this->memcached->addServer($this->config['cache_mc_server'], $port);
                $result = true;
                $this->started = true;
            } else {
                throw new EMCNotAvailableException(__s("Unable to start cache system, no memcached configuration supplied or memcached not installed."));
            }
        } else {
            $result = true;
            $this->started = true;
        }
        return $result;
    }

    /**
    * Writes a value to the cache. Note that the key will be converted to a valid memcached key if the function finds any invalid characters.
    * Values with empty key's will not be written to the cache.
    *
    * @param $key string Name of the key
    * @param $value mixed Value to store
    * @param $lifetime int The lifetime of the value in seconds, will be cleared once the lifetime is reached. If lifetime
    *                      is not specified the default will be used (as per configuration).
    * @return bool True if the data was written successfully
    */
    public function set($key, $value, $lifetime = null) {
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            // Converts the key to a valid memcached key. This is very important since certain characters
            // such as spaces will hang the PHP client!!! :O
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                if (isset($lifetime)) {
                    return $this->memcached->set($key, $value, time() + $lifetime);
                } else {
                    return $this->memcached->set($key, $value, time() + $this->lifetime);
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
    * Writes multiple values to the cache. Note that the keys will be converted to valid memcached keys if the function finds any invalid characters.
    * Values with empty key's will not be written to the cache.
    *
    * @param $items array Values to store
    * @param $lifetime int The lifetime of the value in seconds, will be cleared once the lifetime is reached. If lifetime
    *                      is not specified the default will be used (as per configuration).
    * @return bool True if the data was written successfully
    */
    public function setMulti($items, $lifetime = null) {
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            $result = false;

            // Converts the keys to valid memcached keys. This is very important since certain characters
            // such as spaces will hang the PHP client!!! :O
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
                    $result = $this->memcached->setMulti($tmp_items, time() + $lifetime);
                } else {
                    $result = $this->memcached->setMulti($tmp_items, time() + $this->lifetime);
                }
            }

            return $result;
        } else {
            return true;
        }
    }

    /**
    * Reads a value from the cache. Note that the key will be converted to a valid memcached key if the function finds any invalid characters.
    *
    * @param $key string Name of the key
    * @param $default mixed Default value if the key is not set
    * @return mixed Returned data
    */
    public function get($key, $default = null) {
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                $result = $this->memcached->get($key);
                if ($this->memcached->getResultCode() == Memcached::RES_SUCCESS) {
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
    * Reads multiple values from the cache. Note that the keys will be converted to valid memcached keys if the function finds any invalid characters.
    *
    * @param $items array Name of the keys and their default values in the format array('key' => 'default')
    * @return mixed Returned data, False if failure
    */
    public function getMulti($items) {
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            $result = $items;

            // Converts the keys to valid memcached keys. This is very important since certain characters
            // such as spaces will hang the PHP client!!! :O
            $tmp_keys = array();
            foreach ($items as $key => $default) {
                $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
                if (!empty($key)) {
                    $tmp_keys[] = $key;
                }
            }

            // Now get the items and set the default values for the items that are empty
            if (count($tmp_keys) > 0) {
                $results = $this->memcached->getMulti($tmp_keys);
                if (is_array($results)) {
                    // Make sure we include the default keys also by merging the defaults array with the
                    // results array
                    $result = array_merge($items, $results);
                }
            } else {
                $result = array();
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
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                $result = $this->memcached->increment($key, $increment_by);
                if ($this->memcached->getResultCode() == Memcached::RES_SUCCESS) {
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
    * Decrements an integer value in the cache. Value can not go into negatives and will return 0 if it happens.
    *
    * @param $key string Name of the key
    * @param $decrement_by int By how much to decrement the number (default = 1)
    * @return int New value of the decremented number
    */
    public function decrement($key, $decrement_by = 1) {
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                $result = $this->memcached->decrement($key, $decrement_by);
                if ($this->memcached->getResultCode() == Memcached::RES_SUCCESS) {
                    return $result;
                } else {
                    $this->set($key, 0);
                    return 0;
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
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            $key = preg_replace("/[^A-Za-z0-9]/", "", $key);
            if (!empty($key)) {
                return $this->memcached->delete($key);
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
    * @param $delay int Time to wait for the cache is flushed.
    * @return bool True if successfull
    */
    public function flush($delay = 0) {
        if (!$this->started) throw new EMCNotStartedException();

        if ($this->enabled) {
            return $this->memcached->flush($delay);
        } else {
            return true;
        }
    }
}