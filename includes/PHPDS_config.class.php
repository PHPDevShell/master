<?php

class PHPDS_config extends PHPDS_dependant
{
    /**
     * Contains array of all essential stored settings.
     * @var array
     */
    public $essentialSettings;
    /**
     * Contains array of all the plugins installed.
     *
     * @var array
     */
    public $pluginsInstalled;
    /**
     * Contains array of all registered classes to plugins.
     *
     * @var array
     */
    public $registeredClasses;

    /**
     * Stores registered classed into cache and public registry variable.
     *
     * @return array|mixed
     */
    public function classRegistry()
    {
        $cache = $this->cache;

        if ($cache->cacheEmpty('registeredClasses')) {
            $pluginR = $this->config->readClassRegistry();
            if (!empty($pluginR)) {
                foreach ($pluginR as $p) {
                    $fileName  = '';
                    $classname = $p['class_name'];
                    $pos       = strpos($classname, '@');
                    if ($pos) {
                        $fileName  = substr($classname, $pos + 1);
                        $classname = substr($classname, 0, $pos);
                    }
                    $this->registerClass($classname, $p['alias'], $p['plugin_folder'], $fileName);
                }
                $cache->cacheWrite('registeredClasses', $this->registeredClasses);
            }
        } else {
            if (!empty($this->registeredClasses)) {
                $registeredClasses       = $cache->cacheRead('registeredClasses');
                $this->registeredClasses = array_merge($registeredClasses, $this->registeredClasses);
                $cache->cacheWrite('registeredClasses', $this->registeredClasses);
            } else {
                $this->registeredClasses = $cache->cacheRead('registeredClasses');
            }
        }

        return $this->registeredClasses;
    }

    /**
     * Add a class to the registry.
     *
     * @param string $className    the name of the PHP class to register
     * @param string $classAlias   an alternative name for this class
     * @param string $pluginFolder the name/folder of the plugin this class belongs to
     * @param string $fileName     (optional) a file where to load the class from, instead of the default name based on the class name
     */
    public function registerClass($className, $classAlias, $pluginFolder, $fileName = null)
    {
        $this->registeredClasses[$className] = array(
            'class_name'    => $className,
            'alias'         => $classAlias,
            'plugin_folder' => $pluginFolder,
            'file_name'     => $fileName
        );
        if (!empty($classAlias)) {
            $this->registeredClasses[$classAlias] = array(
                'class_name'    => $className,
                'plugin_folder' => $pluginFolder,
                'file_name'     => $fileName
            );
        }
    }

    /**
     * Gets all registered database classes.
     *
     * @return array
     */
    public function readClassRegistry()
    {
        return $this->db->invokeQuery('CONFIG_readPluginClassRegistryQuery');
    }

    public function readClassRegistryNew()
    {
        return $this->connection->query("
            SELECT SQL_CACHE
                t1.class_id, t1.class_name, t1.alias, t1.plugin_folder, t1.enable, t1.rank
            FROM
                _db_core_plugin_classes AS t1
            WHERE
            (t1.enable = 1)
            ORDER BY
                t1.rank
            ASC
        ");
    }

    /**
     * Used to get all essential system settings from the database, preventing multiple queries.
     *
     * @return array Contains array with essential settings.
     */
    public function getEssentialSettings()
    {
        // Pull essential settings and assign it to essential_settings.
        if ($this->cache->cacheEmpty('essential_settings')) {
            $this->essentialSettings = $this->getSettings($this->configuration['preloaded_settings'], 'AdminTools');
            // Write essential settings data to cache.
            $this->cache->cacheWrite('essential_settings', $this->essentialSettings);
        } else {
            $this->essentialSettings = $this->cache->cacheRead('essential_settings');
        }
    }

    /**
     * Loads and returns required settings from database.
     * Class will always use plugin name as prefix for settings if no custom prefix is provided.
     *
     * @param mixed  $settings_required
     * @param string $custom_prefix This allows you to use a prefix value of your choice to select a setting from another plugin, otherwise PHPDevShell will be used.
     * @return array An array will be returned containing all the values requested.
     */
    public function getSettings($settings_required = false, $custom_prefix = null)
    {
        return $this->db->invokeQuery('CONFIG_getSettingsQuery', $settings_required, $custom_prefix);
    }

    /**
     * Used to write general plugin settings to the database.
     * Class will always use plugin name as prefix for settings if no custom prefix is provided.
     *
     * @param array  $write_settings This array should contain settings to write.
     * @param string $custom_prefix  If you would like to have a custom prefix added to your settings.
     * @param array  $notes          For adding notes about setting.
     * @return boolean On success true will be returned.
     * @author Jason Schoeman <titan@phpdevshell.org>
     */
    public function writeSettings($write_settings, $custom_prefix = '', $notes = array())
    {
        return $this->db->invokeQuery('CONFIG_writeSettingsQuery', $write_settings, $custom_prefix, $notes);
    }

    /**
     * Delete all settings stored by a given plugins name, is used when uninstalling a plugin.
     *
     * @param mixed  $settings_to_delete Use '*' to delete all settings for certain plugin.
     * @param string $custom_prefix
     * @return boolean
     */
    public function deleteSettings($settings_to_delete = null, $custom_prefix = null)
    {
        return $this->db->invokeQuery('CONFIG_deleteSettingsQuery', $settings_to_delete, $custom_prefix);
    }

    /**
     * Writes array of all the installed plugins on the system.
     */
    public function installedPlugins()
    {
        if ($this->cache->cacheEmpty('plugins_installed')) {
            $installed_plugins_db = $this->db->invokeQuery('CONFIG_installedPluginsQuery');

            foreach ($installed_plugins_db as $installed_plugins_array) {
                $plugins_installed[$installed_plugins_array['plugin_folder']] = array(
                    'plugin_folder' => $installed_plugins_array['plugin_folder'],
                    'status'        => $installed_plugins_array['status'],
                    'version'       => $installed_plugins_array['version']
                );
            }
            $this->pluginsInstalled = $plugins_installed;

            $this->cache->cacheWrite('plugins_installed', $plugins_installed);
        } else {
            $this->pluginsInstalled = $this->cache->cacheRead('plugins_installed');
        }
    }

    /**
     * Generates a prefix for plugin general settings.
     *
     * @param string $custom_prefix
     * @return string Complete string with prefix.
     */
    public function settingsPrefix($custom_prefix = null)
    {
        // Create prefix.
        if ($custom_prefix == false) {
            // Get active plugin.
            $active_plugin = $this->core->activePlugin();
            if (!empty($active_plugin)) {
                $prefix = $active_plugin . '_';
            } else {
                $prefix = '_db_';
            }
        } else {
            $prefix = $custom_prefix . '_';
        }
        return $prefix;
    }
}