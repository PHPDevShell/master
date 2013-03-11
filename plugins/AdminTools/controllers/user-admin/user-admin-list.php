<?php

class UserAdminList extends PHPDS_controller
{
    public function execute()
    {
        $this->template->heading(__('Users'));
        $this->remoteDeleteUser();
        $this->view($this->db->invokeQuery('PHPDS_readUserQuery'));
    }

    private function view($RESULTS)
    {
        $view = $this->factory('views');

        $view->set('self_url', $this->navigation->buildURL());
        $view->set('new', $this->navigation->buildURL('user-admin'));
        $view->set('pagination', $RESULTS['pagination']);
        $view->set('searchForm', $RESULTS['searchForm']);
        $view->set('th', $RESULTS['th']);
        $view->set('show', !empty($RESULTS['list']));
        $view->set('RESULTS', $RESULTS['list']);

        $view->show();
    }

    private function deleteUser ()
    {
        $iddelete = $this->G('delete-user');
        // Delete user.
        $deleted_user = $this->db->deleteQuick('_db_core_user_users', 'user_user_id',  $iddelete, 'user_user_name');
        $this->db->deleteQuick('_db_core_user_user_permissions', 'user_user_id',  $iddelete);
        $this->db->invokeQuery('PHPDS_updateUserQuery',  $iddelete);
        if ($deleted_user) {
            $this->template->ok(sprintf(__("User %s deleted."), $deleted_user));
            return true;
        } else {
            return false;
        }
    }

    public function remoteDeleteUser ()
    {
        if ($this->G('delete-user')) {
            return $this->deleteUser();
        }
    }

    public function viaAJAX()
    {
        if ($this->G('delete-user')) {
            return ($this->deleteUser()) ? 'true' : 'false';
        }
    }
}

return 'UserAdminList';
