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
        $this->view->set('viewlogs', $this->navigation->selfUrl('view=logs'));
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
            return $this->template->outputMenu();
        }

        // Read plugin config.
        if ($this->G('info')) {
            $modalinfo = $this->repo->pluginConfig($this->G('plugin'));
            if ($modalinfo) {
                $this->view->set('p', $modalinfo);
                return $this->view->getView('info-modal.html');
            } else {
                $this->template->critical(sprintf(__('Could not download or locate config for %s'),
                    $this->G('plugin')));
            }
        }

        // This set of actions is normally runs in sequence once after the other as required.
        if ($this->G('action')) {

            $result = false;
            switch ($this->G('action')) {
                // Will download and move to plugin folder if required.
                case 'dependencies':
                    return $this->repo->pluginDependsCollector($this->G('plugin'));
                    break;
                case 'prepare':
                    $result = $this->repo->pluginPrepare($this->G('plugin'));
                    if ($result == false)
                        $this->template->critical(sprintf(__('Could not prepare plugin %s'),
                            $this->G('plugin')));
                    break;
                case 'download':
                    $result = $this->repo->pluginPrepareDownload($this->G('plugin'));
                    if ($result == false)
                        $this->template->critical(sprintf(__('Could not download or locate plugin %s'),
                            $this->G('plugin')));
                    break;
                case 'refresh':
                    return $this->repoRows($this->G('plugin'));
                    break;
                case 'update':
                    return $this->checkUpdates($this->G('plugin'));
                    break;
            }
            return $result;
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

        return false;
    }
}

return 'PluginActivation';
