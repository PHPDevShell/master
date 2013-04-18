<?php

class PHPDS_core extends PHPDS_dependant
{
    /**
     * Contains controller content.
     * @var string
     */
    public $data;
    /**
     * This variable is used to activate a stop script command, it will be used to end a script immediately while still finishing compiling the template.
     *
     * @var array
     */
    public $haltController;
    /**
     * The node structure that should be used "theme.php" for normal theme.
     * @var string
     */
    public $themeFile;
    /**
     * Name of the theme folder to use
     * @since v3.1.2
     * @var string
     */
    public $themeName;
    /**
     * Use this to have global available variables throughout scripts. For instance in hooks.
     *
     * @var array
     */
    public $skipLogin = false;
    /**
     * Execute theme structure according to node type.
     */
    public function setDefaultNodeParams()
    {
        $configuration = $this->configuration;
        $navigation    = $this->navigation->navigation;
        $current_node  = $navigation[$configuration['m']];

        // Option 1 : Switch via node
        if (!empty($current_node['node_id'])) {
            // Determine correct node theme.
            switch ($current_node['node_type']) {
                // Widget Ajax.
                case 9:
                    $this->ajaxType  = 'widget';
                    $this->themeFile = '';
                    $this->loadMods();
                    break;
                // HTML Ajax.
                case 10:
                    $this->ajaxType  = 'html';
                    $this->themeFile = '';
                    $this->loadMods();
                // HTML Ajax Lightbox.
                case 11:
                    $this->ajaxType  = 'lightbox';
                    $this->themeFile = '';
                    $this->loadMods();
                    break;
                // Raw Ajax (json,xml,etc).
                case 12:
                    $this->ajaxType = 'light';
                    break;
            }
        }

        // Option 2 : Switch via get request
        if (!empty($_REQUEST['via-ajax'])) {
            // Determine correct node theme.
            switch ($_REQUEST['via-ajax']) {
                // Widget Ajax.
                case 'widget':
                    $this->themeFile = '';
                    $this->ajaxType  = 'widget';
                    break;
                // HTML Ajax.
                case 'html':
                    $this->themeFile = '';
                    $this->loadMods();
                    $this->ajaxType = 'html';
                    break;
                // HTML Ajax Lightbox.
                case 'lightbox':
                    $this->themeFile = '';
                    $this->loadMods();
                    $this->ajaxType = 'lightbox';
                    break;
                // HTML Ajax Page.
                case 'page':
                    $this->themeFile = '';
                    $this->loadMods();
                    $this->ajaxType = 'page';
                    break;
                // Will use viaAjax but will also include mods.
                case 'light+mods':
                    $this->themeFile = '';
                    $this->loadMods();
                    $this->ajaxType = 'light+mods';
                    break;
                default:
                    $this->themeFile = '';
                    $this->ajaxType  = 'light';
                    break;
            }
        } else {
            if (PU_isAJAX()) {
                $this->themeFile = '';
                $this->ajaxType  = 'light';
            } else {
                $this->themeFile = 'theme.php';
                $this->loadMods();
                $this->ajaxType = null;
            }
        }
    }

    /**
     * Load mods (html snippets for specific theme.)
     * Creates mod object under $this->mod->...
     */
    public function loadMods()
    {
        $configuration = $this->configuration;
        $template_dir  = 'themes/' . $configuration['theme_folder'] . '/';

        if (file_exists($template_dir . 'mods.php')) {
            include_once $template_dir . 'mods.php';
            if (class_exists($configuration['theme_folder'])) {
                $this->template->mod = $this->factory($configuration['theme_folder']);
            } else {
                $this->template->mod = $this->factory('themeMods');
            }
        } else {
            include_once 'themes/default/mods.php';
            $this->template->mod = $this->factory('themeMods');
        }
    }

    /**
     * Loads and merges theme with controller.
     * @throws PHPDS_exception
     */
    public function loadTheme()
    {
        $configuration = $this->configuration;
        $template_dir  = 'themes/' . $configuration['theme_folder'] . '/';

        if (!empty($this->themeName)) {
            $configuration['theme_folder'] = $this->themeName;
            $template_dir                     = 'themes/' . $configuration['theme_folder'] . '/';
        }

        try {
            ob_start();
            $result = $this->loadFile($template_dir . $this->themeFile, false, true, true, true);
            if (false === $result) {
                $result = $this->loadFile('themes/default/' . $this->themeFile, false, true, true, true);
            }
            if (false === $result) {
                throw new PHPDS_exception('Unable to find the custom template "' . $this->themeFile . '" in directory "' . $template_dir . '"');
            }
            ob_end_flush();
        } catch (Exception $e) {
            PU_cleanBuffers();
            throw $e;
        }
    }

