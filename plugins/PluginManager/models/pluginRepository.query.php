<?php

class PluginManager_readRepository extends PHPDS_query
{
    private $availablePlugins;

    public function invoke($parameters = null)
    {
        $this->availablePlugins = $this->db->invokeQuery('PluginManager_currentPluginStatusQuery');
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
        $p         = $this->availablePlugins;
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
}

class PluginManager_availableClassesQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			class_name, plugin_folder
		FROM
			_db_core_plugin_classes
		WHERE
			enable = 1
		ORDER BY
			rank
		ASC
	";

    public function invoke($parameters = null)
    {
        $cr    = parent::invoke();
        $class = array();
        // Loop and assign available class names.
        foreach ($cr as $cr_) {
            $class[$cr_['class_name']] = $cr_['plugin_folder'];
        }

        return $class;
    }
}

class PluginManager_currentPluginStatusQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			plugin_folder, status, version
		FROM
			_db_core_plugin_activation
	";

    /**
     * Initiate query invoke command.
     * @param int
     * @return array
     */
    public function invoke($parameters = null)
    {
        $plugin_record_db = parent::invoke();

        // Compile results and save into array to compare against user selected options and already installed plugins.
        if (empty($plugin_record_db)) $plugin_record_db = array();
        foreach ($plugin_record_db as $plugin_record_array) {
            $activation_db[$plugin_record_array['plugin_folder']] = array('status' => $plugin_record_array['status'], 'version' => $plugin_record_array['version']);
        }
        if (!empty($activation_db)) {
            return $activation_db;
        } else {
            return array();
        }
    }
}

class PluginManager_updateRepository extends PHPDS_query
{
    public function invoke($parameters = null)
    {
        $config  = $this->configuration;
        $path    = $config['absolute_path'] . 'plugins';
        $repo    = $path . '/repository.json';
        $oldrepo = $this->readJsonRepo($repo);
        $size    = filesize($repo);

        $ch = curl_init($config['repository']);
        $fp = fopen($repo, "w");

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $curl     = curl_exec($ch);
        $sizecurl = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        fclose($fp);
        $newrepo = $this->readJsonRepo($repo);
        if ($curl) {
            if ($size == $sizecurl) {
                return 'false';
            } else {
                $newplugins = array_diff_assoc($newrepo, $oldrepo);
                if (!empty($newplugins))
                    return json_encode($newplugins);
                else
                    return 'false';
            }
        } else {
            return 'false';
        }
    }

    private function readJsonRepo($repo)
    {
        $json     = file_get_contents($repo);
        $repodata = json_decode($json, true);
        return $repodata['plugins'];
    }
}

class PluginManager_getJsonInfo extends PHPDS_query
{
    public function invoke($parameters = null)
    {
        list($plugin) = $parameters;
        $xmlcfgfile = $this->configuration['absolute_path'] . 'plugins/' . $plugin . '/config/plugin.config.xml';
        if (file_exists($xmlcfgfile)) {
            $xml                   = simplexml_load_file($xmlcfgfile);
            $p['database_version'] = (empty($xml->install['version'])) ? 'na' : (int)$xml->install['version'];
            $p['name']             = (empty($xml->name)) ? 'na' : (string)$xml->name;
            $p['version']          = (empty($xml->version)) ? 'na' : (string)$xml->version;
            $p['description']      = (empty($xml->description)) ? 'na' : (string)$xml->description;
            $p['versionurl']       = (empty($xml->versionurl)) ? 'na' : (string)$xml->versionurl;
            $p['current']          = (empty($xml->versionurl['current'])) ? 'na' : (string)$xml->versionurl['current'];
            $p['founder']          = (empty($xml->founder)) ? 'na' : (string)$xml->founder;
            $p['author']           = (empty($xml->author)) ? 'na' : (string)$xml->author;
            $p['email']            = (empty($xml->email)) ? 'na' : (string)$xml->email;
            $p['homepage']         = (empty($xml->homepage)) ? 'na' : (string)$xml->homepage;
            $p['date']             = (empty($xml->date)) ? 'na' : (string)$xml->date;
            $p['copyright']        = (empty($xml->copyright)) ? 'na' : (string)$xml->copyright;
            $p['license']          = (empty($xml->license)) ? 'na' : (string)$xml->license;
            $p['info']             = (empty($xml->info)) ? 'na' : (string)$xml->info;

            if (!empty($xml->install->dependencies[0])) {
                $p['dependency'] = $this->getDependenciesInfo($xml->install->dependencies[0]);
            } else {
                $p['dependency'] = '';
            }
            return json_encode($p);
        } else {
            return 'online';
        }
    }

    public function getDependenciesInfo($da)
    {
        $installed_classes = $this->db->invokeQuery('PluginManager_availableClassesQuery');
        $depends_on        = null;
        if (!empty($da)) {
            // Lets find out what plugins this plugin depends on.
            foreach ($da as $dependecy) {
                // Assign plugin name.
                $pl = (string)$dependecy['plugin'];
                $cl = (string)$dependecy['class'];
                // Create unique items only.
                if (empty($unique_dependency[$cl])) {
                    // Next we need to check what is installed and what not.
                    $depends_on[] = array(
                        'ready'  => empty($installed_classes[$cl]) ? false : true,
                        'class'  => $cl,
                        'plugin' => $pl);
                    $unique_dependency[$cl] = true;
                }
            }
        }
        return $depends_on;
    }
}
