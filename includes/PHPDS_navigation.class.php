<?php

class PHPDS_navigation extends PHPDS_dependant
{
    const node_standard      = 1;
    const node_plain_link    = 2;
    const node_jumpto_link   = 3;
    const node_external_file = 4;
    const node_external_link = 5;
    const node_placeholder   = 6;
    const node_iframe        = 7;
    const node_cron          = 8;
    const node_widget        = 9;
    const node_styled_ajax   = 10;
    const node_lightbox      = 11;
    const node_ajax_raw      = 12;

    /**
     * @var array
     */
    protected $breadcrumbArray = null;
    /**
     * @var array of arrays, for each node which have children, an array of the children IDs
     */
    public $child = null;
    /**
     * Holds all node item information.
     *
     * @var array
     */
    public $navigation;
    /**
     * Holds all node item information.
     *
     * @var array
     */
    public $navAlias;
    /**
     * Holds all combined node and route information.
     *
     * @var array
     */
    public $nodes;

    /**
     * This methods loads the node structure, this according to permission and conditions.
     *
     * @return $this
     */
    public function extractNode()
    {
        $cache = $this->cache;

        $this->nodes = $cache->get('PHPDS_nodes');

        if (empty($this->nodes)) {
            if (empty($this->navigation))   $this->navigation   = array();
            if (empty($this->child))        $this->child        = array();
            if (empty($this->navAlias))     $this->navAlias     = array();
            if (empty($this->nodes))        $this->nodes        = array();

            $user_role = $this->user->getRole($this->configuration['user_id']);
            $this->readNodeTable($user_role);

            $this->nodes['child_navigation'] = $this->child;
            $this->nodes['nav_alias']        = $this->navAlias;
            $this->nodes['router_routes']    = $this->router->routes;
            $this->nodes['router_modules']   = $this->router->modules;
            $this->nodes['navigation']       = $this->navigation;

            $cache->set('PHPDS_nodes', $this->nodes);
        } else {
            $this->child           = $this->nodes['child_navigation'];
            $this->navAlias        = $this->nodes['nav_alias'];
            $this->router->routes  = $this->nodes['router_routes'];
            $this->router->modules = $this->nodes['router_modules'];
            $this->navigation      = $this->nodes['navigation'];
        }

        return $this;
    }

    /**
     * Reads into array all nodes that certain roles have access to.
     *
     * @param int $user_role call all user roles.
     * @throws PHPDS_exception
     */
    protected function readNodeTable($user_role)
    {
        $sql = "
            SELECT DISTINCT SQL_CACHE
                      t1.node_id, t1.parent_node_id, t1.node_name, t1.node_link, t1.plugin,
                      t1.node_type, t1.extend, t1.new_window, t1.rank, t1.hide, t1.theme_id,
                      t1.alias, t1.layout, t1.params, t1.route,
                      t3.is_parent, t3.type,
                      t6.theme_folder
            FROM      _db_core_node_items AS t1
            LEFT JOIN _db_core_user_role_permissions AS t2
            ON        t1.node_id = t2.node_id
            LEFT JOIN _db_core_node_structure AS t3
            ON        t1.node_id = t3.node_id
            LEFT JOIN _db_core_themes AS t6
            ON        t1.theme_id = t6.theme_id
            WHERE     (t2.user_role_id = :roles)
            ORDER BY  t3.id
            ASC
        ";

        if (empty($user_role)) throw new PHPDS_exception('Cannot extract nodes when no roles are given.');

        $select_nodes = $this->db->queryFAR($sql, array('roles' => $user_role));

        $config     = $this->configuration;
        $navigation = $this;
        $aburl      = $config['absolute_url'];
        $sef        = !empty($config['sef_url']);
        $append     = $config['url_append'];
        $charset    = $config['charset'];

        foreach ($select_nodes as $mr) {
            $nid = $mr['node_id'];
            ////////////////////////
            // Create node items. //
            ////////////////////////
            $new_node = array();
            PU_copyArray($mr, $new_node,
                array('node_id', 'parent_node_id', 'alias', 'node_link', 'rank', 'hide',
                      'new_window', 'is_parent', 'type', 'theme_folder', 'layout', 'plugin',
                      'node_type', 'extend', 'route'));
            $new_node['node_name'] =
                $navigation->determineNodeName($mr['node_name'], $mr['node_link'], $mr['node_id'], $mr['plugin']);

            $new_node['params'] = !empty($mr['params']) ? html_entity_decode($mr['params'], ENT_COMPAT, $charset) : '';
            $new_node['plugin_folder'] = 'plugins/' . $mr['plugin'] . '/';
            if ($sef && ! empty($mr['alias'])) {
                $navigation->navAlias[$mr['alias']]
                    = $mr['node_type'] != PHPDS_navigation::node_jumpto_link ? $mr['node_id'] : $mr['extend'];
                $new_node['href'] = $aburl . '/' . $mr['alias'].$append;
            } else {
                $new_node['href']
                    = $aburl.'/index.php?m='.($mr['node_type']
                    != PHPDS_navigation::node_jumpto_link ? $mr['node_id'] : $mr['extend']);
            }

            // Writing children for single level dropdown.
            if (! empty($mr['parent_node_id'])) {
                $navigation->child[$mr['parent_node_id']][] = $nid;
            }

            $navigation->navigation[$nid] = $new_node;

            if (!empty($mr['alias'])) $this->router->addRoute($nid, $mr['alias'], $mr['plugin']);
            if (!empty($mr['route'])) $this->router->addRoute($nid, $mr['route'], $mr['plugin']);
        }
    }

