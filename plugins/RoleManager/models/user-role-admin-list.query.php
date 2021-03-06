<?php

class PHPDS_readRoleQuery extends PHPDS_query
{
	protected $sql = "
		SELECT  user_role_id, user_role_name, user_role_note
		FROM    _db_core_user_roles
    ";

	public function invoke($parameters = null)
	{
		$navigation = $this->navigation;

		// Initiate pagination plugin.
		$pagination = $this->factory('pagination');
		$pagination->columns = array(
			_('Id') => 'user_role_id',
			_('Name') => 'user_role_name',
			_('Notes') => 'user_role_note'
        );
		$select_user_role = $pagination->query($this->sql);
		$RESULTS['pagination'] = $pagination->navPages();
		$RESULTS['searchForm'] = $pagination->searchForm();
		$RESULTS['th'] = $pagination->th();

		// Set page to load.
        $page_edit   = $navigation->buildURL('role-admin', 'edit-role=');
        $page_delete = $navigation->buildURL(null, 'delete-role=');

		foreach ($select_user_role as $select_user_role_array) {
			$user_role_id = $select_user_role_array['user_role_id'];
			$user_role_name = $select_user_role_array['user_role_name'];
			$user_role_note = $select_user_role_array['user_role_note'];
			$translated_role_name = $user_role_name;

			$RESULTS['list'][] = array(
				'user_role_id' => $user_role_id,
				'translated_role_name' => $translated_role_name,
				'user_role_note' => $user_role_note,
				'edit_role_url' => $page_edit . $user_role_id,
				'delete_role_url' => $page_delete . $user_role_id
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