    /**
     * Run default, custom or no template.
     *
     * @throws PHPDS_accessException
     */
    public function startController ()
    {
        $configuration = $this->configuration;
        $node = $configuration['m'];

        // support for deferred, that is code running before and after a controller
        $deferred = null;
        if (is_a($node, 'iPHPDS_deferred')) {
            /* @var iPHPDS_deferred $deferred */
            $deferred = $node;
            $node = $deferred->reduce();
        }

        $configuration['m'] = $this->navigation->checkNode($node);

        $result = false;
        try {
            $this->setDefaultNodeParams();

            ob_start();
            $result = $this->executeController();

            if (empty($this->data)) {
                $this->data = ob_get_clean();
            } else {
                PU_cleanBuffers();
            }
        } catch (Exception $e) {
            if ($deferred) {
                $deferred->failure($result);
                $deferred = null;
            }
            $this->pageException($e);
        }

        if ($deferred) {
            $deferred->success($result);
        }

        // Only if we need a theme.
        if (! empty($this->themeFile)) {
            $this->loadTheme();
        } else {
            print $this->data;
        }
    }

    /**
     * Handles the given exception, dealing with some special cases (page not found, unauthorized, etc)
     *
     * @param Exception $e
     * @throws Exception
     */
    protected function pageException(Exception $e)
    {
        PU_cleanBuffers();
        $this->themeFile = '';

        if (is_a($e, 'PHPDS_accessException')) {
            $logger = $this->factory('PHPDS_debug', 'PHPDS_accessException');
            $url = $this->configuration['absolute_url'] . $_SERVER['REQUEST_URI'];

            PU_silentHeaderStatus($e->HTTPcode);

            switch ($e->HTTPcode) {
                case 401:
                    if (!PU_isAJAX()) {
                        $this->themeFile = 'login.php';
                    }
                    $logger->error('URL unauthorized: ' . $url, '401');
                    break;
                case 404:
                    if (!PU_isAJAX()) {
                        $this->themeFile = '404.php';
                    }
                    $logger->error('URL not found: ' . $url, '404');
                    break;
                case 403:
                    if (!PU_isAJAX()) {
                        $this->themeFile = '403.php';
                    }
                    $logger->error('URL forbidden ' . $url, '403');
                    break;
                case 418:
                    if (!PU_isAJAX()) {
                        $this->themeFile = '418.php';
                    }
                    $logger->error('Spambot for ' . $url, '418');
                    break;
                default:
                    throw $e;
            }

        } else throw $e;
    }

