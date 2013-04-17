<?php

class PHPDS_apc extends PHPDS_dependant implements PHPDS_cacheInterface {
    /**
     * Should cache be enabled?
     * @var bool
     */
    public $enabled = false;
    /**
     * At what intervals should cache be refreshed.
     * @var int
     */
    public $lifetime = 1440;
    /**
     * Should cache be in test mode.
     * @var bool
     */
    public $testMode = false;
    /**
     * Auto property to tell rest of system if cache system was started.
     * @var bool
     */
    public $started = false;

    /**
     * Attempts to starts the cache.
     *
     * @return bool Returns true if all ok.
     * @throws PHPDS_cacheException
     */
    public function start() {
        // Is session handling enabled?
        $this->enabled = !isset($config['cache_refresh_intervals']) ? true : $config['cache_refresh_intervals'];

        if ($this->enabled) {
            // Set the default lifetime
            $this->lifetime = empty($config['cache_refresh_intervals']) ? 1440 : $config['cache_refresh_intervals'];

            if (extension_loaded('apc') && ini_get('apc.enabled') && !$this->testMode) {
                $result = true;
                $this->started = true;
            } else {
                throw new PHPDS_cacheException("Unable to start APC cache system.
                    APC caching is not installed or is currently disabled in your PHP config.");
            }
        } else {
            $result = true;
            $this->started = true;
        }

        return $result;
    }

    /**
     * Writes a value to the cache. Values with empty key's will not be written to the cache.
     *
     * @param string $key       Name of the key
     * @param mixed  $value     Value to store
     * @param int    $lifetime  The lifetime of the value in seconds (Ignored)
     * @return bool True if the data was written successfully
     */
    public function set($key, $value, $lifetime = null) {
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
     * Writes multiple values to the cache. Values with empty key's will not be written to the cache.
     *
     * @param array $items     Values to store
     * @param int   $lifetime  The lifetime of the value in seconds (Ignored)
     * @return bool True if the data was written successfully
     */
    public function setMulti($items, $lifetime = null) {
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
     * Reads a value from the cache.
     *
     * @param string $key      Name of the key
     * @param mixed  $default  Default value if the key is not set
     * @return mixed Returned data
     */
    public function get($key, $default = null) {
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
     * Reads multiple values from the cache.
     *
     * @param array $items  Name of the keys and their default values in the format array('key' => 'default')
     * @return mixed Returned data, False if failure
     */
    public function getMulti($items) {
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
     * @param string $key           Name of the key
     * @param int    $increment_by  By how much to increment the number (default = 1)
     * @return int New value of the incremented number
     */
    public function increment($key, $increment_by = 1) {
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
     * Decrements an integer value in the cache. For consistency with other cache systems, the value
     * can not be decremented to a negative value and will return 0 when attempting to do so.
     *
     * @param string $key           Name of the key
     * @param int    $decrement_by  By how much to decrement the number (default = 1)
     * @return int New value of the decremented number
     */
    public function decrement($key, $decrement_by = 1) {
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
     * @param string $key  Name of the key
     * @return bool True if successful
     */
    public function delete($key) {
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
     * @param int $delay  Time to wait before the cache is flushed. (Ignored)
     * @return bool True if successful
     */
    public function flush($delay = 0) {
        if ($this->enabled) {
            apc_clear_cache();
            apc_clear_cache('user');
            apc_clear_cache('opcode');
        }
        return true;
    }
}
