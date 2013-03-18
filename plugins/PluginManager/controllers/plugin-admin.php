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
        // $view->set('log', $log);

        // Output Template.
        $view->show();
    }

    public function viaAjax()
    {
        // Attempt repo update.
        if ($this->G('update') == 'repo') {
            return $this->updateRepository();
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
        $config = $this->configuration;
        $path   = $config['absolute_path'] . 'plugins';
        $repo   = $path . '/repository.json';
        $size   = filesize($repo);

        $ch = curl_init($config['repository']);
        $fp = fopen($repo, "w");

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $curl     = curl_exec($ch);
        $sizecurl = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        fclose($fp);
        if ($curl) {
            if ($size == $sizecurl) {
                $this->template->info(__('Repository was up to date.'));
                print 'false';
            } else {
                $this->template->ok(__('Repository updated with plugins.'));
                return 'true';
            }
        } else {
            return 'false';
        }
    }
}

return 'PluginActivation';
