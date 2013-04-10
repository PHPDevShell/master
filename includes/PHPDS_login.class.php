<?php

/**
 * This base class implements the foundations for an authentication plugin
 * It does'nt actually provides authentication (it will reject any request) but provides structure, cookie support ("remember me") and writing to the system log
 *
 * Note: it does'nt in any deal with template or GUI, the auth plugin must do that
 */
class PHPDS_login extends PHPDS_dependant
{
    /**
     * The original login page.
     * @var int
     */
    public $loginPageId = 'login';

    /**
     * The original registration page.
     * @var int
     */
    public $registrationPageId = 'register-account';

    /**
     * The original lost password page.
     * @var int
     */
    public $lostPasswordPageId = 'lost-password';

    /**
     * Search the database for the given credentials from a persistent cookie
     *
     * @param string $cookie
     * @return array or false the user record
     */
    public function lookupCookieLogin($cookie)
    {
        $id_crypt   = substr($cookie, 0, 6);
        $pass_crypt = substr($cookie, 6, 32);
        $cookie_id  = null;
        $user_id    = 0;

        $found = false;

        $persistent_array = $this->selectCookie($id_crypt);

        if (!empty($persistent_array)) {
            $persistent_item = end($persistent_array);
            if ($pass_crypt == $persistent_item['pass_crypt']) {
                $cookie_id = $persistent_item['cookie_id'];
                $user_id   = $persistent_item['user_id'];
                $found     = true;
            }
        }

        if (!empty($found)) {
            $this->deleteCookie($cookie_id);
            $this->setUserCookie($user_id);
            $user_array = $this->user->getUser($user_id);
            $this->setLogin($user_array, "Persistent Login");
        } else {
            $this->setGuest();
            return false;
        }
        return false;
    }

    /**
     * Select cookie data by providing cookie crypt key.
     *
     * @param string $id_crypt
     * @return array
     */
    public function selectCookie($id_crypt)
    {
        $sql = "
            SELECT  user_id, cookie_id, pass_crypt
            FROM    _db_core_session
            WHERE   id_crypt = :id_crypt
        ";

        return $this->db->queryFetchAssocRow($sql, array('id_crypt' => $id_crypt));
    }

    /**
     * Set a persistent cookie to be used as a remember me function
     *
     * @param int $user_id
     * @return array or false the user record
     */
    public function setUserCookie($user_id)
    {
        $sql = "
          INSERT INTO _db_core_session (user_id, id_crypt, pass_crypt, timestamp)
		  VALUES      (:user_id, :id_crypt, :pass_crypt, :timestamp)
        ";
        $pass_crypt = md5(uniqid(rand(), true));
        $id_crypt   = substr(md5(uniqid(rand(), true)), 6, 6);
        $timestamp  = time();

        if ($this->db->queryAffects($sql, array(
            'user_id' => $user_id, 'id_crypt' => $id_crypt, 'pass_crypt' => $pass_crypt, 'timestamp' => $timestamp
        ))) {
            return setcookie('pdspc', $id_crypt . $pass_crypt, $timestamp + 63113852);
        } else {
            return false;
        }
    }

    /**
     * Delete cookie from database.
     *
     * @param int $cookie_id
     * @return int
     */
    public function deleteCookie($cookie_id)
    {
        $sql = "
            DELETE FROM _db_core_session
            WHERE       cookie_id = :cookie_id
        ";

        return $this->db->queryAffects($sql, array('cookie_id' => $cookie_id));
    }

