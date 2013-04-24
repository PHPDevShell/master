<?php
define('phpdevshell_version', 'PHPDevShell V-4.0.0-Beta-1-DB-4000');
define('phpdevshell_db_version', '4000');

require_once 'PHPDS_utils.inc.php';
require_once 'db/PHPDS_dbInterface.class.php';
require_once 'cache/PHPDS_cacheInterface.class.php';
require_once 'session/PHPDS_sessionInterface.class.php';

/**
 * Root class of PHPDevShell, binds all methods/plugins together.
 */
class PHPDS
{
    /**
     * Core object.
     *
     * @var object
     */
    protected $auth;
    /**
     * The textual path of PHPDS directory on disk (not the URL).
     *
     * @var string
     */
    protected $basepath;
    /**
     * Core cache object.
     *
     * @var object
     */
    protected $cache;
    /**
     * Main class factory and registry.
     *
     * @var PHPDS_classFactory
     */
    protected $classes;
    /**
     * Core system configuration settings.
     *
     * @var object
     */
    protected $configuration;
    /**
     * Core config object.
     *
     * @var object
     */
    protected $config;
    /**
     * Core object.
     *
     * @var object
     */
    protected $core;
    /**
     * The higher the less backward-compatible.
     *
     * @var int
     */
    protected $compatMode = 1;
    /**
     * Core database object.
     *
     * @var object
     */
    protected $db;
    /**
     * Main instance of the debug module, which handles startup init.
     *
     * @var object
     */
    protected $debug;
    /**
     * Core navigation object.
     *
     * @var object
     */
    protected $navigation;
    /**
     * Main instance of the notification module
     *
     * @var PHPDS_notif
     */
    protected $notif;
    /**
     * Core router object.
     *
     * @var object
     */
    protected $router;
    /**
     * Execution stage (i.e. run level)
     *
     * @var int
     */
    protected $stage = 1; // 1 => initialization; 2 => running
    /**
     * Core session object.
     *
     * @var object
     */
    protected $session;
    /**
     * Core template object.
     *
     * @var object
     */
    protected $template;
    /**
     * Main instance of the tagger module
     */
    protected $tagger;
    /**
     * Core user object.
     *
     * @var object
     */
    protected $user;


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
            $this->factorConfig();
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
        include_once $path;
        $this->log("Loading config file $path", 'Loading');
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

        $this->log("Looking for general config file \"$filename\"", 'Loading');

