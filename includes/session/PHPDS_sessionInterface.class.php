<?php

interface PHPDS_sessionInterface {
    /**
     * Starts a session. See: http://php.net/manual/en/function.session-start.php
     *
     * @return bool
     * @throws PHPDS_sessionException
     */
    public function start();
    /**
     * Saves the current session.
     * @return void
     */
    public function save();
    /**
     * Writes a value to the current session
     *
     * @param string $key    Name of the key
     * @param mixed  $value  Value to store
     * @return bool True if the data was written successfully
     */
    public function set($key, $value);
    /**
     * Reads a value from the current session.
     *
     * @param string $key      Name of the key
     * @param mixed  $default  Default value if the key is not set
     * @return mixed Returned data
     */
    public function get($key, $default = null);
    /**
     * Completely destroys a session. Useful when clearing the current user's session completely after a log-out
     *
     * @param  bool $force Force a flush, whether the session is enabled or not
     */
    public function flush($force = false);
}