    /**
     * Determines what the node item should be named.
     *
     * @param string $replacement_name
     * @param string $node_link
     * @param int    $node_id
     * @param string $plugin
     * @return string
     */
    public function determineNodeName($replacement_name = '', $node_link = '', $node_id = 0, $plugin = '')
    {
        if (!empty($replacement_name)) {
            return __("$replacement_name", "$plugin");
        } else {
            return $node_link;
        }
    }

    /**
     * Returns true if node should show.
     *
     * @param integer $hide_type
     * @param integer $node_id
     * @param integer $active_id
     * @return bool
     */
    public function showNode($hide_type, $node_id = null, $active_id = null)
    {
        if (!empty($node_id) && ($hide_type == 4) && $active_id == $node_id) {
            return true;
        } else {
            if ($hide_type == 0 || $hide_type == 2) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Compiles node items in order.
     *
     * @return string will return HTML node list
     */
    public function createMenuStructure()
    {
        $node          = false;
        $configuration = $this->configuration;
        $nav           = $this->navigation;
        $mod           = $this->template->mod;

        if (!empty($nav)) {
            // Start the main loop, the main loop handles the top level nodes.
            // When child nodes are found the callFamily function is used to render those nodes.
            // The callFamily function may or may not go recursive at that point.
            foreach ($nav as $m) {
                if ($this->showNode($m['hide'], $m['node_id'], $configuration['m']) &&
                    ((string)$nav[$m['node_id']]['parent_node_id'] == '0')) {
                    ($m['node_id'] == $configuration['m']) ? $url_active = 'active' : $url_active = 'inactive';
                    if ($m['is_parent'] == 1) {
                        $call_family = $this->callFamily($m['node_id']);
                        if (!empty($call_family)) {
                            $call_family = $mod->menuUlParent($call_family);
                            $p_type      = 'grand-parent';
                        } else {
                            $p_type = $url_active;
                        }
                        $node .= $mod->menuLiParent($call_family, $mod->menuA($m, 'nav-grand'), $p_type, $m);
                    } else {
                        $node .= $mod->menuLiChild($mod->menuA($m, 'first-child'), $url_active, $m);
                    }
                }
            }
            if (empty($node)) {
                $node = $mod->menuLiChild($mod->menuA($nav[$configuration['m']]), 'active');
            }
        }
        return $node;
    }

    /**
     * Assists write_node in calling node children.
     *
     * @param int $node_id
     * @return string will return HTML node list
     */
    public function callFamily($node_id = 0)
    {
        $node          = '';
        $configuration = $this->configuration;
        $nav           = $this->navigation;
        $mod           = $this->template->mod;

        if (!empty($this->child[$node_id])) {
            $child = $this->child[$node_id];
            foreach ($child as $m) {
                if ($this->showNode($nav[$m]['hide'], $m, $configuration['m'])) {
                    ($m == $configuration['m']) ? $url_active = 'active' : $url_active = 'inactive';
                    if ($nav[$m]['is_parent'] == 1) {
                        $call_family = $this->callFamily($m);
                        if (!empty($call_family)) {
                            $call_family = $mod->menuUlChild($call_family);
                            $p_type      = 'parent';
                        } else {
                            $p_type = $url_active;
                        }
                        $node .= $mod->subMenuLiParent($call_family, $mod->menuA($nav[$m], 'nav-parent'), $p_type, $nav[$m]);
                    } else {
                        $node .= $mod->subMenuLiChild($mod->menuA($nav[$m], 'child'), $url_active, $nav[$m]);
                    }
                }
            }
        }
        return $node;
    }

    /**
     * This method compiles the children of active menu, in some cases a theme modder might want to call this to easy navigation.
     *
     * @return string
     */
    public function createSubnav()
    {
        $node          = '';
        $configuration = $this->configuration;
        $nav           = $this->navigation;
        $mod           = $this->template->mod;

        if (empty($nav[$configuration['m']]['is_parent'])) {
            $parentid = (!empty($nav[$configuration['m']]['parent_node_id'])) ? $nav[$configuration['m']]['parent_node_id'] : '0';
        } else {
            $parentid = $configuration['m'];
        }

        if (!empty($this->child[$parentid])) {
            $child = $this->child[$parentid];
            foreach ($child as $m) {
                if ($this->showNode($nav[$m]['hide'], $m, $configuration['m'])) {
                    ($m == $configuration['m']) ? $url_active = 'active' : $url_active = 'inactive';
                    $node .= $mod->subNavMenuLi($mod->menuASubNav($nav[$m]), $url_active, $nav[$m]);
                }
            }
        }

        return $node;
    }

    /**
     * Method assists method generate_history_tree in getting breadcrumb links.
     *
     * @param integer
     */
    protected function callbackParentItem($node_id_)
    {
        $nav = $this->navigation;
        if (!empty($nav[$node_id_]['parent_node_id'])) {
            $recall_parent_node_id = $nav[$node_id_]['parent_node_id'];
        } else {
            $recall_parent_node_id = '0';
        }
        $this->breadcrumbArray[] = $node_id_;
        if ($recall_parent_node_id) {
            $this->callbackParentItem($recall_parent_node_id);
        }
    }

    /**
     * Simply returns current node id.
     *
     * @return string
     */
    public function currentNodeID()
    {
        return $this->configuration['m'];
    }

    /**
     * Returns the complete current node structure
     *
     * @return array
     */
    public function currentNode()
    {
        return $this->navigation[$this->currentNodeID()];
    }

    /**
     * Will try and locate the full path of a filename of a given node id, if it is a link, the original filename will be returned.
     *
     * @param int    $node_id
     * @param string $plugin
     * @return string|boolean
     */
    public function nodeFile($node_id = 0, $plugin = '')
    {
        if (empty($node_id)) $node_id = $this->configuration['m'];
        $absolute_path = $this->configuration['absolute_path'];
        list($plugin, $node_link) = $this->nodePath($node_id, $plugin);
        if (file_exists($absolute_path . 'plugins/' . $plugin . '/controllers/' . $node_link)) {
            return $absolute_path . 'plugins/' . $plugin . '/controllers/' . $node_link;
        } else if (file_exists($absolute_path . 'plugins/' . $plugin . '/' . $node_link)) {
            return $absolute_path . 'plugins/' . $plugin . '/' . $node_link;
        } else {
            return false;
        }
    }

    /**
     * Will locate the nodes item full path.
     *
     * @param int    $node_id
     * @param string $plugin
     * @return array
     */
    public function nodePath($node_id = 0, $plugin = '')
    {
        $configuration = $this->configuration;
        $navigation    = $this->navigation;
        if (empty($configuration['m']))
            $configuration['m'] = 0;
        if (empty($node_id)) $node_id = $configuration['m'];
        if (empty($navigation[$node_id]['extend'])) {
            if (!empty($navigation[$node_id])) {
                $node_link = $navigation[$node_id]['node_link'];
                if (empty($plugin))
                    $plugin = $navigation[$node_id]['plugin'];
            }
        } else {
            $extend    = $navigation[$node_id]['extend'];
            $node_link = $navigation[$extend]['node_link'];
            if (empty($plugin))
                $plugin = $navigation[$extend]['plugin'];
        }
        if (empty($plugin))
            $plugin = 'PHPDS';
        if (empty($node_link))
            $node_link = '';
        return array($plugin, $node_link);
    }

    /**
     * Will return the url for a certain node item when path is provided.
     * @param string $item_path   The string to the path of the node item, 'user/control-panel.php'
     * @param string $plugin_name The plugin name to look for it under, if empty, active plugin will be used.
     * @param string $extend_url  Will extend url with some get values.
     * @return string Will return complete and cleaned sef url if available else normal url will be returned.
     */
    public function buildURLFromPath($item_path, $plugin_name = '', $extend_url = '')
    {
        if (empty($plugin_name))
            $plugin_name = $this->core->activePlugin();
        $lookup  = array('plugin' => $plugin_name, 'node_link' => $item_path);
        $node_id = PU_arraySearch($lookup, $this->navigation);
        if (!empty($node_id)) {
            return $this->buildURL($node_id, $extend_url);
        } else {
            return $this->pageNotFound();
        }
    }

    /**
     * Returns the correct string for use in href when creating a link for a node id. Will return sef url if possible.
     * Will return self url when no node id is given. No starting & or ? is needed, this gets auto determined!
     * If left empty it will return current active node.
     *
     * @param mixed   $node_id The node id or node file location to create a url from.
     * @param string  $extend_url
     * @param boolean $strip_trail Will strip unwanted empty operators at the end.
     * @return string
     */
    public function buildURL($node_id = null, $extend_url = '', $strip_trail = true)
    {
        if (empty($node_id)) $node_id = $this->configuration['m'];
        if (!empty($this->configuration['sef_url'])) {
            if (empty($this->navigation["$node_id"]['alias'])) {
                $alias = $node_id;
            } else {
                $alias = $this->navigation["$node_id"]['alias'];
            }
            if (!empty($extend_url)) {
                $extend_url = "?$extend_url";
            } else if ($strip_trail) {
                $extend_url = '';
            } else {
                $extend_url = '?';
            }
            $url_append = empty($this->configuration['url_append']) ? '' : $this->configuration['url_append'];
            $url        = $alias . $url_append . "$extend_url";
        } else {
            if (!empty($extend_url)) {
                $extend_url = "&$extend_url";
            } else {
                $extend_url = '';
            }
            $url = 'index.php?m=' . "$node_id" . "$extend_url";
        }
        if (!empty($url)) {
            return $this->configuration['absolute_url'] . "/$url";
        } else {
            return null;
        }
    }

    /**
     * Check if the current user is allowed the given node
     * If (s)he's not and $use_default is true, return the ID of the default node
     *
     * @param string $nodeID
     * @param boolean $use_default
     * @return string|boolean
     */
    public function checkNode($nodeID, $use_default = true)
    {
        if (empty($this->navigation[$nodeID])) {
            if ($use_default) {
                if ($this->user->isLoggedIn()) {
                   return $this->configuration['front_page_id_in'];
                } else {
                   return $this->configuration['front_page_id'];
                }
            } else {
                return false;
            }
        } else {
            return $nodeID;
        }

    }

    /**
     * Parses the REQUEST_URI to get the page id
     */
    public function parseRequestString($uri = '')
    {
        if (empty($uri) && !empty($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        }

        $configuration = $this->configuration;
        $route = null;
        $m = 0;
        $basepath = parse_url($configuration['absolute_url'], PHP_URL_PATH);
        $absolute_path = parse_url($uri, PHP_URL_PATH);
        $path = trim(str_replace($basepath, '', $absolute_path), '/');

        if (empty($path)) {
            // no path given, fall back to the what default page has been configured
            $route = $this->user->isLoggedIn() ? $configuration['front_page_id_in'] : $configuration['front_page_id'];
        } else {
            // first case, old-style "index.php?m=nodeid"
            if ('index.php' == $path) {
                $m = $_GET['m'];
                if (!empty($this->navigation[$m])) {
                    $route = $m;
                } else {
                    $route = false;
                }
            } else { // second case, use the router
                $route = $this->router->matchRoute($path);
                if (empty($route)) { // strip off the extension if necessary
                    $path = str_replace($configuration['url_append'], '', $path);
                    $route = $this->router->matchRoute($path);
                }
            }
        }

        if ($route === false) {
            return $this->urlAccessError($path, $m);
        }

        $configuration['m'] = $route;

        return $route;
    }

    /**
     * Checks url access error type and sets it.
     *
     * @param string
     * @param string
     * @return bool
     */
    public function urlAccessError($alias = null, $get_node_id = null)
    {
        $required_node_id = $this->confirmNodeExist($alias, $get_node_id);
        if (empty($required_node_id)) {
            $this->core->haltController = array('type' => '404', 'message' => ___('Page not found'));
            return false;
        } else {
            if ($this->user->isLoggedIn()) {
                $this->core->haltController = array('type' => '403', 'message' =>
                ___('Page found, but you don\'t have the required permission to access this page.'));
                return false;
            } else {
                $this->core->haltController = array('type' => 'auth', 'message' => ___('Authentication Required'));
                $this->configuration['m']   = $required_node_id;
                return false;
            }
        }
    }

    /**
     * Returns the node id of the exact same node only if it exists.
     *
     * @param $alias    string
     * @param $node_id  string
     * @return mixed
     */
    public function confirmNodeExist($alias, $node_id)
    {
        $sql = "
            SELECT  node_id
		    FROM    _db_core_node_items
		    WHERE   alias   = :alias
		    OR      node_id = :node_id
        ";
        if (empty($node_id)) $node_id = '';
        if (empty($alias))   $alias = '';
        return $this->db->querySingle($sql, array('alias' => $alias, 'node_id' => $node_id));
    }

    /**
     * This function support output_script by looking deeper into node structure to find last linked node item that
     * is not linked to another.
     *
     * @param string $extended_node_id
     * @return string
     */
    public function extendNodeLoop($extended_node_id)
    {
        $navigation = $this->navigation;

        // Assign extension value.
        $extend_more = $navigation[$extended_node_id]['extend'];
        // Check if we should look higher up for a working node id and prevent endless looping.
        if (!empty($extend_more) && ($extended_node_id != $navigation[$extend_more]['extend'])) {
            $this->extendNodeLoop($extend_more);
        } else {
            // Final check, to see if we had an endless loop that still has an extension.
            if (!empty($navigation[$extended_node_id]['extend'])) {
                if (!empty($navigation[$extended_node_id]['parent_node_id'])) {
                    // Lets look even higher up now that we jumped the endless loop.
                    $this->extendNodeLoop($navigation[$extended_node_id]['parent_node_id']);
                } else {
                    // We now have no other choice but to show default home page.
                    return '0';
                }
            } else {
                return $extended_node_id;
            }
        }

        return '0';
    }

    /**
     * This method returns the current URL with the option to add more $this->security->get variables like ("&variable1=1&variable2=2")
     * This is mostly used for when additional $this->security->get variables are required! Usefull when using forms.
     *
     * @param string $extra_get_variables Add more $this->security->get variables like ("&variable1=1&variable2=2")
     * @return string
     */
    public function selfUrl($extra_get_variables = '')
    {
        return $this->buildURL(false, $extra_get_variables, true);
    }

    /**
     * Will convert any given plugin script location to its correct url.
     *
     * @param string $file_path   The full file path, "DummyPlugin/sample/sample1.php"
     * @param string $extend_url  Should the url be extended with $_GET vars, 'e=12'
     * @param bool $strip_trail Will strip unwanted empty operators at the end.
     * @return string
     */
    public function purl($file_path, $extend_url = '', $strip_trail = true)
    {
        $node_id = $this->createNodeId($file_path);
        return $this->buildURL($node_id, $extend_url, $strip_trail);
    }

    /**
     * Simply converts a url to a clean SEF url if SEF is enabled.
     *
     * @param int     $node_id
     * @param string  $extend_url  'test1=foo1&test2=foo2&test3=foobar'
     * @param boolean $strip_trail should extending ? be removed.
     *
     * @return string
     */
    public function sefURL($node_id = null, $extend_url = '', $strip_trail = true)
    {
        $url = $this->buildURL($node_id, $extend_url, $strip_trail);

        if (!empty($this->configuration['sef_url'])) {
            return preg_replace(array('/\?/', '/\&/', '/\=/'), '/', $url);
        } else {
            return $url;
        }
    }

    /**
     * Convert plugin file location to unsigned CRC32 value. This is unique and allows one to locate a node item from location as well.
     *
     * @param string $path The plugin folder the file is in.
     * @return integer
     */
    public function createNodeId($path)
    {
        return sprintf('%u', crc32(str_ireplace('/', '', $path)));
    }

    /**
     * Redirects to new url.
     *
     * @param string $url URL to redirect to.
     * @param integer $time Time in seconds before redirecting.
     */
    public function redirect($url = null, $time = 0)
    {
        if ($url == null) {
            $redirect_url = $this->template->mod->nodeRedirect($this->buildURL($this->configuration['m']), $time);
        } else {
            $redirect_url = $this->template->mod->nodeRedirect($url, $time);
        }
        print $redirect_url;
    }

    /**
     * Returns the url of the 404 page selected by the admin.
     *
     * @return string
     */
    public function pageNotFound()
    {
        $node_id = $this->config->getSettings(array('404_error_page'), 'PHPDS');
        return $this->buildURL($node_id['404_error_page']);
    }
}