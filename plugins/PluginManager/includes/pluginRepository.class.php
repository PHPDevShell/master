<?php
/**
 * Manages plugin relations read action.
 *
 */
class pluginRepository extends PHPDS_dependant
{
    public function read()
    {
        return $this->db->invokeQuery('PluginManager_readRepository');
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