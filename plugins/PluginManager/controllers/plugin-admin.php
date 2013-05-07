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

        $this->view->set('reporows', $this->repoRows());
        $this->view->set('updaterepo', $this->navigation->selfUrl('update=repo'));
        $this->view->set('updateplugins', $this->navigation->selfUrl('update=plugins'));
        $this->view->set('updatemenus', $this->navigation->selfUrl('update=menus'));
        $this->view->set('updatelocal', $this->navigation->selfUrl());

        // Output Template.
        $this->view->show();
    }

    public function repoRows($plugin=null)
    {
        // Read plugin directory.
        $RESULTS = $this->repo->initiateRepository($plugin);

        // Set Array.
        $this->view->set('RESULTS', $RESULTS);

        return $this->view->getView('repo-rows.html');
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
                case 'delete':
                    return $this->repo->pluginDelete($this->G('plugin'));
                    break;
            }
        }

        // Perform a plugin action related to database changes.
        if ($this->P('action')) {
            switch ($this->P('action')) {
                case 'extract':
                    return $this->repo->pluginExtraction($this->P('plugin'), $this->P('zip'));
                    break;
                case 'install':
                    return $this->factory->install($this->P('plugin'));
                    break;
                case 'upgrade':
                    return $this->factory->upgrade($this->P('plugin'));
                    break;
                case 'reinstall':
                    return $this->factory->reinstall($this->P('plugin'));
                    break;
                case 'uninstall':
                    return $this->factory->uninstall($this->P('plugin'));
                    break;
            }
        }

        // Check for updates online for installed plugins.
        if ($this->P('update') == 'check') {
            //return $this->repo->checkOnlineUpdates();
        }

        return false;
    }
}

return 'PluginActivation';
