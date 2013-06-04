<?php

class UserRoleAdminList extends PHPDS_controller
{
	public function execute()
	{
        $this->template->heading(__('Access Roles'));
        $this->remoteDeleteRole();
		$this->forView($this->db->invokeQuery('PHPDS_readRoleQuery'));
	}

    private function forView($RESULTS)
    {
        $this->view->set('self_url', $this->navigation->buildURL());
        $this->view->set('new', $this->navigation->buildURL('role-admin'));
        $this->view->set('pagination', $RESULTS['pagination']);
        $this->view->set('searchForm', $RESULTS['searchForm']);
        $this->view->set('th', $RESULTS['th']);
        $this->view->set('show', !empty($RESULTS['list']));
        $this->view->set('RESULTS', $RESULTS['list']);

        $this->view->show();
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
