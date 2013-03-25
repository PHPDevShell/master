<?php

class USER_guestRoleNameQuery extends PHPDS_query
{
    protected $sql = "
			SELECT
				user_role_name
			FROM
				_db_core_user_roles
			WHERE
				user_role_id = '%u'
		";

    protected $singleValue = true;
    protected $focus = 'user_role_name';

    public function checkParameters(&$parameters = null)
    {
        $settings_array = $this->config->essentialSettings;
        $parameters     = $settings_array['guest_role'];
        return true;
    }
}

class USER_isRootQuery extends PHPDS_query
{
    protected $sql = "
			SELECT
				user_role
			FROM
				_db_core_users
			WHERE
				user_id = '%u'
		";

    protected $singleValue = true;
    protected $focus = 'user_role';

    public function checkParameters(&$parameters = null)
    {
        list($user_id) = $parameters;
        return intval($user_id) > 0;
    }
}

class USER_getRolesQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			user_role
		FROM
			_db_core_users
		WHERE
			user_id = '%u'
	";

    protected $singleValue = true;

    public function invoke($parameters = null)
    {
        list($user_id) = $parameters;
        $configuration = $this->configuration;
        $config        = $this->config;

        if (empty($user_id))
            $user_id = (!empty($configuration['user_id'])) ? $configuration['user_id'] : 0;

        // Check if user is a guest.
        if (!empty($user_id)) {
            if ($user_id == $configuration['user_id']) {
                return $configuration['user_role'];
            } else {
                $role = parent::invoke($user_id);
                return $role;
            }
        } else {
            $settings   = $config->essentialSettings;
            $guest_role = $settings['guest_role'];
            if (empty($guest_role)) throw new PHPDS_exception('Unable to get the GUEST ROLE from essential settings.');
            return $guest_role;
        }
    }
}

class USER_roleExistQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			user_role_id
		FROM
			_db_core_user_roles
	";

    public function invoke($parameters = null)
    {
        $role_id = $parameters[0];
        $user    = $this->user;

        if (empty($user->rolesArray)) {
            $roles = parent::invoke();
            foreach ($roles as $results_array) {
                $user->rolesArray[$results_array['user_role_id']] = true;
            }
        }
        if (!empty($user->rolesArray[$role_id])) {
            return true;
        } else {
            return false;
        }
    }
}

class USER_belongsToRoleQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			t1.user_id
		FROM
			_db_core_users AS t1
		WHERE
			(t1.user_role = '%u')
		AND
			(t1.user_id = '%u')
	";

    protected $singleRow = true;

    public function invoke($parameters = null)
    {
        list($user_id, $user_role) = $parameters;
        if ($this->user->isRoot($user_id)) return true;

        if (empty($user_id)) {
            (!empty($this->configuration['user_id'])) ? $user_id = $this->configuration['user_id'] : $user_id = null;
        }

        $check_user_in_role_db = parent::invoke(array($user_role, $user_id));

        if ($check_user_in_role_db['user_id'] == $user_id) {
            return true;
        } else {
            return false;
        }
    }
}

class USER_updateUserRoleQuery extends PHPDS_query
{
    protected $sql = "
		UPDATE
			_db_core_users
		SET
			user_role = FALSE
		WHERE
			user_role = '%u'
    ";
    protected $returnId = true;
}

class USER_logThisQuery extends PHPDS_query
{
    protected $sql = "
		INSERT INTO
			_db_core_logs (id, log_type, log_description, log_time, user_id, user_display_name, node_id, file_name, node_name, user_ip)
		VALUES
			%s
		";

    public function invoke($parameters = null)
    {
        $log_array = $parameters[0];
        // Check if we need to log.
        if (!empty($log_array) && $this->configuration['system_logging'] == true) {
            // Set.
            $database_log_string = false;
            $navigation          = $this->navigation->navigation;
            // Log types are :
            // 1 = OK
            // 2 = Warning
            // 3 = Critical
            // 4 = Log-in
            // 5 = Log-out
            foreach ($log_array as $logged_data) {
                if (empty($logged_data['timestamp']))
                    $logged_data['timestamp'] = $this->configuration['time'];
                if (empty($logged_data['user_id']))
                    $logged_data['user_id'] = $this->configuration['user_id'];
                if (empty($logged_data['logged_by']))
                    $logged_data['logged_by'] = $this->configuration['user_display_name'];
                if (empty($logged_data['node_id']))
                    $logged_data['node_id'] = $this->configuration['m'];
                if (empty($logged_data['file_name']) && !empty($navigation[$this->configuration['m']]['node_link'])) {
                    $logged_data['file_name'] = $navigation[$this->configuration['m']]['node_link'];
                } else {
                    $logged_data['file_name'] = ___('N/A');
                }
                if (empty($logged_data['node_name']) && !empty($navigation[$this->configuration['m']]['node_name'])) {
                    $logged_data['node_name'] = $navigation[$this->configuration['m']]['node_name'];
                } else {
                    $logged_data['node_name'] = ___('N/A');
                }
                if (empty($logged_data['user_ip']))
                    $logged_data['user_ip'] = $this->user->userIp();

                $logged_data['log_description'] = $this->protectString($logged_data['log_description']);

                $logged_data = $this->protectArray($logged_data);

                if (!empty($logged_data['log_type']) || !empty($logged_data['log_description']))
                    $database_log_string .= "(NULL, '{$logged_data['log_type']}', '{$logged_data['log_description']}', '{$logged_data['timestamp']}', '{$logged_data['user_id']}', '{$logged_data['logged_by']}', '{$logged_data['node_id']}', '{$logged_data['file_name']}', '{$logged_data['node_name']}', '{$logged_data['user_ip']}'),";
            }
            $database_log_string = rtrim($database_log_string, ',');
            if (!empty($database_log_string))
                parent::invoke($database_log_string);
        }
    }
}


