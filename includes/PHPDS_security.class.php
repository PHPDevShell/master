<?php

class PHPDS_security extends PHPDS_dependant
{
    /**
     * Cleaned up $_GET.
     *
     * @var mixed
     */
    public $get;
    /**
     * Cleaned up $_POST.
     *
     * @var mixed
     */
    public $post;
    /**
     * Cleaned up $_REQUEST.
     *
     * @var mixed
     */
    public $request;
    /**
     * Cleaned up $_SESSION.
     *
     * @var mixed
     */
    public $session;
    /**
     * Cleaned up $_COOKIE.
     *
     * @var mixed
     */
    public $cookie;

    /**
     * This method does the actual security check, other security checks are done on a per call basis to this method in specific objects.
     */
    public function securityIni()
    {
        if (isset($_SESSION['user_id']))
            $this->log(sprintf(___('Security check for user id %s'), $_SESSION['user_id']));

        if (!empty($this->configuration['system_down'])) {
            if ($this->configuration['user_role'] == $this->configuration['root_role']) {
                if ($this->configuration['system_down_bypass'] == false) {
                    $this->template->warning(___('System is switched off for normal users, only root can access the system.'), false, false);
                }
            } else if ($this->configuration['system_down_bypass'] == false) {
                $settings_message           = $this->db->getSettings(array('system_down_message'));
                $this->core->skipLogin      = true;
                $this->core->haltController = sprintf($settings_message['system_down_message'], $this->configuration['scripts_name_version']);
            }
        }

        if (!empty($_POST)) $this->post = $this->sqlWatchdog($_POST);
        if (!empty($_GET)) $this->get = $this->sqlWatchdog($_GET);
        if (!empty($_COOKIE)) $this->cookie = $this->sqlWatchdog($_COOKIE);
        if (!empty($_SESSION)) $this->session = $_SESSION;
        if (!empty($_REQUEST)) $this->request = array_merge((array)$this->post, (array)$this->get);
    }

    /**
     * Function just like mysql_real_escape_string, but does so recursive through array.
     *
     * @param string|array $input
     * @return string|array
     */
    public function sqlWatchdog($input)
    {
        if (is_array($input)) {
            foreach ($input as $k => $i) {
                $output[$k] = $this->sqlWatchdog($i);
            }
        } else {
            $output = trim(htmlentities(str_replace('\\', '', $input), ENT_QUOTES, $this->configuration['charset']));
        }
        return $output;
    }

    /**
     * Use inside your form brackets to send through a token validation to limit $this->post received from external pages.
     *
     * @return string Returns hidden input field.
     */
    public function postValidation()
    {
        return $this->validatePost();
    }

    /**
     * Use inside your form brackets to send through a token validation to limit $this->post received from external pages.
     *
     * @return string Returns hidden input field.
     */
    public function validatePost()
    {
        $token                              = md5(uniqid(rand(), true));
        $key                                = md5($this->configuration['crypt_key']);
        $_SESSION['token_validation'][$key] = $token;
        return $this->template->mod->securityToken($token);
    }

    /**
     * This is used in the search filter to validate $this->post made by the search form.
     *
     * @return string Returns hidden input field.
     */
    public function searchFormValidation()
    {
        $search_token                              = md5(uniqid(rand(), true));
        $search_key                                = md5(sha1($this->configuration['crypt_key']));
        $_SESSION['token_validation'][$search_key] = $search_token;
        return $this->template->mod->searchToken($search_token);
    }

    /**
     * Encrypts a string with the configuration key provided.
     *
     * @param string $string
     * @return string
     */
    public function encrypt($string)
    {
        $result = false;
        for ($i = 0; $i < strlen($string); $i++) {
            $char    = substr($string, $i, 1);
            $keychar = substr($this->configuration['crypt_key'], ($i % strlen($this->configuration['crypt_key'])) - 1, 1);
            $char    = chr(ord($char) + ord($keychar));
            $result .= $char;
        }
        return urlencode(base64_encode($result));
    }

    /**
     * Decrypts a string with the configuration key provided.
     *
     * @param string $string
     * @return string
     */
    public function decrypt($string)
    {
        $result = false;
        $string = base64_decode(urldecode($string));
        for ($i = 0; $i < strlen($string); $i++) {
            $char    = substr($string, $i, 1);
            $keychar = substr($this->configuration['crypt_key'], ($i % strlen($this->configuration['crypt_key'])) - 1, 1);
            $char    = chr(ord($char) - ord($keychar));
            $result .= $char;
        }
        return $result;
    }

    /**
     * Creates a "secret" version of the password
     *
     * @param string $password the clear password
     * @return string the hashed password
     */
    public function hashPassword($password = '')
    {
        return empty($password) ? '*' : md5($password);
    }

    /**
     * Validates email address.
     *
     * @param string $email_string Email address.
     * @return boolean
     */
    public function validateEmail($email_string)
    {
        if (filter_var($email_string, FILTER_VALIDATE_EMAIL) == true) {
            return true;
        } else {
            return false;
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
}