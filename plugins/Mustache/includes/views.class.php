<?php

class views extends PHPDS_dependant
{
    /**
     * Default extension of view file.
     * @var string
     */
    public $extension = '.html';
    /**
     * Contains mustache object.
     *
     * @var object
     */
    public $view;
    /**
     * Collects an array of assignments for mustache template.
     *
     * @var array
     */
    public $set;
    /**
     * Current active node link.
     * @var string
     */
    public $nodeLink;

    public function construct($plugin_override = '')
    {
        $configuration = $this->configuration;
        $view_dir      = $this->tplBaseDir($plugin_override);

        require BASEPATH . 'plugins/Mustache/resources/src/Mustache/Autoloader.php';
        Mustache_Autoloader::register();
        $loader     = new Mustache_Loader_FilesystemLoader($view_dir, array('extension' => $this->extension));
        $this->view = new Mustache_Engine(array(
            'template_class_prefix' => '__view_',
            'cache'                 => BASEPATH . $configuration['compile_path'],
            'loader'                => $loader,
            'helpers'               => array('i' => function ($text) { return __($text); }),
            'escape'                => function ($value) { return htmlspecialchars($value, ENT_COMPAT); },
            'charset'               => $configuration['charset']));
    }

    /**
     * Sets mustache variables to be passed to it.
     *
     * @param mixed $var
     * @param mixed $value
     */
    public function set($var, $value)
    {
        $this->set[$var] = $value;
    }

    /**
     * Loads the default or custom template (tpl) file and prints it out.
     * Enter the template file for appropriate script here.
     *
     * @param string $load_view Load an alternative view directly.
     * @return string
     */
    public function show($load_view = '')
    {
        $tpl = $this->getTpl($load_view);
        echo $this->view->render($tpl, $this->set);
    }

    /**
     * Loads the default or custom template (tpl) file and returns it.
     * Works well where blocks of html from ajax requests are wanted.
     *
     * @param string $load_view Load an alternative view directly.
     * @return string
     */
    public function getView($load_view = '')
    {
        $tpl = $this->getTpl($load_view);
        return $this->view->render($tpl, $this->set);
    }

    /**
     * Base directory where view file is suppose to sit in.
     *
     * @param string $load_view
     * @param string $plugin_override
     * @return string
     */
    private function tplBaseDir($load_view = '', $plugin_override = '')
    {
        $configuration = $this->configuration;
        $navigation    = $this->navigation;
        $core          = $this->core;

        // Node link.
        if (empty($navigation->navigation[$configuration['m']]['extend'])) {
            $node_link = $navigation->navigation[$configuration['m']]['node_link'];
        } else {
            $node_link     = $navigation->navigation[$navigation->navigation[$configuration['m']]['extend']]['node_link'];
            $plugin_extend = $navigation->navigation[$navigation->navigation[$configuration['m']]['extend']]['plugin'];
        }

        $this->nodeLink = $node_link;

        // Do template engine.
        if (empty($plugin_override) && empty($plugin_extend)) {
            $plugin_folder = $configuration['absolute_path'] . 'plugins/' . $core->activePlugin();
        } else if (!empty($plugin_override)) {
            $plugin_folder = $configuration['absolute_path'] . 'plugins/' . $plugin_override;
        } else if (!empty($plugin_extend)) {
            $plugin_folder = $configuration['absolute_path'] . 'plugins/' . $plugin_extend;
        } else {
            $plugin_folder = '';
        }

        $node_link = $this->reverseStrrchr($node_link, "/", 0);
        $tpl_dir   = empty($node_link) ? $plugin_folder . '/views' : $plugin_folder . '/views/' . $node_link;

        return $tpl_dir;
    }

    /**
     * Gets the correct tpl file.
     *
     * @author jason <titan@phpdevshell.org>
     *
     * @param string $load_view
     * @throws PHPDS_exception
     * @return string
     */
    private function getTpl($load_view = '')
    {
        $configuration = $this->configuration;
        $navigation    = $this->navigation;

        // Do we have a custom template file?
        if (empty($load_view) && !empty($navigation->navigation[$configuration['m']]['layout'])) {
            $load_view = $navigation->navigation[$configuration['m']]['layout'];
        }

        // Check if we have a custom layout otherwise use default.
        if (!empty($load_view)) {
            return $load_view;
        } else {
            $tpl = substr(strrchr($this->nodeLink, "/"), 1);
            if (empty($tpl)) {
                $tpl = $this->nodeLink;
            }
        }
        $tpl = str_replace('.php', '', $tpl);
        if (empty($tpl)) throw new PHPDS_exception('No view file available.');
        return $tpl;
    }

    /**
     * Returns the first part of a string after last needle is found.
     * @example: reverseStrrchr('some/stupid/dir/example.php', '/', 0); returns some/stupid/dir
     *
     * @param $haystack
     * @param $needle
     * @param $trail
     *
     * @return string
     */
    private function reverseStrrchr($haystack, $needle, $trail)
    {
        return strrpos($haystack, $needle) ? substr($haystack, 0, strrpos($haystack, $needle) + $trail) : '';
    }
}