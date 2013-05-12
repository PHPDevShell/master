<?php
/**
 * Manages plugin relations read action.
 * @property PHPDS_template $template
 * @property PHPDS_config   $config
 */
class pluginRepository extends PHPDS_dependant
{
    /**
     * @var string
     */
    private $githubsub     = 'https://raw.';
    /**
     * @var string
     */
    private $githubcfg     = '/%s/config/';
    /**
     * @var string
     */
    private $githubbranch  = 'master';
    /**
     * @var string
     */
    private $githubarchive = 'archive';
    /**
     * @var string
     */
    private $repotype      = 'github';
    /**
     * @var int
     */
    private $timeout       = 30;

    /**
     *
     */
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

    /**
     * @param null $plugin
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
     * @return array
     * @throws PHPDS_exception
     */
    private function readOriginalJsonRepo()
    {
        $config = $this->configuration;

        $jsonfile = $config['absolute_path'] . 'plugins/repository.json';

        if (file_exists($jsonfile)) {
            $json      = file_get_contents($jsonfile);
            $repoarray = json_decode($json, true);

            if (is_array($repoarray) && !empty($repoarray)) {
                if (!empty($config['repository']['plugins']) && is_array($config['repository']['plugins'])) {
                    $repoarray_ = array_merge($config['repository']['plugins'], $repoarray['plugins']);
                    $repoarray = array(
                        'compatibility-version' => $repoarray['compatibility-version'],
                        'plugins'               => $repoarray_
                    );
                    if (empty($repoarray['compatibility-version']) || empty($repoarray['plugins']))
                        $this->template->critical(__('Custom repository plugins not formed correctly'));
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
     * @param $repoarray
     * @return array
     */
    private function repositoryList($repoarray)
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
     * @param $remote
     * @return array
     */
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
                    $local[$object] = $this->localPluginsListInfo($base, $object);
                } else {
                    $local[$object] = $this->localPluginsListInfo($base, $object, $remote[$object]);
                }
            }
        }

        return $local;
    }

    /**
     * @param $remote
     * @param $local
     * @return array
     */
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

    /**
     * @param       $directory
     * @param       $plugin
     * @param array $remote
     * @return array
     */
    private function localPluginsListInfo($directory, $plugin, $remote = array())
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

    /**
     * @param $plugin
     * @return bool
     */
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

    /**
     * @return bool|string
     */
    public function updateRepository()
    {
        $update = $this->updateRepositoryFile();
        if (!$update) {
            $this->template->info(__('Repository was up to date.'));
        } else {
            $this->template->ok(__('New plugins added to repository.'));
        }
        return $update;
    }

