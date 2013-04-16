<?php

class PHPDS_filecache extends PHPDS_dependant
{
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
     * The cache directory to write in.
     * @var string
     */
    public $writeDir = "";
    /**
     * The extension if to which cache files should be attached to.
     * @var string
     */
    public $cacheFileExt = ".PHPDS_cache";
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
     * Returns a fully qualified filename for the cache entry based on the key name.
     *
     * @param $key string Name of the key
     * @return string
     */
    private function getCacheName($key)
    {
        return $this->writeDir . sha1($key) . $this->cacheFileExt;
    }

    /**
     * Attempts to starts the cache.
     *
     * @return bool Returns true if all ok.
     * @throws PHPDS_cacheException
     */
    public function start()
    {
        $config = $this->configuration;

        // Is caching enabled?
        $this->enabled = !isset($config['cache_refresh_intervals']) ? true : $config['cache_refresh_intervals'];

        if ($this->enabled) {
            // Set the default lifetime
            $this->lifetime = empty($config['cache_refresh_intervals']) ? 1440 : $config['cache_refresh_intervals'];

            if (!empty($config['cache_path'])) {
                $this->writeDir = BASEPATH . $config['cache_path'];
                if (is_dir($this->writeDir) && is_writable($this->writeDir) && !$this->testMode) {
                    $result        = true;
                    $this->started = true;
                } else {
                    throw new PHPDS_cacheException(sprintf(__s("Unable to start cache system, cache directory is not writable. (%s)"), $this->writeDir));
                }
            } else {
                throw new PHPDS_cacheException(__s("Unable to start cache system, cache directory was not specified."));
            }
        } else {
            $this->started = true;
            $result        = true;
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
    public function set($key, $value, $lifetime = null)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                $result     = false;
                $cache_file = $this->getCacheName($key);
                if ($fp = @fopen($cache_file, 'wb')) {
                    if (@flock($fp, LOCK_EX)) {
                        fwrite($fp, serialize($value));
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        @chmod($cache_file, 0777);
                        $result = true;
                    }
                }
                return $result;
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
    public function setMulti($items, $lifetime = null)
    {
        if ($this->enabled) {
            if (count($items) > 0) {
                foreach ($items as $key => $value) {
                    $this->set($key, $value, $lifetime);
                }
                return true;
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
    public function get($key, $default = null)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                $result     = $default;
                $cache_file = $this->getCacheName($key);

                if (@file_exists($cache_file)) {
                    if (filemtime($cache_file) < (time() - $this->lifetime)) {
                        $this->delete($key);
                    } else {
                        if ($fp = @fopen($cache_file, 'rb')) {
                            flock($fp, LOCK_SH);
                            $result = null;
                            if (filesize($cache_file) > 0) {
                                $result = unserialize(fread($fp, filesize($cache_file)));
                            }
                            flock($fp, LOCK_UN);
                            fclose($fp);
                        }
                    }
                }
                return $result;
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
    public function getMulti($items)
    {
        if ($this->enabled) {
            $results = array();
            foreach ($items as $key => $default) {
                $result = $this->get($key);
                if (isset($result)) {
                    $results[$key] = $result;
                } else {
                    $results[$key] = $default;
                }
            }
            return $results;
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
    public function increment($key, $increment_by = 1)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                $value = $this->get($key);
                if (is_int($value) || is_float($value)) {
                    $value += $increment_by;
                } else {
                    $value = $increment_by;
                }

                $this->set($key, $value);
                return $value;
            } else {
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
    public function decrement($key, $decrement_by = 1)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                $value = $this->get($key);
                if (is_int($value) || is_float($value)) {
                    $value -= $decrement_by;
                } else {
                    $value = -$decrement_by;
                }

                if ($value < 0) $value = 0;
                $this->set($key, $value);
                return $value;
            } else {
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
    public function delete($key)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                $cache_file = $this->getCacheName($key);
                if (file_exists($cache_file)) {
                    @unlink($cache_file);
                    return true;
                } else {
                    return false;
                }
            } else {
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
    public function flush($delay = 0)
    {
        if ($this->enabled) {
            $cache_files = glob($this->writeDir . '*' . $this->cacheFileExt);
            foreach ($cache_files as $filename) {
                @unlink($filename);
            }
        }
        return true;
    }
}