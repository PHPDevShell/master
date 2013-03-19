<?php

class PluginActivation extends PHPDS_controller
{
    /**
     * @var pluginManager $pm
     */
    public $pm;

    public function onLoad()
    {
        $this->pm = $this->factory('pluginManager');
    }

    public function execute()
    {
        // Pre-checks.
        $this->canPluginManagerWork();

        /////////////////////////////////////////////////
        // Call current plugins status from database. ///
        /////////////////////////////////////////////////
        // Read plugin directory.
        $RESULTS = $this->db->invokeQuery('PluginManager_readRepository');

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
            return $this->updateRepository();
        }

        // Refresh menus.
        if ($this->G('update') == 'menus') {
            return $this->template->outputMenu();
        }

        // Read plugin config.
        if ($this->G('info')) {
            return $this->readPluginConfig($this->G('plugin'));
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

    public function canPluginManagerWork()
    {
        $path = $this->configuration['absolute_path'] . 'plugins';
        $repo = $path . '/repository.json';

        if (!is_writable($path)) {
            $this->template->critical(sprintf('Plugin manager cannot write to: %s', $path));
        } else {
            if (!is_writable($repo)) {
                $this->template->critical(sprintf('Plugin manager cannot write to repository: %s', $repo));
            }
        }

        if (!function_exists('curl_init')) {
            $this->template->critical('Plugin manager required the cURL PHP Extention to work.');
        }
    }

    private function isWritable($dir)
    {
        $folder = opendir($dir);
        while ($file = readdir($folder))
            if ($file != '.' && $file != '..' &&
                (!is_writable($dir . "/" . $file) ||
                    (is_dir($dir . "/" . $file) && !$this->isWritable($dir . "/" . $file)))
            ) {
                closedir($dir);
                return false;
            }
        closedir($dir);
        return true;
    }

    private function updateRepository()
    {
        return $this->db->invokeQuery('PluginManager_updateRepository');
    }

    private function readPluginConfig($plugin)
    {
        return $this->db->invokeQuery('PluginManager_getJsonInfo', $plugin);
    }
}

return 'PluginActivation';
