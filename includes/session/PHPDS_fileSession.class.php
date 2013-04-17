<?php

class PHPDS_fileSession extends PHPDS_dependant implements PHPDS_sessionInterface
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
     * The cache directory to write in.
     * @var string
     */
    public $writeDir = '';
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
     * Starts a session. See: http://php.net/manual/en/function.session-start.php
     * @param object $storage Not used with this session manager.
     * @return bool
     * @throws PHPDS_sessionException
     */
    public function start($storage = null)
    {
        $config = $this->configuration;

        // Is session handling enabled?
        $this->enabled = !isset($config['session_life']) ? true : $config['session_life'];

        if ($this->enabled) {
            if (!empty($config['session_path'])) {
                $this->writeDir = BASEPATH . $config['session_path'];
                if (is_dir($this->writeDir) && is_writable($this->writeDir) && !$this->testMode) {
                    if (!$this->started) {
                        if (!empty($config['session_cfg'])) {
                            foreach ($config['session_cfg'] as $skey => $svalue) {
                                ini_set($skey, $svalue);
                            }
                        }
                        $this->lifetime = (!empty($config['session_life']))
                            ? $config['session_life'] : ini_get('session.gc_maxlifetime');

                        // Set our own session write path
                        session_save_path($this->writeDir);

                        // Make sure that the session is closed when all objects are free'd
                        register_shutdown_function('session_write_close');

                        $result        = session_start();
                        $this->started = $result;

                        // Custom session timeout check. We can't rely on the garbage collector
                        // to clear sessions since there is only a probability that it
                        // will be cleared while setting the gc_divisor and gc_probability to a
                        // 1:1 ratio causes to much work for the gc.
                        if (isset($_SESSION['PHPDS_last_activity']) &&
                            (time() - $_SESSION['PHPDS_last_activity'] > $this->lifetime)
                        ) {
                            $this->flush(true);
                            $result        = session_start();
                            $this->started = $result;
                        }
                        $_SESSION['PHPDS_last_activity'] = time(); // update last activity time stamp

                        // When protect is enabled we make sure to regenerate a new session id
                        $protect = empty($config['session_protect']) ? true : $config['session_protect'];
                        if ($protect && !isset($_SESSION['PHPDS_session'])) {
                            // Basic session hijacking prevention
                            $this->oldSessionId = session_id();
                            session_regenerate_id();
                            $this->sessionId           = session_id();
                            $_SESSION['PHPDS_session'] = true;
                        }
                    } else {
                        $result        = true;
                        $this->started = $result;
                    }
                } else {
                    throw new PHPDS_sessionException(
                        sprintf("Session directory is not writable. (%s)"), $this->writeDir);
                }
            } else {
                throw new PHPDS_sessionException("Session directory was not specified.");
            }
        } else {
            $this->started = true;
            $result        = true;
        }
        return $result;
    }

    /**
     * Saves the current session.
     * @return void
     */
    public function save()
    {
        if ($this->enabled) {
            session_write_close();
        }
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
     * @param  bool $force Force a flush, whether the session is enabled or not
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
}