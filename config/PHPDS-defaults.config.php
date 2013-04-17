<?php

//////////////////////////////////////////////////////////////////////////////
// DEFAULT VALUES FOR SYSTEM USE ONLY ////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
// DON'T MODIFY THIS FILE, CREATE YOUR OWN OR MODIFY single-site.config.php //
//////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////
// Database //////////////////////////////////////////////////////////////////

/**
 * The is the class that handles the db connection found inside includes/db/
 * @global string
 */
$configuration['driver']['db'] = 'PHPDS_pdo';
/**
 * Main database connection parameters for default connector.
 * If you install a different database connector you might want to.
 *
 * Having multiple databases allows you to query with $this->db->in('slave')->query('...sql...');
 *
 * @global array
 */
$configuration['database']['master'] = array(
    /**
     * Database DSN (Data Source Name) string. Used for PDO based connections.
     * @var string
     */
    'dsn'        => 'mysql:host=localhost;dbname=phpdev',
    /**
     * Database Server Username.
     * @var string
     */
    'username'   => 'root',
    /**
     * Database Server Password.
     * @var string
     */
    'password'   => 'root',
    /**
     * Default prefix to use in front of table names.
     * @var string
     */
    'prefix'     => '_db_',
    /**
     * Alternative driver options.
     * @var array
     */
    'options'     => array(PDO::ATTR_PERSISTENT => false),
    /**
     * This handy switch allows you to turn off automatic transactions, this is useful for instance
     * where you want your slave db to only read where not transactions is required.
     * Transactions will start at the beginning, commit at the end, or rollback on any exception or critical error.
     * @var bool
     */
    'autotransact' => true

    /**
     * NOTE: Some plugins/connectors might require you to enter a separate host, db name, etc.
     *       This is only be needed of the 'dsn' string is not supported by any other connector type.
     */
);

//////////////////////////////////////////////////////////////////////////////
// Cache /////////////////////////////////////////////////////////////////////

/**
 * The class that handles the cache connection found inside includes/cache/
 * Also supports PHPDS_memcached (additional server info needed and server needs to have the memcached extension
 * installed, please note memcache and memcached extensions are different (this requires memcached))
 * Also supports PHPDS_apc (easier setup than memcached)
 *
 * Memcached is a distributed caching system, whereas APC is non-distributed - and mainly an opcode cache.
 * If (and only if) you have a web application which has to live on different webservers (loadbalancing),
 * you have to use memcached for distributed caching. If not, just stick to APC. (Stackoverflow)
 *
 * @global string
 */
$configuration['driver']['cache'] = 'PHPDS_apc';
/**
 * Views cache path (used by PHPDS_filecache)
 * (Needs to be writable)
 * @global string $configuration['cache_path']
 */
$configuration['cache_path'] = 'write/cache/';
/**
 * Cache refresh intervals in seconds.
 * Helps with overall performance of your system. The higher the value the less queries will be done,
 * but your settings will be slower to update.
 * @global integer (seconds)
 */
$configuration['cache_refresh_intervals'] = 1440;
/**
 * Memcached server details.
 * Only complete this when you are using the memcached driver, this is not needed for file based or apc caching.
 * Duplicate cache server block to create more memcached servers which gets utilised by memcached depending on weight.
 * @global array
 */
$configuration['memcached_cacheserver'][0] = array(
    /**
     * Point to the host where memcached is listening for connections.
     */
    'host'           => 'localhost',
    /**
     * Point to the port where memcached is listening for connections.
     * Set this parameter to 0 when using UNIX domain sockets.
     */
    'port'           => 11211,
    /**
     * Number of buckets to create for this server which in turn control its probability of it being selected.
     * The probability is relative to the total weight of all servers.
     */
    'weight'         => 0
);

//////////////////////////////////////////////////////////////////////////////
// Session ///////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////
// System ////////////////////////////////////////////////////////////////////

/**
 * The class that handles the session connection found inside includes/session/
 * Also supports : PHPDS_apcSession, PHPDS_memSession
 * @global string
 */
$configuration['driver']['session'] = 'PHPDS_fileSession';
/**
 * When you experience a delay in views updating after changes, enable this to correct it.
 * Note disable this in production as it uses allot of memory.
 * @global integer
 */
$configuration['force_views_compile'] = false;
/**
 * Enables views caching.
 * When triggered in view, the page will be static. Note this is aggressive caching and will not work on dynamic pages without proper configuration.
 * @global integer
 */
