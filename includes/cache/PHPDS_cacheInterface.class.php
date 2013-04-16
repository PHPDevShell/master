<?php
interface PHPDS_cacheInterface
{
    /**
     * Attempts to starts the cache.
     *
     * @return bool Returns true if all ok.
     * @throws PHPDS_cacheException
     */
    public function start();
    /**
     * Writes a value to the cache. Values with empty key's will not be written to the cache.
     *
     * @param string $key       Name of the key
     * @param mixed  $value     Value to store
     * @param int    $lifetime  The lifetime of the value in seconds (Ignored)
     * @return bool True if the data was written successfully
     */
    public function set($key, $value, $lifetime = null);
    /**
     * Writes multiple values to the cache. Values with empty key's will not be written to the cache.
     *
     * @param array $items     Values to store
     * @param int   $lifetime  The lifetime of the value in seconds (Ignored)
     * @return bool True if the data was written successfully
     */
    public function setMulti($items, $lifetime = null);
    /**
     * Reads a value from the cache.
     *
     * @param string $key      Name of the key
     * @param mixed  $default  Default value if the key is not set
     * @return mixed Returned data
     */
    public function get($key, $default = null);
    /**
     * Reads multiple values from the cache.
     *
     * @param array $items  Name of the keys and their default values in the format array('key' => 'default')
     * @return mixed Returned data, False if failure
     */
    public function getMulti($items);
    /**
     * Increments an integer value in the cache.
     *
     * @param string $key           Name of the key
     * @param int    $increment_by  By how much to increment the number (default = 1)
     * @return int New value of the incremented number
     */
    public function increment($key, $increment_by = 1);
    /**
     * Decrements an integer value in the cache. For consistency with other cache systems, the value
     * can not be decremented to a negative value and will return 0 when attempting to do so.
     *
     * @param string $key           Name of the key
     * @param int    $decrement_by  By how much to decrement the number (default = 1)
     * @return int New value of the decremented number
     */
    public function decrement($key, $decrement_by = 1);
    /**
     * Deletes a value from the cache.
     *
     * @param string $key  Name of the key
     * @return bool True if successful
     */
    public function delete($key);
    /**
     * Completely clears the cache.
     *
     * @param int $delay  Time to wait before the cache is flushed. (Ignored)
     * @return bool True if successful
     */
    public function flush($delay = 0);
}