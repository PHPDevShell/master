<?php

class PHPDS_cache extends PHPDS_dependant
{
    public $connector;

    /**
     * Does the connection to the cache server.
     */
    public function connectCacheServer()
    {
        $configuration = $this->configuration;

        // Get cache configuration.
        $conf['cache_refresh_intervals'] = $configuration['cache_refresh_intervals'];

        // Assign configuration arrays.
        if ($configuration['cache_type'] != 'PHPDS_sessionCache') {
            $conf['cache_host']           = $configuration['cache_host'];
            $conf['cache_port']           = $configuration['cache_port'];
            $conf['cache_persistent']     = $configuration['cache_persistent'];
            $conf['cache_weight']         = $configuration['cache_weight'];
            $conf['cache_timeout']        = $configuration['cache_timeout'];
            $conf['cache_retry_interval'] = $configuration['cache_retry_interval'];
            $conf['cache_status']         = $configuration['cache_status'];
        }

        // Load Cache Class.
        require_once 'cache/' . $configuration['cache_type'] . '.inc.php';
        $this->connector = new $configuration['cache_type'];

        // Check connection type.
        $this->connector->connectCacheServer($conf);
    }

    /**
     * Writes new data to cache.
     *
     * @param string        $unique_key
     * @param mixed         $cache_data
     * @param boolean       $compress
     * @param int|boolean   $timeout
     */
    public function cacheWrite($unique_key, $cache_data, $compress = false, $timeout = false)
    {
        // Check caching type.
        $this->connector->cacheWrite($unique_key, $cache_data, $compress, $timeout);
    }

    /**
     * Return existing cache result to required item.
     * @param mixed $unique_key
     * @return mixed
     */
    public function cacheRead($unique_key)
    {
        return $this->connector->cacheRead($unique_key);
    }

    /**
     * Clear specific or all cache memory.
     * @param mixed $unique_key
     */
    public function cacheClear($unique_key = false)
    {
        $this->connector->cacheClear($unique_key);
    }

    /**
     * Checks if we have an empty cache container.
     * @param mixed $unique_key
     * @return boolean
     */
    public function cacheEmpty($unique_key)
    {
        return $this->connector->cacheEmpty($unique_key);
    }
}