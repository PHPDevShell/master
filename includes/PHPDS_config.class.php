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
     * Returns a value of a specific key or whole config set if no key are given.
     *
     * @param string $key
     * @return mixed
     */
    public function get($key=null)
    {
        if (!isset($key)) return $this->configuration;
        return $this->configuration->{$key};
    }

    /**
     * Temporarily override current settings key value.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set($key, $value=null)
    {
        return $this->configuration->{$key} = $value;
    }

    /**
     * Stores registered classed into cache and public registry variable.
     *
     * @return array|mixed
     */
    public function classRegistry()
    {
        $cache = $this->cache;

        $registeredClasses = $cache->get('registeredClasses');
        if (empty($registeredClasses)) {
            $pluginR = $this->readClassRegistry();
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
                $cache->set('registeredClasses', $this->registeredClasses);
            }
        } else {
            if (! empty($this->registeredClasses)) {
                $this->registeredClasses = array_merge($registeredClasses, $this->registeredClasses);
                $cache->set('registeredClasses', $this->registeredClasses);
            } else {
                $this->registeredClasses = $registeredClasses;
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
        $sql = "
            SELECT SQL_CACHE  t1.class_id, t1.class_name, t1.alias, t1.plugin_folder, t1.enable, t1.rank
            FROM              _db_core_plugin_classes AS t1
            WHERE             (t1.enable = 1)
            ORDER BY          t1.rank
            ASC
        ";
        return $this->db->queryFAR($sql);
    }

    /**
     * Used to get all essential system settings from the database, preventing multiple queries.
     *
     * @return array Contains array with essential settings.
     */
    public function getEssentialSettings()
    {
        // Pull essential settings and assign it to essential_settings.
        $this->essentialSettings = $this->cache->get('essential_settings');
        if (empty($this->essentialSettings)) {
            $this->essentialSettings = $this->getSettings($this->configuration['preloaded_settings'], 'PHPDS');
            // Write essential settings data to cache.
            $this->cache->set('essential_settings', $this->essentialSettings);
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
        $sql = "
                SELECT SQL_CACHE setting_id, setting_value
                FROM             _db_core_settings
                WHERE            setting_id
         ";

        $settings = array();

        if ($custom_prefix == '*') {
            $prefix = '%%';
        } else {
            $prefix = $this->settingsPrefix($custom_prefix);
        }

        if (is_array($settings_required)) {

            $db_get_query = false;

            foreach ($settings_required as $setting_from_db) {
                if (!empty($setting_from_db)) {
                    $db_get_query .= "'$prefix" . "$setting_from_db',";
                    $settings[$setting_from_db] = null;
                }
            }
            $db_get_query = PU_rightTrim($db_get_query, ",");

            $db_get_query = " IN ($db_get_query) ";
        } else {
            $db_get_query = " LIKE '$prefix%%' ";
        }

        if (!empty($db_get_query)) {
            $settings_db = $this->db->queryFAR($sql . PHP_EOL . $db_get_query);
        }

        if (!empty($settings_db) && is_array($settings_db)) {
            foreach ($settings_db as $fetch_setting_array) {
                $description = $fetch_setting_array['setting_id'];
                $value       = $fetch_setting_array['setting_value'];

                $description = preg_replace("/$prefix/", '', $description);

                $settings[$description] = $value;
            }
            return $settings;
        } else {
            return false;
        }
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
        $sql = "
                REPLACE INTO _db_core_settings (setting_id, setting_value, note)
                VALUES  (:setting_id, :setting_value, :note)
        ";

        $this->db->prepare($sql);

        if ($custom_prefix == '*') {
            $prefix = '%';
        } else {
            $prefix = $this->settingsPrefix($custom_prefix);
        }

        if (is_array($write_settings)) {

            foreach ($write_settings as $settings_id => $settings_value) {

                if (!empty($notes[$settings_id])) {
                    $note = trim($notes[$settings_id]);
                } else {
                    $note = '';
                }
                $settings_id    = trim($prefix . $settings_id);
                $settings_value = trim($settings_value);
                $this->db->execute(array(
                    'setting_id' => $settings_id, 'setting_value' => $settings_value, 'note' => $note
                ));
            }
            $insert_settings = $this->db->affectedRows();

            if ($insert_settings) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Delete all settings stored by a given plugins name, is used when uninstalling a plugin.
     *
     * @param array|string  $settings_to_delete Use '*' to delete all settings for certain plugin.
     * @param string        $custom_prefix
     * @return boolean
     */
    public function deleteSettings($settings_to_delete = null, $custom_prefix = null)
    {
        $sql = "
            DELETE FROM _db_core_settings
            WHERE       setting_id
        ";

        $delete_settings = null;

        if ($custom_prefix == '*') {
            $prefix = '%%';
        } else {
            $prefix = $this->settingsPrefix($custom_prefix);
        }

        $db_delete_query = false;

        if (is_array($settings_to_delete)) {
            foreach ($settings_to_delete as $setting_from_db) {
                $db_delete_query .= "'$prefix" . "$setting_from_db',";
            }
            $db_delete_query = rtrim($db_delete_query, ",");

            $db_delete_query = " IN ($db_delete_query) ";
        } else if ($settings_to_delete == '*') {
            $db_delete_query = " LIKE '$prefix%%' ";
        }
        if (!empty($db_delete_query)) {
            $delete_settings = $this->db->queryAffects($sql . PHP_EOL . $db_delete_query);
        }

        if ($delete_settings) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Writes array of all the installed plugins on the system.
     */
    public function installedPlugins()
    {
        $this->pluginsInstalled = $this->cache->get('plugins_installed');
        if (empty($this->pluginsInstalled)) {
            $sql = "
              SELECT  plugin_folder, status, version
              FROM    _db_core_plugin_activation
            ";
            $installed_plugins_db = $this->db->queryFAR($sql);

            foreach ($installed_plugins_db as $installed_plugins_array) {
                $plugins_installed[$installed_plugins_array['plugin_folder']] = array(
                    'plugin_folder' => $installed_plugins_array['plugin_folder'],
                    'status'        => $installed_plugins_array['status'],
                    'version'       => $installed_plugins_array['version']
                );
            }

            if (! empty($plugins_installed)) {
                $this->pluginsInstalled = $plugins_installed;
                $this->cache->set('plugins_installed', $plugins_installed);
            }
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