    /**
     * Executes the controller.
     */
    public function executeController()
    {
        $navigation    = $this->navigation->navigation;
        $configuration = $this->configuration;
        $result        = false;

        // Node Types:
        // 1. Standard Page from Plugin
        // 2. Link to Existing Node
        // 3. Jump to Existing Node
        // 4. Simple Place Holder Link
        // 5. Load External File
        // 6. External HTTP URL
        // 7. iFrame (Very old fashioned)
        // 8. Automatic Cronjob
        // 9. HTML Ajax Widget (Serves as module inside web page)
        // 10. HTML Ajax (Used for ajax)
        // 11. HTML Ajax Lightbox (Floats overtop of web page)
        // 12. Raw Ajax (json, xml, etc.)
        // Load script to buffer.
        if (!empty($navigation[$configuration['m']]['node_id'])) {
            // We need to assign active node_id.
            $node_id = $configuration['m'];
            // Determine correct node action.
            switch ($navigation[$configuration['m']]['node_type']) {
                // Plugin File.
                case 1:
                    $node_case = 1;
                    break;
                // Link.
                case 2:
                    break;
                // Jump.
                case 3:
                    break;
                // External File.
                case 4:
                    $node_case = 4;
                    break;
                // HTTP URL.
                case 5:
                    $node_case = 5;
                    break;
                // Placeholder.
                case 6:
                    break;
                // iFrame.
                case 7:
                    $node_case = 7;
                    break;
                // Cronjob.
                case 8:
                    $node_case = 8;
                    break;
                // HTML Widget.
                case 9:
                    $node_case = 9;
                    break;
                // HTML Ajax.
                case 10:
                    $node_case = 10;
                    break;
                // HTML Ajax Lightbox.
                case 11:
                    $node_case = 11;
                    break;
                // Raw Ajax (json,xml,etc).
                case 12:
                    $node_case = 12;
                    break;
                default:
                    // Do case.
                    $node_case = 1;
                    break;
            }
            ///////////////////////////////////
            // Do further checking on links. //
            ///////////////////////////////////
            if (empty($node_case)) {
                // So we have some kind of link, we now need to see what kind of link we have.
                // Get node extended data.
                $extend = $navigation[$node_id]['extend'];
                // Get node type.
                if (!empty($navigation[$extend]['node_type'])) {
                    $linked_node_type = $navigation[$extend]['node_type'];
                } else {
                    throw new PHPDS_extendNodeException(array($navigation[$configuration['m']]['node_id'], $extend));
                }
                // We now have the linked node type and can now work accordingly.
                // Determine correct node action.
                switch ($linked_node_type) {
                    // Plugin File.
                    case 1:
                        $node_case = 1;
                        $node_id   = $extend;
                        break;
                    // Link.
                    case 2:
                        $node_case = 2;
                        $node_id   = $this->navigation->extendNodeLoop($navigation[$extend]['extend']);
                        break;
                    // Jump.
                    case 3:
                        $node_case = 2;
                        $node_id   = $this->navigation->extendNodeLoop($navigation[$extend]['extend']);
                        break;
                    // External File.
                    case 4:
                        $node_case = 4;
                        $node_id   = $extend;
                        break;
                    // HTTP URL.
                    case 5:
                        $node_case = 5;
                        $node_id   = $extend;
                        break;
                    // Placeholder.
                    case 6:
                        $node_case = 2;
                        $node_id   = $this->navigation->extendNodeLoop($navigation[$extend]['extend']);
                        break;
                    // iFrame.
                    case 7:
                        $node_case = 7;
                        $node_id   = $extend;
                        break;
                    // Cronjob.
                    case 8:
                        $node_case = 8;
                        $node_id   = $extend;
                        break;
                    // HTML Ajax Widget.
                    case 9:
                        $node_case = 9;
                        $node_id   = $extend;
                        break;
                    // HTML Ajax.
                    case 10:
                        $node_case = 10;
                        $node_id   = $extend;
                        break;
                    // HTML Ajax Lightbox.
                    case 11:
                        $node_case = 11;
                        $node_id   = $extend;
                        break;
                    // Raw Ajax.
                    case 12:
                        $node_case = 12;
                        $node_id   = $extend;
                        break;
                    default:
                        $node_case = 1;
                        $node_id   = $extend;
                        break;
                }
            }
            // Execute repeated node cases.
            switch ($node_case) {
                // Plugin Script.
                case 1:
                    $result = $this->mvcNodeStructure($node_id);
                    break;
                // Link, Jump, Placeholder.
                case 2:
                    // Is this an empty node item?
                    if (empty($node_id)) {
                        // Lets take user to the front page as last option.
                        // Get correct frontpage id.
                        ($this->user->isLoggedIn()) ? $node_id = $configuration['front_page_id_in'] : $node_id = $configuration['front_page_id'];
                    }
                    $result = $this->mvcNodeStructure($node_id);
                    break;
                // External File.
                case 4:
                    // Require external file.
                    $result = $this->loadFile($navigation[$node_id]['node_link']);
                    if (false == $result) {
                        throw new PHPDS_exception(sprintf(___('File could not be found after trying to execute filename : %s'), $navigation[$node_id]['node_link']));
                    }
                    break;
                // HTTP URL.
                case 5:
                    $result = true;
                        // Redirect to external http url.
                    $this->navigation->redirect($navigation[$node_id]['node_link']);
                    break;
                // iFrame.
                case 7:
                    $result = true;
                        // Clean up height.
                    $height = preg_replace('/px/i', '', $navigation[$node_id]['extend']);
                    // Create Iframe.
                    $this->data = $this->template->mod->iFrame($navigation[$node_id]['node_link'], $height, '100%');
                    break;
                // Cronjob.
                case 8:
                    // Require script.
                    $result = $this->mvcNodeStructure($node_id);
                    if ($result) {
                        $time_now = time();
                        // Update last execution.
                        $sql = "
                        	UPDATE _db_core_cron      AS t1
                            SET    t1.last_execution  = :last_execution
                            WHERE  t1.node_id         = :node_id
                        ";
                        $this->db->query($sql, array('last_execution' => $time_now, 'node_id' => $node_id));
                    }
                    break;
                // HTML Ajax Widget.
                case 9:
                    $result = $this->mvcNodeStructure($node_id);
                    break;
                // HTML Ajax.
                case 10:
                    $result = $this->mvcNodeStructure($node_id);
                    break;
                // HTML Ajax Lightbox.
                case 11:
                    $result = $this->mvcNodeStructure($node_id);
                    break;
                // Raw Ajax.
                case 12:
                    $result = $this->mvcNodeStructure($node_id);
                    break;

                // something went wrong
                default:
                    throw new PHPDS_exception('Broken controller node');
            }
        } else {
            throw new PHPDS_exception('Controller node not found');
        }

        if (isset($this->haltController)) {
            switch ($this->haltController['type']) {
                case 'auth':
                    throw new PHPDS_securityException($this->haltController['message']);
                    break;

                case '404':
                    throw new PHPDS_pageException404($this->haltController['message'], $this->haltController['type']);
                    break;

                case '403':
                    throw new PHPDS_securityException403($this->haltController['message'], $this->haltController['type']);
                    break;

                case '418':
                    throw new PHPDS_pageException418($this->haltController['message'], $this->haltController['type']);
                    break;

                default:
                    throw new PHPDS_securityException($this->haltController['message'], $this->haltController['type']);
                    break;
            }
        }
        return $result;
    }