$configuration['views_cache'] = false;
/**
 * Views cache refresh intervals in seconds.
 * When enabled, this will rewrites views cache every som many seconds.
 * @global integer
 */
$configuration['views_cache_lifetime'] = 360;
/**
 * If you are running a very large site, you might want to consider running a dedicated light http server (httpdlight, nginx) that
 * only serves static content like images and static files, call it a CDN if you like.
 * By adding a host here 'http://192.34.22.33/project/cdn', all images etc, of PHPDevShell will be loaded from this address.
 * NO TRAILING SLASH
 * @global string
 */
$configuration['static_content_host'] = '';
/**
 * If you have a website tracking, analytics or affiliate script you may add it here, it will be added at the end of the body tag.
 * @global string
 */
$configuration['footer_js'] = <<<JS
	<!-- Ending Javascript -->
JS;
/**
 * Login session life.
 * This is how long the session will be remembered with each new login.
 * To disable, create session life as 0.
 * @global integer
 */
$configuration['session_life'] = 1800;
/**
 * Sets the temp session data save path, false to use default.
 * (Needs to be writable)
 * @global string $configuration['session_path']
 */
$configuration['session_path'] = 'write/session/';
/**
 * Views compile path.
 * (Needs to be writable)
 * @global string $configuration['compile_path']
 */
$configuration['compile_path'] = 'write/compile/';
/**
 * Temporary writable folder path.
 * (Needs to be writable)
 * @global string $configuration['tmp_path']
 */
$configuration['tmp_path'] = 'write/tmp/';
/**
 * Files uploading folder path.
 * (Needs to be writable)
 * @global string $configuration['upload_path']
 */
$configuration['upload_path'] = 'write/upload/';
/**
 * Force system down bypass.
 * If your session expired while system was set to down/maintenance in the config gui, you can gain login access again by setting this option true.
 * @global boolean $configuration['system_down_bypass']
 */
$configuration['system_down_bypass'] = false;
/**
 * If true $lang variables will also be converted to constants.
 * @global boolean $configuration['constant_conversion']
 */
$configuration['constant_conversion'] = false;
/**
 * Select extra functions to load in engine. Functions in these files will always be available.
 * Example : utils.php
 * @global array
 */
$configuration['function_files'] = array();
/**
 * Default charset to use - note this is php html entities coding, not PDO's or mysql's
 * @see  http://www.php.net/manual/en/function.htmlentities.php
 * @global string
 */
$configuration['charset'] = 'UTF-8';

/**
 * This is the repository the plugin manager will use to check for updates or install new plugins.
 *
 * @global string
 */
$configuration['repository'] = 'https://raw.github.com/PHPDevShell/repository/master/repository.json';

/**
 * This is all the settings that will be available in $configuration['value'] loaded from database.
 * In general this would never be changed, however a developer
 * might need to add their own variables they would need on every page.
 *
 * @global array
 */
$configuration['preloaded_settings'] = array(
    'scripts_name_version',
    'redirect_login',
    'footer_notes',
    'front_page_id',
    'front_page_id_out',
    'front_page_id_in',
    'loginandout',
    'custom_logo',
    'custom_css',
    'system_down',
    'demo_mode',
    'charset_format',
    'locale_format',
    'charset',
    'language',
    'debug_language',
    'region',
    'root_id',
    'root_role',
    'root_group',
    'force_core_changes',
    'system_logging',
    'access_logging',
    'crypt_key',
    'date_format',
    'date_format_short',
    'default_template',
    'default_theme_id',
    'printable_template',
    'split_results',
    'guest_role',
    'guest_group',
    'system_timezone',
    'setting_admin_email',
    'email_critical',
    'sef_url',
    'queries_count',
    'allow_registration',
    'registration_page',
    'allow_remember',
    'url_append',
    'skin',
    'meta_keywords',
    'meta_description',
    'node_behaviour',
    'spam_assassin',
    'custom_css'
);

/**
 * Allows a developer to override/extend a core class with his own.
 * Add extending class inside includes/extend/ folder and register its name by defining a value (NOT KEY) below.
 * e.g  If PHPDS_auth will be extended by PHPDS_OAuth2 make sure you have includes/extend/PHPDS_OAuth2.class.php
 *      The class name should then be "class PHPDS_OAuth2 extends PHPDS_auth {}"
 *
 * @global array
 */
