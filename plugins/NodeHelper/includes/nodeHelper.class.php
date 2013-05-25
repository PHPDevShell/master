<?php

/**
 * Class contains methods to calculate the structure and other elements of node items,
 * the methods are dependent of each other.
 *
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
class nodeHelper extends PHPDS_dependant
{
    protected $rootNodeFamily   = array();
    protected $parentNodeFamily = array();
    protected $databaseInsert;
    protected $groupedNodeItems = array();

    /**
     * This method requires data from the database and saves it an array into different groups.
     *
     */
    private function nodeArray()
    {
        $sql = "
          SELECT    t1.node_id, t1.parent_node_id
          FROM      _db_core_node_items as t1
          ORDER BY  t1.rank
          ASC
        ";

        $db         = $this->db;
        $main_query = $db->queryFAR($sql);

        foreach ($main_query as $main_query_array) {
            $parent_node_id = (string)$main_query_array['parent_node_id'];
            $node_id        = (string)$main_query_array['node_id'];

            // Collect all root node items.
            if (!$parent_node_id) {
                $this->rootNodeFamily[] = (string)$node_id;
            } // Collect all root parents node items.
            else {
                $this->parentNodeFamily[] = (string)$parent_node_id;
            }
            // Save node items per group.
            $this->groupedNodeItems["$parent_node_id"][] = (string)$node_id;
        }

        // Structure start point.
        $this->divideRootNodeItems();
    }

    /**
     * Compile and divide root group items.
     *
     */
    private function divideRootNodeItems()
    {
        foreach ($this->rootNodeFamily as $root_group_node_id) {
            // Divide root parents and root children from root group.
            // Root Parent -> continue loading children.
            if (in_array($root_group_node_id, $this->parentNodeFamily)) {
                $this->databaseInsert[] =
                    array('id' => null, 'node_id' => $root_group_node_id, 'is_parent' => 1, 'type' => 1);
                $this->nodeGroupExtract($root_group_node_id);
            } // Root Child -> stop.
            else {
                $this->databaseInsert[] =
                    array('id' => null, 'node_id' => $root_group_node_id, 'is_parent' => 0, 'type' => 2);
            }
        }
    }

    /**
     * Extract specific node group items in their relevant groups on group request.
     *
     * @param integer $parent_node_id
     */
    private function nodeGroupExtract($parent_node_id)
    {
        // Loop through group and call sub node group divider.
        foreach ($this->groupedNodeItems["$parent_node_id"] as $node_id) {
            $this->divideSubNodeItems($node_id);
        }
    }

    /**
     * Compile and divide sub node items as parents or children.
     *
     * @param integer $node_id
     */
    private function divideSubNodeItems($node_id)
    {
        // Divide parents and children from requested group per node item.
        // Sub Parent -> write parent and continue loading children through next loop.
        if (in_array($node_id, $this->parentNodeFamily)) {
            $this->databaseInsert[] =
                array('id' => null, 'node_id' => $node_id, 'is_parent' => 1, 'type' => 3);
            $this->nodeGroupExtract($node_id);
        } // Sub Child -> stop.
        else {
            $this->databaseInsert[] =
                array('id' => null, 'node_id' => $node_id, 'is_parent' => 1, 'type' => 4);
        }
    }

    /**
     * Write generated structure to database.
     */
    public function writeNodeStructure()
    {
        $sql = "
          INSERT INTO _db_core_node_structure (id, node_id, is_parent, type)
		  VALUES                              (:id, :node_id, :is_parent, :type)
        ";

        $db = $this->db;

        // Initiate starting point with node array.
        $this->nodeArray();

        // Submit results to database.
        if (!empty($this->databaseInsert)) {

            // Clear previous results.
            $db->query("DELETE FROM _db_core_node_structure");

            // Reset auto increment counter.
            $db->query("ALTER TABLE _db_core_node_structure AUTO_INCREMENT = 0;");

            $db->prepare($sql);

            // Insert new results.
            if (!empty($this->databaseInsert) && is_array($this->databaseInsert)) {
                foreach ($this->databaseInsert as $params) {
                    $db->execute($params);
                }
            }
        }
        // Clear old cache.
        $this->cache->flush();
    }

    /**
     * Completely delete a node item and all its sub tables.
     *
     * @param mixed   $node_id              Node id, or could be left out.
     * @param string  $plugin               Plugin, or delete node items by
     *                                      plugin which is always the folder the plugin lies in.
     * @param boolean $delete_critical_only Checks if only critical node data needs to be
     *                                      deleted while ignoring data like permissions etc.
     *
     * @return boolean
     */
    public function deleteNode($node_id = null, $plugin = null, $delete_critical_only = false)
    {
        $sql = "
          SELECT  node_id
		  FROM    _db_core_node_items
		  WHERE   plugin = :plugin
        ";

        $db = $this->db;

        // Define.
        $db_condition = '';

        // Check if plugin item should be deleted.
        if ($plugin != false && $node_id == false) {
            $node_id_db = $db->queryFAR($sql, array('plugin' => $plugin));

            if (!empty($node_id_db)) {
                foreach ($node_id_db as $node_id_array) {
                    $db_condition .= "'{$node_id_array['node_id']}',";
                }
            }
            // Check if there is any condition.
            if (!empty($db_condition)) {
                // Correct for database condition.
                $db_condition = rtrim($db_condition, ",");
                // Complete condition.
                $condition = " IN ($db_condition)";
            }
        } // The user may want to give an array of items to be deleted.
        else if (is_array($node_id)) {
            foreach ($node_id as $item_to_delete) {
                // Check if item needs to be converted to node item.
                $db_condition .= "'$item_to_delete',";
            }
            // Check if there is any condition.
            if (!empty($db_condition)) {
                // Correct for database condition.
                $db_condition = rtrim($db_condition, ",");
                // Complete condition.
                $condition = " IN ($db_condition)";
            }
        } else {
            // Complete condition.
            $condition = " = '$node_id'";
        }
        // Only execute when not empty.
        if (!empty($condition)) {
            // Delete Node Items.
            $db->query('DELETE FROM _db_core_node_items WHERE node_id' . PHP_EOL . $condition);
            return true;
        }
        return false;
    }

    /**
     * Insert a new node item in database.
     */
    public function insertNode(
        $node_id = null, $parent_node_id, $node_name, $node_link, $plugin, $node_type, $extend = false,
        $new_window = false, $rank = 0, $hide = false, $theme_id = null, $alias = null, $layout = null, $params = null)
    {
        $sql = "
          INSERT INTO  _db_core_node_items (
            node_id, parent_node_id, node_name, node_link, plugin, node_type,
            extend, new_window, rank, hide, theme_id, alias, layout, params
          ) VALUES (
            :node_id, :parent_node_id, :node_name, :node_link, :plugin, :node_type,
            :extend, :new_window, :rank, :hide, :theme_id, :alias, :layout, :params
          ) ON DUPLICATE KEY UPDATE node_id        = :node_id,
                                    parent_node_id = :parent_node_id,
                                    node_name      = :node_name,
                                    node_link      = :node_link,
                                    plugin         = :plugin,
                                    node_type      = :node_type,
                                    extend         = :extend,
                                    new_window     = :new_window,
                                    rank           = :rank,
                                    hide           = :hide,
                                    theme_id       = :theme_id,
                                    alias          = :alias,
                                    layout         = :layout,
                                    params         = :params

        ";

        $parameters = array(
            'node_id'        => $node_id,
            'parent_node_id' => $parent_node_id,
            'node_name'      => $node_name,
            'node_link'      => $node_link,
            'plugin'         => $plugin,
            'node_type'      => $node_type,
            'extend'         => $extend,
            'new_window'     => $new_window,
            'rank'           => $rank,
            'hide'           => $hide,
            'theme_id'       => $theme_id,
            'alias'          => $alias,
            'layout'         => $layout,
            'params'         => $params
        );

        $db = $this->db;

        // Check and make sure we have a node id.
        if (!empty($node_id)) {
            ////////////////////////////////
            // Save new item to database. //
            ////////////////////////////////
            if ($db->queryAffects($sql, $parameters)) {
                // Write the node structure.
                $this->writeNodeStructure();
            }
        }
    }

    /**
     * Deletes a node item while returning node plugin name.
     *
     * @param string $node_id
     * @return boolean
     */
    public function getDelete($node_id)
    {
        $sql = "
          SELECT  plugin
          FROM    _db_core_node_items
          WHERE   node_id = :node_id
        ";

        $db = $this->db;

        // Call plugin name from database.
        $get_plugin = $db->querySingle($sql, array('node_id' => $node_id));

        // Now we can see if a delete is possible.
        if (!empty($node_id) && !empty($get_plugin)) {
            // Do the actual node delete.
            $this->deleteNode($node_id);
            // Write the node structure.
            $this->writeNodeStructure();
            return true;
        } else {
            return false;
        }
    }

    /**
     * This is a unique and allows one to locate a node item from location as well.
     *
     * @param string $plugin_folder The plugin folder the file is in.
     * @param string $link          Actual file link.
     * @return integer|string
     * @author Jason Schoeman
     */
    public function createNodeId($plugin_folder, $link)
    {
        $sql = "
          SELECT  node_id
		  FROM    _db_core_node_items
		  WHERE   node_link = :node_link
		  AND     plugin    = :plugin
        ";

        $db         = $this->db;
        $node_id_db = $db->querySingle($sql, array('node_link' => $link, 'plugin' => $plugin_folder));

        // Before we create a node id, we need to check if it is available already.
        // If it is, we need to get it and return this value rather.
        if (!empty($node_id_db)) {
            return $node_id_db;
        } else if (!empty($plugin_folder)) {
            $new_id = $plugin_folder . "-" . $link;
            $new_id = preg_replace("/.php/", '', $new_id);
            $new_id = PU_safeName($new_id);
            // Create node id from string.
            return $new_id;
        } else {
            return 0;
        }
    }

    /**
     * This method will update an existing node id with a new node id if the old id exists.
     *
     * @param int|string $new_id
     * @param int|string $old_id
     * @param boolean    $skip_check True checks if the node id already exists.
     * @return mixed
     */
    public function updateNodeId($new_id, $old_id, $skip_check = false)
    {
        $db = $this->db;

        // Check if we the node item exists.
        if (!$skip_check) {
            $exisiting_id = $this->nodeIdExist($old_id);
        } else {
            $exisiting_id = true;
        }

        if (!empty($exisiting_id)) {
            $db->query("UPDATE _db_core_node_items SET
                node_id = :node_id WHERE node_id = :old_node_id",
                array('node_id' => $new_id, 'old_node_id' => $old_id));

            $db->query("UPDATE _db_core_node_items SET
                parent_node_id = :parent_node_id WHERE parent_node_id = :old_parent_node_id",
                array('parent_node_id' => $new_id, 'old_parent_node_id' => $old_id));

            $db->query("UPDATE _db_core_node_items SET
                extend = :extend WHERE extend = :old_extend",
                array('extend' => $new_id, 'old_extend' => $old_id));

            $db->query("UPDATE _db_core_cron SET
                node_id = :node_id WHERE node_id = :old_node_id",
                array('node_id' => $new_id, 'old_node_id' => $old_id));

            $db->query("UPDATE _db_core_filter SET
                node_id = :node_id WHERE node_id = :old_node_id",
                array('node_id' => $new_id, 'old_node_id' => $old_id));

            $db->query("UPDATE _db_core_node_structure SET
                node_id = :node_id WHERE node_id = :old_node_id",
                array('node_id' => $new_id, 'old_node_id' => $old_id));

            $db->query("UPDATE _db_core_user_role_permissions SET
                node_id = :node_id WHERE node_id = :old_node_id",
                array('node_id' => $new_id, 'old_node_id' => $old_id));

            $db->query("UPDATE _db_core_settings SET
                setting_value = :setting_value WHERE setting_value = :old_setting_value",
                array('setting_value' => $new_id, 'old_setting_value' => $old_id));

            $db->query("UPDATE _db_core_tags SET
                tag_target = :tag_target WHERE tag_target = :old_tag_target",
                array('tag_target' => $new_id, 'old_tag_target' => $old_id));

            return $new_id;
        } else {
            return false;
        }
    }

    /**
     * Check if a node exists.
     *
     * @param int $node_id
     * @return mixed
     */
    public function nodeIdExist($node_id)
    {
        $sql = "
          SELECT  node_id
		  FROM    _db_core_node_items
		  WHERE   node_id = :node_id;
        ";

        return $this->db->querySingle($sql, array('node_id' => $node_id));
    }
}