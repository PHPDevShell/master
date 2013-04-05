<?php
/**
 * With version 4.0 we did an API cleanup.
 *
 * Direct access to database connection data is deprecated:
 * - $server, $dbUserName, $dbPassword, $dbName
 */


/**
 * Database specifics features
 *
 * Note that this deals with software specific features (such as language, error codes...)
 * The connector deals with specific calls (i.e. communication protocol)
 */
interface iPHPDS_dbSpecifics
{

    /**
     * Build and throw a database specific exception
     *
     * The first parameter can be a message (as a string) or a previous exception (can also be omitted)
     * If a message is provided, an error code can also be provided
     *
     * @param PHPDS_Exception|string|null $e
     * @param integer|null error code
     */
    public function throwException($e = null, $code = 0);


    /**
     * Sets the configuration settings for this connector as per the configuration file.
     *
     * The first parameter is a pointer to the connector's internal data array
     *
     * The parameter allows flexible configuration:
     * - if it's empty, the configuration in $this->dbConfig is used; if the later is empty too,
     *     the default system config is used
     * - if it's a string, a configuration by that name is looked up into the global configuration
     * - if it's an array, it's used a direct connection info
     *
     * Note that if a connection is already up, it's disconnected
     *
     * @param array $current_config the field which actually holds the connector data
     * @param string|array|null $db_config new data to specify how to connect to the database
     * @return void
     *
     */
    public function applyConfig(&$current_config, $db_config = null);
}


/**
 * A generic implementation of the iPHPDS_dbSpecifics interface
 *
 * Used when there is not specific support for a given database server software
 */
