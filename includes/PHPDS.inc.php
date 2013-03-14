<?php
define('phpdevshell_version', 'PHPDevShell V-4.0.0-Beta-1-DB-4000');
define('phpdevshell_db_version', '4000');

require_once 'PHPDS_utils.inc.php';
/**
 * Root class of PHPDevShell, binds all methods/plugins together.
 */
class PHPDS
{
    /**
     * Core system configuration settings.
     *
     * @var object
     */
    protected $configuration;
    /**
     * Core system language values.
     *
     * @var object
     */
    protected $lang;
    /**
     * Core database object.
     *
     * @var object
     */
    protected $db;
    /**
     * Core object.
     *
     * @var object
     */
    protected $core;
    /**
     * Core user object.
     *
     * @var object
     */
    protected $user;
    /**
     * Core security object.
     *
     * @var object
     */
    protected $security;
    /**
     * Core navigation object.
     *
     * @var object
     */
    protected $navigation;
    /**
     * Core template object.
     *
     * @var object
     */
    protected $template;
    /**
     * Main instance of the debug module, which handles startup init.
     *
     * @var object
     */
    protected $debugInstance;
    /**
     * Main instance of the tagger module
     */
    protected $tagger;
    /**
     * Main instance of the notification module
     *
     * @var PHPDS_notif
     */
    protected $notif;
    /**
     * Main class factory and registry
     *
     * @var PHPDS_classFactory
     */
    protected $classes;
    /**
     * PHPDS is used throught the lib (true) or standalone (false).
     *
     * @var boolean
     */
    protected $embedded;
    /**
     * The textual path of PHPDS directory on disk (not the URL).
     *
     * @var string
     */
    protected $basepath;
    /**
     * The higher the less backward-compatible.
     *
     * @var int
     */
    protected $compatMode = 1;
    /**
     * Execution stage (i.e. run level)
     *
     * @var int
     */
    protected $stage = 1; // 1 => initialization ; 2 => running

    /**
     * Constructor: Initialize the instance and starts the configuration process.
     *
     * @param boolean $embedded
     * @return self
     */
    public function __construct($embedded = false)
    {
        // Start Ob.
        ob_start();
        $this->embedded    = $embedded;
        $this->objectCache = new PHPDS_array;
        $this->basepath    = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR;
        try {
            $this->config();
        } catch (Exception $e) {
            $this->PHPDS_errorHandler()->doHandleException($e);
        }
        return $this;
    }

    /**
     * Destruct Class.
     */
    public function __destruct()
    {
        PU_cleanBuffers(true);
    }

    /**
     * Load one config file, if it exists
     * If it's ok, its name is added to $configuration['config_files']
     *
     * @param string $path absolute file name
     * @param array  $configuration
     * @return boolean whether it's successful
     */
    protected function includeConfigFile($path, &$configuration)
    {
        if (empty($path)) return false;

        if (!file_exists($path)) {
            $configuration['config_files_missing'][] = $path;
            return false;
        }
        $this->log("Loading config file $path");

        include_once $path;

        $configuration['config_files_used'][] = $path;
        return true;
    }

    /**
     * Load one config file, if it exists; also try a ".local" with the same name
     *
     * @param string $filename short file name
     * @param array  $configuration
     * @return boolean
     */
    protected function loadConfigFile($filename, &$configuration)
    {
        if (empty($filename)) return false;

        $this->log("Looking for general config file \"$filename\"");

        return $this->includeConfigFile($this->basepath('config') . $filename . '.config.php', $configuration);
    }

    /**
     * Load plugin-specific host-style config
     *
     * This allows plugins to provide a configuration, for example when 1 plugin <=> 1 site
     * @param array $configuration
     */
    protected function loadPluginsConfig(&$configuration)
    {
        $files = glob($this->basepath('plugins') . '/*/config/host.config.php');
        if (!empty($files)) {
            foreach ($files as $filename) {
                $this->log("Looking for plugin config file \"$filename\"");
                $this->includeConfigFile($filename, $configuration);
            }
        }
    }

