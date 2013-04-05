<?php

class PHPDS_readMaxNodesRankQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			MAX(t1.rank)
		FROM
			_db_core_node_items AS t1
    ";

    protected $singleValue = true;
}

class PHPDS_readMinNodesRankQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			MIN(t1.rank)
		FROM
			_db_core_node_items AS t1
    ";

    protected $singleValue = true;
}

class PHPDS_createThemeQuery extends PHPDS_query
{
    protected $sql = "
		REPLACE INTO
			_db_core_themes (theme_id, theme_folder)
		VALUES
			('%s', '%s')
    ";
}

class PHPDS_deleteRolePermissionsPluginQuery extends PHPDS_query
{
    protected $sql = "
		DELETE FROM
			_db_core_user_role_permissions
		WHERE
			node_id = '%s'
		AND
			user_role_id = %u
    ";
}

class PHPDS_writeRolePermissionsPluginQuery extends PHPDS_query
{
    protected $sql = "
		REPLACE INTO
			_db_core_user_role_permissions (user_role_id, node_id)
		VALUES
			(%u, '%s')
    ";
}

class PHPDS_writeNodePluginQuery extends PHPDS_query
{
    protected $sql = "
		REPLACE INTO
			_db_core_node_items (
                node_id,
                parent_node_id,
                node_name,
                node_link,
                plugin,
                node_type,
                extend,
                new_window,
                rank,
                hide,
                theme_id,
                alias,
                layout,
                params
			)
		VALUES
			('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    ";
}

class PHPDS_rankClassesQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			MAX(rank)
		FROM
			_db_core_plugin_classes
		WHERE
			class_name = '%s'
    ";

    protected $singleValue = true;
}

class PHPDS_writeClassesQuery extends PHPDS_query
{
    protected $sql = "
		REPLACE INTO
			_db_core_plugin_classes (class_id, class_name, alias, plugin_folder, enable, rank)
		VALUES
			%s
    ";

    /**
     * Initiate invoke query.
     */
    public function invoke($parameters = null)
    {
        list($classes_array, $plugin_folder) = $parameters;

        // Check if settings exists.
        $class_db = '';
        // Loop through all settings.
        if (empty($classes_array)) $classes_array = array();
        foreach ($classes_array as $class_array) {
            // Assign setting as string.
            if (!empty($class_array['name']))
                $name = (string)$class_array['name'];
            if (!empty($class_array['alias']))
                $alias = (string)$class_array['alias'];
            if (!empty($class_array['plugin']))
                $plugin = (string)$class_array['plugin'];
            if (!empty($class_array['rank']))
                $rank = $class_array['rank'];

            if (empty($name)) $name = $plugin_folder;
            if (empty($plugin)) $plugin = $plugin_folder;
            if (empty($alias)) $alias = '';
            if (empty($rank) || $rank == 'last') {
                $max_rank = $this->db->invokeQuery('PHPDS_rankClassesQuery', $name);
                (empty($max_rank)) ? $rank = 1 : $rank = $max_rank + 1;
            }
            // Assign settings array.
            $class_db .= "('', '$name', '$alias', '$plugin', 1, '$rank'),";
        }
        // Remove last comma.
        $class_db = rtrim($class_db, ',');
        // We can now insert the classes.
        if (!empty($class_db)) {
            // Write new classes to database.
            parent::invoke(array($class_db));
        }
    }
}

class PHPDS_doQuery extends PHPDS_query
{
    protected $sql = "%s";
}

class PHPDS_writePluginVersionQuery extends PHPDS_query
{
    protected $sql = "
		INSERT INTO
			_db_core_plugin_activation (plugin_folder, status, version, persistent)
		VALUES
			('%s', '%s', '%s', null)
	";
}

class PHPDS_deleteClassesQuery extends PHPDS_query
{
    protected $sql = "
		DELETE FROM
			_db_core_plugin_classes
		WHERE
			plugin_folder = '%s'
	";
}

class PHPDS_deleteVersionQuery extends PHPDS_query
{
    protected $sql = "
		DELETE FROM
			_db_core_plugin_activation
		WHERE
			plugin_folder = '%s'
	";
}

class PHPDS_upgradeVersionQuery extends PHPDS_query
{
    protected $sql = "
		UPDATE
			_db_core_plugin_activation
		SET
			status        = '%s',
			version       = '%s'
		WHERE
			plugin_folder = '%s'
	";
}

class PHPDS_doesNodeExist extends PHPDS_query
{
    protected $sql = "
		SELECT
			t1.node_id
		FROM
			_db_core_node_items AS t1
		WHERE
			t1.node_id = '%s'
	";

    protected $singleValue = true;
}

class PHPDS_updateNodeLink extends PHPDS_query
{
    protected $sql = "
		UPDATE
			_db_core_node_items
		SET
			node_link = '%s'
		WHERE
			node_id = '%s'
	";
}

