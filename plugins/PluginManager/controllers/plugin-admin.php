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

    /**
     *
     */
    public function onLoad()
    {
        $this->factory = $this->factory('pluginFactory');
        $this->repo    = $this->factory('pluginRepository');
    }

    /**
     * @return mixed|void
     */
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

    /**
     * @param null $plugin
     * @return mixed
     */
    public function repoRows($plugin=null)
    {
        // Read plugin directory.
        $RESULTS = $this->repo->initiateRepository($plugin);

        // Set Array.
        $this->view->set('RESULTS', $RESULTS);

        return $this->view->getView('repo-rows.html');
    }

    /**
     * @return bool|mixed|string
     */
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
                case 'refresh':
                    return $this->repoRows($this->G('plugin'));
                    break;
            }
        }

        // Perform a plugin action related to database and file changes.
        if ($this->P('action')) {
            switch ($this->P('action')) {
                case 'extract':
                    $result = $this->repo->pluginExtraction($this->P('plugin'), $this->P('zip'));
                    break;
                case 'install':
                    $result = $this->factory->install($this->P('plugin'));
                    if ($result == true) $this->template->ok(sprintf("Plugin %s installed", $this->P('plugin')));
                    break;
                case 'upgrade':
                    $result = $this->factory->upgrade($this->P('plugin'));
                    if ($result == true) $this->template->ok(sprintf("Plugin %s upgraded", $this->P('plugin')));
                    break;
                case 'reinstall':
                    $result = $this->factory->reinstall($this->P('plugin'));
                    if ($result == true) $this->template->ok(sprintf("Plugin %s reinstalled", $this->P('plugin')));
                    break;
                case 'uninstall':
                    $result = $this->factory->uninstall($this->P('plugin'));
                    if ($result == true) $this->template->ok(sprintf("Plugin %s uninstalled", $this->P('plugin')));
                    break;
                case 'delete':
                    $result = $this->repo->pluginDelete($this->P('plugin'));
                    if ($result == true) $this->template->ok(sprintf("Plugin %s removed", $this->P('plugin')));
                    break;
                default:
                    $result = null;
                    break;
            }
            if (!empty($this->factory->log)) {
                PU_silentHeader("ajaxPluginManagerLog: " . json_encode($this->factory->log));
            }
            return $result;
        }

        // Check for updates online for installed plugins.
        if ($this->P('update') == 'check') {
            //return $this->repo->checkOnlineUpdates();
        }
    }
}

return 'PluginActivation';
