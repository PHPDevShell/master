<?php
/**
 * Delphex Web Framework Core
 *
 * @link http://www.delphexonline.com
 * @copyright Copyright (C) 2012 Delphex Technologies CC, All rights reserved.
 * @author Don Schoeman
 *
 * Copyright notice: See readme/notice
 * By using DWF you agree to notice and license, if you dont agree to this notice/license you are not allowed to use DWF.
 *
 */

// A lightweight DB (PDO) Layer
class DBPDO implements DBi {
    /**
     * @var DSN string    A string containing the DSN (Data Source Name)
     */
    public $dbDSN = "";

    /**
     * @var dbHost string   A string containing the hostname
     */
    public $dbHost = "";

    /**
     * @var dbName string   A string containing the database name
     */
    public $dbName = "";

    /**
     * @var dbUsername string   A string containing the database username
     */
    public $dbUsername = "";

    /**
     * @var dbPassword string   A string containing the database password
     */
    public $dbPassword = "";

    /**
     * @var dbPrefix string A string containing the database prefix
     */
    public $dbPrefix = "";

    /**
     * @var dbPersistent string A string containing the database persistence setting
     */
    public $dbPersistent = false;

    /**
     * @var dbCharset string    A string containing the database connection character set.
     *                          Ignored by pdoConnector since the character set must be
     *                          specified in the DSN.
     */
    public $dbCharset = "";

    /**
     * @var php resource type,  the connection for the mysql connection (as returned by new PDO())
     */
    public $connection = null;

    /**
     * @var php resource type,  the result resource of a query (as returned by a PDO query)
     */
    public $result;

    /**
     * @var int The number of queries executed over the lifetime of this instance
     */
    public $queryCount = 0;

    /**
     * @var string The last querie that was executed
     */
    public $lastQuery = '';

    /**
     * Connect to the database server.
     *
     */
    public function connect() {
        if (empty($this->connection)) {
            // Set the PDO driver options
            $driver_options = null;
            if ($this->dbPersistent) $driver_options = array(PDO::ATTR_PERSISTENT => true);

            // Connect to the server and database
            $this->connection = new PDO($this->dbDSN, $this->dbUsername, $this->dbPassword, $driver_options);

            // Set the error reporting attribute so that SQL errors also generates exceptions
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param $sql string The SQL statement to prepare
     * @param $driver_options array Optional driver options
     * @return PDOStatement
     */
    public function prepare($sql, $driver_options = array()) {
        $sql = str_replace('/_db_/', $this->dbPrefix, $sql);
        $this->lastQuery = $sql;
        $this->result = (!empty($sql)) ? $this->connection->prepare($sql, $driver_options): false;
        return $this->result;
    }

    /**
     * Executes a prepared statement. See prepare()
     *
     * @param $params array The parameters
     * @param $statment object The SQL statement as returned by the prepare function.
     * @return resource The resulting resource (or false if no statement was supplied)
     */
    public function execute($params = null, $statement = null) {
        $result = false;
        if (!isset($statement)) $statement = $this->result;

        if (is_a($statement, 'PDOStatement')) {
            $this->queryCount+=1;
            if ($statement->execute($params)) $this->result = $statement;
        }

        return $this->result;
    }

    /**
     * Executes a query without preparing it first.
     *
     * @param $sql string The SQL statement to be executed
     * @param $params array The parameters
     * @return resource The resulting resource (or false if no statement was supplied)
     */
    public function query($sql, $params = null) {
        if (!empty($sql)) {
            // Replace the DB prefix.
            $sql = str_replace('/_db_/', $this->dbPrefix, $sql);

            $this->queryCount+=1;

            if (!is_null($params)) {
                // Replace the parameters with values
                foreach ($params as $key => $value) {
                    if (gettype($value) == 'string') {
                        $sql = str_replace(":".$key, "'".$this->escape($value)."'", $sql);
                    } else {
                        $sql = str_replace(":".$key, $this->escape($value), $sql);
                    }
                }
            }

            $this->lastQuery = $sql;
            $this->result = $this->connection->query($sql);
            return $this->result;
        } else {
            $this->result = false;
            return false;
        }
    }

    /**
     * Executes a query without preparing it first, then it fetches the row and returns it as an array or object,
     * depending on the specified mode.
     *
     * @param $sql string The SQL statement to be executed
     * @param $params array The parameters
     * @param $mode int, the return mode
     * @param $classname string The name of the class to instantiate, set the properties of and return.
     *   If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param $statement resource, the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function queryFetchRow($sql, $params = null, $mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null) {
        $result = false;
        $statement = $this->query($sql, $params);
        if ($statement) $result = $this->fetch($mode, $classname, $statement);
        return $result;
    }

    /**
     * Executes a query without preparing it first, then it fetches the row as an associative array
     * and returns the result.
     *
     * @param $sql string The SQL statement to be executed
     * @param $params array The parameters
     * @return mixed Associative array of result or False if failed.
     */
    public function queryFetchAssocRow($sql, $params = null) {
        $result = false;
        $statement = $this->query($sql, $params);
        if ($statement) $result = $this->fetchAssoc($statement);
        return $result;
    }

    /**
     * Return the next row as an array or object, depending on the specified mode
     *
     * @param $mode int, the return mode
     * @param $classname string The name of the class to instantiate, set the properties of and return.
     *   If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param $statement resource, the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function fetch($mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null) {
        if (!isset($statement)) $statement = $this->result;

        if ($statement) {
            switch ($mode)
            {
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
     * @param $statement resource, the previously returned statement
     * @return array The resulting row (or false is nothing is found)
     */
    public function fetchAssoc($statement = null) {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->fetch(PDO::FETCH_ASSOC) : false;
    }

    /**
     * Return the next row as an object
     *
     * @param $classname string The name of the class to instantiate, set the properties of and return.
     *   If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param $statement resource, the previously returned statement
     * @return object The resulting object
     */
    public function fetchObject($classname = "stdClass", $statement = null) {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->fetchObject($classname) : false;
    }

    /**
     * Return the number of rows
     *
     * @param $statement resource, the previously returned statement
     * @return integer The number of rows
     */
    public function numRows($statement = null) {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->rowCount() : 0;
    }

    /**
     * Return the number of affected rows
     *
     * @param $statement resource, the previously returned statement
     * @return integer, the number of affected rows
     */
    public function affectedRows($statement = null) {
        if (!isset($statement)) $statement = $this->result;
        return (is_a($statement, 'PDOStatement')) ? $statement->rowCount() : 0;
    }

    /**
     * Returns last inserted id from database.
     *
     * @return int
     */
    public function lastId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Start SQL transaction.
     *
     * @return bool
     */
    public function startTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Ends SQL transaction.
     *
     * @return bool
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Roll's back an SQL transaction.
     *
     * @return bool
     */
    public function rollBack() {
        return $this->connection->rollBack();
    }

    /**
     * Protect a string from SQL injection. This function should only be used when not preparing statements as PDO
     * will protect any parameters when preparing them. This function emulates the mysql_real_escape_string() function
     * which it is not available for PDO.
     *
     * @param $param string, the parameter to escape
     * @return string The escaped string
     */
    public function escape($param) {
        return strtr($param, array("\x00" => '\x00', "\n" => '\n', "\r" => '\r',
            '\\' => '\\\\', "'" => "\'", '"' => '\"', "\x1a" => '\x1a'));
    }
}