    /**
     * Create a config array from the config files, and store it in the instance field
     *
     * The actual configuration is loaded from two files (one is generic, the other is custom)
     * from the config/ folder.
     *
     * @return $this
     */
    protected function loadConfig()
    {
        // starts with an empty array which will contain the actual names of the configurations files used
        $configuration                         = array();
        $configuration['config_files_used']    = array();
        $configuration['config_files_missing'] = array();

        $configuration['phpdevshell_version']    = phpdevshell_version;
        $configuration['phpdevshell_db_version'] = phpdevshell_db_version;

        if (!is_dir($this->basepath('config'))) {
            print 'The folder "<tt>' . $this->basepath . 'config</tt>" is missing.<br />';
            print 'It must be present and contain at least one config file.<br />';
            print 'Read the install instruction on creating a config file and installing the database.<br>';
            print '<a href="http://wiki.phpdevshell.org/wiki/Installing_PHPDevShell" target="_blank">Click here to read install instructions.</a><br>';
            print 'PHPDevShell basepath is "<tt>' . $this->basepath . '</tt>".<br>';
            exit();
        }

        $this->loadConfigFile('PHPDS-defaults', $configuration);
        $this->loadConfigFile('multi-host', $configuration);
        $this->loadConfigFile('single-site', $configuration);

        $this->loadPluginsConfig($configuration);

        if (!empty($_SERVER['SERVER_NAME'])) {
            if (!empty($configuration['host'][$_SERVER['SERVER_NAME']])) {
                $this->loadConfigFile($configuration['host'][$_SERVER['SERVER_NAME']], $configuration);
            }
            $this->loadConfigFile($_SERVER['SERVER_NAME'], $configuration);
        }

        if (count($configuration['config_files_used']) == 0) {
            print 'No config file found for host "' . $_SERVER['SERVER_NAME'] . '" inside folder "<tt>' . $this->basepath('config') . '</tt>"<br>';
            print 'At least one file among those listed below should be readable.<br />';
            print 'Read the install instruction on creating a config file and installing the database.<br>';
            print 'PHPDevShell basepath is "<tt>' . $this->basepath . '</tt>".<br>';
            print 'All these files were tried:<br><tt>';
            print implode('<br>', $configuration['config_files_missing']);
            print '</tt>';
            exit();
        }
        $this->PHPDS_configuration($configuration);
        spl_autoload_register(array($this, "PHPDS_autoloader"));
        return $this; // to allow fluent interface
    }

    /**
     * Deal with the "session" part of the configuration
     *
     * In standalone mode, create the sessions; in embedded mode,
     * fetch the current session (we hijack the session, we don't create a new one)
     *
     * NOTE: in embedded mode you MUST create a session before using PHPDSlib
     *
     * @throws PHPDS_sessionException
     * @return $this
     */
    protected function configSession()
    {
        if (empty($this->configuration['absolute_url'])) {
            $protocol                            = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
            $this->configuration['absolute_url'] = $protocol . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        }

        try {
            if ($this->embedded) {
                $this->configuration['session_name'] = session_name();
                // TODO: deal with empty session
            } else {
                if (!empty($this->configuration['absolute_url']))
                    $this->configuration['session_name'] = md5($this->configuration['absolute_url']);

                if (!empty($this->configuration['session_name']))
                    session_name(md5($this->configuration['session_name']));
                if (!empty($this->configuration['session_path']))
                    session_save_path($this->configuration['session_path']);

                session_start();
            }

            // Make sure we don't keep our session longer then it should be.
            if (!empty($this->configuration['session_life'])) {
                if (isset($_SESSION['SYSTEM_SESSION_TIME']) && (time() - $_SESSION['SYSTEM_SESSION_TIME']) > $this->configuration['session_life']) {
                    // Session can now be destroyed.
                    session_destroy();
                    session_regenerate_id(true);
                    $_SESSION = array();
                } else {
                    $_SESSION['SYSTEM_SESSION_TIME'] = time();
                }
            }
        } catch (Exception $e) {
            throw new PHPDS_sessionException(session_save_path(), 0, $e);
        }

        return $this; // to allow fluent interface
    }

    /**
     * Deal with database access configuration. Also makes the first master connection to the database.
     * Fix: The database and connector classes will load their own configuration.
     *
     * @return $this the current instance
     */
    protected function configDb()
    {
        $db = $this->PHPDS_db();
        $db->connect();
        $db->connectCacheServer();

        return $this; // to allow fluent interface
    }

    /**
     * Copy settings from the database-loaded array. Converts and defaults to false if the value isn't set
     *
     * @param array $settings
     * @param mixed $type the type of value to cast (currently only boolean or null for everything else)
     */
    protected function copySettings($settings, $type = null)
    {
        $this->copyArray($this->PHPDS_db()->essentialSettings, $this->configuration, $settings, $type);
    }

    /**
     * Copy an array to another and defaults to false if the value isn't set
     *
     * @param array  $source    the array to extract values from
     * @param array  $target    the array to add the values to
     * @param array  $indexes   the indexes of the values to copy
     * @param string $type      the type of value to cast (currently only boolean or null for everything else)
     */
    public function copyArray($source, &$target, $indexes, $type = null)
    {
        if (!is_array($indexes)) $indexes = array($indexes);
        switch ($type) {
            case 'boolean':
                foreach ($indexes as $index) {
                    $target[$index] = isset($source[$index]) ? (boolean)$source[$index] : false;
                }
                break;
            default:
                foreach ($indexes as $index) {
                    $target[$index] = isset($source[$index]) ? $source[$index] : false;
                }
        }
    }

