<?php

class PHPDS_apcSession extends PHPDS_dependant implements PHPDS_sessionInterface
{
    /**
     * Should session be enabled?
     * @var bool
     */
    public $enabled = false;
    /**
     * At what intervals should session be refreshed.
     * @var int
     */
    public $lifetime = 1440;
    /**
     * Holds latest fresh session id.
     * @var int
     */
    public $sessionId = 0;
    /**
     * Hold previous session id before refresh.
     * @var int
     */
    public $oldSessionId = 0;
    /**
     * Should session be in test mode.
     * @var bool
     */
    public $testMode = false;
    /**
     * Auto property to tell rest of system if cache system was started.
     * @var bool
     */
    public $started = false;

    /**
     * Destructor
     */
    public function __destruct()
    {
        session_write_close();
    }

    /**
     * Starts a session. See: http://php.net/manual/en/function.session-start.php
     *
     * @return bool
     * @throws PHPDS_sessionException
     */
    public function start()
    {
        $result = false;
        $config = $this->configuration;

        // Is session handling enabled?
        $this->enabled = !isset($config['session_life']) ? true : $config['session_life'];

        if ($this->enabled) {
            if (!empty($config['session_cfg'])) {
                foreach ($config['session_cfg'] as $skey => $svalue) {
                    ini_set($skey, $svalue);
                }
            }
            $this->lifetime = (!empty($config['session_life']))
                ? $config['session_life'] : ini_get('session.gc_maxlifetime');

            if (!$this->started) {
                if (!isset($_SESSION)) {
                    // Replace the default session handler functions
                    session_set_save_handler(
                        array($this, "open"),
                        array($this, "close"),
                        array($this, "read"),
                        array($this, "write"),
                        array($this, "destroy"),
                        array($this, "gc")
                    );

                    // Make sure that the session is closed when all objects are free'd
                    register_shutdown_function('session_write_close');
                    $result = session_start();

                    // When protect is enabled we make sure to regenerate a new session id
                    $protect = empty($config['session_protect']) ? true : $config['session_protect'];
                    if ($protect && !isset($_SESSION['PHPDS_session'])) {
                        // Basic session hijacking prevention
                        $this->oldSessionId = session_id();
                        session_regenerate_id();
                        $this->sessionId           = session_id();
                        $_SESSION['PHPDS_session'] = true;
                    }
                }
            } else {
                $result = true;
            }
        } else {
            $result = true;
        }
        return $result;
    }

    /**
     * Saves the current session.
     *
     * @return void
     */
    public function save()
    {
        session_write_close();
    }

    /**
     * Writes a value to the current session
     *
     * @param string $key    Name of the key
     * @param mixed  $value  Value to store
     * @return bool True if the data was written successfully
     */
    public function set($key, $value)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                $_SESSION[$key] = $value;
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Reads a value from the current session.
     *
     * @param string $key      Name of the key
     * @param mixed  $default  Default value if the key is not set
     * @return mixed Returned data
     */
    public function get($key, $default = null)
    {
        if ($this->enabled) {
            if (!empty($key)) {
                return (isset($_SESSION[$key])) ? $_SESSION[$key] : $default;
            } else {
                return $default;
            }
        } else {
            return $default;
        }
    }

    /**
     * Completely destroys a session. Useful when clearing the current user's session completely after a log-out
     *
     * @param bool $force Force a flush, whether the session is enabled or not
     */
    public function flush($force = false)
    {
        if ($this->enabled || $force) {
            @session_unset();
            @session_destroy();
            @session_write_close();
            @setcookie(session_name(), '', 0, '/');
            @session_regenerate_id(true);
            unset($_SESSION);
        }
    }

    /**
     * Open the session handler
     *
     * @param string $savePath
     * @param string $sessionName
     * @return bool True if everything succeed
     * @throws PHPDS_sessionException
     */
    public function open($savePath, $sessionName)
    {
        if ($this->enabled) {
            if (extension_loaded('apc') && ini_get('apc.enabled') && !$this->testMode) {
                $this->started = true;
            } else {
                throw new PHPDS_sessionException("Unable to start APC system.
                    APC caching is not installed or is currently disabled in your PHP config.");
            }
        }
        return true;
    }

    /**
     * Called when the session is started, reads the data from apc based on the session id
     *
     * @param string $id The Session ID
     * @return string The session data that is currently stored
     */
    public function read($id)
    {
        if ($this->enabled && $this->started) {
            $tmp_session = $_SESSION;
            $success     = false;
            $_SESSION    = apc_fetch("sessions/{$id}", $success);
            if ($success) {
                if (isset($_SESSION) && !empty($_SESSION)) {
                    $new_data = session_encode();
                    $_SESSION = $tmp_session;
                    return $new_data;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Writes the session data, convert to json before storing. Called when the session needs to be saved and closed.
     *
     * @param string $id   The Session ID
     * @param string $data The data to store, already serialized by PHP
     * @return bool True if apc was able to write the session data
     */
    public function write($id, $data)
    {
        if ($this->enabled) {
            if ($this->started) {
                $tmp_session = $_SESSION;
                session_decode($data);
                $new_data = $_SESSION;
                $_SESSION = $tmp_session;
                return apc_store("sessions/{$id}", $new_data, $this->lifetime);
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Called when a session is destroyed and its data needs to be removed.
     *
     * @param string $id The Session ID
     * @return bool True if apc was able delete the session data successfully
     */
    public function destroy($id)
    {
        if ($this->enabled && $this->started) {
            return apc_delete("sessions/{$id}");
        } else {
            return true;
        }
    }

    /**
     * Close gc
     *
     * @param int $lifetime
     * @return bool Always true
     */
    public function gc($lifetime)
    {
        return true;
    }

    /**
     * Close session
     *
     * @return bool Always true
     */
    public function close()
    {
        return true;
    }

}