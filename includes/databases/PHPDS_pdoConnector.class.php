<?php

/**
 *
 * NOTE: you're not supposed to deal with connectors any way
 *
 * @author greg <greg@phpdevshell.org>
 * @author Don
 *
 * @property string $dsn
 * @property string Charset
 * @property string host
 * @property string database
 * @property string username
 * @property string password
 * @property string prefix
 * @property string persistent
 *
 */
class PHPDS_pdoConnector extends PHPDS_dependant implements iPHPDS_dbConnector
{
    /**
     * Allow the connector class to provide itself with connection data.
     * Useful when you provided a database connection directly with a daughter class
     *
     * If it's not an array, it will be filled with the correct data at construction time.
     *
     * The content is an associative array as such:
     *
     * $this->dbSettings['dsn'] // a complete PDO DSN, built if not provided
     * $this->dbSettings['host']
     * $this->dbSettings['database']
     * $this->dbSettings['username']
     * $this->dbSettings['password']
     * $this->dbSettings['prefix']
     * $this->dbSettings['persistent']
     * $this->dbSettings['charset']
     *
     * @var string|array either a DSN or an array of configuration data
     */
    public $dbSettings;

    /* @var integer $selectedRows the number of rows selected by the last SELECT query */
    public $selectedRows = -1;

    /* @var integer $affectedRows the number of rows affected by the last INSERT/DELETE/etc query */
    public $affectedRows = -1;


    /**
     * @var PDO the link for the mysql connection (as returned by new PDO())
     */
    protected $link = null;

    /**
     * @var PDOStatement the result resource of a query (as returned by a PDO query)
     */
    protected $result;


    /**
     * This class helps dealing with database specific features
     *
     * @var iPHPDS_dbSpecifics $database
     */
    protected $dbSpecifics;


    /**
     * Constructor
     *
     *
     * @version 1.0
     *
     * @date 20130223 (1.0) (greg) added
     *
     * @author  greg <greg@phpdevshell.org>
     *
     * @param string|array|null $db_config data to specify how to connect to the database
     * @return null
     */
    public function construct($db_config = null) // variable argument list
    {
        $this->dbSpecifics = $this->factory('PHPDS_genericDB');

        // the following call could be done by the factory, but in this case we override the method
        $this->applyConfig($db_config);
    }

    /**
     * Clears the current connection (useful for example if we're fetching one row at a time and we give up before the end)
     * Note that there is no mysql_free_result() equivalent for PDO. The closest method to free resources for PDO is
     * PDOStatement::closeCursor(). closeCursor() frees up the connection to the server so that other SQL statements may
     * be issued, but leaves the statement in a state that enables it to be executed again.
     *
     * Also returns "true" if there is no connection to close
     *
     * @version       2.0
     *
     * @date 20120321 (1.0) (don) added
     * @date 20130223 (2.0) (greg) complete rewrite
     *
     * @author        Don Schoeman
     * @author        greg <greg@phpdevshell.org>
     *
     * @return boolean, TRUE on success or FALSE on failure
     *
     * @see           includes/PHPDS_db_connector#free()
     */
    public function free()
    {
        $result = true;
        if (!empty($this->result)) {
            $result       = $this->result->closeCursor();
            $this->result = null;
        }
        return $result;
    }

    /**
     * Sets the configuration settings for this connector as per the configuration file.
     *
     * The parameter allows flexible configuration:
     * - if it's empty, the configuration in $this->dbConfig is used; if the later is empty too,
     *     the default system config is used
     * - if it's a string, a configuration by that name is looked up into the global configuration
     * - if it's an array, it's used a direct connection info
     *
     * Note that if a connection is already up, it's disconnected
     *
     * @param string|array|null $db_config data to specify how to connect to the database
     * @return null
     *
     * @throw         PHPDS_DatabaseException
     *
     * @version       2.0
     *
     * @date 20120321 (1.0) (don) added
     * @date 20130223 (2.0) (greg) complete rewrite
     *
     * @author        Don Schoeman
     * @author        greg <greg@phpdevshell.org>
     */
    public function applyConfig($db_config = null)
    {
        if (!empty($this->link)) {
            $this->disconnect();
        }
        $this->dbSpecifics->applyConfig($this->dbSettings, $db_config);

        $this->Charset = empty($this->dbSettings['charset']) ? '' : $this->dbSettings['charset'];
    }