    /**
     * Fetch core settings from the site configuration stored in the database
     * Also fetch some settings from the session and the locales
     *
     * @return $this the current instance
     */
    protected function configCoreSettings()
    {
        // Set core settings. //////////////////////////////////////////////////////////////
        $this->configuration['absolute_path'] = $this->basepath(); /////////////////////////
        $this->copySettings($this->configuration['preloaded_settings']); ///////////////////
        ////////////////////////////////////////////////////////////////////////////////////

        // Prepares login. /////////////////////////////////////////////////////////////////
        $this->PHPDS_user()->controlLogin(); ///////////////////////////////////////////////

        // Asign locale to use. ////////////////////////////////////////////////////////////
        $this->configuration['locale'] = $this->configuration['user_locale']; //////////////

        // Set environment. ////////////////////////////////////////////////////////////////
        putenv('LANG=' . $this->configuration['locale']); //////////////////////////////////

        // Set locale. /////////////////////////////////////////////////////////////////////
        setlocale(LC_ALL, $this->configuration['locale']); /////////////////////////////////

        // Server date and timezones. //////////////////////////////////////////////////////
        date_default_timezone_set($this->configuration['system_timezone']); ////////////////

        // Assign clean locale directory. //////////////////////////////////////////////////
        $this->configuration['locale_dir'] = $this->PHPDS_core()->formatLocale(false); /////

        ////////////////////////////////////////////////////////////////////////////////////
        return $this; // to allow fluent interface
    }

    /**
     * Loads extra broad based system functions.
     */
    public function loadFunctions()
    {
        if (!empty($this->configuration['function_files'])) {
            foreach ($this->configuration['function_files'] as $function_file) {
                $function_file = $this->basepath('includes') . $function_file;
                if (file_exists($function_file)) {
                    include_once ($function_file);
                }
            }
        }
    }

    /**
     * Main configuration method: load settings, create nodes, templates, and so on
     * After that, everything is ready to run.
     * all config_*() methods are meant to be called only at startup time by config()
     */
    protected function config()
    {
        // Loads available plugins from database. /////////////////////////////
        $this->classes = $this->PHPDS_classFactory();

        ///////////////////////////////////////////////////////////////////////
        // load various configuration files. //////////////////////////////////
        $this->loadConfig(); //////////////////////////////////////////////////

        // Does the user want utils to load. //////////////////////////////////
        $this->loadFunctions(); ///////////////////////////////////////////////

        // Init error engine. /////////////////////////////////////////////////
        $this->PHPDS_errorHandler(); //////////////////////////////////////////

        // Init main debug instance. //////////////////////////////////////////
        $this->PHPDS_debug(); /////////////////////////////////////////////////

        // Various init subroutines. //////////////////////////////////////////
        $this->configSession()->configDb(); ///////////////////////////////////

        // Loads important settings from db enabling system to run correctly. /
        $this->db->getEssentialSettings(); ////////////////////////////////////

        $this->classes->loadRegistry();

        // Loads settings from configuration file. ////////////////////////////
        $this->configCoreSettings(); //////////////////////////////////////////

        // Checks which plugins is installed.  ////////////////////////////////
        $this->db->installedPlugins(); ////////////////////////////////////////

        // Loads node language translation engine. ////////////////////////////
        $this->PHPDS_core()->loadNodeLanguage(); //////////////////////////////

        // This is both for templates and security: it builds the list of /////
        // resources the current user is allowed to ///////////////////////////
        // Parse the request string and set the node item. ////////////////////
        $this->PHPDS_navigation()->extractNode()->parseRequestString(); ///////

        // Template Folder ////////////////////////////////////////////////////
        $this->configuration['template_folder'] = $this->PHPDS_core()->activeTemplate();

        // Set core language files. ///////////////////////////////////////////
        $this->PHPDS_core()->loadCoreLanguage(); //////////////////////////////

        // Set user session discription settings //////////////////////////////
        $this->configuration['user_display_name'] = !isset($_SESSION['user_display_name']) ? '' : $_SESSION['user_display_name'];
        $this->configuration['user_role_name']    = !isset($_SESSION['user_role_name']) ? '' : $_SESSION['user_role_name'];

        // Set default plugin language files //////////////////////////////////
        $this->PHPDS_core()->loadDefaultPluginLanguage(); /////////////////////
        ///////////////////////////////////////////////////////////////////////
    }

    /**
     * Actual starting point of the (non-embedded) PHPDS engine
     */
    public function run()
    {
        $this->stage = 2;
        try {
            // Run template as required.
            $this->PHPDS_core()->startController();
            // Write collected logs to database.
            $this->PHPDS_db()->logThis();
        } catch (Exception $e) {
            $this->PHPDS_errorHandler()->doHandleException($e);
        }
    }

    /**
     * shortcut to PHPDS_classFactory::factorClass()
     */
    public function _factory($classname, $params = null, $dependancy = null)
    {
        if (empty($dependancy)) {
            $dependancy = $this;
        }
        return $this->classes->factorClass($classname, $params, $dependancy);
    }

    /*
     * Most fields contains objects which are "lazy initiated" (that is created
     * the first time they are asked for).
     */

    /**
     * Allow access to configuration, either read (no param) or write
     * This makes possible to start with a forced configuration, for testing for example
     * It returns the configuration array
     * CAUTION: an array is not an object so be careful to use & if you need to modify it
     *
     * @param array $configuration possibly a new configuration array
     * @return array the configuration array
     */
    public function PHPDS_configuration($configuration = null)
    {
        if (empty($this->configuration) && is_array($configuration)) {
            $this->configuration         = new PHPDS_array($configuration);
            $this->configuration['time'] = time();
        }
        return $this->configuration;
    }

