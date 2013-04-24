<?php

/**
 * Lightweight PGO wrapper.
 * @property PDO $connection[]
 */
class PHPDS_pdo extends PHPDS_dependant implements PHPDS_dbInterface
{
    /**
     * Property of default or active server to do queries from.
     * @var string $server
     */
    public $server = 'master';
    /**
     * Object of multiple database instances.
     * @var object $connection
     */
    public $connection;
    /**
     * Property of default or active server to do queries from.
     * @var string $instance
     */
    public $prefix;
    /**
     * Result resource of a query (as returned by a PDO query)
     * @var resource $result
     */
    public $result;
    /**
     * The number of queries executed over the lifetime of this instance
     * @var int $queryCount
     */
    public $queryCount = 0;
    /**
     * The last query that was executed
     * @var string $lastQuery
     */
    public $lastQuery = '';
    /**
     * This handy switch allows you to turn off automatic transactions, this is useful for instance
     * where you want your slave db to only read where not transactions is required.
     * Transactions will start at the beginning, commit at the end, or rollback on any exception or critical error.
     * @var array
     */
    public $autoTransact = array();

    /**
     * Connect to the database server.
     */
    public function connect()
    {
        try {
            if (empty($this->connection[$this->server])) {
                if (empty($this->configuration['database'][$this->server]))
                    throw new PHPDS_exception('No database configuration found for server : ' . $this->server);

                $cfg          = (object)$this->configuration['database'][$this->server];
                $this->prefix = $cfg->prefix;

                // Connect to the server and database
                $this->connection[$this->server] = new PDO($cfg->dsn, $cfg->username, $cfg->password, $cfg->options);

                // Set the error reporting attribute so that SQL errors also generates exceptions
                $this->connection[$this->server]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                if (!empty($cfg->autotransact)) {
                    $this->autoTransact[$this->server] = true;
                } else {
                    $this->autoTransact[$this->server] = false;
                }

                // Start transaction if required by settings.
                $this->startTransaction();
            }
        } catch (PDOException $e) {
            throw new PHPDS_databaseException('Cannot connect to database', 0, $e);
        }
    }

