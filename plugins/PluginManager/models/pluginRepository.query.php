<?php

class PluginManager_readRepository extends PHPDS_query
{
    private $availablePlugins;
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
        } else {
            //return 'online';
        }

        $view = $this->factory('views');
        $view->set('p', $p);
        return $view->get('info-modal.html');
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