    /**
     * Will attempt to load controller file from specific node locations and create its MVC structure.
     *
     * @param int   $node_id
     * @param string $include_query if set, load the query file before the controller is run (either a prefix or true for default "query" prefix) - default is not to
     * @param string $include_view  if set, run the view file after the controller is run (a prefix) ; default is the "view" prefix)
     * @param string $include_model  |boolean $include_view if set, run the view file after the controller is run (a prefix) ; default is the "view" prefix)
     *
     * @throws PHPDS_exception
     * @return string
     */
    public function mvcNodeStructure($node_id, $include_query = 'query', $include_view = 'view', $include_model = 'model')
    {
        $navigation = $this->navigation->navigation;
        $control_   = null;
        $model      = null;
        $view       = null;

        if (!empty($navigation[$node_id])) {
            $plugin_folder    = $navigation[$node_id]['plugin_folder'];
            $old_include_path = PU_addIncludePath($plugin_folder . '/includes/');
            $node_link        = $navigation[$node_id]['node_link'];

            // Query
            if ($include_query) {
                $this->loadFile($plugin_folder . 'models/' . preg_replace(
                    "/.php/",
                    '.' . $include_query . '.php',
                    $node_link)
                );
            }

            // Model
            if ($include_model) {
                $model_ = $this->loadFile($plugin_folder . 'models/' . preg_replace(
                    "/.php/",
                    '.' . $include_model . '.php',
                    $node_link))
                ;
                if (is_string($model_) && class_exists($model_)) {
                    $model = $this->factory($model_);
                    $model->extends = true;
                } else {
                    $model = $this->factory($this->configuration['extend']['model']);
                }
            }

            // View
            if ($include_view) {
                $view_ = $this->loadFile($plugin_folder . 'views/' . preg_replace(
                    "/.php/",
                    '.' . $include_view . '.php',
                    $node_link)
                );
                if (is_string($view_) && class_exists($view_)) {
                    $view = $this->factory($view_);
                    $view->extends = true;
                } else {
                    $view = $this->factory($this->configuration['extend']['view']);
                }
            }

            // Controller
            $active_dir = $plugin_folder . '%s' . $node_link;
            $control_    = $this->loadFile(sprintf($active_dir, 'controllers/'));
            if ($control_ === false) {
                $control_ = $this->loadFile(sprintf($active_dir, ''));
            }

            if (is_string($control_) && class_exists($control_)) {
                $controller         = $this->factory($control_);
                $controller->model  = $model;
                $controller->view   = $view;
                $controller->run();
            }

            set_include_path($old_include_path);
        }
        if ($control_ === false && empty($this->haltController)) {
            throw new PHPDS_exception(sprintf(___('The controller of node id %d could not be found after trying to execute filename : "%s"'), $node_id, sprintf($active_dir, '{controllers/}')));
        }
        return $control_;
    }