    /**
     * Gives the ability to create an instance of another server configuration, as many can be created as needed.
     * Config must exist for a particular name or an exception will be throw.
     *
     * @param string $server The name of the database configuration a connection or instance should be created.
     * @return $this
     */
    public function in($server)
    {
        $this->server = $server;
        $this->connect();
        return $this;
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $sql            The SQL statement to prepare
     * @param array  $driver_options Optional driver options
     * @return PDOStatement
     */
    public function prepare($sql, $driver_options = array())
    {
        if ($this->prefix !== '_db_') $sql = str_replace('/_db_/', $this->prefix, $sql);
        $this->lastQuery = $sql;
        $this->result    = (!empty($sql)) ? $this->connection[$this->server]->prepare($sql, $driver_options) : false;
        return $this->result;
    }

    /**
     * Executes a prepared statement. See prepare()
     *
     * @param array  $params    The parameters
     * @param object $statement The SQL statement as returned by the prepare function.
     * @return mixed resource The resulting resource (or false if no statement was supplied)
     */
    public function execute($params = null, $statement = null)
    {
        if (!isset($statement)) $statement = $this->result;

        if (is_a($statement, 'PDOStatement')) {
            $this->queryCount += 1;
            if ($statement->execute($params)) $this->result = $statement;
        }

        return $this->result;
    }

    /**
     * Executes a query without preparing it first.
     *
     * @param $sql    string The SQL statement to be executed
     * @param $params array The parameters
     * @return resource The resulting resource (or false if no statement was supplied)
     * @throws PHPDS_queryException
     */
    public function query($sql, $params = null)
    {
        try {
            if (!empty($sql)) {
                // Replace the DB prefix.
                if ($this->prefix !== '_db_') $sql = str_replace('/_db_/', $this->prefix, $sql);

                $this->queryCount += 1;

                if (!is_null($params)) {
                    // Replace the parameters with values
                    foreach ($params as $key => $value) {
                        if (gettype($value) == 'string') {
                            $sql = str_replace(":" . $key, "'" . $this->escape($value) . "'", $sql);
                        } else if (gettype($value) == 'NULL') {
                            $sql = str_replace(":" . $key, '\'\'', $sql);
                        } else {
                            $sql = str_replace(":" . $key, $this->escape($value), $sql);
                        }
                    }
                }

                $this->lastQuery = $sql;
                $this->result    = $this->connection[$this->server]->query($sql);
                $this->log('Database Query : ' . $sql);
                return $this->result;
            } else {
                $this->result = false;
                return false;
            }
        } catch (PDOException $e) {
            throw new PHPDS_queryException($sql, 0, $e);
        }
    }

    /**
     * Executes a query and returns the affected rows count.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $params The parameters
     * @return bool|int
     */
    public function queryAffects($sql, $params = null)
    {
        $result    = false;
        $statement = $this->query($sql, $params);
        if ($statement) $result = $this->affectedRows($statement);
        return $result;
    }

    /**
     * Executes a query and returns the last created id.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $params The parameters
     * @return bool|int|string
     */
    public function queryReturnId($sql, $params = null)
    {
        $result    = false;
        $statement = $this->query($sql, $params);
        if ($statement) $result = $this->lastId($statement);
        return $result;
    }

    /**
     * Can both build and execute a query or just build. The build is adding the AND/OR condition in between conditions.
     * After building it, it will pass it to be queried as a full sql string.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $array  The array containing the different conditions to be joined with AND/Or.
     * @param array  $params (Optional parameters)
     * @param string $join   The string that will be used to join the array example = :example AND foo = :foo AND bar...
     * @param string $where  Adds a string WHERE on how the sql should be joined with rest of query.
     * @return resource|string Can return the statement resource or complete SQL string.
     */
    public function queryBuild($sql, $array, $params = null, $join = 'AND', $where = 'WHERE')
    {
        $array = array_filter($array, 'strlen');
        $join  = join(" $join ", $array);
        $sql .= ($join) ? PHP_EOL . " $where " . PHP_EOL . $join : PHP_EOL;

        return ($params) ? $this->query($sql, $params) : $sql;
    }

    /**
     * Executes a query without preparing it first, then it fetches the row and returns it as an array or object,
     * depending on the specified mode.
     *
     * @param string   $sql            The SQL statement to be executed
     * @param array    $params         The parameters
     * @param int      $mode           , the return mode
     * @param string   $classname      The name of the class to instantiate, set the properties of and return.
     *                                 If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param resource $statement      , the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function queryFetchRow($sql, $params = null, $mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null)
    {
        $result = false;
        if (!isset($statement)) $statement = $this->query($sql, $params);
        if ($statement) $result = $this->fetch($mode, $classname, $statement);
        return $result;
    }

    /**
     * Executes a query without preparing it first, then it fetches all rows and returns it as an array or object,
     * depending on the specified mode.
     *
     * @param string   $sql            The SQL statement to be executed
     * @param array    $params         The parameters
     * @param int      $mode           , the return mode
     * @param string   $classname      The name of the class to instantiate, set the properties of and return.
     *                                 If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param resource $statement      , the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function queryFetchRows($sql, $params = null, $mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null)
    {
        $results = false;
        if (!isset($statement)) $statement = $this->query($sql, $params);
        if ($statement) {
            while ($result = $this->fetch($mode, $classname, $statement)) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Executes a query without preparing it first, then it fetches the row as an associative array
     * and returns the result.
     *
     * @param string $sql     The SQL statement to be executed
     * @param array  $params  The parameters
     * @return mixed Associative array of result or False if failed.
     */
    public function queryFetchAssocRow($sql, $params = null)
    {
        $result    = false;
        $statement = $this->query($sql, $params);
        if ($statement) $result = $this->fetchAssoc($statement);
        return $result;
    }

    /**
     * Executes a query without preparing it first, then it fetches all rows as an associative array
     * and returns a complete result set.
     *
     * @param string $sql     The SQL statement to be executed
     * @param array  $params  The parameters
     * @return mixed Associative array of result or False if failed.
     */
    public function queryFetchAssocRows($sql, $params = null)
    {
        $results   = array();
        $statement = $this->query($sql, $params);
        if ($statement) {
            while ($result = $this->fetchAssoc($statement)) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Alias for queryFetchAssocRows
     *
     * @see PHPDS_dbInterface::queryFetchAssocRows
     */
    public function queryFAR($sql, $params = null)
    {
        return $this->queryFetchAssocRows($sql, $params);
    }

    /**
     * Returns a single string result from a single column.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $params The parameters
     * @return string
     */
    public function querySingle($sql, $params = null)
    {
        $result = $this->queryFetchAssocRow($sql, $params);
        return ($result) ? reset($result) : null;
    }

    /**
     * Return the next row as an array or object, depending on the specified mode
     *
     * @param int      $mode           , the return mode
     * @param string   $classname      The name of the class to instantiate, set the properties of and return.
     *                                 If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param resource $statement      , the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function fetch($mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null)
    {
        if (!isset($statement)) $statement = $this->result;

        if ($statement) {
            switch ($mode) {
                case self::MODE_ASSOC_KEY:
                    return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_ASSOC) : false;
                    break;

                case self::MODE_ASSOC:
                    return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_ASSOC) : false;
                    break;

                case self::MODE_NUM:
                    return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_NUM) : false;
                    break;

                case self::MODE_BOTH:
                    return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_BOTH) : false;
                    break;

                case self::MODE_OBJECT:
                    return (is_a($statement, 'PDOStatement')) ? $statement->fetchObject($classname) : false;
                    break;

                default:
                    return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_ASSOC) : false;
            }
        } else return false;
    }

    /**
     * Return the next row as an associative array.
     *
     * @param resource $statement, the previously returned statement
     * @return array The resulting row (or false is nothing is found)
     */
    public function fetchAssoc($statement = null)
    {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_ASSOC) : false;
    }

    /**
     * Return the next row as an object
     *
     * @param string $classname  The name of the class to instantiate, set the properties of and return.
     *                           If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param        $statement  resource, the previously returned statement
     * @return object The resulting object
     */
    public function fetchObject($classname = "stdClass", $statement = null)
    {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->fetchObject($classname) : false;
    }

    /**
     * Return the number of rows
     *
     * @param resource $statement, the previously returned statement
     * @return integer The number of rows
     */
    public function numRows($statement = null)
    {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->rowCount() : 0;
    }

    /**
     * Return the number of affected rows
     *
     * @param resource $statement, the previously returned statement
     * @return integer, the number of affected rows
     */
    public function affectedRows($statement = null)
    {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->rowCount() : 0;
    }

    /**
     * Returns last inserted id from database.
     *
     * @return int
     */
    public function lastId()
    {
        return $this->connection[$this->server]->lastInsertId();
    }

    /**
     * Start SQL transaction.
     *
     * @return bool
     */
    public function startTransaction()
    {
        if ($this->autoTransact[$this->server] && !$this->connection[$this->server]->inTransaction()) {
            return $this->connection[$this->server]->beginTransaction();
        } else {
            return false;
        }
    }

    /**
     * Start SQL transaction.
     *
     * @return bool
     */
    public function endTransaction()
    {
        if (!empty($this->autoTransact)) {
            foreach ($this->autoTransact as $server => $autotransact) {
                if ($autotransact && $this->connection[$server]->inTransaction()) {
                    $this->connection[$server]->commit();
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Ends SQL transaction.
     *
     * @return bool
     */
    public function commit()
    {
        $this->endTransaction();
    }

    /**
     * Roll's back an SQL transaction.
     *
     * @return bool
     */
    public function rollBack()
    {
        if (!empty($this->autoTransact)) {
            foreach ($this->autoTransact as $server => $autotransact) {
                if ($autotransact && $this->connection[$server]->inTransaction()) {
                    $this->connection[$server]->rollBack();
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the database handle.
     *
     * @return mixed
     */
    public function errorCode()
    {
        if (! empty($this->result))
            return $this->result->errorCode();
        else
            return false;
    }

    /**
     * Fetch extended error information associated with the last operation on the database handle.
     *
     * @return mixed
     */
    public function errorInfo()
    {
        if (!empty($this->result))
            return $this->result->errorInfo();
        else
            return false;
    }

    /**
     * Protect a string from SQL injection. This function should only be used when not preparing statements as PDO
     * will protect any parameters when preparing them. This function emulates the mysql_real_escape_string() function
     * which it is not available for PDO.
     *
     * @param string $param, the parameter to escape
     * @return string The escaped string
     */
    public function escape($param)
    {
        return strtr($param, array("\x00" => '\x00', "\n" => '\n', "\r" => '\r',
                                   '\\'   => '\\\\', "'" => "\'", '"' => '\"', "\x1a" => '\x1a'));
    }
}