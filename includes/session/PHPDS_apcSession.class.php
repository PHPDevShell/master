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

class EAPCSNotAvailableException extends Exception {}
class EAPCSNotStartedException extends Exception {
    public function __construct($message = "", $code = 0 , $previous = null) {
        parent::__construct($message, $code, $previous);
        $message = __s("Session not started properly. APCSession::start() not called or failed to start cache properly.");
    }
};

// Implements an APC based session layer
class PHPDS_apcSession implements SessionIntf {

    public $config;
    public $session_id = 0;
    public $old_session_id = 0;
    public $enabled = False;
    public $started = false;
    public $lifetime = 0;

    /**
    * Class constructor.
    * @param array $config Possible Options:
    *   config['session_enabled']: default = False
    *   config['session_write_mode']: default = 0 (0 = Normal, 1 = JSON Storage mode)
    *   config['session_protect']: default = True
    *   config['session_lifetime']: default = 1440
    *   config['session_gc_maxlifetime']: default = 1
    *   config['session_gc_probability']: default = 100
    *   config['session_gc_maxlifetime']: default = 1440
    */
    public function __construct($config = array()) {
        $this->config = $config;
    }

    /**
     * Destructor
     */
    public function __destruct() {
        session_write_close();
    }

    /**
    * Starts a session. See: http://php.net/manual/en/function.session-start.php
    * @param object $storage Not used with this session manager.
    *
    * @return bool
    */
    public function start($storage = null) {
        $result = false;

        // Is session handling enabled?
        $this->enabled = !isset($this->config['session_enabled']) ? True: $this->config['session_enabled'];

        if ($this->enabled) {
            $this->write_mode = empty($this->config['session_write_mode']) ? 0: $this->config['session_write_mode'];

            if (!empty($this->config['session_gc_probability'])) ini_set('session.gc_probability', $this->config['session_gc_probability']);
            if (!empty($this->config['session_gc_divisor'])) ini_set('session.gc_divisor', $this->config['session_gc_divisor']);
            if (!empty($this->config['session_gc_maxlifetime'])) ini_set('session.gc_maxlifetime', $this->config['session_gc_maxlifetime']);

            $this->lifetime = (!empty($this->config['session_lifetime'])) ? $this->config['session_lifetime']: ini_get('session.gc_maxlifetime');

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
                    $protect = !isset($this->config['session_protect']) ? True: $this->config['session_protect'];
                    if ($protect && !isset($_SESSION['session']))
                    {
                        // Basic session hijacking prevention
                        $this->old_session_id = session_id();
                        session_regenerate_id();
                        $this->session_id = session_id();
                        $_SESSION['session'] = true;
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
    */
    public function save() {
        session_write_close();
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
        if (!$this->started) throw new EAPCSNotStartedException();

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
        if (!$this->started) throw new EAPCSNotStartedException();

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
        if (!$this->started && !$force) throw new EAPCSNotStartedException();

        if ($this->enabled || $force) {
            @session_unset();
            @session_destroy();
            @session_write_close();
            @setcookie(session_name(),'',0,'/');
            @session_regenerate_id(true);
            unset($_SESSION);
        }
    }

    /**
     * Open the session handler
     *
     * @return bool True if everything succeed
     */
    public function open($savePath, $sessionName) {
        if ($this->enabled) {
            if (extension_loaded('apc') && ini_get('apc.enabled')) {
                $this->started = true;
            } else {
                throw new EAPCSNotAvailableException(__s("Unable to start session. APC extension is not available or not installed."));
            }
        }

        return true;
    }

    /**
     * Called when the session is started, reads the data from APC based on the session id
     *
     * @param string The Session ID
     * @return string The session data that is currently stored
     */
    public function read($id) {
        if ($this->enabled && $this->started) {
            $tmp_session = $_SESSION;
            $success = false;
            $_SESSION = apc_fetch("sessions/{$id}", $success);
            if ($success) {
                if(isset($_SESSION) && !empty($_SESSION)) {
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
     * @param string $id The Session ID
     * @param string $data The data to store, already serialized by PHP
     * @return bool True if APC was able to write the session data
     */
    public function write($id, $data) {
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
     * @return bool True if APC was able delete the session data successfully
     */
    public function destroy($id) {
        if ($this->enabled && $this->started) {
            return apc_delete("sessions/{$id}");
        } else {
            return true;
        }
    }

    /**
     * Close gc
     *
     * @return bool Always true
     */
    public function gc($lifetime) {
        return true;
    }

    /**
     * Close session
     *
     * @return bool Always true
     */
    public function close() {
        return true;
    }

}