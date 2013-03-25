<?php
/**
 * Manages plugin relations read action.
 *
 */
class pluginRepository extends PHPDS_dependant
{
    public function read()
    {
        return $this->handleRepositoryData($this->readJsonRepo());
    }

    private function readJsonRepo()
    {
        $config = $this->configuration;

        $jsonfile = $config['absolute_path'] . 'plugins/repository.json';
        if (file_exists($jsonfile)) {
            $json      = file_get_contents($jsonfile);
            $repoarray = json_decode($json, true);
            if (is_array($repoarray) && !empty($repoarray)) {
                return $repoarray;
            } else {
                throw new PHPDS_exception('Local repository plugins/repository.json file empty?');
            }
        } else {
            throw new PHPDS_exception('Local repository file missing or unreadable in plugins/repository.json');
        }
    }

    private function handleRepositoryData($repoarray)
    {
        $remote = array();

        // Plugins available in repository.
        foreach ($repoarray['plugins'] as $name => $data) {
            $remote[$name] = array(
                'name'      => $name,
                'desc'      => $data['desc'],
                'repo'      => $data['repo'],
                'installed' => $this->isPluginInstalled($name)
            );
        }

        // Check plugins that exists locally.
        $local     = $this->localAvailablePlugins($remote);
        $allpugins = $this->sortPluginByName($remote, $local);

        return $allpugins;
    }

    private function localAvailablePlugins($remote)
    {
        // Plugins available locally.
        $directory      = $this->configuration['absolute_path'] . 'plugins';
        $base           = $directory . '/';
        $subdirectories = opendir($base);
        $local          = array();

        while (false !== ($object = readdir($subdirectories))) {
            if (ctype_alnum($object)) {
                if (empty($remote[$object])) {
                    $local[$object] = $this->getLocalPluginInfo($base, $object);
                } else {
                    $local[$object] = $this->getLocalPluginInfo($base, $object, $remote[$object]);
                }
            }
        }

        return $local;
    }

    private function getLocalPluginInfo($directory, $plugin, $remote = array())
    {
        if (empty($remote)) {
            $installed = $this->isPluginInstalled($plugin);
            $repo      = '';
        } else {
            $installed = $remote['installed'];
            $repo      = $remote['repo'];
        }
        // get local plugins with config files, ignore the rest.f
        $xmlconfig = $directory . $plugin . '/config/plugin.config.xml';
        $localxml  = @simplexml_load_file($xmlconfig);
        if (!empty($localxml) && !empty($localxml->name)) {
            $local = array(
                'name'      => (string)$localxml->name,
                'desc'      => rtrim((string)$localxml->description, '.'),
                'repo'      => $repo,
                'local'     => true,
                'installed' => $installed
            );
        } else {
            $local = array(
                'name'      => $plugin,
                'desc'      => '',
                'repo'      => $repo,
                'cfgerror'  => $xmlconfig,
                'broken'    => true,
                'local'     => true,
                'installed' => $installed
            );
        }

        return $local;
    }

    private function sortPluginByName($remote, $local)
    {
        $reposorted = array();
        $repo       = array_merge($remote, $local);
        ksort($repo);
        foreach ($repo as $value) {
            $reposorted[] = $value;
        }
        return $reposorted;
    }

    private function isPluginInstalled($plugin)
    {
        $p         = $this->config->pluginsInstalled;
        $installed = false;
        if (!empty($p[$plugin]['status'])) {
            switch ($p[$plugin]['status']) {
                case 'install':
                    $installed = true;
                    break;
                default:
                    $installed = false;
                    break;
            }
        }
        return $installed;
    }

    public function updateRepository()
    {
        $update = $this->db->invokeQuery('PluginManager_updateRepository');
        if ($update === 'false') {
            $this->template->info(__('Repository was up to date.'));
        } else {
            $this->template->ok(__('New plugins added to repository.'));
        }
        return $update;
    }

    public function readPluginConfig($plugin)
    {
        return $this->db->invokeQuery('PluginManager_getJsonInfo', $plugin);
    }

    static function someTest()
    {
        echo "Hello World";
    }
}