    /**
     * Connect to the database server (compatibility method)
     *
     * @date 20120321 (1.0) added
     * @date 20130223 (2.0) rewrite for PHPDevShell 4.0
     *
     * @version        2.0
     * @author         don schoeman
     * @author         greg <greg@phpdevshell.org>
     *
     * @throw          PHPDS_databaseException
     *
     * @param string|array $db_config if the connection is already up, disconnect it and reconnect with the new settings
     */
    public function connect($db_config = null)
    {
        if (empty($this->link)) {
            try {
                // Apply database config settings to this instance of the connector
                if (!is_null($db_config)) {
                    $this->applyConfig($db_config);
                }

                // Set the PDO driver options
                $driver_options = null;
                if (!empty($this->dbSettings['persistent'])) {
                    $driver_options = array(PDO::ATTR_PERSISTENT => true); // Connection must be persistent
                }

                // Connect to the server and database
                $this->link = new PDO($this->dbSettings['dsn'],
                    $this->dbSettings['username'],
                    $this->dbSettings['password'],
                    $driver_options
                );

                // Set the error reporting attribute so that SQL errors also generates exceptions
                $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            } catch (Exception $e) {
                // TODO: For now throw an unknown error database exception since the driver will be returning with the
                // error code and we don't know how to deal with all of them yet. We have to deal with this properly
                // at some point in the future.
                $this->dbSpecifics->throwException($e);
            }

        }
    }

    /**
     * Shutdown the connection to the database server
     *
     * @return void
     */
    public function disconnect()
    {
        // this sucks but it's the official way for PDO
        $this->link = null;
    }

    /**
     * Executes a query the old fashioned way. Each query is automatically prepared.
     *
     * @version  1.0
     * @author   don schoeman
     * @param    $sql string, the actual sql query
     * @param    $parameters string
     * @return   PDOStatement|boolean, the resulting resource, false on error, true for a successful query with no result
     * @throws   PHPDS_databaseException
     */
    public function query($sql, $parameters = null)
    {
        try {
            $this->connect();

            // Replace the DB prefix.
            if (!empty($this->dbSettings['prefix'])) {
                $sql = preg_replace('/_db_/', $this->dbSettings['prefix'], $sql);
            }
            // Run query.
            if (!empty($sql)) {
                // Count Queries Used...
                $this->db->countQueries++;

                // Since we don't know whether modifier query is passed we don't know whether to use exec() or query().
                // The alternative option is to prepare the statement and then call execute.
                /* @var PDOStatement $statement */
                /*$statement = $this->link->prepare($sql);
                $statement->execute();*/
                $statement = $this->link->query($sql);

                if ($statement->columnCount() == 0) {
                    $this->result       = $statement;
                    $this->affectedRows = $statement->rowCount();
                    $this->selectedRows = -1;
                    return true; // This was an INSERT/UPDATE/DELETE query
                } else {
                    $this->result       = $statement;
                    $this->affectedRows = -1;
                    $this->selectedRows = $statement->rowCount();
                    return $this->result; // This was a SELECT query, we need to return the result set
                }
            } else {
                return false;
            }
        } catch (Exception $e) {
            $msg = '<p>The PDO database engine returned with an error (code ' . $e->getCode() . ' - ' . $e->getMessage() . '</p>';
            throw new PHPDS_databaseException($msg, 0, $e);
        }
    }

    /**
     * Protect a string from SQL injection. This function should'nt really be used since preparing a statement
     * will protect any parameters passed to the statement automatically within PDO. This function simulates
     * the mysql_real_escape_string() function since it is not available within PDO.
     *
     * TODO: Modify all phpds code to run prepared statements together with parameters queries to
     * protect the query from SQL injection instead of using protect()
     *
     * @date    20120321
     * @version 1.0
     * @author  don schoeman
     * @param   $param string, the parameter to escape
     * @return string, the escaped string
     */
    public function protect($param)
    {
        return strtr($param, array("\x00" => '\x00', "\n" => '\n', "\r" => '\r', '\\' => '\\\\', "'" => "\'", '"' => '\"', "\x1a" => '\x1a'));
    }

