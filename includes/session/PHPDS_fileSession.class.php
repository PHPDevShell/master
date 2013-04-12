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

class EFSWriteableException extends Exception {};

class EFSNotStartedException extends Exception {
    public function __construct($message = "", $code = 0 , $previous = null) {
        parent::__construct($message, $code, $previous);
        $message = __s("Session not started properly. FileSession::start() not called or failed to start session properly.");
    }
};

// Implements a standard PHP file based session layer
class PHPDS_fileSession implements SessionIntf {
    public $config;
    public $session_id = 0;
    public $old_session_id = 0;
    public $enabled = false;
    public $started = false;
    public $write_dir = "";
    public $test_mode = false;
    public $lifetime = 1440;

    /**
    * Class constructor.
    * @param array $config Possible Options:
    *   config['session_enabled']: default = False
    *   config['session_root_dir']: default = blank
    *   config['session_write_dir']: default = blank
    *   config['session_protect']: default = True
    *   config['session_lifetime']: default = 1440
    *   config['session_gc_probability']: default = 1
    *   config['session_gc_divisor']: default = 100
    *   config['session_gc_maxlifetime']: default = 1440
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
    * Starts a session. See: http://php.net/manual/en/function.session-start.php
    * @param object $storage Not used with this session manager.
    * @return bool
    */
    public function start($storage = null) {
        $result = false;

        // Is session handling enabled?
        $this->enabled = !isset($this->config['session_enabled']) ? True: $this->config['session_enabled'];
        if ($this->enabled) {
            if (!empty($this->config['session_write_dir'])) {
                if (!empty($this->config['session_root_dir'])) {
                    $this->write_dir = $this->joinDirs($this->config['session_root_dir'], $this->config['session_write_dir']);
                } else {
                    $this->write_dir = $this->config['session_write_dir'];
                }

                if (is_dir($this->write_dir) && is_writable($this->write_dir) && !$this->test_mode) {
                    if (!$this->started) {
                        if (!empty($this->config['session_gc_probability'])) ini_set('session.gc_probability', $this->config['session_gc_probability']);
                        if (!empty($this->config['session_gc_divisor'])) ini_set('session.gc_divisor', $this->config['session_gc_divisor']);
                        if (!empty($this->config['session_gc_maxlifetime'])) ini_set('session.gc_maxlifetime', $this->config['session_gc_maxlifetime']);

                        $this->lifetime = (!empty($this->config['session_lifetime'])) ? $this->config['session_lifetime']: ini_get('session.gc_maxlifetime');

                        // Set our own session write path
                        session_save_path($this->write_dir);

                        // Make sure that the session is closed when all objects are free'd
                        register_shutdown_function('session_write_close');

                        $result = session_start();
                        $this->started = $result;

                        // Custom session timeout check. We can't rely on the garbage collector to clear sessions since there is only a probability that it
                        // will be cleared and setting the gc_divisor and gc_probability to a 1:1 ratio causes to much work for the gc.
                        if (isset($_SESSION['session_last_activity']) && (time() - $_SESSION['session_last_activity'] > $this->lifetime)) {
                            $this->flush(true);
                            $result = session_start();
                            $this->started = $result;
                        }
                        $_SESSION['session_last_activity'] = time(); // update last activity time stamp

                        // When protect is enabled we make sure to regenerate a new session id
                        $protect = empty($this->config['session_protect']) ? True: $this->config['session_protect'];
                        if ($protect && !isset($_SESSION['session']))
                        {
                            // Basic session hijacking prevention
                            $this->old_session_id = session_id();
                            session_regenerate_id();
                            $this->session_id = session_id();
                            $_SESSION['session'] = true;
                        }

                    } else {
                        $result = true;
                        $this->started = $result;
                    }
                } else {
                    throw new EFSWriteableException(sprintf(__s("Unable to start session, session directory is not writable. (%s)"), $this->write_dir));
                }
            } else {
                throw new EFSWriteableException(__s("Unable to start session, session directory was not specified."));
            }
        } else {
            $this->started = true;
            $result = true;
        }
        return $result;
    }

    /**
    * Saves the current session.
    *
    */
    public function save() {
        if (!$this->started) throw new EFSNotStartedException();

        if ($this->enabled) {
            session_write_close();
        }
    }

    /**
    * Writes a value to the current session
    *
    * @param $key string Name of the key
    * @param $value mixed Value to store
    *
    * @return bool True if the data was written successfuly
    */
    public function set($key, $value) {
        if (!$this->started) throw new EFSNotStartedException();

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
    * @param $key string Name of the key
    * @param $default mixed Default value if the key is not set
    *
    * @return mixed Returned data
    */
    public function get($key, $default = null) {
        if (!$this->started) throw new EFSNotStartedException();

        if ($this->enabled) {
            if (!empty($key)) {
                return (isset($_SESSION[$key])) ? $_SESSION[$key]: $default;
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
    * @param $force bool Force a flush, whether the session is enabled or not
    */
    public function flush($force = false) {
        if (!$this->started && !$force) throw new EFSNotStartedException();

        if ($this->enabled || $force) {
            @session_unset();
            @session_destroy();
            @session_write_close();
            @setcookie(session_name(),'',0,'/');
            @session_regenerate_id(true);
            unset($_SESSION);
        }
    }
}