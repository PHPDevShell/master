<?php

class PHPDS_user extends PHPDS_dependant
{
    /**
     * Set roles that exists.
     *
     * @var array
     */
    public $rolesArray;
    /**
     * Set groups that exists.
     *
     * @var array
     */
    public $groupsArray;
    /**
     * Array for log data to be written.
     *
     * @var string
     */
    public $logArray;

    /**
     * Return roles id for a given user id,
     *
     * @param integer $user_id
     * @return integer
     */
    public function getRoles($user_id = null)
    {
        return $this->db->invokeQuery('USER_getRolesQuery', $user_id);
    }

    /**
     * Check to see if a certain role exists.
     *
     * @param integer $role_id
     * @return boolean
     */
    public function roleExist($role_id)
    {
        return $this->db->invokeQuery('USER_roleExistQuery', $role_id);
    }

    /**
     * Check if user belongs to given role.
     *
     * @param integer $user_id
     * @param integer $user_role
     * @return boolean
     */
    public function belongsToRole($user_id = null, $user_role = null)
    {
        return $this->db->invokeQuery('USER_belongsToRoleQuery', $user_id, $user_role);
    }

    /**
     * Creates a query to extend a role query, it will return false if user is root so everything can get listed.
     * This is meant to be used inside an existing role query.
     *
     * @param string $query_request      Normal query to be returned if user is not a root user.
     * @param string $query_root_request If you want a query to be processed for a root user seperately.
     * @return string|bool
     */
    public function setRoleQuery($query_request, $query_root_request = null)
    {
        if ($this->user->isRoot()) {
            if (!empty($query_root_request)) {
                return " $query_root_request ";
            } else {
                return false;
            }
        } else {
            return " $query_request ";
        }
    }

    /**
     * Deletes a specific role by given ID.
     *
     * @param int $id
     * @return string
     */
    public function deleteRole($id)
    {
        $deleted_role = $this->db->deleteQuick('_db_core_user_roles', 'user_role_id', $id, 'user_role_name');
        $this->db->deleteQuick('_db_core_user_role_permissions', 'user_role_id', $id);
        $this->db->invokeQuery('USER_updateUserRoleQuery', $id);
        return $deleted_role;
    }

    /**
     * Deletes a specific user by given ID.
     *
     * @param int $id
     * @return string
     */
    public function deleteUser($id)
    {
        return $this->db->deleteQuick('_db_core_users', 'user_id', $id, 'user_display_name');
    }

    /**
     * Check if user is a root user.
     *
     * @param int $user_id If not logged in user, what user should be checked (primary role check only).
     * @return boolean
     */
    public function isRoot($user_id = 0)
    {
        if (!empty($user_id)) {
            if ($this->configuration['user_id'] == $user_id) {
                if ($this->configuration['user_role'] == $this->configuration['root_role']) {
                    return true;
                } else {
                    return false;
                }
            } else {
                $check_role_id = $this->db->invokeQuery('USER_isRootQuery', $user_id);
                if ($check_role_id == $this->configuration['root_role']) {
                    return true;
                } else {
                    return false;
                }
            }
        } else if (($this->configuration['user_role'] == $this->configuration['root_role'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns current logged in user id.
     *
     * @return integer
     */
    public function currentUserID()
    {
        if (!empty($this->configuration['user_id'])) {
            return $this->configuration['user_id'];
        } else {
            return false;
        }
    }

    /**
     * Simple method to return users IP, this method will be improved in the future if needed.
     *
     * @return string
     */
    public function userIp()
    {
        return $this->getUserIp();
    }

    /**
     * Simple method to return users IP, this method will be improved in the future if needed.
     *
     * @return string
     */
    public function getUserIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
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
     * Check if a user has access to a given node id.
     *
     * @param string  $node_id This can have both the node id as an integer or as a string.
     * @param string  $type    The type of item requested, node_id, node_name etc...
     * @return boolean|string Will return requested variable if user has access to requested node item node item.
     */
    public function canAccessNode($node_id, $type = 'node_id')
    {
        if (!empty($this->navigation->navigation[$node_id][$type])) {
            return $this->navigation->navigation[$node_id][$type];
        } else {
            return false;
        }
    }

    /**
     * Simply writes user session data.
     */
    public function userConfig()
    {
        $conf = $this->configuration;

        $conf['user_id']           = empty($_SESSION['user_id']) ? 0 : $_SESSION['user_id'];
        $conf['user_name']         = empty($_SESSION['user_name']) ? '' : $_SESSION['user_name'];
        $conf['user_display_name'] = empty($_SESSION['user_display_name']) ? '' : $_SESSION['user_display_name'];
        $conf['user_role']         = empty($_SESSION['user_role']) ? 0 : $_SESSION['user_role'];
        $conf['user_email']        = empty($_SESSION['user_email']) ? '' : $_SESSION['user_email'];
        $conf['user_language']     = empty($_SESSION['user_language']) ? '' : $_SESSION['user_language'];
        $conf['user_region']       = empty($_SESSION['user_region']) ? '' : $_SESSION['user_region'];
        $conf['user_timezone']     = empty($_SESSION['user_timezone']) ? '' : $_SESSION['user_timezone'];
        $conf['user_locale']       = empty($_SESSION['user_locale']) ? $this->core->formatLocale() : $_SESSION['user_locale'];
    }

    /**
     * Actual processing of login page.
     */
    public function controlLogin()
    {
        if (!isset($_SESSION['user_id']) || !empty($_POST['login']) || !empty($_REQUEST['logout'])) {
            $this->factory('StandardLogin')->controlLogin();
        }
        $this->userConfig();
    }

    /**
     * This method logs error and success entries to the database.
     */
    public function logThis()
    {
        $this->db->invokeQuery('USER_logThisQuery', $this->logArray);
    }
}