    /**
     * Calculates correct time for logged in user.
     *
     * @param int    $time_stamp
     * @param string $format_type_or_custom
     * @param string $custom_timezone
     * @return bool|string
     */
    public function formatTimeDate($time_stamp, $format_type_or_custom = 'default', $custom_timezone = null)
    {
        $configuration = $this->configuration;
        // Check if we habe an empty time stamp.
        if (empty($time_stamp)) return false;
        // Check if we have a custom timezone.
        if (!empty($custom_timezone)) {
            $timezone = $custom_timezone;
        } else if (!empty($configuration['user_timezone'])) {
            $timezone = $configuration['user_timezone'];
        } else {
            $timezone = $configuration['system_timezone'];
        }

        if ($format_type_or_custom == 'default') {
            $format = $configuration['date_format'];
        } else if ($format_type_or_custom == 'short') {
            $format = $configuration['date_format_short'];
        } else {
            $format = $format_type_or_custom;
        }
        if (phpversion() < '5.2.0') return strftime('%c', $time_stamp);
        try {
            $ut = new DateTime(date('Y-m-d H:i:s', $time_stamp));
            $tz = new DateTimeZone($timezone);
            $ut->setTimezone($tz);
        } catch (Exception $e) {
            // Work around error from old database column.
            $configuration['user_timezone'] = $configuration['system_timezone'];
            return date(DATE_RFC822);
        }

        return $ut->format($format);
    }

    /**
     * Function formats locale according to logged in user settings else will default to system.
     *
     * @param bool|string $charset
     * @param bool|string $user_language
     * @param bool|string $user_region
     * @return string
     */
    public function formatLocale($charset = true, $user_language = false, $user_region = false)
    {
        $configuration = $this->configuration;
        if (empty($configuration['charset_format'])) $configuration['charset_format'] = false;
        if (!empty($user_language)) $configuration['user_language'] = $user_language;
        if (!empty($user_region)) $configuration['user_region'] = $user_region;
        if (empty($configuration['user_language'])) $configuration['user_language'] = $configuration['language'];
        if (empty($configuration['user_region'])) $configuration['user_region'] = $configuration['region'];
        if ($charset && !empty($configuration['charset_format'])) {
            $locale_format = preg_replace('/\{charset\}/', $configuration['charset_format'], $configuration['locale_format']);
            $locale_format = preg_replace('/\{lang\}/', $configuration['user_language'], $locale_format);
            $locale_format = preg_replace('/\{region\}/', $configuration['user_region'], $locale_format);
            $locale_format = preg_replace('/\{charset\}/', $configuration['charset'], $locale_format);
            return $locale_format;
        } else {
            $locale_format = preg_replace('/\{lang\}/', $configuration['user_language'], $configuration['locale_format']);
            $locale_format = preg_replace('/\{region\}/', $configuration['user_region'], $locale_format);
            $locale_format = preg_replace('/\{charset\}/', '', $locale_format);
            return $locale_format;
        }
    }

    /**
     * This methods allows you to load translation by giving their locations and name.
     *
     * @param string $mo_directory This is the location where language mo file is found.
     * @param string $mo_filename The mo filename the translation is compiled in.
     * @param string $textdomain The actual text domain identifier.
     */
    protected function loadTranslation($mo_directory, $mo_filename, $textdomain)
    {
        $configuration  = $this->configuration;
        $bindtextdomain = $configuration['absolute_path'] . $mo_directory;
        $loc_dir        = $bindtextdomain . $configuration['locale_dir'] . '/LC_MESSAGES/' . $mo_filename;

        (file_exists($loc_dir)) ? $mo_ok = true : $mo_ok = false;
        if ($mo_ok) {
            $this->log('Found Translation File : ' . $loc_dir);
            bindtextdomain($textdomain, $bindtextdomain);
            bind_textdomain_codeset($textdomain, $configuration['charset']);
            textdomain($textdomain);
        } else {
            $this->debugInstance()->warning('MISSING Translation File : ' . $loc_dir);
        }
    }

