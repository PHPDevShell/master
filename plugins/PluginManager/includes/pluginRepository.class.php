<?php
/**
 * Manages plugin relations read action.
 * @property PHPDS_core             $core
 * @property PHPDS_config           $config
 * @property PHPDS_cacheInterface   $cache
 * @property PHPDS_debug            $debug
 * @property PHPDS_sessionInterface $session
 * @property PHPDS_navigation       $navigation
 * @property PHPDS_router           $router
 * @property PHPDS_dbInterface      $db
 * @property PHPDS_template         $template
 * @property PHPDS_tagger           $tagger
 * @property PHPDS_user             $user
 * @property PHPDS_notif            $notif
 * @property PHPDS_auth             $auth
 */
class pluginRepository extends PHPDS_dependant
{
    /**
     * Git server url prefix.
     * @var string
     */
    protected $githubsub = 'https://raw.';
    /**
     * Directory where configuration lays.
     * @var string
     */
    protected $githubcfg = '/%s/config/';
    /**
     * The repository url trunk that contains plugin.
     * @var string
     */
    protected $githubbranch = 'master';
    /**
     * The archive location in the URL.
     * @var string
     */
    protected $githubarchive = 'archive';
    /**
     * What server to use for repository.
     * @var string
     */
    protected $repotype = 'github';
    /**
     * The suffix url for forking.
     * @var string
     */
    protected $fork = 'fork';
    /**
     * Timeout before accepting it as an timeout error.
     * @var int
     */
    protected $timeout = 45;
    /**
     * Collects plugin dependency array due for action.
     * @var array
     */
    protected $collector = array();

    /****************************************************
     * Public helper methods continue...
     ****************************************************/

    /**
     * Checks if plugin manager can actually perform the needed tasks by checking permissions.
     */
    public function canPluginManagerWork()
    {
        $path = $this->configuration['absolute_path'] . 'plugins';
        $repo = $path . '/repository.json';

        if (!is_writable($path)) {
            $this->template->critical(sprintf('Plugin manager cannot write to: %s', $path));
        } else {
            if (!is_writable($repo)) {
                $this->template->critical(
                    sprintf('Plugin manager cannot write to repository (check file permissions): %s', $repo));
            }
        }

        if (!function_exists('curl_init')) {
            $this->template->critical('Plugin manager required the cURL PHP Extention to work.');
        }
    }

    /**
     * Loads most current repository data.
     *
     * @param string $plugin
     * @return array
     */
    public function initiateRepository($plugin = null)
    {
        $repo_array = $this->repositoryList($this->readOriginalJsonRepo());

        if (isset($plugin) && is_array($repo_array)) {
            foreach ($repo_array as $plugin_) {
                if ($plugin_['name'] == $plugin)
                    return array($plugin_);
            }
            return array();
        } else {
            return $this->repositoryList($this->readOriginalJsonRepo());
        }
    }

    /**
     * Alias to writing and updating local repository file.
     *
     * @return bool|string
     */
    public function updateRepository()
    {
        return $this->updateRepositoryFile();
    }

    /**
     * Loads depending on install status the config xml of the plugin and returns it as array.
     *
     * @param string $plugin
     * @param bool $remote_only
     * @return array|bool
     */
    public function pluginConfig($plugin, $remote_only=false)
    {
        if ($remote_only) {
            $cfg = $this->pluginConfigGithubRemote($plugin);
        } else {
            $plugin_exists = $this->pluginExistsLocally($plugin);
            if ($plugin_exists) {
                $cfg = $this->pluginConfigLocal($plugin_exists);
            } else {
                $cfg = $this->pluginConfigGithubRemote($plugin);
            }
        }

        $repo = $this->readOriginalJsonRepo();

        if (isset($cfg) && is_array($cfg)) {
            if (! empty($repo['plugins'][$plugin]['repo'])) {
                $cfg['repository'] = $repo['plugins'][$plugin]['repo'];
                $cfg['fork']       = $repo['plugins'][$plugin]['repo'] . DIRECTORY_SEPARATOR . $this->fork;
            }
            return $cfg;
        } else {
            return false;
        }
    }