    /**
     * Custom Error Handler.
     * One is created if necessary.
     * You can override to use you own core subsystem
     *
     * @return PHPDS_errorHandler
     */
    public function PHPDS_errorHandler()
    {
        if (empty($this->errorHandler)) {
            $this->errorHandler = $this->_factory('PHPDS_errorHandler');
        }
        return $this->errorHandler;
    }

    /**
     * Allow access to the global core subsystem
     * One is created if necessary.
     * You can override to use you own core subsystem
     *
     * @return PHPDS_core
     */
    public function PHPDS_core()
    {
        if (empty($this->core)) {
            $this->core = $this->_factory('PHPDS_core');
        }
        return $this->core;
    }

    /**
     * Provides a variety of user functions.
     * One is created if necessary.
     * You can override to use you own core subsystem
     *
     * @return PHPDS_user
     */
    public function PHPDS_user()
    {
        if (empty($this->user)) {
            $this->user = $this->_factory('PHPDS_user');
        }
        return $this->user;
    }

    /**
     * Allow access to the global debugging subsystem
     * One is created if necessary.
     * You can override to use you own core subsystem
     *
     * @return PHPDS_debug instance
     */
    public function PHPDS_debug()
    {
        if (empty($this->debug)) {
            $domain      = ($this->embedded ? 'authlib' : 'skel');
            $this->debug = $this->_factory('PHPDS_debug', $domain);
        }
        return $this->debug;
    }

    /**
     * Allow access to the global navigation subsystem
     * One is created if necessary.
     * You can override to use you own navigation subsystem
     *
     * @return PHPDS_navigation
     */
    public function PHPDS_navigation()
    {
        if (empty($this->navigation)) {
            $this->navigation = $this->_factory('PHPDS_navigation');
        }
        return $this->navigation;
    }

    /**
     * Send info data to the debug subsystem (console, firebug, ...)
     * The goal of this function is to be called all throughout the code to be able to track bugs.
     *
     * @param string $data
     */
    public function log($data)
    {
        if (!empty($this->debug)) $this->PHPDS_debug()->debug($data);
        else {
            if (!empty($GLOBALS['early_debug'])) error_log('EARLY INFO: ' . $data);
        }
    }

    /**
     * Allow access to the (formerly) global database subsystem
     * One is created if necessary.
     * You can override to use you own database subsystem
     *
     * @return PHPDS_db
     */
    public function PHPDS_db()
    {
        if (empty($this->db)) {
            $this->db = $this->_factory('PHPDS_db');
        }
        return $this->db;
    }

    /**
     * Allow access to the (formerly) global security subsystem
     * One is created if necessary.
     * You can override to use you own security subsystem
     *
     * @return PHPDS_security
     */
    public function PHPDS_security()
    {
        if (empty($this->security)) {
            $this->security = $this->_factory('PHPDS_security');
        }
        return $this->security;
    }

    /**
     * Allow access to the class factory
     * One is created if necessary.
     * You can override to use you own class factory
     *
     * @return PHPDS_classFactory
     */
    public function PHPDS_classFactory()
    {
        if (empty($this->classes)) {
            $this->classes = new PHPDS_classFactory($this);
        }
        return $this->classes;
    }

    /**
     * Allow access to the global templating subsystem
     * One is created if necessary
     * You can override to use you own templating subsystem
     *
     * @param boolean $lazy if true (default) the template is created if wasn't before
     *
     * @return PHPDS_template
     */
    public function PHPDS_template($lazy = true)
    {
        if (empty($this->template) && $lazy) {
            $this->template = $this->_factory('PHPDS_template');
            //if ($this->compatMode < 2) $GLOBALS['template'] = & $this->template;
        }
        return $this->template;
    }

    /**
     * Allow access to the tagging subsystem
     * One is created if necessary.
     * You can override to use you own tagging subsystem
     *
     * @return PHPDS_tagger
     */
    public function PHPDS_tagger()
    {
        if (empty($this->tagger)) {
            $this->tagger = $this->_factory(('PHPDS_tagger'));
        }
        return $this->tagger;
    }

    /**
     * Allow access to the asynchronous notifications subsystem
     * One is created if necessary.
     * You can override to use you own tagging subsystem
     *
     * @return PHPDS_notif
     */
    public function PHPDS_notif()
    {
        if (empty($this->notif)) {
            $this->notif = $this->_factory(('PHPDS_notif'));
        }
        return $this->notif;
    }

    /**
     * Allow access to the global templating subsystem
     * One is created if necessary.
     * You can override to use you own templating subsystem
     *
     * @return self
     */
    public function PHPDS_lang()
    {
        if (empty($this->lang)) {
            $this->lang = new PHPDS_array();
        }
        return $this->lang;
    }