    /**
     * This method loads the core language array and assigns it to a variable.
     */
    public function loadCoreLanguage()
    {
        $this->loadTranslation('language/', 'core.lang.mo', 'core.lang');
    }

    /**
     * This method loads the default node language array and assigns it to a variable.
     */
    public function loadNodeLanguage()
    {
        // Lets loop the installed plugins.
        foreach ($this->config->pluginsInstalled as $installed_plugins_array) {
            $plugin_folder = $installed_plugins_array['plugin_folder'];
            $this->loadTranslation("plugins/$plugin_folder/language/", "$plugin_folder.mo", "$plugin_folder");
        }
    }

    /**
     * This method loads the plugin language with default items and icons array.
     */
    public function loadDefaultPluginLanguage()
    {
        $active_plugin = $this->activePlugin();
        textdomain($active_plugin);
    }

    /**
     * Function to return the current running/active plugin.
     */
    public function activePlugin()
    {
        $navigation    = $this->navigation;
        $configuration = $this->configuration;

        if (!empty($configuration['m']) && !empty($navigation->navigation[$configuration['m']]['plugin'])) {
            return $navigation->navigation[$this->configuration['m']]['plugin'];
        } else {
            return 'PHPDS';
        }
    }

    /**
     * Function to return the current running/active template.
     *
     * @return string
     */
    public function activeTemplate()
    {
        $settings   = $this->configuration;
        $navigation = $this->navigation;
        if (!empty($navigation->navigation[$this->configuration['m']]['theme_folder'])) {
            return $navigation->navigation[$this->configuration['m']]['theme_folder'];
        } else {
            return $settings['default_theme'];
        }
    }

    /**
     * Turns any given relative path to the absolute version of the path.
     * @param string $relative_path Provide path like 'test/testpath'
     * @return string
     */
    public function absolutePath($relative_path)
    {
        $absolute_path = $this->configuration['absolute_path'] . ltrim($relative_path, '/');
        return str_ireplace('//', '/', $absolute_path);
    }

    /**
     * Assumes role of loading files.
     *
     * @param string $path
     * @param boolean $required Should the file be required or else included.
     * @param boolean $relative Is this a relative path, if true, it will be converted to absolute path.
     * @param boolean $once_only Should it be called only once?
     * @param boolean $from_template Should it be called only once?
     *
     * @return mixed whatever the file returned when executed or false if it couldn't be found
     * @throws PHPDS_exception
     */
    public function loadFile($path, $required = false, $relative = true, $once_only = true, $from_template = false)
    {
        // These vars are assigned to deal with inline files using them directly.
        if ($from_template) $template = $this->template;
        $core          = $this->core;
        $configuration = $this->configuration;
        $navigation    = $this->navigation;

        if (empty($path)) throw new PHPDS_exception('Trying to load a file with an empty path.');

        if ($relative) $path = $configuration['absolute_path'] . $path;

        $this->log('Loading : ' . $path);

        // switch the domain to "user" so the developer can filter to see only its own output
        $this->debugInstance()->domain('user');

        $result = false;

        if (file_exists($path)) {
            if ($required) {
                if (!empty($once_only)) $result = require_once ($path); else $result = require ($path);
            } else {
                if (!empty($once_only)) $result = include_once ($path); else $result = include ($path);
            }
        } else {
            if ($required) throw new PHPDS_exception('Trying to load a non-existant file: "' . $path . '"');
        }

        // revert to the "core" domain since we're out of the developer's code
        $this->debugInstance()->domain('core');

        return $result;
    }

    /**
     * Method is used to wrap the gettext international language conversion tool inside PHPDevShell.
     * Converts text to use gettext PO system.
     *
     * @param string $say_what The string required to output or convert.
     * @param string $domain   Override textdomain that should be looked under for this text string.
     * @return string Will return converted string or same string if not available.
     */
    public function __($say_what, $domain = '')
    {
        return __($say_what, $domain);
    }

    /**
     * Will log current configuration data to firephp.
     * @return void
     */
    public function logConfig()
    {
        $this->log((array)$this->configuration);
    }
}