$configuration['extend'] = array(
    'auth'         => 'PHPDS_auth',
    'config'       => 'PHPDS_config',
    'core'         => 'PHPDS_core',
    'debug'        => 'PHPDS_debug',
    'errorHandler' => 'PHPDS_errorHandler',
    'model'        => 'PHPDS_model',
    'navigation'   => 'PHPDS_navigation',
    'notif'        => 'PHPDS_notif',
    'tagger'       => 'PHPDS_tagger',
    'template'     => 'PHPDS_template',
    'user'         => 'PHPDS_user',
    'view'         => 'PHPDS_view'
);

/**
 * The engine will look in the order they are placed in for classes in possible listed folders.
 * For instance to look in folders that overrides main classes add 'includes/override' as
 * first folder and add the override class in includes/override/PHPDS_someclass.class.php
 * The system will now look in this folder for the engine class first.
 * @global array
 */
$configuration['class_folders'] = array('includes', 'includes/extend');

//////////////////////////////////////////////////////////////////////////////
// Debugging /////////////////////////////////////////////////////////////////

/**
 * When your system goes to production, set this to TRUE to avoid information leaks.
 * Will force compile on template engine.
 *
 * Overrides most 'debug' and 'error' settings
 *
 * @var boolean
 */
$configuration['production'] = true;

/**
 * Enable Debugging.
 *
 * @global boolean
 */
$configuration['debug']['enable'] = false;

/**
 * Debug domains filter to include in debugging output, domains must be listed here for the messages to appear.
 * This will control what to monitor indepently on how the message will be delivered (see below).
 * Example:
 * $configuration['debug']['domains'] = array('core', 'db', 'navigation', 'security', 'template', 'user', '!');
 * There is a special domain: exclamation mark ('!') which refers to the low-level skel
 * Note that you can use star ('*') as a wildcard
 *
 * @global array
 */
$configuration['debug']['domains'] = array('core', 'db', 'navigation', 'security', 'template', 'user', '!');

/**
 * Debug level.
 * DEBUG = 4;
 * INFO = 3;
 * WARN = 2;
 * ERROR = 1;
 * LOG = 0;
 *
 * @global integer
 */
$configuration['debug']['level'] = 0;


// Error settings
/**
 * Use FirePHP as debugging platform.
 * Overriden by 'production' = true.
 *
 * @global boolean
 */
$configuration['error']['firePHP'] = false;

/**
 * Do server debugging logs.
 * Overriden by 'production' = true.
 * Default: true (recommended)
 *
 * @global boolean
 */
$configuration['error']['serverlog'] = true;

/**
 * To what directory should errors file logs be written to.
 * (Needs to be writable)
 *
 * @global string
 */
$configuration['error']['file_log_dir'] = 'write/logs/';

/**
 * To what email should critical errors be emailed to, make sure php can send mail, this does not use the PHPDevShell mailing engine.
 *
 * @global string
 */
$configuration['error']['email_critical'] = '';

/**
 * Should messages be shown onscreen in the web browser?
 * Note that messages generated before the View is created will be outputed in a very raw manner.
 * Overriden by 'production' = true.
 * Default: true
 *
 * @global boolean
 */
$configuration['error']['display'] = true;

/**
 * Ignore notices?
 * If this is true, the error handler will NOT handle notices, which may lead to your site being broken
 * Default: false (recommended)
 *
 * @global boolean
 */
$configuration['error']['ignore_notices'] = false;

/**
 * Ignore warnings?
 * If this is true, the error handler will NOT handle warnings, which may lead to your site being broken
 * Default: false (recommended)
 *
 * @global boolean
 */
$configuration['error']['ignore_warnings'] = false;

/**
 * If true, a warning will be handled as an exception (and stops the cycle if not handled)
 * Default: true (recommended)
 *
 * @global boolean
 */
$configuration['error']['warningsAreFatal'] = true;

/**
 * If true, a notice will be handled as an exception (and stops the cycle if not handled)
 * If both this and 'ignore_notices' are false, and 'display' is true, an inline notice message will be included in the page
 * Default: false (recommended)
 *
 * @global boolean
 */
$configuration['error']['noticesAreFatal'] = false;

/**
 * Set error handler reporting.
 *
 * @global string
 */
$configuration['error']['mask'] = E_ALL | E_STRICT; //  you should change to  E_ALL | E_STRICT to be clean

/**
 * Enable some development-related features.
 * 1. Change this to true if you would like to set the theme to use the normal css and javascript instead of minified.
 *
 * @global boolean
 */
$configuration['development'] = false;