    /**
     * This is a generic accessor to allow field access through an homogeneous and controlled way.
     * For example:
     * $instance->get('core')
     * will return the core object as returned by the core() accessor method
     *
     * @param $field
     * @throws Exception
     * @return mixed depends on what is asked for
     */
    public function get($field)
    {
        $method_name = 'PHPDS_' . $field;
        if (method_exists($this, $method_name)) {
            return $this->$method_name();
        }
        throw new Exception('Class "' . get_class($this) . '" has no field "' . $field . '", sorry!');
    }

    /**
     * Check if instance is embedded.
     *
     * @return mixed
     */
    public function isEmbedded()
    {
        return !empty($this->embedded);
    }

    /**
     * Simply returns basepath
     * An optional postfix (i.e. folder name) can be given to retrieve the path a subfolder
     *
     * @param string $postfix
     * @return string
     */
    public function basepath($postfix = '')
    {
        $path = realpath($this->basepath . $postfix);
        if ($path) return $path . DIRECTORY_SEPARATOR; else return false;
    }

    /**
     * Try to load a class from a file
     * NOTE: for performance reason we DON'T try first to see if the class already exists
     *
     * @param string $classname name of the class to look for
     * @param string $filename  name of the file to look into
     * @return boolean whether we found the class or not
     */
    public function sneakClass($classname, $filename)
    {
        if (is_file($filename)) {
            include_once ($filename);
            if (class_exists($classname, false)) {
                $this->log("Autoloading $classname from $filename");
                return true;
            }
        }
        return false;
    }

    /**
     * Autoloader: when a class is instantiated, this method will load the proper php file
     * Note: the various folders where the files are looked for depends on the
     * instance configuration, and on the current plugin
     * A model file is also loaded if present
     *
     * @param $class_name
     * @return boolean it's a callback for the new() operator (however we return true on success, false otherwise)
     */
    function PHPDS_autoloader($class_name)
    {
        $configuration = $this->PHPDS_configuration();
        $absolute_path = $this->basepath();

        // Check if we have plugin classes to load.
        $classParams = $this->classes->classParams($class_name);
        if ($classParams) {
            $filename    = empty($classParams['file_name']) ? $classParams['class_name'] : $classParams['file_name'];
            $classFolder = $classParams['plugin_folder'];

            $engine_include_path = $absolute_path . 'plugins/' . $classFolder . '/includes/' . $filename . '.class.php';
            $query_include_path  = $absolute_path . 'plugins/' . $classFolder . '/models/' . $filename . '.query.php';
            if ($this->sneakClass($class_name, $engine_include_path)) {
                $this->sneakClass($class_name, $query_include_path);
                return true;
            }
        }

        // Engine classes default directories
        $includes = array('includes/local', 'includes');
        foreach ($includes as $path) {
            $engine_include_path = $absolute_path . $path . '/' . $class_name . '.class.php';
            if ($this->sneakClass($class_name, $engine_include_path)) {
                $query_include_path = $absolute_path . $path . '/models/' . $class_name . '.query.php';
                $this->sneakClass($class_name, $query_include_path);
                return true;
            }
            $engine_include_path = $absolute_path . $path . 'default.class.php';
            if ($this->sneakClass($class_name, $engine_include_path)) return true;
        }

        // Try the plugin files - if a plugin is currently running
        if (!empty($configuration['m'])) {
            $navigation = $this->PHPDS_navigation();
            if (!empty($navigation->navigation[$configuration['m']]['plugin'])) {

                // Check if file exists in active plugins folder.
                $plugin_include_class = $absolute_path . 'plugins/' . $navigation->navigation[$configuration['m']]['plugin'] . '/includes/' . $class_name . '.class.php';
                if ($this->sneakClass($class_name, $plugin_include_class)) {
                    $plugin_include_query = $absolute_path . 'plugins/' . $navigation->navigation[$configuration['m']]['plugin'] . '/models/' . $class_name . '.query.php';
                    $this->sneakClass($class_name, $plugin_include_query);
                    return true;
                }

                $plugin_include_class = $absolute_path . 'plugins/' . $navigation->navigation[$configuration['m']]['plugin'] . '/includes/default.class.php';
                if ($this->sneakClass($class_name, $plugin_include_class)) return true;
            }

            // We should have found it by now, lets seek the plugin folders include directories, not optimal, but better then an error I guess.
            $files = glob($this->basepath('plugins') . '/*/includes/' . $class_name . '.class.php');
            if (!empty($files)) {
                foreach ($files as $filename) {
                    if ($this->sneakClass($class_name, $filename)) {
                        // Oh awesome, it worked, now the query file.
                        $filenamequery = preg_replace("/.class.php/", '.query.php', $filename);
                        $this->sneakClass($class_name, $filenamequery);
                        return true;
                    }
                }
            }

        }

        // Check PHPDS's own include folder
        $phpdev_include_class = $absolute_path . 'plugins/AdminTools/includes/' . $class_name . '.class.php';
        if ($this->sneakClass($class_name, $phpdev_include_class)) return true;

        // Oh this is becoming a problem, perhaps we can locate the file here.
        if (!empty($configuration['plugin_alt'])) {
            // Ok last chance, is it in here?
            $plugin_include_class = $absolute_path . $configuration['plugin_alt'] . 'includes/' . $class_name . '.class.php';
            if ($this->sneakClass($class_name, $plugin_include_class)) return true;
        }

        return false;
    }
}

