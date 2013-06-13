<?php

/**
 * This base class implements the foundations for an authentication plugin
 * It does'nt actually provides authentication (it will reject any request) but provides structure, cookie support ("remember me") and writing to the system log
 *
 * Note: it does'nt in any deal with template or GUI, the auth plugin must do that
 */
class PHPDS_auth extends PHPDS_dependant
{
    /**
     * The default login node id.
     * @var string
     */
    public $loginPageId = 'login';
    /**
     * The default registration node id.
     * @var string
     */
    public $registrationPageId = 'register-account';
    /**
     * The default lost password node id.
     * @var int
     */
    public $lostPasswordPageId = 'lost-password';

    /**
     * Controller that will always run from core to check any login attempt or restore existing cookie authentication.
     *
     * @return void
     */
    public function start()
    {
        if (!isset($_SESSION['user_id']) || !empty($_POST['PHPDS_login']) || !empty($_REQUEST['logout'])) {
            if (!empty(
            $this->configuration['allow_remember']) && empty($_SESSION['user_id']) &&
                isset($_COOKIE['PHPDS_auth']) && empty($_REQUEST['logout']) && empty($_POST['PHPDS_login'])
            ) {
                $this->lookupCookie($_COOKIE['PHPDS_auth']);
            } else {
                if (!empty($_REQUEST['logout']) && $this->isUserSession()) {
                    $this->clearSession();
                } else {
                    if (!empty($_POST['PHPDS_login'])) {
                        $user_name     = empty($_POST['user_name']) ? '' : $_POST['user_name'];
                        $user_password = empty($_POST['user_password']) ? '' : $_POST['user_password'];
                        $this->processRequest($user_name, $user_password);
                    } else {
                        $this->createGuestSession();
                    }
                }
            }
        }
        $this->storeSession();
    }

