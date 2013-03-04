<?php

/**
 * Controller Class: User Role Administration
 * @author Jason Schoeman
 * @return string
 */
class UserRoleAdmin extends PHPDS_controller
{
    public $crud;
    public $id;

    public function onLoad()
    {
         /* @var $crud crud */
        $crud = $this->factory('crud');
        $this->crud = $crud;
    }

	/**
	 * Execute Controller
	 * @author Jason Schoeman
	 */
	public function execute()
	{
        $permission = array();

        if ($this->G('edit-role') || $this->P('save')) {
            $this->template->heading(__('Edit Access Role'));
        } else {
            $this->template->heading(__('Add Access Role'));
        }

        if ($this->G('edit-role')) {
            $permission = $this->editAction();
        }

        if ($this->P('save') || $this->P('new')) {
            $permission = $this->saveAction();
        }

		if ($this->P('new')) {
			$this->crud->f->user_role_id = 0;
			$this->crud->f->user_role_name = '';
			$this->crud->f->user_role_note = '';
		}

        $this->view($this->db->invokeQuery('PHPDS_readNodesQuery', $permission));
	}

    private function view($node_item_options)
    {
        $crud = $this->crud;
        $view = $this->factory('views');

        $view->set('self_url', $this->navigation->selfUrl());
        $view->set('list_url', $this->navigation->buildUrl('role-admin-list'));
        $view->set('delete_url', empty($crud->f->user_role_id) ? '' : $this->navigation->buildUrl('role-admin-list', 'delete-role=' . $crud->f->user_role_id));
        $view->set('user_role_id', $crud->f->user_role_id);
        $view->set('user_role_name', $crud->f->user_role_name);
        $view->set('user_role_note', $crud->f->user_role_note);

        $view->set('tagger',
            $this->tagger->tagArea('role',
            $crud->f->user_role_id,
            $this->P('tagger_name'),
            $this->P('tagger_value'),
            $this->P('tagger_id')));

        $view->set('nodes_select', $node_item_options);

        $view->show();
    }

    private function editAction()
    {
        $crud = $this->crud;
        $crud->importFields($this->db->invokeQuery('PHPDS_readRoleUserQuery', $this->G('edit-role')));
        return $this->db->invokeQuery('PHPDS_readRoleNodeQuery', $this->G('edit-role'));
    }

    private function saveAction()
    {
        $crud = $this->crud;
        $crud->addField('user_role_id');
        $crud->addField('user_role_note');

        if (!$crud->is('user_role_name')) {
            $crud->error();
        }

        if ($this->P('permission')) {
            $permission = $this->P('permission');
        } else {
            $permission = array();
        }

        if ($this->db->doesRecordExist('_db_core_user_roles', 'user_role_name', "{$crud->f->user_role_name}", 'user_role_id', "{$crud->f->user_role_id}") == true)
            $crud->errorElse(sprintf(__('%s exists already.'), $crud->f->user_role_name), 'user_role_name');

        if ($crud->ok()) {
            $crud->f->user_role_id = $this->db->invokeQuery('PHPDS_writeRoleQuery', $crud->f->user_role_id, $crud->f->user_role_name, $crud->f->user_role_note);
            $this->db->invokeQuery('PHPDS_deletePermissionsQuery', $crud->f->user_role_id);
            $this->db->invokeQuery('PHPDS_writePermissionsQuery', $crud->f->user_role_id, $permission);
            $this->tagger->tagUpdate('role',
                $crud->f->user_role_id,
                $this->P('tagger_name'),
                $this->P('tagger_value'),
                $this->P('tagger_id'));
            $this->template->ok(sprintf(__('Saved %s.'), $crud->f->user_role_name));
        } else {
            $this->template->warning(__("Cannot save, check errors!"));
            $crud->errorShow();
        }

        return $permission;
    }

    public function viaAJAX()
    {
        if ($this->G('delete-tag')) {
            if ($this->tagger->tagDelete($this->G('delete-tag'))) {
                $this->template->ok(sprintf("Tag id %u deleted", $this->G('delete-tag')));
                return 'true';
            } else {
                return 'false';
            }
        }
        if ($this->P('user_role_name_watch')) {
            if ($this->db->invokeQuery('PHPDS_readRoleNameQuery', $this->P('user_role_name_watch'))) {
                $this->crud->error("Role already exist", 'user_role_name');
                $this->crud->errorShow();
                return 'true';
            } else {
                return 'false';
            }
        }
        if ($this->P('save')) {
            $this->saveAction();
            return 'true';
        }
    }
}

return 'UserRoleAdmin';