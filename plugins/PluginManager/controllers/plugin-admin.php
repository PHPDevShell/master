<?php

class PluginActivation extends PHPDS_controller
{
    /**
     * @var pluginFactory $factory
     */
    public $factory;

    /**
     * @var pluginRepository $repo
     */
    public $repo;


    public function onLoad()
    {
        $this->factory = $this->factory('pluginFactory');
        $this->repo    = $this->factory('pluginRepository');
    }

    public function execute()
    {
        // Pre-checks.
        $this->repo->canPluginManagerWork();

        /////////////////////////////////////////////////
        // Call current plugins status from database. ///
        /////////////////////////////////////////////////
        // Read plugin directory.
        $RESULTS = $this->repo->initiateRepository();

        // Load views.
        $view = $this->factory('views');

        // Set Array.
        $view->set('RESULTS', $RESULTS);
        $view->set('updaterepo', $this->navigation->selfUrl('update=repo'));
        $view->set('updateplugins', $this->navigation->selfUrl('update=plugins'));
        $view->set('updatemenus', $this->navigation->selfUrl('update=menus'));
        $view->set('updatelocal', $this->navigation->selfUrl());
        // $view->set('log', $log);

        // Output Template.
        $view->show();
    }

    public function viaAjax()
    {
        // Repo update.
        if ($this->G('update') == 'repo') {
            return $this->repo->updateRepository();
        }

        // Refresh menus.
        if ($this->G('update') == 'menus') {
            return $this->template->outputMenu();
        }

        // Read plugin config.
        if ($this->G('info')) {
            $modal =  $this->repo->pluginModalInfo($this->G('plugin'));
            if (is_array($modal)) {
                $view = $this->factory('views');
                $view->set('p', $modal);
                return $view->get('info-modal.html');
            } else if ($modal) {
                return $modal;
            } else {
                $this->template->warning(sprintf(__('No info available for %s'), $this->G('plugin')));
                return 'false';
            }
        }

        // Plugin activation starts.
        if ($this->P() && $this->user->isRoot()) {
            $plugin = $this->security->post['plugin'];
            /////////////////////////////////////////////////////////////////////
            // When save is submitted... ////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////
            // Install   = 1 ////////////////////////////////////////////////////
            // Uninstall = 2 ////////////////////////////////////////////////////
            // Reinstall = 3 ////////////////////////////////////////////////////
            // Upgrade   = 4 ////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////
            if (isset($this->security->post['install'])) {
                // Execute plugin method.
                $this->pm->setPlugin($plugin, 'install');
            } else if (isset($this->security->post['uninstall'])) {
                // Execute plugin method.
                $this->pm->setPlugin($plugin, 'uninstall');
            } else if (isset($this->security->post['reinstall'])) {
                // Execute plugin method.
                $this->pm->setPlugin($plugin, 'reinstall');
            } else if (isset($this->security->post['upgrade'])) {
                // Execute plugin method.
                $this->pm->setPlugin($plugin, 'upgrade', $this->security->post['version']);
            } else if (isset($this->security->post['auto_upgrade'])) {
                // Execute plugin method.
                $this->pm->setPlugin($plugin, 'auto_upgrade');
            } else if (isset($this->security->post['set_logo'])) {
                // Execute plugin method.
                $this->pm->setPlugin($plugin, 'set_logo');
            }
            /////////////////////////////////////////////////////////////////////
            // End save is submitted... /////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////
        }
    }
}

return 'PluginActivation';
