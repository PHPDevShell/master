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
     * Array for log data to be written.
     *
     * @var array
     */
    public $logArray;

    /**
     * Selects single user array by provided user id.
     *
     * @param int $user_id
     * @return array
     */
    public function getUser($user_id)
    {
        $sql = "
            SELECT
			        t1.user_id, t1.user_display_name, t1.user_password, t1.user_name,
			        t1.user_email, t1.user_role, t1.language, t1.timezone AS user_timezone, t1.region
		    FROM    _db_core_users AS t1
		    WHERE
			        t1.user_id = :user_id
        ";
        return $this->db->queryFetchAssocRow($sql, array('user_id' => $user_id));
    }

    /**
     * Return roles id for a given user id,
     *
     * @param integer $user_id
     * @return integer
     * @throws PHPDS_exception
     */
    public function getRoles($user_id = null)
    {
        $sql = "
            SELECT  user_role
            FROM    _db_core_users
            WHERE   user_id = :user_id
        ";

        $configuration = $this->configuration;
        $config        = $this->config;

        if (empty($user_id))
            $user_id = (!empty($configuration['user_id'])) ? $configuration['user_id'] : 0;

        // Check if user is a guest.
        if (!empty($user_id)) {
            if ($user_id == $configuration['user_id']) {
                return $configuration['user_role'];
            } else {
                $role = $this->db->querySingle($sql, array('user_id' => $user_id));
                return $role;
            }
        } else {
            $settings   = $config->essentialSettings;
            $guest_role = $settings['guest_role'];
            if (empty($guest_role)) throw new PHPDS_exception('Unable to get the GUEST ROLE from essential settings.');
            return $guest_role;
        }
    }

    /**
     * Check to see if a certain role exists.
     *
     * @param integer $role_id
     * @return boolean
     */
    public function roleExist($role_id)
    {
        $sql = "
            SELECT  user_role_id
		    FROM    _db_core_user_roles
        ";

        if (empty($this->rolesArray)) {
            $roles = $this->db->queryFAR($sql);
            foreach ($roles as $results_array) {
                $this->rolesArray[$results_array['user_role_id']] = true;
            }
        }
        if (!empty($this->rolesArray[$role_id])) {
            return true;
        } else {
            return false;
        }
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
        $sql = "
            SELECT  t1.user_id
		    FROM    _db_core_users AS t1
		    WHERE   (t1.user_role = :user_role)
		    AND     (t1.user_id = :user_id)
        ";

        if ($this->isRoot($user_id)) return true;

        if (empty($user_id)) {
            (!empty($this->configuration['user_id'])) ? $user_id = $this->configuration['user_id'] : $user_id = null;
        }

        $check_user_in_role_db = $this->db->queryFetchAssocRow($sql,
            array('user_role' => $user_role, 'user_id' => $user_id)
        );

        if ($check_user_in_role_db['user_id'] == $user_id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Creates a query to extend a role query, it will return false if user is root so everything can get listed.
     * This is meant to be used inside an existing role query.
     *
     * @param string $query_request      Normal query to be returned if user is not a root user.
     * @param string $query_root_request If you want a query to be processed for a root user separately.
     * @return string|bool
     */
    public function setRoleQuery($query_request, $query_root_request = null)
    {
        if ($this->isRoot()) {
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
     * Deletes a specific role and loose end by giving role ID.
     *
     * @param int $id
     * @return string
     */
    public function deleteRole($id)
    {
        $deleted_role = $this->deleteRoleSafe($id);
        $this->deleteRolePermissions($id);
        $this->disableUsersByRole($id);
        if ($deleted_role) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * Deletes role permission from role permission table by providing role id.
     *
     * @param int $id
     * @return bool|int
     */
    public function deleteRolePermissions($id)
    {
        $sql = "
            DELETE FROM _db_core_user_role_permissions
            WHERE user_role_id = :user_role_id
        ";

        return $this->db->queryAffects($sql, array('user_role_id' => $id));
    }

    /**
     * Deletes only the role from role table by providing role id while keeping all permissions intact.
     *
     * @param int $id
     * @return bool|int
     */
    public function deleteRoleSafe($id)
    {
        $sql = "
            DELETE FROM _db_core_user_roles
            WHERE user_role_id = :user_role_id
        ";

        return $this->db->queryAffects($sql, array('user_role_id' => $id));
    }

    /**
     * Disable all users by changing his role to null by providing role id.
     *
     * @param int $id
     * @return bool|int
     */
    public function disableUsersByRole($id)
    {
        $sql = "
            UPDATE  _db_core_users
		    SET     user_role = null
		    WHERE   user_role = :user_role
        ";

        return $this->db->queryAffects($sql, array('user_role' => $id));
    }

    /**
     * Deletes a specific user by given ID.
     *
     * @param int $id
     * @return string
     */
    public function deleteUser($id)
    {
        $sql = "
            DELETE FROM _db_core_users
            WHERE user_id = :user_id
        ";

        $results = $this->db->queryAffects($sql, array('user_id' => $id));

        if ($results) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * Check if user is a root user.
     *
     * @param int $user_id If not logged in user, what user should be checked (primary role check only).
     * @return boolean
     */
    public function isRoot($user_id = 0)
    {
        $sql = "
            SELECT  user_role
			FROM    _db_core_users
			WHERE   user_id = :user_id
        ";

        if (!empty($user_id)) {
            if ($this->configuration['user_id'] == $user_id) {
                if ($this->configuration['user_role'] == $this->configuration['root_role']) {
                    return true;
                } else {
                    return false;
                }
            } else {
                $check_role_id = $this->db->querySingle($sql, array('user_id' => $user_id));
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

        $conf['user_id']           = empty($_SESSION['user_id'])                ? 0
            : $_SESSION['user_id'];
        $conf['user_name']         = empty($_SESSION['user_name'])              ? ''
            : $_SESSION['user_name'];
        $conf['user_display_name'] = empty($_SESSION['user_display_name'])      ? ''
            : $_SESSION['user_display_name'];
        $conf['user_role']         = empty($_SESSION['user_role'])              ? 0
            : $_SESSION['user_role'];
        $conf['user_email']        = empty($_SESSION['user_email'])             ? ''
            : $_SESSION['user_email'];
        $conf['user_language']     = empty($_SESSION['user_language'])          ? ''
            : $_SESSION['user_language'];
        $conf['user_region']       = empty($_SESSION['user_region'])            ? ''
            : $_SESSION['user_region'];
        $conf['user_timezone']     = empty($_SESSION['user_timezone'])          ? ''
            : $_SESSION['user_timezone'];
        $conf['user_locale']       = empty($_SESSION['user_locale'])            ? $this->core->formatLocale()
            : $_SESSION['user_locale'];
    }

    /**
     * Actual processing of login page.
     */
    public function controlLogin()
    {
        if (!isset($_SESSION['user_id']) || !empty($_POST['login']) || !empty($_REQUEST['logout'])) {
            $this->login->controlLogin();
        }
        $this->userConfig();
    }

    /**
     * This method logs error and success entries to the database.
     *
     * @return int
     */
    public function logThis()
    {
        $sql = "
            INSERT INTO _db_core_logs
                (id, log_type, log_description, log_time, user_id,
                user_display_name, node_id, file_name, node_name, user_ip)
		    VALUES
			    (NULL, :log_type, :log_description, :log_time, :user_id,
                :user_display_name, :node_id, :file_name, :node_name, :user_ip)
        ";
        $this->db->prepare($sql);

        $config    = $this->configuration;
        $log_array = $this->logArray;

        // Check if we need to log.
        if (!empty($log_array) && $this->configuration['system_logging'] == true) {
            // Set.
            $navigation          = $this->navigation->navigation;
            // Log types are :
            // 1 = OK
            // 2 = Warning
            // 3 = Critical
            // 4 = Log-in
            // 5 = Log-out
            foreach ($log_array as $logged_data) {
                if (empty($logged_data['timestamp']))
                    $logged_data['timestamp'] = $config['time'];
                if (empty($logged_data['user_id']))
                    $logged_data['user_id'] = $config['user_id'];
                if (empty($logged_data['logged_by']))
                    $logged_data['logged_by'] = $config['user_display_name'];
                if (empty($logged_data['node_id']))
                    $logged_data['node_id'] = $config['m'];
                if (empty($logged_data['file_name']) && !empty($navigation[$config['m']]['node_link'])) {
                    $logged_data['file_name'] = $navigation[$config['m']]['node_link'];
                } else {
                    $logged_data['file_name'] = ___('N/A');
                }
                if (empty($logged_data['node_name']) && !empty($navigation[$config['m']]['node_name'])) {
                    $logged_data['node_name'] = $navigation[$config['m']]['node_name'];
                } else {
                    $logged_data['node_name'] = ___('N/A');
                }
                if (empty($logged_data['user_ip']))
                    $logged_data['user_ip'] = $this->userIp();

                if (!empty($logged_data['log_type']) || !empty($logged_data['log_description'])) {
                    $this->db->execute(array(
                        'log_type'          => $logged_data['log_type'],
                        'log_description'   => $logged_data['log_description'],
                        'log_time'          => $logged_data['timestamp'],
                        'user_id'           => $logged_data['user_id'],
                        'user_display_name' => $logged_data['logged_by'],
                        'node_id'           => $logged_data['node_id'],
                        'file_name'         => $logged_data['file_name'],
                        'node_name'         => $logged_data['node_name'],
                        'user_ip'           => $logged_data['user_ip']
                    ));
                }
            }
            return $this->db->affectedRows();
        }
        return false;
    }
}