/**
 * This is a base class for PHPDS subsystems
 * It allows dependency injection and dependency fetching; also mimics multiple inheritance;
 *
 * @property PHPDS_core       $core
 * @property PHPDS_navigation $navigation
 * @property PHPDS_db         $db
 * @property PHPDS_security   $security
 * @property PHPDS_template   $template
 * @property PHPDS_tagger     $tagger
 * @property PHPDS_user       $user
 * @property PHPDS_notif      $notif
 *
 */
class PHPDS_dependant
{
    /**
     * The object this object depend on. Ultimately up the chain it should be the main PHPSD instance
     * @var PHPDS_dependant or PHPDS
     */
    protected $dependance;
    /**
     * The private debug instance of this object (with domain selection)
     * @var object
     */
    private $debugInstance;
    /**
     * Holds a dependent extended object to appear as parent.
     * @var object or name of the field containing the object
     */
    protected $parent;
    /**
     * Allows you to make fields read only, will not be able to write.
     * @var boolean true will make fields read only.
     */
    protected $privateFields = false;

    /**
     * magic constructor
     * parameter list is not explicit: we're expecting the LAST argument to be the dependence, other are fed to construct()
     * @throws PHPDS_exception
     */
    public function __construct()
    {
        $args = func_get_args();
        $dep  = array_pop($args);
        $this->PHPDS_dependance($dep);

        $success = call_user_func_array(array($this, 'construct'), $args);

        if ($success === false)
            throw new PHPDS_exception('Error constructing an object.');
    }

    /**
     * Empty function called by the actual constructor; meant to be overriden
     * Supposed to return false (exactly) in case of error, otherwise return the object itself
     *
     * @return boolean or object
     */
    public function construct() // variable argument list
    {
        return true;
    }

    /**
     * Inject the given dependency into $this, and/or returns the owner
     * The default behavior is to try to get the top owner, ie the main PHPDS instance.
     * However the given parmeter is supposed to be the object from where the new object is created.
     * That means you can override this to "catch" the real owner at this time.
     *
     * @param object $dependance, the "owner", can be either PHPDS or PHPDS_dependant
     * @return self owner of $this
     * @throws PHPDS_exception
     */
    public function PHPDS_dependance($dependance = null)
    {
        if (!empty($dependance) && empty($this->dependance)) {
            $this->dependance = $dependance;
        }
        if (!is_object($this->dependance)) {
            throw new PHPDS_exception("Dependancy error: dependance is not an object.");
        }
        return method_exists($this->dependance, 'PHPDS_dependance') ? $this->dependance->PHPDS_dependance() : $this->dependance;
    }

    /**
     * Magic php function, called when trying to access a non-defined field
     * When a method want a field which doesn't exist, we assume it's a data from the father
     *
     * @date 20121119 (v2.1.1) (greg) last change added a biiig bug, reorganised the IFs to avoid it
     *
     * @param $name string name of the field to fetch
     * @return mixed
     * @throws PHPDS_exception
     */
    public function __get($name)
    {
        $result = '';

        try {
            // for a local field, try to find an accessor
            if (method_exists($this, $name)) {
                return call_user_func(array($this, $name));
            } // if not found, but the property exists, give a read-only access
            elseif (property_exists($this, $name)) {
                return $this->{$name};
            } // try to find a field in the parent
            elseif (!empty($this->parent) && (isset($this->parent->{$name}) || property_exists($this->parent, $name))) {
                return $this->parent->{$name};
            } // special case, we want the debug instance
            elseif ('debug' == $name) {
                return $this->debugInstance();
            } else {
                $top = $this->PHPDS_dependance();
                if (method_exists($top, 'get')) {
                    $result = $top->get($name);
                }
                // and then cache it
                if ($result) {
                    $this->{$name} = $result;
                    return $result;
                }
            }
            throw new PHPDS_exception("Non-existent field '$name' (maybe dependancy is wrong).'");
        } catch (Exception $e) {
            throw new PHPDS_exception("Can't get any '$name' (maybe dependancy is wrong).", 0, $e);
        }
    }

    /**
     * Sets property names when none existent and makes them available in core.
     *
     * @date 20121119 (v1.1) (greg) added local set, so we actually create the field
     * @param string $name
     * @param mixed $value
     * @return mixed
     * @throws PHPDS_exception
     */
    public function __set($name, $value)
    {
        try {
            if (!empty($this->parent)) {
                if (isset($this->parent->{$name}) || property_exists($this->parent, $name)) {
                    $this->parent->{$name} = $value;
                    return true;
                }
            }

            // if the parent doesn't provide the field, then it's ours
            $this->{$name} = $value;

            # Below breaks code.
            #throw new PHPDS_exception('Trying to set non-existent (or maybe masked) field "'.$name.'"');
        } catch (Exception $e) {
            throw new PHPDS_exception("Can't set any '$name' (maybe dependancy is wrong)", 0, $e);
        }
    }