    /**
     * Checks for dependencies of all installed plugins.
     *
     * @return bool|string
     */
    public function checkDependencies()
    {
        $p = $this->config->pluginsInstalled;
        if (!empty($p)) {
            foreach ($p as $plugin => $data) {
                $pluginxmllocation = $this->pluginExistsLocally($plugin);
                if (!empty($pluginxmllocation)) {
                    $cfg = $this->pluginConfigLocal($pluginxmllocation);
                    if (!empty($cfg['dependency']) && is_array($cfg['dependency'])) {
                        $dependency[$plugin] = $cfg['dependency'];
                    }
                }
            }

            if (!empty($dependency)) {
                return json_encode($dependency);
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    /**
     * Return json of all installed plugins.
     *
     * @return string
     */
    public function checkPlugins()
    {
        $p = $this->config->pluginsInstalled;
        if (!empty($p)) {
            return json_encode($p);
        } else {
            return false;
        }
    }

    /**
     * Checks if any update is required on all installed plugins returning json of the items requiring updates.
     *
     * @param string $plugin
     * @param bool $version
     * @return bool|string
     */
    public function checkUpdate($plugin, $version)
    {
        if (empty($version)) return false;

        $repo = $this->readOriginalJsonRepo();

        // Check online first.
        if (!empty($repo['plugins'][$plugin]['repo'])) {
            $config = $this->pluginConfig($plugin, true);
        } else {
            $config = $this->pluginConfig($plugin);
        }

        if (! empty($config['database_version']) && ! empty($version)) {
            if ($config['database_version'] > $version) {
                return json_encode(array('plugin' => $plugin));
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Call dependencies for plugin with dependence dependency.
     *
     * @param $plugin
     * @return array
     */
    public function pluginCollector($plugin)
    {
        $collect = $this->pluginDependencyHelper($plugin);
        if (!empty($collect)) {
            foreach ($collect as $plugin_) {
                if (in_array($plugin_, $this->collector)) continue;
                if ($plugin_ != $plugin) {
                    $this->childDependencies($plugin_);
                    $this->collector[] = $plugin_;
                }
            }
        }
        array_push($this->collector, $plugin);
        return json_encode($this->collector);
    }

    /**
     * Child factory for recalling children dependencies.
     *
     * @param $plugin
     */
    protected function childDependencies($plugin)
    {
        if (!in_array($plugin, $this->collector)) {
            $collect = $this->pluginDependencyHelper($plugin);
            if (!empty($collect)) {
                foreach ($collect as $plugin_) {
                    if (in_array($plugin_, $this->collector)) continue;
                    if ($plugin_ != $plugin) {
                        $this->childDependencies($plugin_);
                        $this->collector[] = $plugin_;
                    }
                }
            }
        }
    }

    /**
     * Collects dependencies for a specific requested plugin.
     *
     * @param string $plugin
     * @return bool|array
     */
    protected function pluginDependencyHelper($plugin)
    {
        $cfg = $this->pluginConfig($plugin);
        if (empty($cfg)) {
            $error = sprintf(__('%s config xml could not be loaded'), $plugin);
            $this->template->critical($error);
            return false;
        }

        if (!empty($cfg['dependency']) && is_array($cfg['dependency'])) {
            // add main plugin first.
            $install[] = $plugin;
            foreach ($cfg['dependency'] as $dep) {
                if (!$this->isPluginInstalled($dep['plugin']))
                    if (empty($dep['ready'])) $install[] = $dep['plugin'];
            }
            if (!empty($install)) return array_reverse($install);
        }
        return array($plugin);
    }

    /**
     * Physically deletes a specific provide plugin from file system.
     *
     * @param string $plugin
     * @return bool
     * @throws PHPDS_exception
     */
    public function pluginDelete($plugin)
    {
        if (empty($plugin)) throw new PHPDS_exception(__('No plugin name provided to delete'));

        $dir = $this->configuration['absolute_path'] . 'plugins/' . $plugin . DIRECTORY_SEPARATOR;

        if (!is_dir($dir) || !$this->isWritable($dir)) {
            $this->template->critical(sprintf(__("File permission denied deleting %s"), $dir));
            return false;
        }

        return $this->recursiveFolderDelete($dir);
    }

    /**
     * Prepares a specific action to take on a plugin action requested.
     *
     * @param string $plugin
     * @param string $actiontype
     * @return string
     */
    public function pluginPrepare($plugin, $actiontype = null)
    {
        if ($this->pluginExistsLocally($plugin)) {
            if ($this->isPluginInstalled($plugin)) {
                $repo = $this->readOriginalJsonRepo();
                if (!empty($repo['plugins'][$plugin]['repo'])) {
                    return $this->pluginPrepareNeedDownload();
                } else {
                    if ($actiontype == 'upgrade') {
                        return $this->pluginUpgradeReadyLocally();
                    } else {
                        return $this->pluginReinstallReadyLocally();
                    }
                }
            } else {
                return $this->pluginPrepareReadyLocally($plugin, $actiontype);
            }
        } else {
            return $this->pluginPrepareNeedDownload();
        }
    }

    /**
     * Extracts and moves plugin to its correct location.
     *
     * @param string $plugin
     * @param string $zip
     * @param string $actiontype
     * @return bool|string
     * @throws PHPDS_exception
     */
    public function pluginExtraction($plugin, $zip, $actiontype = null)
    {
        $config        = $this->configuration;
        $plugin_folder = $config['absolute_path'] . 'plugins';
        $tmp_folder    = $config['absolute_path'] . $config['tmp_path'] . PU_createRandomString(6);

        clearstatcache();

        if (file_exists($zip)) {
            $archive   = new ZipArchive();
            $container = $archive->open($zip, ZipArchive::CREATE);
            if ($container === true) {
                try {
                    $old_folder_name = trim($archive->getNameIndex(0), "/");
                    if (empty($old_folder_name)) throw new PHPDS_exception('No root folder found in archive');
                    if (!preg_match("/$plugin/", $old_folder_name))
                        throw new PHPDS_exception('Plugin folder not found in archive');

                    $results = $archive->extractTo($tmp_folder);
                    $archive->close();

                    // Folder will most probably be incorrect, rename.
                    $wrong_name   = $tmp_folder     . '/' . $old_folder_name;
                    $right_name   = $tmp_folder     . '/' . $plugin;
                    $final_folder = $plugin_folder  . '/' . $plugin;

                    if (file_exists($wrong_name) && is_dir($wrong_name)) {
                        if (rename($wrong_name, $right_name)) {
                            $this->rcopy($right_name, $final_folder);
                        } else {
                            throw new PHPDS_exception(sprintf('Could not renaming plugin in %s', $wrong_name));
                        }
                    } else {
                        throw new PHPDS_exception(sprintf('Plugin did not extract to %s', $wrong_name));
                    }

                    if (!file_exists($final_folder) && !is_dir($final_folder)) {
                        $this->template->critical(sprintf("There was a problem creating plugin in %s", $final_folder));
                        return false;
                    }

                    if ($results) {
                        return $this->pluginPrepareReadyLocally($plugin, $actiontype);
                    } else {
                        return false;
                    }
                } catch (Exception $e) {
                    throw new PHPDS_exception(sprintf('Could not extract plugin, error message: %s', $e->getMessage()));
                }
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Activates action for a plugin to be downloaded.
     *
     * @param string $plugin
     * @return bool|string
     */
    public function pluginPrepareDownload($plugin)
    {
        $repo = $this->readOriginalJsonRepo();
        if (!empty($repo['plugins'][$plugin]['repo'])) {
            return $this->pluginAttemptGithubDownload($plugin, $repo['plugins'][$plugin]['repo']);
        }
        return false;
    }

    /**
     * Does final check to see if all dependencies are met, this is to prevent a foreign key constraint error.
     *
     * @param $plugin
     * @return bool|array true if success, array with missing dependencies if failure.
     */
    public function finalDependencyCheck($plugin)
    {
        $cfg   = $this->pluginConfig($plugin);
        $instc = $this->config->registeredClasses;
        $instp = $this->config->pluginsInstalled;

        if (!empty($cfg['dependency'])) {
            foreach ($cfg['dependency'] as $dependency) {
                $classd  = $dependency['class'];
                $plugind = $dependency['plugin'];
                if (empty($instc[$classd]) || empty($instp[$plugind])) {
                    return $dep_error = array('error' => array('plugin' => $plugind, 'class' => $classd));
                }
            }
        }

        return true;
    }

    /****************************************************
     * Private helper methods continue...
     ****************************************************/

    /**
     * Reads last written repository from disk.
     *
     * @return array
     * @throws PHPDS_exception
     */
    protected function readOriginalJsonRepo()
    {
        $config = $this->configuration;

        $jsonfile = $config['absolute_path'] . 'plugins/repository.json';

        if (file_exists($jsonfile)) {
            $json      = file_get_contents($jsonfile);
            $repoarray = json_decode($json, true);

            if (is_array($repoarray) && !empty($repoarray)) {
                if (!empty($config['repository']['plugins']) && is_array($config['repository']['plugins'])) {
                    $repoarray_ = array_merge($config['repository']['plugins'], $repoarray['plugins']);
                    $repoarray  = array(
                        'compatibility-version' => $repoarray['compatibility-version'],
                        'plugins'               => $repoarray_
                    );
                    if (empty($repoarray['compatibility-version']) || empty($repoarray['plugins']))
                        throw new PHPDS_exception(__('Custom repository plugins not formed correctly'));
                }
                return $repoarray;
            } else {
                throw new PHPDS_exception('Local repository plugins/repository.json file empty?');
            }
        } else {
            throw new PHPDS_exception('Local repository file missing or unreadable in plugins/repository.json');
        }
    }

    /**
     * Generates readable array of data for repository display.
     *
     * @param array $repoarray
     * @return array
     */
    protected function repositoryList($repoarray)
    {
        $remote = array();

        // Plugins available in repository.
        foreach ($repoarray['plugins'] as $name => $data) {
            $installed = $this->isPluginInstalled($name);
            if ($installed) {
                $broken = ($this->pluginExistsLocally($name)) ? false : true;
            } else {
                $broken = false;
            }

            $remote[$name] = array(
                'name'      => $name,
                'desc'      => $data['desc'],
                'repo'      => $data['repo'],
                'installed' => $installed,
                'broken'    => $broken
            );
        }

        // Check plugins that exists locally.
        $local     = $this->localAvailablePlugins($remote);
        $allpugins = $this->sortPluginByName($remote, $local);

        return $allpugins;
    }

    /**
     * Creates array of plugins which contains a file structure locally.
     *
     * @param array $remote
     * @return array
     */
    protected function localAvailablePlugins($remote)
    {
        // Plugins available locally.
        $directory      = $this->configuration['absolute_path'] . 'plugins';
        $base           = $directory . '/';
        $subdirectories = opendir($base);
        $local          = array();

        while (false !== ($object = readdir($subdirectories))) {
            if (ctype_alnum($object)) {
                if (empty($remote[$object])) {
                    $local[$object] = $this->localPluginsListInfo($base, $object);
                } else {
                    $local[$object] = $this->localPluginsListInfo($base, $object, $remote[$object]);
                }
            }
        }

        return $local;
    }

    /**
     * Sorts plugins by their names in alphabetic order.
     *
     * @param array $remote
     * @param array $local
     * @return array
     */
    protected function sortPluginByName($remote, $local)
    {
        $reposorted = array();
        $repo       = array_merge($remote, $local);
        ksort($repo);
        foreach ($repo as $value) {
            $reposorted[] = $value;
        }
        return $reposorted;
    }

    /**
     * Reads local xml cfg information and adds data to array.
     *
     * @param string $directory
     * @param string $plugin
     * @param array $remote
     * @return array
     * @throws PHPDS_exception
     */
    protected function localPluginsListInfo($directory, $plugin, $remote = array())
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
        if (file_exists($xmlconfig))
            try {
                $localxml = simplexml_load_file($xmlconfig);
            } catch (Exception $e) {
                throw new PHPDS_exception(sprintf('XML config file could be malformed in %s. %s', $xmlconfig,
                    $e->getMessage()));
            }
        if (!empty($localxml) && !empty($localxml->name)) {
            $local = array(
                'name'      => $plugin,
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

    /**
     * Check if plugin is already installed.
     *
     * @param string $plugin
     * @return bool
     */
    protected function isPluginInstalled($plugin)
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

    /**
     * Actual writing and updating of local repository file.
     *
     * @return bool|string
     * @throws PHPDS_exception
     */
    protected function updateRepositoryFile()
    {
        $config    = $this->configuration;
        $path      = $config['absolute_path'] . 'plugins';
        $repo      = $path . '/repository.json';
        $oldrepo   = $this->readPluginsJsonRepoFile($repo);
        $size      = filesize($repo);
        $newsize   = $size;
        $buildrepo = array();

        if (empty($config['repository']['url'])) throw new PHPDS_exception('No repository specified in config.');
        foreach ($config['repository']['url'] as $repourl) {
            $tmppath = $config['absolute_path'] . $config['tmp_path'] . PU_createRandomString(6) . '_repository.json';
            $ch      = curl_init($repourl);
            $fp      = fopen($tmppath, "w");

            if (!empty($config['repository']['username']) && !empty($config['repository']['password'])) {
                curl_setopt($ch, CURLOPT_USERPWD,
                    "{$config['repository']['username']}:{$config['repository']['password']}");
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);

            if (!$this->generateCurlResponse($ch, $repourl)) {
                return false;
            } else {
                curl_close($ch);
                fclose($fp);
                $repo_r = $this->readJsonRepoFile($tmppath);
                if (!empty($repo_r['compatibility-version']) && !empty($repo_r['plugins'])) {
                    $compat_version = $repo_r['compatibility-version'];
                    $buildrepo      = $repo_r['plugins'] + $buildrepo;
                } else {
                    throw new PHPDS_exception(sprintf('Invalid repo data for %s, view tmp json in %s',
                        $repourl, $tmppath));
                    continue;
                }
            }
        }

        if (!empty($compat_version)) {
            $repoarray = array(
                'compatibility-version' => $compat_version,
                'plugins'               => $buildrepo
            );
            if (empty($repoarray['compatibility-version']) || empty($repoarray['plugins']))
                throw new PHPDS_exception(__('Custom repository json not formed correctly'));

            // Write official repo then...
            if (is_writable($repo)) {
                $fpfinal = fopen($repo, "w");
                fwrite($fpfinal, json_encode($repoarray,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                fclose($fpfinal);
                clearstatcache();
                $newsize = filesize($repo);
            } else {
                $this->template->critical(sprintf('Plugin repo not writable in %s', $repo));
            }
        } else {
            throw new PHPDS_exception('There was an unknown issue while building the repository');
        }

        $newrepo = $this->readPluginsJsonRepoFile($repo);

        if (!empty($buildrepo) && is_array($buildrepo)) {
            if ($size == $newsize) {
                return false;
            } else {
                $newplugins = PU_array_diff_assoc_recursive($newrepo, $oldrepo);
                if (!empty($newplugins))
                    return json_encode($newplugins);
                else
                    return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Reads json repository file and decodes the json to an array.
     *
     * @param string $repo
     * @return array
     */
    protected function readJsonRepoFile($repo)
    {
        $json     = file_get_contents($repo);
        $repodata = json_decode($json, true);
        return $repodata;
    }

    /**
     * Reads json repository file and decodes the json to an array specifically for plugins.
     *
     * @param string $repo
     * @return array
     */
    protected function readPluginsJsonRepoFile($repo)
    {
        $json     = file_get_contents($repo);
        $repodata = json_decode($json, true);
        return $repodata['plugins'];
    }

    /**
     * Checks if plugin exists locally, if it does, it will return xml config location.
     *
     * @param string $plugin
     * @return bool|string
     */
    protected function pluginExistsLocally($plugin)
    {
        $xmlcfgfile = $this->configuration['absolute_path'] . 'plugins/' . $plugin . '/config/plugin.config.xml';

        if (file_exists($xmlcfgfile)) {
            return $xmlcfgfile;
        } else {
            return false;
        }
    }

    /**
     * Reads local xml config file and parces the xml into an object returning the object and converts it to array.
     *
     * @param string $xmlcfgfile
     * @return bool|array
     * @throws PHPDS_exception
     */
    protected function pluginConfigLocal($xmlcfgfile)
    {
        try {
            $xml = simplexml_load_file($xmlcfgfile);
        } catch (Exception $e) {
            throw new PHPDS_exception(sprintf('XML config file could be malformed in %s. %s', $xmlcfgfile,
                $e->getMessage()));
        }
        if (!isset($xml) && !is_array($xml)) {
            $this->template->warning(sprintf(__('No info available for: %s'), $xmlcfgfile));
            return false;
        }
        return $this->xmlPluginConfigToArray($xml);
    }

    /**
     * Loads config xml from remote location and converts it to array returning it.
     *
     * @param string $plugin
     * @return bool|array
     */
    protected function pluginConfigGithubRemote($plugin)
    {
        $config      = $this->configuration;
        $remote_repo = $this->readOriginalJsonRepo();
        $data        = false;

        if (!empty($remote_repo['plugins'][$plugin]['repo'])) {
            $repo_url = $remote_repo['plugins'][$plugin]['repo'];
            if (!empty($remote_repo['plugins'][$plugin]['branch']))
                $this->githubbranch = $remote_repo['plugins'][$plugin]['branch'];
            if (filter_var($repo_url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                if ($this->repotype == 'github') {
                    $repo_raw_xml = $this->prepGithubRawConfigUrl($repo_url);
                } else {
                    return false;
                }
                $ch = curl_init($repo_raw_xml);
                if (!empty($config['repository']['username']) && !empty($config['repository']['password'])) {
                    curl_setopt($ch, CURLOPT_USERPWD,
                        "{$config['repository']['username']}:{$config['repository']['password']}");
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                $data = curl_exec($ch);
                if (!$this->generateCurlResponse($ch, $repo_raw_xml)) return false;
                curl_close($ch);
            }
        }
        if (!empty($data)) {
            $xml = simplexml_load_string($data);
            return $this->xmlPluginConfigToArray($xml);
        }
        return false;
    }

    /**
     * Handles the response received by the php curl object.
     *
     * @param object $ch
     * @param string $url
     * @return bool
     */
    protected function generateCurlResponse($ch, $url)
    {
        $config       = $this->configuration;
        $curl_errornr = curl_errno($ch);
        $curl_error   = curl_error($ch);
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($curl_errornr != CURLE_OK || $http_code != 200) {
            if ($http_code == 200) {
                $this->template->critical(sprintf('cURL Error : %s (%s)', $curl_error, $url));
            } else {
                if (!empty($config['repository']['username']) && !empty($config['repository']['password'])) {
                    $this->template->critical(
                        sprintf('HTTP Response Error : %s (%s) -- %s', $http_code, $url,
                            __('* Try again, or if private repo Check your repository
                            USERNAME and PASSWORD in custom config.'))
                    );
                } else {
                    $this->template->critical(sprintf('HTTP Response Error : %s (%s)', $http_code, $url));
                }
            }
            curl_close($ch);
            PU_silentHeaderStatus($http_code);
            return false;
        }
        return true;
    }

    /**
     * Builds the url for online get of the xml config file.
     *
     * @param string $repo_url
     * @return string
     */
    protected function prepGithubRawConfigUrl($repo_url)
    {
        $repo_raw_xml = $repo_url . sprintf($this->githubcfg . 'plugin.config.xml', $this->githubbranch);
        return str_replace("https://", $this->githubsub, $repo_raw_xml);
    }

    /**
     * Converts xml object to array returning it.
     *
     * @param object $xml
     * @return bool|array
     */
    protected function xmlPluginConfigToArray($xml)
    {
        $p['database_version'] = (empty($xml->install['version'])) ? 0 : (int)$xml->install['version'];
        $p['name']             = (empty($xml->name)) ? null : (string)$xml->name;
        $p['version']          = (empty($xml->version)) ? null : (string)$xml->version;
        $p['description']      = (empty($xml->description)) ? null : (string)$xml->description;
        $p['versionurl']       = (empty($xml->versionurl)) ? null : (string)$xml->versionurl;
        $p['current']          = (empty($xml->versionurl['current'])) ? null : (string)$xml->versionurl['current'];
        $p['founder']          = (empty($xml->founder)) ? null : (string)$xml->founder;
        $p['author']           = (empty($xml->author)) ? null : (string)$xml->author;
        $p['email']            = (empty($xml->email)) ? null : (string)$xml->email;
        $p['homepage']         = (empty($xml->homepage)) ? null : (string)$xml->homepage;
        $p['date']             = (empty($xml->date)) ? null : (string)$xml->date;
        $p['copyright']        = (empty($xml->copyright)) ? null : (string)$xml->copyright;
        $p['license']          = (empty($xml->license)) ? null : (string)$xml->license;
        $p['info']             = (empty($xml->info)) ? null : (string)$xml->info;

        if (!empty($xml->install->dependencies[0])) {
            $p['dependency'] = $this->pluginDependencies($xml->install->dependencies[0]);
        }

        if ($xml && $p['name'] && $p['version']) {
            return ($xml) ? $p : false;
        } else {
            return false;
        }
    }

    /**
     * Checks class dependencies of all installed plugins against the required dependency list.
     *
     * @param array $dependencies
     * @return array|null
     */
    protected function pluginDependencies($dependencies)
    {
        $installed_classes = $this->config->registeredClasses;
        $depends_on        = null;
        if (!empty($dependencies)) {
            // Lets find out what plugins this plugin depends on.
            foreach ($dependencies as $dependecy) {
                // Assign plugin name.
                $pl = (string)$dependecy['plugin'];
                $cl = (string)$dependecy['class'];
                // Create unique items only.
                if (empty($unique_dependency[$cl])) {
                    // Next we need to check what is installed and what not.
                    $depends_on[]           = array(
                        'ready'  => empty($installed_classes[$cl]) ? false : true,
                        'class'  => $cl,
                        'plugin' => $pl);
                    $unique_dependency[$cl] = true;
                }
            }
        }
        return $depends_on;
    }

    /**
     * Prepares a plugin for a specific action for local available plugins.
     *
     * @param string $plugin
     * @param string $actiontype
     * @return string
     */
    protected function pluginPrepareReadyLocally($plugin, $actiontype = null)
    {
        if ($this->isPluginInstalled($plugin)) {
            if ($actiontype == 'upgrade') {
                return $this->pluginUpgradeReadyLocally();
            } else if ($actiontype == 'reinstall') {
                return $this->pluginReInstallReadyLocally();
            } else {
                return false;
            }
        } else {
            return $this->pluginInstallReadyLocally();
        }
    }

    /**
     * Json for plugin is ready to be installed.
     *
     * @return string
     */
    protected function pluginInstallReadyLocally()
    {
        return json_encode(array('status' => 'install', 'message' => __('Installing...')));
    }

    /**
     * Json for plugin is ready to be reinstalled.
     *
     * @return string
     */
    protected function pluginReinstallReadyLocally()
    {
        return json_encode(array('status' => 'reinstall', 'message' => __('Re-installing...')));
    }

    /**
     * Json for plugin is ready to be upgraded.
     *
     * @return string
     */
    protected function pluginUpgradeReadyLocally()
    {
        return json_encode(array('status' => 'upgrade', 'message' => __('Upgrading...')));
    }

    /**
     * Json for plugin requesting downloading.
     *
     * @return string
     */
    protected function pluginPrepareNeedDownload()
    {
        return json_encode(array('status' => 'download', 'message' => __('Downloading...')));
    }

    /**
     * Does actual download of plugin through curl.
     *
     * @param string $plugin
     * @param string $repo
     * @return bool|string
     */
    protected function pluginAttemptGithubDownload($plugin, $repo)
    {
        $config      = $this->configuration;
        $archive_url = $repo . '/' . $this->githubarchive . '/' . $this->githubbranch . '.zip';
        $zip_file    = $plugin . '_' . time() . '_' . PU_createRandomString(6) . '.zip';
        $zip_path    = $config['absolute_path'] . $config['tmp_path'] . $zip_file;

        $ch = curl_init($archive_url);
        $fp = fopen($zip_path, "w");

        if (!empty($config['repository']['username']) && !empty($config['repository']['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD,
                "{$config['repository']['username']}:{$config['repository']['password']}");
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $curl = curl_exec($ch);
        if (!$this->generateCurlResponse($ch, $archive_url)) return false;
        curl_close($ch);
        fclose($fp);
        if ($curl) {
            return json_encode(array('status' => 'extract', 'message' => __('Extracting...'), 'zip' => $zip_path));
        } else {
            return false;
        }
    }

    /**
     * Recursively copies a plugin from one location to another.
     *
     * @param string $src
     * @param string $dest
     *
     * @return bool
     * @throws PHPDS_exception
     */
    protected function rcopy($src, $dest)
    {
        // If source is not a directory stop processing
        if (!is_dir($src)) throw new PHPDS_exception(sprintf('Directory %s is not a directory', $src));

        // If the destination directory does not exist create it
        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                throw new PHPDS_exception(sprintf('Directory %s could not be created', $dest));
            }
        }

        $files = new DirectoryIterator($src);

        foreach ($files as $file) {
            if ($file->isFile()) {
                copy($file->getRealPath(), "$dest/" . $file->getFilename());
            } else if (!$file->isDot() && $file->isDir()) {
                $this->rcopy($file->getRealPath(), "$dest/$file");
            }
        }

        if (!empty($files) && $this->isWritable($src)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Recursively deletes a folder.
     *
     * @param string $dir
     * @return bool
     */
    protected function recursiveFolderDelete($dir)
    {
        $it = new RecursiveDirectoryIterator($dir);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach($files as $file) {
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);

        if (!is_dir($dir) && !file_exists($dir)) {
           return true;
        } else {
           return false;
        }
    }

    /**
     * Checks recursively if folder and files are writable.
     *
     * @param string $dir
     * @return bool
     */
    protected function isWritable($dir)
    {
        $files = new DirectoryIterator($dir);

        foreach ($files as $file) {
            if (!$file->isDot() && !$file->isWritable()) {
                return false;
            }
        }
        return true;
    }
}