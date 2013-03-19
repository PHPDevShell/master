<?php

class PHPDS_core extends PHPDS_dependant
{
    /**
     * Contains controller content.
     * @var string
     */
    public $data;
    /**
     * Used as a bridge between controller to view data.
     * @var mixed
     */
    public $toView;
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
        $template_dir  = 'themes/' . $configuration['template_folder'] . '/';

        if (file_exists($template_dir . 'mods.php')) {
            include_once $template_dir . 'mods.php';
            if (class_exists($configuration['template_folder'])) {
                $this->template->mod = $this->factory($configuration['template_folder']);
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
        $template_dir  = 'themes/' . $configuration['template_folder'] . '/';

        if (!empty($this->themeName)) {
            $configuration['template_folder'] = $this->themeName;
            $template_dir                     = 'themes/' . $configuration['template_folder'] . '/';
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
     * @date 20120920 (v2.1.1) (greg) fixed a typo with $url
     * @throws PHPDS_accessException
     */
    public function startController()
    {
        $this->setDefaultNodeParams();
        try {
            ob_start();
            $this->db->startTransaction();
            $this->executeController();
            $this->db->endTransaction();

            if (empty($this->data)) {
                $this->data = ob_get_clean();
            } else {
                PU_cleanBuffers();
            }
        } catch (Exception $e) {
            PU_cleanBuffers();
            $this->themeFile = '';

            if (is_a($e, 'PHPDS_accessException')) {
                $logger = $this->factory('PHPDS_debug', 'PHPDS_accessException');
                $url    = $this->configuration['absolute_url'] . $_SERVER['REQUEST_URI'];

                switch ($e->HTTPcode) {
                    case 401:
                        if (!PU_isAJAX()) {
                            $this->themeFile = 'login.php';
                        }
                        PU_silentHeader("HTTP/1.1 401 Unauthorized");
                        PU_silentHeader("Status: 401");
                        $logger->error('URL unauthorized: ' . $url, '401');
                        break;
                    case 404:
                        if (!PU_isAJAX()) {
                            $this->themeFile = '404.php';
                        }
                        PU_silentHeader("HTTP/1.1 404 Not Found");
                        PU_silentHeader("Status: 404");
                        $logger->error('URL not found: ' . $url, '404');
                        break;
                    case 403:
                        if (!PU_isAJAX()) {
                            $this->themeFile = '403.php';
                        }
                        PU_silentHeader("HTTP/1.1 403 Forbidden");
                        PU_silentHeader("Status: 403");
                        $logger->error('URL forbidden ' . $url, '403');
                        break;
                    case 418:
                        sleep(30); // don't make spambot life live in the fast lane
                        if (!PU_isAJAX()) {
                            $this->themeFile = '418.php';
                        }
                        PU_silentHeader("HTTP/1.1 418 I'm a teapot and you're a spambot");
                        PU_silentHeader("Status: 418");
                        $logger->error('Spambot for ' . $url, '418');
                        break;
                    default:
                        throw $e;
                }

            } else throw $e;
        }
        // Only if we need a theme.
        if (!empty($this->themeFile)) {
            $this->loadTheme();
        } else {
            print $this->data;
        }
    }

    /**
     * Executes the controller.
     */
    public function executeController()
    {
        $navigation    = $this->navigation->navigation;
        $configuration = $this->configuration;

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
                    $this->loadControllerFile($node_id);
                    break;
                // Link, Jump, Placeholder.
                case 2:
                    // Is this an empty node item?
                    if (empty($node_id)) {
                        // Lets take user to the front page as last option.
                        // Get correct frontpage id.
                        ($this->user->isLoggedIn()) ? $node_id = $configuration['front_page_id_in'] : $node_id = $configuration['front_page_id'];
                    }
                    $this->loadControllerFile($node_id);
                    break;
                // External File.
                case 4:
                    // Require external file.
                    if (!$this->loadFile($navigation[$node_id]['node_link'])) {
                        throw new PHPDS_exception(sprintf(___('File could not be found after trying to execute filename : %s'), $navigation[$node_id]['node_link']));
                    }
                    break;
                // HTTP URL.
                case 5:
                    // Redirect to external http url.
                    $this->navigation->redirect($navigation[$node_id]['node_link']);
                    break;
                // iFrame.
                case 7:
                    // Clean up height.
                    $height = preg_replace('/px/i', '', $navigation[$node_id]['extend']);
                    // Create Iframe.
                    $this->data = $this->template->mod->iFrame($navigation[$node_id]['node_link'], $height, '100%');
                    break;
                // Cronjob.
                case 8:
                    // Require script.
                    if (!$this->loadControllerFile($node_id)) {
                        $time_now = time();
                        // Update last execution.
                        $this->db->invokeQuery('TEMPLATE_cronExecutionLogQuery', $time_now, $node_id);
                        // Always log manual touched cronjobs.
                        $this->template->ok(sprintf(___('Cronjob %s executed manually.'), $navigation[$node_id]['node_name']));
                    }
                    break;
                // HTML Ajax Widget.
                case 9:
                    $this->loadControllerFile($node_id);
                    break;
                // HTML Ajax.
                case 10:
                    $this->loadControllerFile($node_id);
                    break;
                // HTML Ajax Lightbox.
                case 11:
                    $this->loadControllerFile($node_id);
                    break;
                // HTML Ajax Lightbox.
                case 12:
                    $this->loadControllerFile($node_id);
                    break;
            }
        }

        if (isset($this->haltController)) {
            // Roll back current transaction.
            $this->db->invokeQuery('TEMPLATE_rollbackQuery');
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
    }

    /**
     * Will attempt to load controller file from various locations.
     *
     * @version 1.0.2
     *
     * @date 20100917 (v1.0) (Jason)
     * @date 20110308 (v1.0.1) (greg) loadFile returns an exact false when the file is not found
     * @date 20120606 (v1.0.2) (greg) add the "includes/" folder of the plugin in the include path
     *
     * @param int   $node_id
     * @param mixed $include_model |boolean $include_model if set, load the model file before the controller is run (either a prefix or true for default "query" prefix) - default is not to
     * @param mixed $include_view  |boolean $include_view if set, run the view file after the controller is run (a prefix) ; default is the "view" prefix)
     *
     * @throws PHPDS_exception
     * @return string
     */
    public function loadControllerFile($node_id, $include_model = false, $include_view = 'view')
    {
        $navigation = $this->navigation->navigation;
        $result_    = false;

        if (!empty($navigation[$node_id])) {
            $plugin_folder    = $navigation[$node_id]['plugin_folder'];
            $old_include_path = PU_addIncludePath($plugin_folder . '/includes/');

            if ($include_model) {
                if ($include_model === true) $include_model = 'query';
                $this->loadFile($plugin_folder . 'models/' . preg_replace("/.php/", '.' . $include_model . '.php', $navigation[$node_id]['node_link']));
            }

            $active_dir = $plugin_folder . '%s' . $navigation[$node_id]['node_link'];
            $result_    = $this->loadFile(sprintf($active_dir, 'controllers/'));
            if ($result_ === false) {
                $result_ = $this->loadFile(sprintf($active_dir, ''));
            }

            if (is_string($result_) && class_exists($result_)) {
                $controller = $this->factory($result_);
                $controller->run();
            }

            // Load view class.
            if ($include_view && !empty($this->themeFile)) {
                $load_view   = preg_replace("/.php/", '.' . $include_view . '.php', $navigation[$node_id]['node_link']);
                $view_result = $this->loadFile($plugin_folder . 'views/' . $load_view);
                if (is_string($view_result) && class_exists($view_result)) {
                    $view = $this->factory($view_result);
                    $view->run();
                }
            }
            set_include_path($old_include_path);
        }
        if ($result_ === false && empty($this->haltController)) {
            throw new PHPDS_exception(sprintf(___('The controller of node id %d could not be found after trying to execute filename : "%s"'), $node_id, sprintf($active_dir, '{controllers/}')));
        }
        return $result_;
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
     * Returns the difference in seconds between the currently logged in user's timezone
     * and the server's configured timezone (under General Settings). If the server
     * timezone is 2 hours behind the user timezone, it will return -7200 for example. If
     * the server timezone is 2 hours ahead of the user timezone, it will return 7200.
     *
     * @param integer $custom_timestamp Timestamp to compare dates timezones in the future or past.
     * @return integer The difference between the user's timezone and server timezone (in seconds).
     */
    public function userServerTzDiff($custom_timestamp = 0)
    {
        $configuration = $this->configuration;
        if (empty($custom_timestamp)) {
            $timestamp = $configuration['time'];
        } else {
            $timestamp = $custom_timestamp;
        }
        if (phpversion() < '5.2.0')
            return 0;
        $ut = new DateTime(date('Y-m-d H:i:s', $timestamp));
        $tz = new DateTimeZone($configuration['user_timezone']);
        $ut->setTimezone($tz);
        $user_timezone_sec = $ut->format('Z');
        $tz                = new DateTimeZone($configuration['system_timezone']);
        $ut->setTimezone($tz);
        $server_timezone_sec = $ut->format('Z');
        return $server_timezone_sec - $user_timezone_sec;
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
        foreach ($this->db->pluginsInstalled as $installed_plugins_array) {
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
            return 'AdminTools';
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
        if (!empty($navigation->navigation[$this->configuration['m']]['template_folder'])) {
            return $navigation->navigation[$this->configuration['m']]['template_folder'];
        } else {
            return $settings['default_template'];
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
        $core = $this->core;
        if ($from_template) $template = $this->template;
        $configuration = $this->configuration;
        $navigation    = $this->navigation;
        $db            = $this->db;
        $security      = $this->security;

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


    /**
     * Alternative charset aliases.
     * @param $charset
     * @return null|string
     */
    public function mangleCharset($charset)
    {
        $configuration = $this->configuration;

        $charsetList = !empty($configuration['charsetList']) ? $configuration['charsetList'] :
            array(
                'utf8'       => 'UTF-8',
                'latin1'     => 'ISO-8859-1',
                'latin5'     => 'ISO-8859-5',
                'big5'       => 'BIG5',
                'koi8r'      => 'KOI8-R',
                'macroman'   => 'MacRoman',
                'sjis'       => 'Shift_JIS',

                'UTF-8'      => 'utf8',
                'ISO-8859-1' => 'latin1',
                'ISO-8859-5' => 'latin5',
                'BIG5'       => 'big5',
                'KOI8-R'     => 'koir8r',
                'MacRoman'   => 'macroman',
                'Shift_JIS'  => 'sjis'
            );
        return empty($charsetList[$charset]) ? null : $charsetList[$charset];
    }
}