    /**
     * @return bool|string
     */
    private function updateRepositoryFile()
    {
        $config  = $this->configuration;
        $path    = $config['absolute_path'] . 'plugins';
        $repo    = $path . '/repository.json';
        $oldrepo = $this->readJsonRepoFile($repo);
        $size    = filesize($repo);

        $ch = curl_init($config['repository']['url']);
        $fp = fopen($repo, "w");

        if (!empty($config['repository']['username']) && !empty($config['repository']['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD,
                "{$config['repository']['username']}:{$config['repository']['password']}");
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $curl     = curl_exec($ch);
        $sizecurl = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        if (!$this->generateCurlResponse($ch, $config['repository']['url'])) {
            fclose($fp);
            // Recover repository else its going to be empty.
            $fprevert = fopen($repo, "w");
            fwrite($fprevert, json_encode(array('plugins' => $oldrepo),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fclose($fprevert);
            return false;
        }
        curl_close($ch);
        fclose($fp);
        $newrepo = $this->readJsonRepoFile($repo);
        if ($curl) {
            if ($size == $sizecurl) {
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
     * @param $repo
     * @return mixed
     */
    private function readJsonRepoFile($repo)
    {
        $json     = file_get_contents($repo);
        $repodata = json_decode($json, true);
        return $repodata['plugins'];
    }

    /**
     * @param $plugin
     * @return array|bool
     */
    public function pluginConfig($plugin)
    {
        $plugin_exists = $this->pluginExistsLocally($plugin);

        if ($plugin_exists) {
            $cfg = $this->pluginConfigLocal($plugin_exists);
        } else {
            $cfg = $this->pluginConfigGithubRemote($plugin);
        }

        if (isset($cfg) && is_array($cfg)) {
            return $cfg;
        } else {
            return false;
        }
    }

    /**
     * @param $plugin
     * @return bool|string
     */
    private function pluginExistsLocally($plugin)
    {
        $xmlcfgfile = $this->configuration['absolute_path'] . 'plugins/' . $plugin . '/config/plugin.config.xml';

        if (file_exists($xmlcfgfile)) {
            return $xmlcfgfile;
        } else {
            return false;
        }
    }

    /**
     * @param $xmlcfgfile
     * @return bool
     */
    private function pluginConfigLocal($xmlcfgfile)
    {
        $xml = @simplexml_load_file($xmlcfgfile);
        if (!isset($xml) && !is_array($xml)) {
            $this->template->warning(sprintf(__('No info available for: %s'), $xmlcfgfile));
            return false;
        }
        return $this->xmlPluginConfigToArray($xml);
    }

    /**
     * @param $plugin
     * @return bool
     */
    private function pluginConfigGithubRemote($plugin)
    {
        $config      = $this->configuration;
        $remote_repo = $this->readOriginalJsonRepo();
        $data        = false;

        if (! empty($remote_repo['plugins'][$plugin]['repo'])) {
            $repo_url = $remote_repo['plugins'][$plugin]['repo'];
            if (! empty($remote_repo['plugins'][$plugin]['branch']))
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
        if(!empty($data)) {
            $xml = @simplexml_load_string($data);
            return $this->xmlPluginConfigToArray($xml);
        }
        return false;
    }

    /**
     * @param $ch
     * @param $url
     * @return bool
     */
    private function generateCurlResponse($ch, $url)
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
                            __('Check your repository USERNAME and PASSWORD in custom config'))
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
     * @param $repo_url
     * @return mixed
     */
    private function prepGithubRawConfigUrl($repo_url)
    {
        $repo_raw_xml = $repo_url . sprintf($this->githubcfg . 'plugin.config.xml', $this->githubbranch);
        return str_replace("https://", $this->githubsub, $repo_raw_xml);
    }

    /**
     * @param $xml
     * @return bool
     */
    private function xmlPluginConfigToArray($xml)
    {
        $p['database_version'] = (empty($xml->install['version'])) ? 'na' : (int)$xml->install['version'];
        $p['name']             = (empty($xml->name)) ? false : (string)$xml->name;
        $p['version']          = (empty($xml->version)) ? false : (string)$xml->version;
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
            $p['dependency'] = $this->pluginDependencies($xml->install->dependencies[0]);
        }

        if ($xml && $p['name'] && $p['version']) {
            return ($xml) ? $p : false;
        } else {
            return false;
        }
    }

    /**
     * @param $dependencies
     * @return array|null
     */
    private function pluginDependencies($dependencies)
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
     * @param $plugin
     * @return bool|string
     */
    public function pluginDependsCollector($plugin)
    {
        $cfg = $this->pluginConfig($plugin);
        if (empty($cfg)) {
            $error = sprintf(__('%s config xml could not be loaded'), $plugin);
            $this->template->critical($error);
            return false;
        }

        if (! empty($cfg['dependency']) && is_array($cfg['dependency'])) {
            foreach($cfg['dependency'] as $dep) {
                if (empty($dep['ready'])) $install[] = $dep['plugin'];
            }
            if (!empty($install)) return json_encode($install);
        }
        return json_encode(array($plugin));
    }

    /**
     * @param $plugin
     * @return bool
     */
    public function pluginDelete($plugin)
    {
        if (empty($plugin)) {
            $this->template->critical(__('No plugin name provided'));
            return false;
        }
        $dir = $this->configuration['absolute_path'] . 'plugins/' . $plugin . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) || !is_writable($dir)) {
            $this->template->critical(sprintf(__("Permission denied to deleting: %s"), $dir));
            return false;
        }
        return $this->recursiveFolderDelete($dir);
    }

    /**
     * @param $dir
     * @return bool
     */
    private function recursiveFolderDelete($dir)
    {
        if (!is_dir($dir) || is_link($dir)) return unlink($dir);
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') continue;
            if (!$this->recursiveFolderDelete($dir . DIRECTORY_SEPARATOR . $file)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!$this->recursiveFolderDelete($dir . DIRECTORY_SEPARATOR . $file)) return false;
            };
        }
        return rmdir($dir);
    }

    /**
     * @param $plugin
     * @return string
     */
    public function pluginPrepare($plugin)
    {
        if ($this->pluginExistsLocally($plugin)) {
            if ($this->isPluginInstalled($plugin)) {
                return $this->pluginReinstallReadyLocally();
            } else {
                return $this->pluginPrepareReadyLocally();
            }
        } else {
            return $this->pluginPrepareNeedDownload();
        }
    }

    /**\
     * @return string
     */
    private function pluginPrepareReadyLocally()
    {
        return json_encode(array('status' => 'install', 'message' => __('Installing...')));
    }

    /**
     * @return string
     */
    private function pluginReinstallReadyLocally()
    {
        return json_encode(array('status' => 'reinstall', 'message' => __('Re-installing...')));
    }

    /**
     * @return string
     */
    private function pluginPrepareNeedDownload()
    {
        return json_encode(array('status' => 'download', 'message' => __('Downloading...')));
    }

    /**
     * @param $plugin
     * @return bool|string
     */
    public function pluginPrepareDownload($plugin)
    {
        $repo = $this->readOriginalJsonRepo();
        if (! empty($repo['plugins'][$plugin]['repo'])) {
            return $this->pluginAttemptGithubDownload($plugin, $repo['plugins'][$plugin]['repo']);
        }
        return false;
    }

    /**
     * @param $plugin
     * @param $repo
     * @return bool|string
     */
    private function pluginAttemptGithubDownload($plugin, $repo)
    {
        $config      = $this->configuration;
        $archive_url = $repo . '/' . $this->githubarchive . '/' . $this->githubbranch . '.zip';
        $zip_file    = $plugin . '_' . time() . '_' . rand(1, 9999) . '.zip';
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
     * @param $plugin
     * @param $zip
     * @return bool|string
     */
    public function pluginExtraction($plugin, $zip)
    {
        $plugin_folder = $this->configuration['absolute_path'] . 'plugins';

        if (file_exists($zip)) {
            $archive   = new ZipArchive();
            $container = $archive->open($zip);
            if ($container === true) {
                $results         = $archive->extractTo($plugin_folder);
                $old_folder_name = trim($archive->getNameIndex(0), "/");
                $archive->close();

                // Folder will most probably be incorrect, rename.
                $wrong_name = $plugin_folder . '/' . $old_folder_name;
                $new_name   = $plugin_folder . '/' . $plugin;
                if (file_exists($wrong_name))
                    if (!rename($wrong_name, $new_name)) return false;
                if (!file_exists($new_name)) {
                    $this->template->critical(sprintf("There was a problem creating plugin in %s", $new_name));
                    return false;
                }
                if ($results) {
                    return $this->pluginPrepareReadyLocally();
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @param $dir
     * @return bool
     */
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
}