    /**
     * Magic php function used when a non-defined method is called. Here we mimics multi-inheritance by calling methods from "roots"
     *
     * @param string $name
     * @param mixed  $arguments
     * @return mixed
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        $root = $this->parent;
        if (gettype($root) == 'string') {
            $root = $this->{$root};
        }
        if ((gettype($root) == 'object') && method_exists($root, $name)) {
            return call_user_func_array(array($root, $name), $arguments);
        }
        throw new Exception("No root have a \"$name()\" method, maybe you're trying to call a protected/private method.");
    }

    /**
     * Create instance of PHPDS_debug.
     *
     * @param object
     * @return PHPDS_debug
     */
    public function debugInstance($domain = null)
    {
        if (empty($this->debugInstance)) {
            if (empty($domain))
                $domain = preg_replace('/^PHPDS_/', '', get_class($this));
            $this->debugInstance = $this->factory('PHPDS_debug', $domain);
        }
        return $this->debugInstance;
    }

    /**
     * Send info data to the debug subsystem (console, firebug, ...)
     * The goal of this function is to be called all throughout the code to be able to track bugs.
     *
     * @param string $data
     */
    public function log($data)
    {
        if (is_a($this, 'debug')) $this->debug($data);
        else $this->debugInstance()->debug($data);
    }

    /**
     * DEPRECATED: alias for log()
     *
     * @deprecated
     * @param $data
     */
    public function _log($data)
    {
        $this->log($data);
    }

    /**
     * DEPRECATED: alias for _log()
     *
     * @deprecated
     * @param $data
     */
    public function info($data)
    {
        $this->log($data);
    }

    /**
     * Create a new instance of the given class and link it as dependant (variable number of argument)
     *
     * @date 20111219 (v1.2.1) (greg) rewrote signature description for phpDoc
     *
     * @param string|array $classname name of the class to instantiate, or factory array parameter (see PHPDS->_factory() )
     *
     * @see PHPDS_debug
     * @see PHPDS::_factory()
     *
     * @return object instance of $classname
     */
    public function factory($classname) // actually more parameters can be given
    {
        $params = func_get_args();
        array_shift($params);

        return $this->factoryWith($classname, $params);
    }

    /**
     * Create a new instance of the given class and link it as dependant (variable number of argument)
     *
     *
     * @param string|array $classname name of the class to instantiate, or factory array parameter (see PHPDS->_factory() )
     * @param array        $params    optional class specific parameters
     *
     * @return object of $classname
     */
    public function factoryWith($classname, array $params)
    {
        $father = $this->PHPDS_dependance();

        return $father->_factory($classname, $params, $this);
    }

    /**
     * Gives an instance of the class according to the singleton pattern
     * i.e., the first time the class is asked for, an object is instanciated
     * and every following class will return the same object
     *
     * @param string $classname name of the class to instantiate
     *
     * @return object of $classname
     */
    public function singleton($classname)
    {
        $params = func_get_args();
        array_shift($params);

        return $this->singletonWith($classname, $params);
    }

    /**
     * Gives an instance of the class according to the singleton pattern
     * i.e., the first time the class is asked for, an object is instanciated
     * and every following class will return the same object
     *
     * @param string $classname name of the class to instantiate
     * @param array  $params   optional class specific parameters
     *
     *
     * @return object of $classname
     */
    public function singletonWith($classname, array $params)
    {
        return $this->factory(array('classname' => $classname, 'factor' => 'singleton'));
    }
}

/**
 * Turn core arrays into objects.
 */
class PHPDS_array extends ArrayObject
{

    /**
     * Magic set array.
     *
     * @param string $name
     * @param string $val
     */
    public function __set($name, $val)
    {
        $this[$name] = $val;
    }

    /**
     * Magic get array.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return (isset($this[$name]) ? $this[$name] : null);
    }
}

/**
 * A class to handle the class aliasing mechanism and factory'ing
 */
class PHPDS_classFactory extends PHPDS_dependant
{
    /**
     * Contains array of installed supportive plugin classes.
     *
     * @var array
     */
    protected $PluginClasses = array();
    /**
     * a cache array for singletons
     *
     * @var array
     */
    protected $objectCache;

    /**
     * Add a class to the registry
     * Note that classes added "on the fly" superseed the registry stored in the database
     *
     * @version 1.1
     * @since   3.1.2
     * @author  greg <greg@phpdevshell.org>
     *
     * @date 20120113 (v1.0) (greg) added
     * @date 20120606 (v1.1) (greg) added support for fileName parameter
     *
     * @param string $className    the name of the PHP class to register
     * @param string $classAlias   an altername name for this class
     * @param string $pluginFolder the name/folder of the plugin this class belongs to
     * @param string $fileName     (optional) a file where to load the class from, instead of the default name based on the class name
     */
    public function registerClass($className, $classAlias, $pluginFolder, $fileName = null)
    {
        $this->PluginClasses[$className] = array(
            'class_name'    => $className,
            'alias'         => $classAlias,
            'plugin_folder' => $pluginFolder,
            'file_name'     => $fileName
        );
        if (!empty($classAlias)) {
            $this->PluginClasses[$classAlias] = array(
                'class_name'    => $className,
                'plugin_folder' => $pluginFolder,
                'file_name'     => $fileName
            );
        }
    }


