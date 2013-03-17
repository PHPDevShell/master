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

        // Pre-checks.
        $this->canPluginManagerWrite();
    }

    public function execute()
    {
        /////////////////////////////////////////////////
        // Call current plugins status from database. ///
        /////////////////////////////////////////////////
        // Read plugin directory.
        $RESULTS = $this->db->invokeQuery('PluginManager_readRepository');

        // Load views.
        $view = $this->factory('views');

        // Set Array.
        $view->set('RESULTS', $RESULTS);
        // $view->set('log', $log);

        // Output Template.
        $view->show();
    }

    public function viaAjax()
    {

        $log[]         = '';

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

            // Plugin log
            if (!empty($this->pm->log)) {
                $log["{$plugin}"] = $this->pm->log;
            } else {
                $log["{$plugin}"] = '';
            }

            /////////////////////////////////////////////////////////////////////
            // End save is submitted... /////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////
        }
    }

    public function canPluginManagerWrite()
    {
        $path = $this->configuration['absolute_path'] . 'plugins';
        $repo = $path . '/repository.json';


        if (!is_writable($path)) {
            $this->template->critical(sprintf(__('Plugin manager cannot write to: %s'), $path));
        } else {
            if (!is_writable($repo)) {
                $this->template->critical(sprintf(__('Plugin manager cannot write to repository: %s'), $repo));
            }
        }
    }

    function is_removeable($dir)
    {
        $folder = opendir($dir);
        while ($file = readdir($folder))
            if ($file != '.' && $file != '..' &&
                (!is_writable($dir . "/" . $file) ||
                    (is_dir($dir . "/" . $file) && !is_removeable($dir . "/" . $file)))
            ) {
                closedir($dir);
                return false;
            }
        closedir($dir);
        return true;
    }
}

return 'PluginActivation';