    /**
     * Delete the current persistent cookie from the db and kill the cookie on the user end.
     *
     * @param int $user_id
     * @return boolean
     */
    public function clearUserCookie($user_id)
    {
        $sql = "
            DELETE FROM _db_core_session
            WHERE       (id_crypt = :id_crypt AND user_id = :user_id)
        ";

        if (!empty($_COOKIE['pdspc'])) {
            $id_crypt = substr($_COOKIE['pdspc'], 0, 6);

            if ($this->db->queryAffects($sql, array('id_crypt' => $id_crypt, 'user_id' => $user_id)))
            {
                return setcookie('pdspc', 'false', 0);
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Check what to do with login action.
     */
    public function controlLogin()
    {
        if (!empty(
            $this->configuration['allow_remember']) && empty($_SESSION['user_id']) &&
            isset($_COOKIE['pdspc']) && empty($_REQUEST['logout']) && empty($_POST['login'])) {
            $this->lookupCookieLogin($_COOKIE['pdspc']);
        } else {
            if (!empty($_REQUEST['logout']) && $this->isLoggedIn()) {
                $this->clearLogin(true);
                $this->setGuest();
            } else {
                if (!empty($_POST['login'])) {
                    $user_name     = empty($_POST['user_name']) ? '' : $_POST['user_name'];
                    $user_password = empty($_POST['user_password']) ? '' : $_POST['user_password'];
                    $this->processLogin($user_name, $user_password);
                } else {
                    $this->setGuest();
                }
            }
        }
    }

    /**
     * Make the given user the logged in user
     *
     * @param array $select_user_array
     * @param bool $persistent
     */
    public function setLogin($select_user_array, $persistent = false)
    {
        $conf = $this->configuration;
        $user = $this->user;

        $user_name_db          = $select_user_array['user_name'];
        $user_display_name_db  = $select_user_array['user_display_name'];
        $user_email_db         = $select_user_array['user_email'];
        $user_id_db            = $select_user_array['user_id'];
        $user_role_db          = $select_user_array['user_role'];
        $user_role_name_db     = $select_user_array['user_role_name'];
        $user_language_db      = $select_user_array['language'];
        $user_region_db        = $select_user_array['region'];
        if (!empty($select_user_array['user_timezone'])) {
            $user_timezone_db = $select_user_array['user_timezone'];
        } else if (!empty($conf['system_timezone'])) {
            $user_timezone_db = $conf['system_timezone'];
        } else {
            $user_timezone_db = date_default_timezone_get();
        }

        $_SESSION['user_display_name'] = $user_display_name_db;
        $_SESSION['user_email']        = $user_email_db;
        $_SESSION['user_id']           = $user_id_db;
        $_SESSION['user_name']         = $user_name_db;
        $_SESSION['user_role']         = $user_role_db;
        $_SESSION['user_role_name']    = $user_role_name_db;
        $_SESSION['user_timezone']     = $user_timezone_db;

        if (!empty($user_language_db)) {
            $user_language = $user_language_db;
        } else {
            $user_language = $conf['language'];
        }

        if (!empty($user_region_db)) {
            $user_region = $user_region_db;
        } else {
            $user_region = $conf['region'];
        }

        $_SESSION['user_language'] = $user_language;
        $_SESSION['user_region']   = $user_region;

        $_SESSION['user_locale'] = $this->core->formatLocale(true, $user_language, $user_region);
        if (!empty($this->configuration['m'])) {

            if (!$persistent) {
                $user->logArray[] = array('log_type' => 4, 'user_id' => $user_id_db,
                                          'logged_by' => $user_display_name_db, 'log_description' => ___('Logged-in'));
            } else {
                $user->logArray[] = array('log_type' => 4, 'user_id' => $user_id_db,
                                          'logged_by' => $user_display_name_db, 'log_description' => $persistent);
            }
        }

        $this->cache->cacheClear();
    }

    /**
     * Destroys login session data.
     *
     * @param bool $set_guest
     */
    public function clearLogin($set_guest = true)
    {
        $user = $this->user;

        $this->clearUserCookie($_SESSION['user_id']);

        $user->logArray[] = array('log_type' => 5, 'log_description' => ___('Logged-out'));

        unset($_SESSION['user_email']);
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_role']);
        unset($_SESSION['user_display_name']);
        unset($_SESSION['user_role_name']);
        unset($_SESSION['user_language']);
        unset($_SESSION['user_timezone']);
        unset($_SESSION['user_region']);
        unset($_SESSION['user_locale']);

        $this->cache->cacheClear();

        $_SESSION = array();

        session_destroy();

        if ($set_guest) $this->setGuest();
    }

    /**
     * Sets all settings to guest account.
     *
     * @return string
     */
    public function setGuest()
    {
        $conf = $this->configuration;

        if (empty($_SESSION['user_name'])) {
            $settings_array = $this->config->essentialSettings;

            if (!empty($conf['system_timezone'])) {
                $user_timezone = $conf['system_timezone'];
            } else {
                $user_timezone = date_default_timezone_get();
            }
            $_SESSION['user_name']         = 'guest';
            $_SESSION['user_display_name'] = 'Guest User';
            $_SESSION['user_role_name']    = '';
            $_SESSION['user_role']         = $settings_array['guest_role'];
            $_SESSION['user_email']        = '';
            $_SESSION['user_language']     = $conf['language'];
            $_SESSION['user_region']       = $conf['region'];
            $_SESSION['user_timezone']     = $user_timezone;
            $_SESSION['user_locale']       = $this->core->formatLocale();
            $_SESSION['user_id']           = 0;
        }
    }

    /**
     * Check is user is logged in, return false if not.
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Loads the username & password html template form.
     *
     * @param boolean $return
     * @return string
     */
    public function loginForm($return = false)
    {
        $settings      = $this->config->essentialSettings;
        $navigation    = $this->navigation;
        $template      = $this->template;
        $configuration = $this->configuration;
        $redirect_page = '';

        if (empty($configuration['m']))
            $configuration['m'] = $this->loginPageId;

        // Determine page to post form too.
        if ($configuration['m'] == $this->loginPageId) {
            $post_login_url = $navigation->buildURL($settings['redirect_login']);
        } else {
            $post_login_url = $_SERVER['REQUEST_URI'];
        }

        // Assign reusable username.
        $user_name = (empty($_POST['user_name'])) ? '' : $_POST['user_name'];

        // Determine what to show as per request.
        if (!$this->isLoggedIn()) {
            // Check if not registered link should appear.
            if ((boolean)$settings['allow_registration'] == true) {
                // Check if we have a custom registration page.
                $registration = (!empty($settings['registration_page'])) ?
                    $navigation->buildURL($settings['registration_page']) :
                    $navigation->buildURL($this->registrationPageId);
            } else {
                $registration = false;
            }

            // Create the "remember me" checkbox, if needed
            if (!empty($settings['allow_remember'])) {
                $remember = ___('Remember Me?');
            } else {
                $remember = false;
            }
            // Create HTML login field.
            return $template->mod->loginForm(
                $post_login_url,
                ___('Username or Email'),
                ___('Password'),
                $redirect_page,
                $navigation->buildURL($this->lostPasswordPageId),
                ___('Lost Password?'),
                $registration,
                ___('Not registered yet?'),
                $remember,
                $template->postValidation(),
                ___('Account Detail'),
                $user_name,
                __('Submit'));
        }
        return null;
    }

    /**
     * Checks to see if user and password is correct and allowed. Then creates session data accordingly.
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public function processLogin($username, $password)
    {
        if (empty($username) || empty($password)) {
            $this->template->notice(___('You did not complete required username and password fields.'));
        } else {
            if ($this->lookupUsername($username)) {
                // Simple method to lookup user by providing username and password.
                $user_array = $this->lookupUser($username, $password);

                // Check if we have a login to process.
                if (!empty($user_array)) {
                    $this->setLogin($user_array);
                    if ($this->config->essentialSettings['allow_remember'] && isset($_POST['user_remember'])) {
                        $this->setUserCookie($user_array['user_id']);
                    }
                } else {
                    $this->core->haltController = array('type' => 'auth', 'message' => ___('Incorrect Password'));
                    $this->template->notice(
                        ___('You used a valid username with a <strong>wrong password</strong>.
                             Remember, it is Case Sensitive.')
                    );
                }
            } else {
                $this->core->haltController = array('type' => 'auth', 'message' => ___('Incorrect Login Data'));
                $this->template->notice(
                    ___('Your <strong>username</strong> could not be found. Remember, it is Case Sensitive.')
                );
            }
        }
    }

    /**
     * Search the database for the given credentials
     *
     * If don't give the password (not the same an empty string), only the username will be checked
     *
     * @param string $username
     * @param string $password
     * @return array or false the user record
     */
    public function lookupUser($username, $password = '')
    {
        $sql = "
            SELECT      t1.user_id, t1.user_display_name, t1.user_password, t1.user_name,
                        t1.user_email, t1.user_role, t1.language, t1.timezone AS user_timezone, t1.region,
			            t2.user_role_name
		    FROM        _db_core_users AS t1
		    LEFT JOIN   _db_core_user_roles AS t2
		    ON          t1.user_role = t2.user_role_id
		    WHERE       (t1.user_name = :user_name OR t1.user_email = :user_name)
		    AND         IF(:user_password = '*', 1, t1.user_password = :user_password)
        ";

        $password = is_null($password) ? '*' : PU_hashPassword($password);
        return $this->db->queryFetchAssocRow($sql, array('user_name' => $username, 'user_password' => $password));
    }

    /**
     * Check if the username exists.
     *
     * @param string $username
     * @return string
     */
    public function lookupUsername($username)
    {
        $sql = "
            SELECT  t1.user_id
		    FROM    _db_core_users AS t1
		    WHERE   (t1.user_name = :user_name OR t1.user_email = :user_name)
        ";

        return $this->db->querySingle($sql, array('user_name' => $username));
    }
}