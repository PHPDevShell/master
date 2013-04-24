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
$configuration['driver']['cache'] = 'PHPDS_filecache';

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
 * @global integer (seconds) (0 to turn off any cache)
 */
$configuration['cache_refresh_intervals'] = 1440;

/**
 * Memcached cache server details.
 * Only complete this when you are using the memcached driver, this is not needed for file based or apc caching.
 * Duplicate cache server block to create more memcached servers which gets utilised by memcached depending on weight.
 * @global array
 */
//$configuration['memcached_cacheserver'][0] = array(
    /**
     * Point to the host where memcached is listening for connections.
     */
    //'host'           => 'localhost',
    /**
     * Point to the port where memcached is listening for connections.
     * Set this parameter to 0 when using UNIX domain sockets.
     */
    //'port'           => 11211,
    /**
     * Number of buckets to create for this server which in turn control its probability of it being selected.
     * The probability is relative to the total weight of all servers.
     */
    //'weight'         => 0
//);

//////////////////////////////////////////////////////////////////////////////
// Session ///////////////////////////////////////////////////////////////////

/**
 * The class that handles the session connection found inside includes/session/
 * Also supports : PHPDS_apcSession, PHPDS_memcachedSession
 * @global string
 */
$configuration['driver']['session'] = 'PHPDS_fileSession';

/**
 * The lifespan of the session created.
 * @global integer (seconds) (0 to turn off any sessions)
 */
$configuration['session_life'] = 1440;

/**
 * Will attempt to protect system against potential session hijacking.
 * @global bool
 */
$configuration['session_protect'] = false;

/**
 * Allows you to specify specific runtime configuration options for your servers sessions.
 * @see http://www.php.net/manual/en/session.configuration.php
 * You can add additional setting to the array.
 * @global array
 */
$configuration['session_cfg'] = array(
    //'session.gc_probability' => 1,
    //'session.gc_divisor' => 100,
    //'session.gc_maxlifetime' => $configuration['session_life']
);

/**
 * Sets the temp session data save path, false to use default.
 * (Needs to be writable)
 * @global string $configuration['session_path']
 */
$configuration['session_path'] = 'write/session/';

/**
 * Memcached session server details.
 * Only complete this when you are using the memcached driver, this is not needed for file based or apc sessions.
 * Duplicate cache server block to create more memcached servers which gets utilised by memcached depending on weight.
 * USE MAIN MEMCACHED INSTANCE: To use main cache server instance, simply comment this line out.
 * @global array
 */
//$configuration['memcached_sessionserver'][0] = array(
    /**
     * Point to the host where memcached is listening for connections.
     */
    //'host'           => 'localhost',
    /**
     * Point to the port where memcached is listening for connections.
     * Set this parameter to 0 when using UNIX domain sockets.
     */
    //'port'           => 11211,
    /**
     * Number of buckets to create for this server which in turn control its probability of it being selected.
     * The probability is relative to the total weight of all servers.
     */
    //'weight'         => 0
//);

//////////////////////////////////////////////////////////////////////////////
// System ////////////////////////////////////////////////////////////////////

/**
 * Should URLs be rewritten to use neat search engine friendly URLS?
 * Please note you must rename the rename.htaccess file to .htaccess
 * You also need to have mod_rewrite installed on your server, this generally does not work on Windows.
 * @global boolean
 */
$configuration['sef_url'] = false;

/**
 * What suffix should be added to the end of a node name, e.g .html, .php, .asp etc. whatever you like.
 * Leave this blank to have a no suffix like e.g example.com/somenode
 * @global string
 */
$configuration['url_append'] = '';

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
 * How the charset will be suffix to the language and region.
 * E.g '.{charset}' will be formatted as en_US.UTF-8 where '.{charset}' translate to '.UTF-8'
 * @global string
 */
$configuration['charset_format'] = '.{charset}';

/**
 * Default system language code as installed by your server (used for i18n gettext translation).
 * @see http://www.iana.org/assignments/language-subtag-registry
 * @global string
 */
$configuration['language'] = 'en';

/**
 * Default system region code as installed and wanted by your server (used for i18n gettext translation).
 * @see http://www.iana.org/assignments/language-subtag-registry
 * @global string
 */
$configuration['region'] = 'US';

/**
 * The full locale as will be passed through to the system config (some servers requires a different format).
 * This is mostly used for i18n gettext translations.
 * E.g '{lang}_{region}{charset}' will be formatted as en_US.UTF-8 depending on user selection or other options.
 * @global string
 */
$configuration['locale_format'] = '{lang}_{region}{charset}';

/**
 * This is the repository the plugin manager will use to check for updates or install new plugins.
 * @global string
 */
$configuration['repository'] = 'https://raw.github.com/PHPDevShell/repository/master/repository.json';

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
    'router'       => 'PHPDS_router',
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

/**
 * If you have a website tracking, analytics or affiliate script you may add it here, it will be added at the end of the body tag.
 * @global string
 */
$configuration['footer_js'] = <<<JS
	<!-- Ending Javascript -->
JS;

//////////////////////////////////////////////////////////////////////////////
// Debugging /////////////////////////////////////////////////////////////////

/**
 * Shows some basic information onscreen.
 * @global boolean
 */
$configuration['page_loadtimes'] = false;

/**
 * When your system goes to production, set this to TRUE to avoid information leaks.
 * Will force compile on template engine.
 *
 * Overrides most 'debug' and 'error' settings
 *
 * @global boolean
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
 * This will control what to monitor independently on how the message will be delivered (see below).
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
 * Override by 'production' = true.
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