        return $this->includeConfigFile($this->basepath('config') . $filename . '.config.php', $configuration);
    }

    /**
     * Load plugin-specific configuration files.
     * This allows plugins to provide a configuration, for example when 1 plugin <=> 1 site
     *
     */
    protected function loadPluginsConfig(&$configuration)
    {
        $files = glob($this->basepath('plugins') . '*/config/plugin.config.php');
        if (! empty($files)) {
            foreach($files as $filename) {
                $this->log("Looking for plugin config file \"$filename\"", 'Loading');
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
            print 'All these files were tried:<br>';
            print implode('<br>', $configuration['config_files_missing']);
            exit();
        }
        $this->PHPDS_configuration($configuration);
        spl_autoload_register(array($this, "PHPDS_autoloader"));

        if (empty($this->configuration['absolute_url'])) {
            $protocol                            = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
            $this->configuration['absolute_url'] =
                $protocol . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        }

        return $this; // to allow fluent interface
    }

    /**
     * Deal with database access configuration. Also makes the first master connection to the database.
     *
     * @return $this the current instance
     */
    protected function configDb()
    {
        $db = $this->PHPDS_db();
        $db->connect();

        return $this; // to allow fluent interface
    }

    /**
     * Copy settings from the database-loaded array.
     */
    protected function copySettingsFromDb()
    {
        PU_copyArray($this->PHPDS_config()->getEssentialSettings(), $this->configuration);
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

        // Assign all core db settings to configuration. ///////////////////////////////////
        $this->copySettingsFromDb(); ///////////////////////////////////////////////////////

        // Prepare auth. ///////////////////////////////////////////////////////////////////
        $this->PHPDS_auth()->start(); //////////////////////////////////////////////////////

        // Assign locale to use. ///////////////////////////////////////////////////////////
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
    protected function factorConfig()
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

        // Start database connection. /////////////////////////////////////////
        $this->configDb(); ////////////////////////////////////////////////////

        // Connects cache server. /////////////////////////////////////////////
        $this->PHPDS_cache()->start(); ////////////////////////////////////////

        // Connects session server. ///////////////////////////////////////////
        $this->PHPDS_session()->start(); //////////////////////////////////////

        // Will load all available classes from registry. /////////////////////
        $this->classes->loadRegistry(); ///////////////////////////////////////

        // Loads settings from configuration file. ////////////////////////////
        $this->configCoreSettings(); //////////////////////////////////////////

        // Checks which plugins is installed.  ////////////////////////////////
        $this->config->installedPlugins(); ////////////////////////////////////

        // Loads node language translation engine. ////////////////////////////
        $this->PHPDS_core()->loadNodeLanguage(); //////////////////////////////

        // This is both for templates and security: it builds the list of /////
        // resources the current user is allowed to ///////////////////////////
        // Parse the request string and set the node item. ////////////////////
        $this->PHPDS_navigation()->extractNode()->parseRequestString(); ///////

        // Template Folder ////////////////////////////////////////////////////
        $this->configuration['theme_folder'] = $this->PHPDS_core()->activeTemplate();

        // Set core language files. ///////////////////////////////////////////
        $this->PHPDS_core()->loadCoreLanguage(); //////////////////////////////

        // Set user session discription settings //////////////////////////////
        $this->configuration['user_display_name'] =
            !isset($_SESSION['user_display_name']) ? '' : $_SESSION['user_display_name'];
        $this->configuration['user_role_name']    =
            !isset($_SESSION['user_role_name']) ? '' : $_SESSION['user_role_name'];

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
            $this->core->startController();
            // Write collected logs to database.
            $this->PHPDS_user()->logActions();
            $this->db->endTransaction();
        } catch (Exception $e) {
            $this->PHPDS_errorHandler()->doHandleException($e);
        }
    }

    /**
     * @alias PHPDS_classFactory::factorClass
     */
    public function _factory($classname, $params = null, $dependancy = null)
    {
        if (empty($dependancy)) {
            $dependancy = $this;
        }
        return $this->classes->factorClass($classname, $params, $dependancy);
    }

    /**
     * Allow access to the global config subsystem
     * One is created if necessary.
     *
     * @return PHPDS_auth
     */
    public function PHPDS_auth()
    {
        if (empty($this->auth)) {
            $this->auth = $this->_factory($this->configuration['extend']['auth']);
        }
        return $this->auth;
    }

    /**
     * Allow access to the global cache subsystem
     * One is created if necessary.
     *
     * @return PHPDS_cacheInterface
     */
    public function PHPDS_cache()
    {
        if (empty($this->cache)) {
            $driver = $this->configuration['driver']['cache'];
            // Hard code, its faster... don't sneak this one.
            require_once 'includes/cache' . DIRECTORY_SEPARATOR . $driver . '.class.php';
            $this->cache = $this->_factory($driver);
        }
        return $this->cache;
    }

    /**
     * Allow access to the global config subsystem
     * One is created if necessary.
     *
     * @return PHPDS_config
     */
    public function PHPDS_config()
    {
        if (empty($this->config)) {
            $this->config = $this->_factory($this->configuration['extend']['config']);
        }
        return $this->config;
    }

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
     * Allow access to the global core subsystem
     * One is created if necessary.
     *
     * @return PHPDS_core
     */
    public function PHPDS_core()
    {
        if (empty($this->core)) {
            $this->core = $this->_factory($this->configuration['extend']['core']);
        }
        return $this->core;
    }

    /**
     * Allow access to the global database subsystem
     * One is created if necessary.
     *
     * @return PHPDS_cacheInterface
     */
    public function PHPDS_db()
    {
        if (empty($this->db)) {
            $driver = $this->configuration['driver']['db'];
            // Hard code, its faster... don't sneak this one.
            require_once 'includes/db' . DIRECTORY_SEPARATOR . $driver . '.class.php';
            $this->db = $this->_factory($driver);
        }
        return $this->db;
    }

    /**
     * Allow access to the global debugging subsystem
     * One is created if necessary.
     *
     * @return PHPDS_debug
     */
    public function PHPDS_debug()
    {
        if (empty($this->debug)) {
            $this->debug = $this->_factory($this->configuration['extend']['debug']);
        }
        return $this->debug;
    }

    /**
     * Custom Error Handler.
     * One is created if necessary.
     *
     * @return PHPDS_errorHandler
     */
    public function PHPDS_errorHandler()
    {
        if (empty($this->errorHandler)) {
            $this->errorHandler = $this->_factory($this->configuration['extend']['errorHandler']);
        }
        return $this->errorHandler;
    }

    /**
     * Allow access to the global navigation subsystem
     * One is created if necessary.
     *
     * @return PHPDS_navigation
     */
    public function PHPDS_navigation()
    {
        if (empty($this->navigation)) {
            $this->navigation = $this->_factory($this->configuration['extend']['navigation']);
        }
        return $this->navigation;
    }

    /**
     * Allow access to the asynchronous notifications subsystem
     * One is created if necessary.
     *
     * @return PHPDS_notif
     */
    public function PHPDS_notif()
    {
        if (empty($this->notif)) {
            $this->notif = $this->_factory(($this->configuration['extend']['notif']));
        }
        return $this->notif;
    }

    /**
     * Allow access to the global navigation subsystem
     * One is created if necessary.
     *
     * @return PHPDS_router
     */
    public function PHPDS_router()
    {
        if (empty($this->router)) {
            $this->router = $this->_factory($this->configuration['extend']['router']);
        }
        return $this->router;
    }

    /**
     * Allow access to the global cache subsystem
     * One is created if necessary.
     *
     * @return PHPDS_sessionInterface
     */
    public function PHPDS_session()
    {
        if (empty($this->session)) {
            $driver = $this->configuration['driver']['session'];
            // Hard code, its faster... don't sneak this one.
            require_once 'includes/session' . DIRECTORY_SEPARATOR . $driver . '.class.php';
            $this->session = $this->_factory($driver);
        }
        return $this->session;
    }

    /**
     * Allow access to the global templating subsystem
     * One is created if necessary
     *
     * @param boolean $lazy if true (default) the template is created if wasn't before
     *
     * @return PHPDS_template
     */
    public function PHPDS_template($lazy = true)
    {
        if (empty($this->template) && $lazy) {
            $this->template = $this->_factory($this->configuration['extend']['template']);
        }
        return $this->template;
    }

    /**
     * Allow access to the tagging subsystem
     * One is created if necessary.
     *
     * @return PHPDS_tagger
     */
    public function PHPDS_tagger()
    {
        if (empty($this->tagger)) {
            $this->tagger = $this->_factory(($this->configuration['extend']['tagger']));
        }
        return $this->tagger;
    }

    /**
     * Allow access to the global user subsystem
     * One is created if necessary.
     *
     * @return PHPDS_user
     */
    public function PHPDS_user()
    {
        if (empty($this->user)) {
            $this->user = $this->_factory($this->configuration['extend']['user']);
        }
        return $this->user;
    }

    /**
     * Allow access to the class factory
     * One is created if necessary.
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
     * Send info data to the debug subsystem (console, firebug, ...)
     * The goal of this function is to be called all throughout the code to be able to track bugs.
     *
     * @param string $data
     * @param string $label
     */
    public function log($data, $label=null)
    {
        if (!empty($this->debug)) {
            $this->debug->debug($data, $label);
        } else {
            if (!empty($GLOBALS['early_debug'])) error_log('EARLY INFO: ' . $data);
        }
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
        if (class_exists($classname, false)) return true;
        if (is_file($filename)) {
            include_once ($filename);
            if (class_exists($classname, false)) {
                $this->log("$classname from $filename", 'Loading');
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
            if ($this->sneakClass($class_name, $engine_include_path)) return true;
        }

        // Engine classes default directories
        $includes = $configuration['class_folders'];
        foreach ($includes as $path) {
            $engine_include_path = $absolute_path . $path . '/' . $class_name . '.class.php';
            if ($this->sneakClass($class_name, $engine_include_path)) return true;
        }

        // Try the plugin files - if a plugin is currently running
        if (!empty($configuration['m'])) {
            $navigation = $this->PHPDS_navigation();
            if (!empty($navigation->navigation[$configuration['m']]['plugin'])) {

                // Check if file exists in active plugins folder.
                $plugin_include_class = $absolute_path . 'plugins/' .
                    $navigation->navigation[$configuration['m']]['plugin'] . '/includes/' . $class_name . '.class.php';
                if ($this->sneakClass($class_name, $plugin_include_class)) return true;

                $plugin_include_class = $absolute_path . 'plugins/' . $navigation->navigation[$configuration['m']]['plugin'] . '/includes/default.class.php';
                if ($this->sneakClass($class_name, $plugin_include_class)) return true;
            }
        }
        return false;
    }
}

