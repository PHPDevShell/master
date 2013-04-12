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

class EFCWriteableException extends Exception {};
class EFCNotStartedException extends Exception {
    public function __construct($message = "", $code = 0 , $previous = null) {
        parent::__construct($message, $code, $previous);
        $message = __s("Cache system not started properly. FileCache::start() not called or failed to start cache properly.");
    }
};

// Implements a simple File based cache
class PHPDS_fileCache {
    public $config;
    public $enabled = False;
    public $lifetime = 1440;
    public $write_dir = "";
    public $cache_file_ext = ".fcache";
    public $test_mode = false;
    public $started = false;

    /**
    * Class constructor.
    * @param array $config Possible Options:
    *   config['cache_enabled']: default = False
    *   config['cache_file_root_dir']: default = blank
    *   config['cache_file_write_dir']: default = blank
    *   config['cache_file_lifetime']: default = 1440
    */
    public function __construct($config = array()) {
        $this->config = $config;
    }

    /**
    * Joins two directories together, takes into consideration the OS directory separator.
    *
    * @param $left string The left side of the path
    * @param $right string The right side of the path
    * @param $separator string The separator to use, default is "/"
    * @return string The joined path
    */
    private function joinDirs($left, $right, $separator = DIRECTORY_SEPARATOR) {
        return rtrim($left, $separator) . $separator . ltrim($right, $separator);
    }

    /**
    * Returns a fully qualified filename for the cache entry based on the key name.
    *
    * @param $key string Name of the key
    * @return string
    */
    private function getCacheName($key)
    {
        return $this->write_dir . sha1($key) . $this->cache_file_ext;
    }

    /**
    * Starts the cache. Will throw an exception if the cache directory is not writable or if an invalid configuration was specified.
    *
    * @return bool Returns true if all ok.
    */
    public function start() {
        $result = false;

        // Is caching enabled?
        $this->enabled = !isset($this->config['cache_enabled']) ? True: $this->config['cache_enabled'];

        if ($this->enabled) {
            // Set the default lifetime
            $this->lifetime = empty($this->config['cache_file_lifetime']) ? 1440: $this->config['cache_file_lifetime'];

            if (!empty($this->config['cache_file_write_dir'])) {
                if (!empty($this->config['cache_file_root_dir'])) {
                    $this->write_dir = $this->joinDirs($this->config['cache_file_root_dir'], $this->config['cache_file_write_dir']);
                } else {
                    $this->write_dir = $this->joinDirs($this->config['cache_file_write_dir'], '');
                }

                if (is_dir($this->write_dir) && is_writable($this->write_dir) && !$this->test_mode) {
                    $result = true;
                    $this->started = true;
                } else {
                    throw new EFCWriteableException(sprintf(__s("Unable to start cache system, cache directory is not writable. (%s)"), $this->write_dir));
                }
            } else {
                throw new EFCWriteableException(__s("Unable to start cache system, cache directory was not specified."));
            }
        } else {
            $this->started = true;
            $result = true;
        }

        return $result;
    }

    /**
    * Writes a value to the cache. Values with empty key's will not be written to the cache.
    *
    * @param $key string Name of the key
    * @param $value mixed Value to store
    * @param $lifetime int The lifetime of the value in seconds (Ignored)
    * @return bool True if the data was written successfuly
    */
    public function set($key, $value, $lifetime = null) {
        if (!$this->started) throw new EFCNotStartedException();

        if ($this->enabled) {
            if (!empty($key)) {
                $result = false;
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
    * @param $items array Values to store
    * @param $lifetime int The lifetime of the value in seconds (Ignored)
    * @return bool True if the data was written successfuly
    */
    public function setMulti($items, $lifetime = null) {
        if (!$this->started) throw new EFCNotStartedException();

        if ($this->enabled) {
            if (count($items) > 0) {
                $tmp_items = array();
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
    * @param $key string Name of the key
    * @param $default mixed Default value if the key is not set
    * @return mixed Returned data
    */
    public function get($key, $default = null) {
        if (!$this->started) throw new EFCNotStartedException();

        if ($this->enabled) {
            if (!empty($key)) {
                $result = $default;
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
    * @param $items array Name of the keys and their default values in the format array('key' => 'default')
    * @return mixed Returned data, False if failure
    */
    public function getMulti($items) {
        if (!$this->started) throw new EFCNotStartedException();

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
    * @param $key string Name of the key
    * @param $increment_by int By how much to increment the number (default = 1)
    * @return int New value of the incremented number
    */
    public function increment($key, $increment_by = 1) {
        if (!$this->started) throw new EFCNotStartedException();

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
    * @param $key string Name of the key
    * @param $decrement_by int By how much to decrement the number (default = 1)
    * @return int New value of the decremented number
    */
    public function decrement($key, $decrement_by = 1) {
        if (!$this->started) throw new EFCNotStartedException();

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
    * @return bool True if successful
    */
    public function delete($key) {
        if (!$this->started) throw new EFCNotStartedException();

        if ($this->enabled) {
            if (!empty($key)) {
               $cache_file = $this->getCacheName($key);
                if (file_exists($cache_file))
                {
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
    * @param $delay int Time to wait before the cache is flushed. (Ignored)
    * @return bool True if successful
    */
    public function flush($delay = 0) {
        if (!$this->started) throw new EFCNotStartedException();

        if ($this->enabled) {
            $cache_files = glob($this->write_dir . '*' . $this->cache_file_ext);
            foreach ($cache_files as $filename) {
                @unlink($filename);
            }
        }

        return true;
    }
}