class PHPDS_genericDB extends PHPDS_dependant implements iPHPDS_dbSpecifics
{
    /**
     * Secondary constructor
     *
     * You can pass a config ref (array or string, @see iPHPDS_dbSpecifics) to have to applied directly
     *
     * @param null $db_config
     * @return bool|void
     */
    public function  construct($db_config = null)
    {
        if (!empty($db_config)) {
            $this->applyConfig($db_config);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function throwException($e = null, $code = 0)
    {
        $message = is_string($e) ? $e : 'Database error';
        $previous = is_a('Exception', $e) ? $e : null;

        if (!is_a($e, 'PHPDS_DatabaseException')) {
            /* @var PHPDS_DatabaseException $e */
            $e = $this->factory('PHPDS_DatabaseException', $message, $code, $previous);
        }
        throw $e;
    }

    /**
     * {@inheritDoc}
     */
    public function applyConfig(&$current_config, $db_config = null)
    {
        $dbSettings = array();

        // try to find a source for the database config
        if (empty($db_config)) {
            $dbSettings = empty($current_config) ? PU_GetDBSettings($this->configuration) : $current_config;
        } else {
            if (is_string($db_config)) {
                $dbSettings = PU_GetDBSettings($this->configuration, $db_config);
            } elseif (is_array($db_config)) {
                $dbSettings = $db_config;
            } else {
                $this->throwException('Wrong database setting specification');
            }
        }

        // build DSN if needed
        if (empty($dbSettings['dsn'])) {
            $dsn = empty($dbSettings['driver']) ? 'mysql' : $dbSettings['driver'];
            $dsn .= ':host=' . (empty($dbSettings['host']) ? 'localhost' : $dbSettings['host']);
            $dsn .= ';dbname=' . (empty($dbSettings['database']) ? 'PHPDS' : $dbSettings['database']);

            if (!empty($dbSettings['charset'])) {
                $dsn .= ';charset=' . $dbSettings['charset'];
            }

            $dbSettings['dsn'] = $dsn;
        }

        $current_config = $dbSettings;
    }
}

/**
 * This is a blueprint for a connector, ie an object which handles the basic I/O to a database.
 * Its main use it to add a layer of exception throwing to mysql functions
 *
 * All of these methods are based on php-mysql interface function of the same name
 *
 * @see iPHPDS_dbSpecifics
 */
interface iPHPDS_dbConnector
{
    /**
     * Clears the current data result (useful for example if we're fetching one row
     * at a time and we give up before the end)
     *
     * @return boolean TRUE on success or FALSE on failure
     * @see includes/PHPDS_db_connector#free()
     */
    public function free();

    /**
     * Connect to the database server
     *
     * If $db_config is provided and the connection is already up, disconnect it and reconnect with the new settings
     *
     * $db_config has been added in 4.0
     *
     * @see stable/phpdevshell/includes/PHPDS_db_connector#connect()
     */
    public function connect($db_config = null);

    /**
     * Shutdown the connection to the database
     *
     * Added in 4.0
     *
     * @return null
     */
    public function disconnect();

    /**
     * Actually send the query to MySQL (through $db)
     *
     * If $parameters is provided, the query maybe be prepared (or not, depending on the connector)
     *
     * $parameters has been added in 4.0
     *
     * May throw a PHPDS_databaseException
     *
     * @param string $sql the actual sql query
     * @param array $parameters
     * @return resource the resulting resource (or false is something bad happened)
     */
    public function query($sql, $parameters = null);

    /**
     * Protect a single string from possible hacker (i.e. escape possible harmful chars)
     *
     * @param    $param        string, the parameter to protect
     * @return string, the escaped string
     * @see includes/PHPDS_db_connector#protect()
     */
    public function protect($param);

    /**
     * Return the next line as an associative array
     *
     * @return array, the resulting line (or false is nothing is found)
     * @see includes/PHPDS_db_connector#fetch_assoc()
     */
    public function fetchAssoc();

    /**
     * Move the internal pointer to the asked line
     *
     * @param    $row_number        integer, the line number
     * @return boolean, TRUE on success or FALSE on failure
     * @see includes/PHPDS_db_connector#seek()
     */
    public function seek($row_number);

    /**
     * Return the number of rows in the result of a SELECT query
     *
     * @return integer, the number of rows
     * @see includes/PHPDS_db_connector#numrows()
     */
    public function numrows();

    /**
     * Return the number of affected rows by a non-SELECT query
     *
     * @return integer, the number of affected rows
     * @see includes/PHPDS_db_connector#affectedRows()
     */
    public function affectedRows();

    /**
     * Simply returns last inserted id from database.
     *
     * @return int
     */
    public function lastId();

    /**
     * Will return a single row as a string depending on what column was selected.
     *
     * @return string
     */
    public function rowResults();

    /**
     * Start SQL transaction.
     */
    public function startTransaction();

    /**
     * Ends SQL transaction.
     *
     * @param boolean $commit
     */
    public function endTransaction($commit = true);
}

/**
 * This is a new version of one the Big5: the db class
 * This new version supports connectors and queries class and should be compatible with the old one
 *
 */
class PHPDS_db extends PHPDS_dependant
{
    /**
     * Contains servers name where PHPDevShell runs on.
     *
     * @var string
     */
    public $server;
    /**
     * Contains database user name where PHPDevShell runs on.
     *
     * @var string
     */
    public $dbUsername;
    /**
     * Contains database user password where PHPDevShell runs on.
     *
     * @var string
     */
    public $dbPassword;
    /**
     * Contains database name where PHPDevShell runs on.
     *
     * @var string
     */
    public $dbName;
    /**
     * Contains connection data.
     *
     * @var object
     */
    public $connection;
    /**
     * Count amount of queries used by the system.
     * Currently it is on 0, we are not counting Start and End transaction.
     *
     * @var integer
     */
    public $countQueries = 0;
    /**
     * Essential settings array.
     *
     * @var array
     */
    public $essentialSettings;
    /**
     * Display erroneous sql statements
     *
     * @var boolean
     */
    public $displaySqlInError = false;

    /**
     * Stores results
     *
     * @var string
     */
    public $result;

    /**
     * Database connector.
     * @var iPHPDS_dbConnector
     */
    protected $connector;

    /**
     * List of alternative connectors (i.e., not the default, primary connector)
     * @var array of iPHPDS_dbConnector
     */
    protected $connectors;

    /**
     * For backward compatibility: a default query instance used for sending sql queries directly
     * @var PHPDS_query
     */
    protected $defaultQuery;

    /**
     * Constructor.
     *
     */
    public function construct()
    {
        $dbSettings = PU_GetDBSettings($this->configuration);

        // For backwards compatibility, set the database class's parameters here as we don't know if anyone references
        // db's properties somewhere else
        $this->server = $dbSettings['host'];
        $this->dbName = $dbSettings['database'];
        $this->dbUsername = $dbSettings['username'];
        $this->dbPassword = $dbSettings['password'];

        $connectorClass = empty($dbSettings['connector']) ? 'PHPDS_pdoConnector' : $dbSettings['connector'];
        $this->connector = $this->factory($connectorClass, $dbSettings);
    }

    /**
     * Force database connection.
     * Jason: Note this is used in core initiation to fix some dependent functions like mysql_real_escape_string requiring a DB connection.
     * Only dbConnecter->query initiated the connection which was unfair to dependent functions.
     *
     * Don: The connector will apply the database settings itself since each connector may have different settings. For backwards
     * compatibility the connector will set the database properties for the main db instance as well. In the feature the db
     * class won't have public properties for the database settings such as $db->server, $db->dbName, etc. since each connector may
     * have different settings.
     *
     * @throws PHPDS_databaseException
     */
    public function connect($db_config = '')
    {
        try {
            $this->connector->connect($db_config);
        } catch (Exception $e) {
            /* @var PHPDS_databaseException $e */
            $e = $this->factory('PHPDS_databaseException', '', 0, $e);
            throw $e;
        }
    }

    /**
     * Handle access to the alternate connector list
     * Give a class name, the connector will be instantiated if needed
     *
     * @param string $connector, class name of the connector
     * @return iPHPDS_dbConnector
     * @throws PHPDS_exception
     */
    public function connector($connector = null)
    {
        if (is_null($connector)) {
            return $this->connector;
        }
        if (is_string($connector) && class_exists($connector)) {
            if (isset($this->connectors[$connector])) {
                return $this->connectors[$connector];
            } else {
                $new = $this->factory($connector);
                if (is_a($new, 'iPHPDS_dbConnector')) {
                    $this->connectors[$connector] = $new;
                    return $new;
                }
            }
        }
        throw new PHPDS_exception('Unable to factor such a connector.');
    }

    /**
     * Compatibility
     * Do direct sql query without models.
     *
     * @param string
     * @return mixed
     * @throws PHPDS_databaseException
     */
    public function newQuery($query)
    {
        try {
            if (empty($this->defaultQuery)) $this->defaultQuery = $this->makeQuery('PHPDS_query');
            $this->defaultQuery->sql($query);
            return $this->defaultQuery->query();
        } catch (Exception $e) {
            if (empty($this->defaultQuery))
                $msg = 'Unable to create default query: ' . $e->getMessage();
            else
                $msg = 'While running default query:<br /><pre>' . $this->defaultQuery->sql() . '</pre>' . $e->getMessage();
            throw new PHPDS_databaseException($msg, 0, $e);
        }
    }

    /**
     * Alias to newQuery
     *
     * @param string $query
     * @return mixed
     */
    public function sqlQuery($query)
    {
        return $this->newQuery($query);
    }

    /**
     * Locates the query class of the given name, loads it, instantiate it, send the query to the DB, and return the result
     *
     * @param string $query_name the name of the query class (descendant of PHPDS_query)
     * @return array (usually), the result data of the query
     */
    public function invokeQuery($query_name) // actually more parameters can be given
    {
        $params = func_get_args();
        array_shift($params); // first parameter of this function is $query_name
        return $this->invokeQueryWith($query_name, $params);
    }

    /**
     * Locates the query class of the given name, loads it, instantiate it, send the query to the DB, and return the result
     *
     * @param string $query_name the name of the query class (descendant of PHPDS_query)
     * @param array $params array of parameters
     * @return array (usually), the result data of the query
     * @throws PHPDS_databaseException
     */
    public function invokeQueryWith($query_name, $params)
    {
        $query = $this->makeQuery($query_name);
        if (!is_a($query, 'PHPDS_query'))
            throw new PHPDS_databaseException('Error invoking query');
        return $query->invoke($params);
    }

    /**
     * Locates the query class of the given name, loads it, instantiate it, and returns the query object
     *
     * @param string $query_name the name of the query class (descendant of PHPDS_query)
     * @return object the query object
     * @throws PHPDS_exception
     */
    public function makeQuery($query_name)
    {
        $configuration = $this->configuration;
        $navigation    = $this->navigation;
        $o             = null;
        $good          = (class_exists($query_name, false));
        if (!$good) {
            $phpds = $this->PHPDS_dependance();
            list($plugin, $node_link) = $navigation->nodePath();
            $query_file = 'models/' . $node_link;
            $query_file = preg_replace('/\.php$/', '.query.php', $query_file);
            $query_file = $configuration['absolute_path'] . 'plugins/' . $plugin . '/' . $query_file;
            $good       = $phpds->sneakClass($query_name, $query_file);
            // Execute class file.
            if (!$good) {
                $node = $configuration['m'];
                if (!empty($navigation->navigation[$node])) {
                    $plugin     = $navigation->navigation[$node]['plugin'];
                    $query_file = $configuration['absolute_path'] . 'plugins/' . $plugin . '/models/plugin.query.php';
                    $good       = $phpds->sneakClass($query_name, $query_file);
                }
            }
        }
        // All is good create class.
        if ($good) {
            $o = $this->factory($query_name);
            if (is_a($o, 'PHPDS_query')) {
                return $o;
            }
            throw new PHPDS_exception('Error factoring query: object is not a PHPDS_query, maybe you mistyped the class superclass.');
        }
        throw new PHPDS_exception('Error making query: unable to find class "' . $query_name . '".');
    }

    /**
     * Set the starting point for a SQL transaction
     * You should call end_transaction(true) for the queries to actually occur
     */
    public function startTransaction()
    {
        $this->connector->startTransaction();
    }

    /**
     * Commits database transactions.
     * @return mixed
     */
    public function endTransaction()
    {
        $configuration = $this->configuration;
        // Should we commit or rollback?
        if (($configuration['demo_mode'] == true)) {
            if ($configuration['user_role'] != $configuration['root_role']) {
                // Roll back all database changes.
                return $this->connector->endTransaction(false);
            } else {
                // Commit all database changes.
                return $this->connector->endTransaction(true);
            }
        } else if ($configuration['demo_mode'] == false) {
            // Commit all database changes.
            return $this->connector->endTransaction(true);
        }
    }

    /**
     * Protect a single string from possible hacker (i.e. escape possible harmfull chars)
     * Actually deleguate the action to the connector
     *
     * @param mixed $param the parameter to escape
     * @return string the escaped string/array
     */
    public function protect($param)
    {
        if (is_array($param)) {
            return $this->protectArray($param);
        } else {
            return $this->connector->protect($param);
        }
    }

    /**
     * Protect a array of strings from possible hacker (i.e. escape possible harmful chars)
     * (this has been moved from PHPDS_query)

     * @param $a array the strings to protect
     * @param $quote string the quotes to add to each non-numerical scalar value
     * @return array the same string but safe
     */
    public function protectArray(array $a, $quote = '')
    {
        foreach ($a as $index => $value) {
            $v = null;
            if (is_array($value)) {
                $v = $this->protectArray($value);
            }
            if (is_scalar($value)) {
                $v = $this->connector->protect($value);
                if (!is_numeric($v) && $quote) {
                    $v = $quote . $v . $quote;
                }
            }
            if (!empty($v)) {
                $a[$index] = $v;
            }
        }

        return $a;
    }

    /**
     * Will convert object configuration into array for parsing.
     */
    public function debugConfig()
    {
        $converted_config = array();
        foreach ($this->configuration as $key => $extended_config) {
            $converted_config[$key] = $extended_config;
        }
        $this->log($converted_config);
    }

    /**
     * Checks if a database table exists.
     *
     * @param string $table
     * @return boolean
     */
    public function tableExist($table)
    {
        return $this->invokeQuery('DB_tableExistQuery', $table);
    }

    /**
     * Simple method to count number of rows in a table.
     *
     * @param string $table_name
     * @param string $column
     *
     * @return integer
     */
    public function countRows($table_name, $column = null)
    {
        // Check what to count.
        if (empty($column)) $column = '*';
        return $this->invokeQuery('DB_countRowsQuery', $column, $table_name);
    }

    /**
     * Determines whether the specified search string already exists in the specified field within the supplied table.
     * Optional: Also looks at an id field (typically the primary key of a table) to make sure that the record you are working with
     * is NOT included in the search.
     * Usefull when modifying an existing record and you need first to check if another record with the same value doesn't already exist.
     *
     * @param string        $table_name The name of the database table.
     * @param array|string  $search_column_names The array names of the columns in which to look for the search strings, a single value can also be given.
     * @param array|string  $search_field_values In the same order as $search_column_name array, the search strings in array that should not be duplicated, a single value can also be given.
     * @param string        $column_name_for_exclusion The name of the primary key column name of the record you will be updating.
     * @param string        $exclude_field_value The value of the primary key of the record you will be updating that should not be included in the search.
     * @return boolean      If TRUE is returned it means the record already exists, FALSE means the record doesn't exist.
     */
    public function doesRecordExist($table_name, $search_column_names, $search_field_values, $column_name_for_exclusion = null, $exclude_field_value = null)
    {
        return $this->invokeQuery('DB_doesRecordExistQuery', $table_name, $search_column_names, $search_field_values, $column_name_for_exclusion, $exclude_field_value);
    }

    /**
     * Get a single result from database with minimal effort.
     *
     * @param string $from_table_name
     * @param string $select_column_name
     * @param string $where_column_name
     * @param string $is_equal_to_column_value
     * @return mixed
     */
    public function selectQuick($from_table_name, $select_column_name, $where_column_name, $is_equal_to_column_value)
    {
        return $this->invokeQuery('DB_selectQuickQuery', $select_column_name, $from_table_name, $where_column_name, $is_equal_to_column_value);
    }

    /**
     * Delete data from the database with minimal effort.
     *
     * @param string $from_table_name
     * @param string $where_column_name
     * @param string $is_equal_to_column_value
     * @param string $return_column_value
     * @return string
     */
    public function deleteQuick($from_table_name, $where_column_name, $is_equal_to_column_value, $return_column_value = null)
    {
        return $this->invokeQuery('DB_deleteQuickQuery', $from_table_name, $where_column_name, $is_equal_to_column_value, $return_column_value);
    }
}