/**
 * This is a base class for PHPDS subsystems
 * It allows dependency injection and dependency fetching; also mimics multiple inheritance;
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
     * Holds a dependent extended object to appear as parent.
     * @var object or name of the field containing the object
     */
    protected $parent;

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
     * Empty function called by the actual constructor; meant to be overridden.
     * Supposed to return false (exactly) in case of error, otherwise return the object itself.
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
        } catch (Exception $e) {
            throw new PHPDS_exception("Can't set any '$name' (maybe dependancy is wrong)", 0, $e);
        }
        return false;
    }

    /**
     * Magic PHP function to determine if a field is set. Used to deal with parent's fields
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        // if not found, but the property exists, give a read-only access
        if (property_exists($this, $name)) {
            return true;
        }
        // try to find a field in the parent
        elseif (!empty($this->parent) && (isset($this->parent->{$name}) || property_exists($this->parent, $name))) {
            return true;
        }
        return false;
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
 * A class to handle the class aliasing mechanism and factory'ing
 */
class PHPDS_classFactory extends PHPDS_dependant
{
    /**
     * Contains array of installed supportive plugin classes.
     *
     * @var array
     */
    protected $pluginClasses = array();
    /**
     * a cache array for singletons
     *
     * @var array
     */
    protected $objectCache;

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
        $this->pluginClasses = $this->config->classRegistry();
    }

    /**
     * Create a new instance of the given class and link it as dependant (arguments as an array)
     *
     * As a special case, if the classname starts with an ampersand ('&'), the class is considered as singleton,
     * and therefore a cached version will be returned after the first instantiation
     *
     * @param PHPDS|PHPDS_dependant $dependancy
     * @param string|array          $classname name of the class to instantiation, or parameter array
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
            if (!empty($this->pluginClasses[$classname]['class_name'])) {
                $classname = $this->pluginClasses[$classname]['class_name'];
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
        if (empty($this->pluginClasses[$class_name])) {
            return false;
        }
        return $this->pluginClasses[$class_name];
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
 * Interface to implement deferred, that is code which can be execute after something else
 */
interface iPHPDS_deferred
{
    /**
     * Return something meaningful for the caller to trigger the action
     *
     * @return mixed
     */
    public function reduce();

    /**
     * Part to execute if the action triggered was successful
     *
     * @param mixed $controller_result whetever was returned by the controller's run
     *
     * @return mixed
     */
    public function success($controller_result = null);

    /**
     * Part to execute if the action triggered has failed
     *
     * @return mixed
     */
    public function failure();
}
