<?php

class PluginManager extends PHPDS_controller
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
     * Always loads independent of ajax or not.
     */
    public function onLoad()
    {
        $this->factory = $this->factory('pluginFactory');
        $this->repo    = $this->factory('pluginRepository');
    }

    /**
     * Main execution.
     * @return mixed|void
     */
    public function execute()
    {
        // Pre-checks.
        $this->repo->canPluginManagerWork();

        $this->view->set('repo_rows', $this->repoRows());
        $this->view->set('update_repo', $this->navigation->selfUrl('update=repo'));
        $this->view->set('check_updates', $this->navigation->selfUrl('check=updates'));
        $this->view->set('check_dependencies', $this->navigation->selfUrl('check=dependencies'));
        $this->view->set('update_menus', $this->navigation->selfUrl('update=menus'));
        $this->view->set('refresh_plugins', $this->navigation->selfUrl('refresh=plugins'));
        $this->view->set('update_local', $this->navigation->selfUrl());

        // Output Template.
        $this->view->show();

        // Add js to view.
        $this->view->jsAsset('plugins/PluginManager/js/asset.js');
    }

    /**
     * Ajax controller.
     *
     * @return bool|mixed|string
     */
    public function viaAjax()
    {
        // Refresh plugins.
        if ($this->G('refresh') == 'plugins') {
            return $this->execute();
        }

        // Repo update.
        if ($this->G('update') == 'repo') {
            $result = $this->repo->updateRepository();
            if (!$result) {
                $this->template->info(__('Repository was up to date.'));
            } else {
                $this->template->ok(__('New plugins added to repository.'));
                return $result;
            }
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

        // Check plugins and health.
        if ($this->G('check')) {
            return $this->getCheck();
        }

        // This set of actions is normally runs in sequence once after the other as required.
        if ($this->G('action')) {
            return $this->getAction();
        }

        // Perform a plugin action related to database and file changes.
        if ($this->P('action')) {
            return $this->postAction();
        }

        return false;
    }

    /**
     * Does checks for repository and processes results also handles some notifications.
     *
     * @return bool|string
     */
    protected function getCheck()
    {
        $result = false;
        switch ($this->G('check')) {
            case 'updates':
                $result = $this->repo->checkPlugins();
                if (!$result)
                    $this->template->warning(__('No plugins installed to check updated for'));
                break;
            case 'msg-dep-ok':
                $this->template->ok(__('All dependencies met'));
                return false;
                break;
            case 'msg-dep-broken':
                $this->template->warning(__('Dependencies are broken'));
                return true;
                break;
            case 'msg-alluptodate':
                $this->template->ok(__('No updates available'));
                return false;
                break;
            case 'msg-updatesavail':
                $this->template->warning(__('There are updates available'));
                return true;
                break;
            case 'update-process':
                $result = $this->repo->checkUpdate($this->G('plugin'), $this->G('version'));
                break;
            case 'dependencies':
                $result = $this->repo->checkDependencies();
                if (!$result)
                    $this->template->ok(__('All plugin dependencies met'));
                break;
        }
        return $result;
    }

    /**
     * Getting information for plugins.
     *
     * @return bool|mixed|string
     */
    protected function getAction()
    {
        $result = false;
        switch ($this->G('action')) {
            case 'dependencies':
                return $this->repo->pluginCollector($this->G('plugin'));
                break;
            case 'prepare':
                $result = $this->repo->pluginPrepare($this->G('plugin'), $this->G('actiontype'));
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
            case 'final-dep-check':
                $result = $this->repo->finalDependencyCheck($this->G('plugin'));
                if ($result !== true) {
                    $this->template->critical(sprintf(
                        __('Dependency constraint not met, looking for class %s in plugin %s'),
                        $result['error']['class'], $result['error']['plugin']));
                    $result = false;
                }
                break;
            case 'refresh':
                return $this->repoRows($this->G('plugin'));
                break;
        }
        return $result;
    }

    /**
     * All actions involving database action for a plugin.
     *
     * @return bool|null|string
     */
    protected function postAction()
    {
        switch ($this->P('action')) {
            case 'extract':
                $result = $this->repo->pluginExtraction(
                    $this->P('plugin'), $this->P('zip'), $this->P('actiontype')
                );
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

    /**
     * Gets and draws all plugin repo rows.
     *
     * @param string $plugin
     * @return string (html)
     */
    protected function repoRows($plugin=null)
    {
        // Read plugin directory.
        $RESULTS = $this->repo->initiateRepository($plugin);

        // Set Array.
        $this->view->set('RESULTS', $RESULTS);

        return $this->view->getView('repo-rows.html');
    }
}

return 'PluginManager';