    /**
     * Search the database for the given auth credentials from a persistent cookie.
     *
     * @param string $cookie
     * @return array or false the user record
     */
    protected function lookupCookie($cookie)
    {
        $id_crypt   = substr($cookie, 0, 6);
        $pass_crypt = substr($cookie, 6, 32);
        $cookie_id  = null;
        $user_id    = 0;

        $found = false;
        $persistent_array = $this->selectCookie($id_crypt);

        if (!empty($persistent_array)) {
            if (!empty($persistent_array['pass_crypt']) && ($pass_crypt == $persistent_array['pass_crypt'])) {
                $cookie_id = $persistent_array['cookie_id'];
                $user_id   = $persistent_array['user_id'];
                $found     = true;
            }
        }

        if (!empty($found)) {
            $this->deleteCookie($cookie_id);
            $this->setCookie($user_id);
            $user_array = $this->user->getUser($user_id);
            $this->createUserSession($user_array, "Persistent Login");
        } else {
            $this->createGuestSession();
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
    protected function selectCookie($id_crypt)
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
    protected function setCookie($user_id)
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
            return setcookie('PHPDS_auth', $id_crypt . $pass_crypt, $timestamp + 63113852);
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
    protected function deleteCookie($cookie_id)
    {
        $sql = "
            DELETE FROM _db_core_session
            WHERE       cookie_id = :cookie_id
        ";

        return $this->db->queryAffects($sql, array('cookie_id' => $cookie_id));
    }

    /**
     * Delete the current persistent cookie from the db and eat the cookie on the user end.
     *
     * @param int $user_id
     * @return boolean
     */
    protected function clearCookie($user_id)
    {
        $sql = "
            DELETE FROM _db_core_session
            WHERE       (id_crypt = :id_crypt AND user_id = :user_id)
        ";

        if (!empty($_COOKIE['PHPDS_auth'])) {
            $id_crypt = substr($_COOKIE['PHPDS_auth'], 0, 6);

            if ($this->db->queryAffects($sql, array('id_crypt' => $id_crypt, 'user_id' => $user_id)))
            {
                return setcookie('PHPDS_auth', 'false', 0);
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Stores user session data to be used by core system.
     *
     * @return void
     */
    protected function storeSession()
    {
        $conf = $this->configuration;

        $conf['user_id']           = empty($_SESSION['user_id']) ? 0
            : $_SESSION['user_id'];
        $conf['user_name']         = empty($_SESSION['user_name']) ? ''
            : $_SESSION['user_name'];
        $conf['user_display_name'] = empty($_SESSION['user_display_name']) ? ''
            : $_SESSION['user_display_name'];
        $conf['user_role']         = empty($_SESSION['user_role']) ? 0
            : $_SESSION['user_role'];
        $conf['user_email']        = empty($_SESSION['user_email']) ? ''
            : $_SESSION['user_email'];
        $conf['user_language']     = empty($_SESSION['user_language']) ? ''
            : $_SESSION['user_language'];
        $conf['user_region']       = empty($_SESSION['user_region']) ? ''
            : $_SESSION['user_region'];
        $conf['user_timezone']     = empty($_SESSION['user_timezone']) ? ''
            : $_SESSION['user_timezone'];
        $conf['user_locale']       = empty($_SESSION['user_locale']) ? $this->core->formatLocale()
            : $_SESSION['user_locale'];
    }

    /**
     * Make the given user the logged in user.
     *
     * @param array $select_user_array
     * @param bool $persistent
     * @return void
     */
    protected function createUserSession($select_user_array, $persistent = false)
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
        $this->cache->flush();
    }

    /**
     * Sets session data as guest auth.
     *
     * @return string
     */
    protected function createGuestSession()
    {
        $conf = $this->configuration;

        if (empty($_SESSION['user_name'])) {
            $config = $this->configuration;

            if (!empty($conf['system_timezone'])) {
                $user_timezone = $conf['system_timezone'];
            } else {
                $user_timezone = date_default_timezone_get();
            }
            $_SESSION['user_name']         = 'guest';
            $_SESSION['user_display_name'] = 'Guest User';
            $_SESSION['user_role_name']    = '';
            $_SESSION['user_role']         = $config['guest_role'];
            $_SESSION['user_email']        = '';
            $_SESSION['user_language']     = $conf['language'];
            $_SESSION['user_region']       = $conf['region'];
            $_SESSION['user_timezone']     = $user_timezone;
            $_SESSION['user_locale']       = $this->core->formatLocale();
            $_SESSION['user_id']           = 0;
        }
    }

    /**
     * Log-out and destroy login session data.
     *
     * @param bool $set_guest
     * @return void
     */
    public function logOut($set_guest = true)
    {
        $this->clearSession($set_guest);
    }

    /**
     * Destroys login session data.
     *
     * @param bool $set_guest
     * @return void
     */
    protected function clearSession($set_guest = true)
    {
        $user = $this->user;

        $this->clearCookie($_SESSION['user_id']);

        $user->logArray[] = array('log_type' => 5, 'log_description' => ___('Logged-out'));

        $this->cache->flush();
        $this->session->flush();

        if ($set_guest) $this->createGuestSession();
    }

    /**
     * Check if user session is active, return false if not.
     *
     * @return boolean
     */
    protected function isUserSession()
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Build request array for typical use e.g draw login form with the data returned.
     *
     * @return array
     */
    public function buildRequest()
    {
        $navigation    = $this->navigation;
        $configuration = $this->configuration;
        $redirect_page = '';

        if (empty($configuration['m']))
            $configuration['m'] = $this->loginPageId;

        // Determine page url to post form to.
        if ($configuration['m'] == $this->loginPageId) {
            $post_login_url = $navigation->buildURL($configuration['redirect_login']);
        } else {
            $post_login_url = str_replace('/logout=1', '', $_SERVER['REQUEST_URI']);
        }

        $user_name = (empty($_POST['user_name'])) ? '' : $_POST['user_name'];

        if (!$this->isUserSession()) {
            // Check if not registered link should appear.
            if (!empty($configuration['allow_registration'])) {
                // Check if we have a custom registration page.
                $registration = (!empty($configuration['registration_page'])) ?
                    $navigation->buildURL($configuration['registration_page']) :
                    $navigation->buildURL($this->registrationPageId);
            } else {
                $registration = null;
            }
            return array(
                'post_login_url'        => $post_login_url,
                'redirect_page'         => $redirect_page,
                'lost_password_page_id' => $navigation->buildURL($this->lostPasswordPageId),
                'registration'          => $registration,
                'user_name'             => $user_name);
        }
        return array();
    }

    /**
     * Checks to see if user and password is correct and allowed.
     * If correct the results is passes on to creates session data.
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    protected function processRequest($username, $password)
    {
        if (empty($username) || empty($password)) {
            $this->template->notice(___('Provide username and password.'));
        } else {
            if ($this->lookupUsername($username)) {
                $user_array = $this->lookupUser($username, $password);
                if (!empty($user_array)) {
                    $this->createUserSession($user_array);
                    if ($this->configuration['allow_remember'] && isset($_POST['user_remember'])) {
                        $this->setCookie($user_array['user_id']);
                    }
                } else {
                    $this->core->haltController = array('type' => 'auth', 'message' => ___('Incorrect Password'));
                    $this->template->notice(
                        ___('You used a valid username with a <strong>wrong password</strong>.
                             Remember, it is Case Sensitive.')
                    );
                }
            } else {
                $this->core->haltController = array('type' => 'auth', 'message' => ___('Incorrect login credentials'));
                $this->template->notice(
                    ___('Incorrect <strong>username</strong>.')
                );
            }
        }
    }

    /**
     * Search the database for the given authentication credentials.
     * If no password is provided, only the username will be checked.
     *
     * @param string $username
     * @param string $password
     * @return array or false the user record
     */
    protected function lookupUser($username, $password = '')
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

        $password = PU_hashPassword($password);
        return $this->db->queryFetchAssocRow($sql, array('user_name' => $username, 'user_password' => $password));
    }

    /**
     * Check if the username exists.
     *
     * @param string $username
     * @return string
     */
    protected function lookupUsername($username)
    {
        $sql = "
            SELECT  t1.user_id
		    FROM    _db_core_users AS t1
		    WHERE   (t1.user_name = :user_name OR t1.user_email = :user_name)
        ";

        return $this->db->querySingle($sql, array('user_name' => $username));
    }
}