    /**
     * Loads the registry stored in the database into memory
     * Deals with cache
     * Note that classes added "on the fly" superseed the registry stored in the database
     *
     * @version 1.2
     * @since   3.1.2
     * @author  greg <greg@phpdevshell.org>
     *
     * @date 20120113 (v1.0) (greg) added
     * @date 20120606 (v1.1) (greg) changed to use $this->registerClass() ; update cache if needed
     * @date 20120606 (v1.2) (greg) added support for fileName parameter when a class name is composite (classname@filename)
     *
     */
    public function loadRegistry()
    {
        $db = $this->db;

        if ($db->cacheEmpty('PluginClasses')) {
            $pluginR = $db->invokeQuery('DB_readPluginClassRegistryQuery');
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
                $db->cacheWrite('PluginClasses', $this->PluginClasses);
            }
        } else {
            if (!empty($this->PluginClasses)) {
                $PluginClasses       = $db->cacheRead('PluginClasses');
                $this->PluginClasses = array_merge($PluginClasses, $this->PluginClasses);
                $db->cacheWrite('PluginClasses', $this->PluginClasses);
            } else {
                $this->PluginClasses = $db->cacheRead('PluginClasses');
            }
        }

    }


    /**
     * Create a new instance of the given class and link it as dependant (arguments as an array)
     *
     * As a special case, if the classname starts with an ampersand ('&'), the class is considered as singleton,
     * and therefore a cached version will be returned after the first instanciation
     *
     * @param PHPDS|PHPDS_dependant $dependancy
     * @param string|array          $classname name of the class to instanciate, or parameter array
     * @param array                 $params    class specific parameters to feed the object's constructor
     *
     * May throw a PHPDS_exception
     *
     * @date 20120312 (v1.1.3) (greg) fixed a bug where non-array params would always be treated as void
     * @date 20120112 (v1.1.2) (greg) moved to PHPDS_classFactory ; predep parameter changed from PHPDS main instance to $dependancy
     * @date 20110915 (v1.1.1) (greg) moved class aliasing support from PHPDS_dependant
     *
     * @author greg <greg@phpdevshell.org>
     *
     * @return object of $classname
     * @throws PHPDS_exception
     */
    public function factorClass($classname, $params = null, $dependancy = null)
    {
        $my_parameters = is_array($classname) ? $classname : array('classname' => $classname, 'factor' => 'default');
        switch ($my_parameters['classname'][0]) {
            case '&':
                $my_parameters['factor']    = 'singleton';
                $my_parameters['classname'] = substr($my_parameters['classname'], 1);
                break;
        }

        $classname = $my_parameters['classname'];

        if (!is_array($params)) {
            $params = is_null($params) ? array() : array($params);
        }

        try {
            // support for plugin aliasing
            if (!empty($this->PluginClasses[$classname]['class_name'])) {
                $classname = $this->PluginClasses[$classname]['class_name'];
            }

            if (('singleton' == $my_parameters['factor']) && isset($this->objectCache[$classname])) {
                return $this->objectCache[$classname];
            }

            if (class_exists($classname, true)) {
                array_push($params, $dependancy);
                $string = '';
                $count  = count($params);
                for ($loop = 0; $loop < $count; $loop++) {
                    if ($string) $string .= ', ';
                    $string .= '$params[' . $loop . ']';
                }
                $o = null;
                $c = "\$o = new $classname($string);";
                eval($c);
                if ($o instanceof $classname) {
                    if ($o instanceof PHPDS_dependant) {
                        $o->PHPDS_dependance($dependancy);
                    }
                    if ('singleton' == $my_parameters['factor']) {
                        $this->objectCache[$classname] = $o;
                    }
                    return $o;
                }
                throw new PHPDS_exception("Unable to factor a new \"$classname\".");
            } else {
                throw new PHPDS_exception("Can't find definition for class \"$classname\".");
            }
        } catch (Exception $e) {
            throw new PHPDS_exception("Error while factoring a new \"$classname\".", 0, $e);
        }
    }

    /**
     * Returns the known data for the given class, if present in the registry
     * Note: it's just the folder name, not the full path
     *
     * @param string $class_name
     * @return array|boolean
     */
    public function classParams($class_name)
    {
        if (empty($this->PluginClasses[$class_name])) {
            return false;
        }
        return $this->PluginClasses[$class_name];
    }

    /**
     * Returns the name of the folder of plugin owning the given class, if present in the registry
     * Note: it's just the relative path (for urls), not the full path
     *
     * @param string $class_name
     * @return array|boolean
     */
    public function classFolder($class_name)
    {
        $data = $this->classParams($class_name);
        return empty($data['plugin_folder']) ? false : 'plugins/' . $data['plugin_folder'];
    }
}