    /**
     * Return the next line as an associative array
     *
     * @date    20100216
     * @version 1.0
     * @author  greg
     * @return array, the resulting line (or false is nothing is found)
     * @see     includes/PHPDS_db_connector#fetch_assoc()
     */
    public function fetchAssoc()
    {
        return (is_a($this->result, 'PDOStatement')) ? $this->result->fetch(PDO::FETCH_ASSOC) : false;
    }

    /**
     * Move the internal pointer to the asked line. Not available for PDO connections, will raise an exception if called.
     *
     * @param  $row_number integer, the line number
     * @return boolean, TRUE on success or FALSE on failure
     * @throws PHPDS_exception
     */
    public function seek($row_number)
    {
        throw new PHPDS_exception('pdoConnector seek() function not implemented.');
    }

    /**
     * Return the number of rows in the result of the query
     *
     * @date            20100216
     * @version   1.0
     * @author    greg
     * @return integer, the number of rows
     * @see       includes/PHPDS_db_connector#numrows()
     */
    public function numrows()
    {
        return $this->selectedRows;
    }

    /**
     * Return the number of affected rows in the result of the query
     *
     * @date 20101103
     * @version   1.0
     * @author    Jason
     * @return integer, the number of affected rows
     * @see       includes/PHPDS_db_connector#affectedRows()
     */
    public function affectedRows()
    {
        return $this->affectedRows;
    }

    /**
     * This method returns the last MySQL error as a string if there is any. It will also
     * return the actual erroneous SQL statement if the display_sql_on_error property is
     * set to true. This is very helpful when debugging an SQL related problem.
     *
     * @param string $query The actual query string.
     * @return string
     * @version 1.0.1
     * @date 20100329 prevent an exception if display_sql_on_error is not set
     * @author  don schoeman
     */
    public function returnSqlError($query)
    {
        $error  = $this->link->errorInfo();
        $result = '[unknown error]';
        if (empty($this->displaySqlOnError) && !empty($error[0])) {
            $result = $error[0] . ": " . $error[2] . ' [' . $error[1] . '] <br />' . $query;
        }
        return $result;
    }

    /**
     * Debugging Instance.
     *
     * @param string
     * @return object
     */
    public function debugInstance($ignored = null)
    {
        return parent::debugInstance('db');
    }

    /**
     * Simply returns last inserted id from database.
     *
     * @date 20100610 (greg) (v1.0.1) added $this->link
     * @version 1.0.1
     * @author  jason
     * @return int
     */
    public function lastId()
    {
        return $this->link->lastInsertId();
    }

    /**
     * Will return a single row as a string depending on what column was selected.
     *
     * @date 17062010 (jason)
     * @param $row int
     * @return string
     * @throws PHPDS_exception
     */
    public function rowResults($row = 0)
    {
        throw new PHPDS_exception('pdoConnector rowResults() function not implemented.');
    }

    /**
     * Start SQL transaction.
     */
    public function startTransaction()
    {
        $this->link->beginTransaction();
    }

    /**
     * Ends SQL transaction.
     *
     * @param boolean $commit
     */
    public function endTransaction($commit = true)
    {
        if ($commit) {
            $this->link->commit();
        } else {
            $this->link->rollBack();
        }
    }

    /**
     * magic method to get read-only access to various data
     *
     * @since   4.0
     * @version 1.0
     * @author  greg <greg@phpdevshell.org>
     *
     * @date 20130224 (v1.0) (greg) added
     *
     * @param string $name name for the parameter to get (ie. "DSN", "Charset", "Host", ...)
     * @return mixed
     */
    public function __get($name)
    {
        if (!empty($this->dbSettings[$name])) {
            return $this->dbSettings[$name];
        }
        return parent::__get($name);
    }
}
