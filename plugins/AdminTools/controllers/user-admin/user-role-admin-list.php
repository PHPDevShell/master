<?php

class UserRoleAdminList extends PHPDS_controller
{
	public function execute()
	{
        $this->template->heading(__('Access Roles'));
        $this->remoteDeleteRole();
		$this->view($this->db->invokeQuery('PHPDS_readRoleQuery'));
	}

    private function view($RESULTS)
    {
        $view = $this->factory('views');

        $view->set('self_url', $this->navigation->buildURL());
        $view->set('new', $this->navigation->buildURL('role-admin'));
        $view->set('pagination', $RESULTS['pagination']);
        $view->set('searchForm', $RESULTS['searchForm']);
        $view->set('th', $RESULTS['th']);
        $view->set('show', !empty($RESULTS['list']));
        $view->set('RESULTS', $RESULTS['list']);

        $view->show();
    }

    private function deleteRole ()
    {
        $iddelete = $this->G('delete-role');
        $deleted_role = $this->user->deleteRole($iddelete);
        if ($deleted_role) {
            $this->template->ok(sprintf(__("Role %s deleted."), $deleted_role));
            return true;
        } else {
            return false;
        }
    }

    public function remoteDeleteRole ()
    {
        if ($this->G('delete-role')) {
            return $this->deleteRole();
        }
    }

    public function viaAJAX()
    {
        if ($this->G('delete-role')) {
            return ($this->deleteRole()) ? 'true' : 'false';
        }
    }
}

return 'UserRoleAdminList';
