<?php

class PHPDS_readUserQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			user_id, user_display_name, user_name, user_email, user_role, date_registered,
			user_role_name
	    FROM
			_db_core_users t1
		LEFT JOIN
		  _db_core_user_roles t2
		ON
		  t1.user_role = t2.user_role_id
    ";

    public function invoke($parameters = null)
    {
        $navigation = $this->navigation;
        $core       = $this->core;

        // Initiate pagination plugin.
        $pagination = $this->factory('pagination');
        $pagination->columns = array(
            _('Id') => 'user_id',
            _('Name') => 'user_display_name',
            _('Login') => 'user_name',
            _('Email') => 'user_email',
            _('Role') => 'user_role_name',
            _('Date') => 'date_registered'
        );
        $select_users = $pagination->query($this->sql);
        $RESULTS['pagination'] = $pagination->navPages();
        $RESULTS['searchForm'] = $pagination->searchForm();
        $RESULTS['th'] = $pagination->th();

        // Set page to load.
        $page_edit   = $navigation->buildURL('user-admin', 'edit-user=');
        $page_delete = $navigation->buildURL(null, 'delete-user=');

        foreach ($select_users as $select_user_array) {
            $u = $select_user_array;

            $RESULTS['list'][] = array(
                'user_id' => $u['user_id'],
                'user_display_name' => $u['user_display_name'],
                'user_name' => $u['user_name'],
                'user_email' => $u['user_email'],
                'user_role' => $u['user_role'],
                'date_registered' => $core->formatTimeDate($u['date_registered'], 'short'),
                'user_role_name' => $u['user_role_name'],
                'edit_role_url' => $page_edit . $u['user_id'],
                'delete_role_url' => $page_delete . $u['user_id']
            );
        }
        if (! empty($RESULTS['list'])) {
            return $RESULTS;
        } else {
            $RESULTS['list'] = array();
            return $RESULTS;
        }
    }
}
