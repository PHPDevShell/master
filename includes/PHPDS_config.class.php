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
     * Gets all registered database classes.
     *
     * @return array
     */
    public function readClassRegistry()
    {
        return $this->db->invokeQuery('CONFIG_readPluginClassRegistryQuery');
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
     * @author Jason Schoeman <titan@phpdevshell.org>
     */
    public function installedPlugins()
    {
        $this->db->invokeQuery('CONFIG_installedPluginsQuery');
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