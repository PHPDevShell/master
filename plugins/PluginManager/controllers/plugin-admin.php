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

        // Set Array.
        $this->view->set('RESULTS', $RESULTS);
        $this->view->set('updaterepo', $this->navigation->selfUrl('update=repo'));
        $this->view->set('updateplugins', $this->navigation->selfUrl('update=plugins'));
        $this->view->set('updatemenus', $this->navigation->selfUrl('update=menus'));
        $this->view->set('updatelocal', $this->navigation->selfUrl());
        // $view->set('log', $log);

        // Output Template.
        $this->view->show();
    }

    public function viaAjax()
    {
        // Repo update.
        if ($this->G('update') == 'repo') {
            return $this->repo->updateRepository();
        }

        // Refresh menus.
        if ($this->G('update') == 'menus') {
            $this->template->outputMenu();
        }

        // Read plugin config.
        if ($this->G('info')) {
            $modalinfo = $this->repo->pluginModalInfo($this->G('plugin'));
            if ($modalinfo) {
                $this->view->set('p', $modalinfo);
                return $this->view->getView('info-modal.html');
            }
        }

        // This set of actions is normally runs in sequence once after the other as required.
        if ($this->G('action')) {
            switch ($this->G('action')) {
                // Will download and move to plugin folder if required.
                case 'prepare':
                    return $this->repo->pluginPrepare($this->G('plugin'));
                    break;
                case 'download':
                    return $this->repo->pluginPrepareDownload($this->G('plugin'));
                    break;
            }
        }

        if ($this->P('action')) {
            switch ($this->P('action')) {
                case 'extract':
                    return $this->repo->pluginExtraction($this->P('plugin'), $this->P('zip'));
                    break;
                case 'install':
                    return $this->pm->setPlugin($this->P('plugin'), 'install');
                    break;
            }
        }

        // Check for updates online for installed plugins.
        if ($this->P('update') == 'check') {
            //return $this->repo->checkOnlineUpdates();
        }

        // Plugin activation starts.
        /**
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
       */
    }
}

return 'PluginActivation';
