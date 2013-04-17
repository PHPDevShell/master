<?php

/**
 * Class PHPDS_memcached
 * @property Memcached $memcached
 */
class PHPDS_memcached extends PHPDS_dependant implements PHPDS_cacheInterface {
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
     * Memcached instance.
     * @var resource
     */
    public $memcached = null;

    /**
     * Attempts to starts the cache.
     *
     * @return bool Returns true if all ok.
     * @throws PHPDS_cacheException
     */
    public function start() {
        // Is session handling enabled?
        $this->enabled = !isset($config['cache_refresh_intervals']) ? true : $config['cache_refresh_intervals'];

        if ($this->enabled && !$this->memcached) {
            // Set the default lifetime
            $this->lifetime = empty($config['cache_refresh_intervals']) ? 1440 : $config['cache_refresh_intervals'];

            if (empty($this->configuration['memcached_cacheserver']))
                throw new PHPDS_cacheException('Memcached configuration not set in config file.');

            if (extension_loaded('memcached') && !$this->testMode) {
                try {
                    $this->memcached = new Memcached();
                    foreach ($this->configuration['memcached_cacheserver'] as $server) {
                        $this->memcached->addServer($server['host'], $server['port'], $server['weight']);
                    }
                } catch (Exception $e) {
                    throw new PHPDS_cacheException('a Memcached configuration error occurred', 0, $e);
                }
                $result = true;
                $this->started = true;
            } else {
                throw new  PHPDS_cacheException("Extention memcached is reported to not be loaded for use in PHP,
                please make sure you have enabled the memcached extention specifically (remember extentions
                which are different is independent named as memcache and memcached) or consider APC rather.");
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
     * Writes multiple values to the cache. Values with empty key's will not be written to the cache.
     *
     * @param array $items     Values to store
     * @param int   $lifetime  The lifetime of the value in seconds (Ignored)
     * @return bool True if the data was written successfully
     */
    public function setMulti($items, $lifetime = null) {
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
     * Reads multiple values from the cache.
     *
     * @param array $items  Name of the keys and their default values in the format array('key' => 'default')
     * @return mixed Returned data, False if failure
     */
    public function getMulti($items) {
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
     * @param string $key           Name of the key
     * @param int    $increment_by  By how much to increment the number (default = 1)
     * @return int New value of the incremented number
     */
    public function increment($key, $increment_by = 1) {
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
     * @param string $key  Name of the key
     * @return bool True if successful
     */
    public function delete($key) {
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
     * @param int $delay  Time to wait before the cache is flushed. (Ignored)
     * @return bool True if successful
     */
    public function flush($delay = 0) {
        if ($this->enabled) {
            return $this->memcached->flush($delay);
        } else {
            return true;
        }
    }
}