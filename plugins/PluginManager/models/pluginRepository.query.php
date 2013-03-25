<?php

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
