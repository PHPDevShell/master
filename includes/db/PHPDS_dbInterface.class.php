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

/**
 * Class PHPDS_dbInterface
 */
interface PHPDS_dbInterface {
    /**
     * Returns the resulting resource as an associative array (same as MODE_ASSOC).
     * Defined because the layer can also return a special key based array,
     * i.e. array([key1] => value, [key2] => value, etc..)
     */
    const MODE_ASSOC_KEY = 0;

    /**
     * Returns the resulting resource as an associative array.
     */
    const MODE_ASSOC = 1;

    /**
     * Returns the resulting resource as an enumerated array (keys are row numbers essentially).
     */
    const MODE_NUM = 2;

    /**
     * Returns the resulting resource array with both associative and an enumerated keys.
     */
    const MODE_BOTH = 3;

    /**
     * Returns the resulting resource as an object.
     */
    const MODE_OBJECT = 4;


    public function connect();
    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $sql            The SQL statement to prepare
     * @param array  $driver_options Optional driver options
     * @return PDOStatement
     */
    public function prepare($sql, $driver_options = null);
    /**
     * Executes a prepared statement. See prepare()
     *
     * @param array  $params    The parameters
     * @param object $statement The SQL statement as returned by the prepare function.
     * @return mixed resource The resulting resource (or false if no statement was supplied)
     */
    public function execute($params = null, $statement = null);
    /**
     * Executes a query without preparing it first.
     *
     * @param $sql    string The SQL statement to be executed
     * @param $params array The parameters
     * @return resource The resulting resource (or false if no statement was supplied)
     */
    public function query($sql, $params = null);
    /**
     * Executes a query and returns the affected rows count.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $params The parameters
     * @return bool|int
     */
    public function queryAffects($sql, $params = null);
    /**
     * Executes a query and returns the last created id.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $params The parameters
     * @return bool|int|string
     */
    public function queryReturnId($sql, $params = null);
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
    public function queryBuild($sql, $array, $params = null, $join = 'AND', $where='WHERE');
    /**
     * Executes a query without preparing it first, then it fetches the row and returns it as an array or object,
     * depending on the specified mode.
     *
     * @param string   $sql          The SQL statement to be executed
     * @param array    $params       The parameters
     * @param int      $mode         , the return mode
     * @param string   $classname    The name of the class to instantiate, set the properties of and return.
     *                               If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param resource $statement    , the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function queryFetchRow($sql, $params = null, $mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null);
    /**
     * Executes a query without preparing it first, then it fetches all rows and returns it as an array or object,
     * depending on the specified mode.
     *
     * @param string   $sql          The SQL statement to be executed
     * @param array    $params       The parameters
     * @param int      $mode         , the return mode
     * @param string   $classname    The name of the class to instantiate, set the properties of and return.
     *                               If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param resource $statement    , the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function queryFetchRows($sql, $params = null, $mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null);
    /**
     * Executes a query without preparing it first, then it fetches the row as an associative array
     * and returns the result.
     *
     * @param string $sql     The SQL statement to be executed
     * @param array  $params  The parameters
     * @return mixed Associative array of result or False if failed.
     */
    public function queryFetchAssocRow($sql, $params = null);
    /**
     * Executes a query without preparing it first, then it fetches all rows as an associative array
     * and returns a complete result set.
     *
     * @param string $sql     The SQL statement to be executed
     * @param array  $params  The parameters
     * @return mixed Associative array of result or False if failed.
     */
    public function queryFetchAssocRows($sql, $params = null);
    /**
     * Alias for queryFetchAssocRows
     *
     * @see PHPDS_dbInterface::queryFetchAssocRows
     */
    public function queryFAR($sql, $params = null);
    /**
     * Returns a single string result of a for from a single column.
     *
     * @param string $sql    The SQL statement to be executed
     * @param array  $params The parameters
     * @return string
     */
    public function querySingle($sql, $params = null);
    /**
     * Return the next row as an array or object, depending on the specified mode
     *
     * @param int      $mode         , the return mode
     * @param string   $classname    The name of the class to instantiate, set the properties of and return.
     *                               If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param resource $statement    , the previously returned statement
     * @return mixed The resulting row (or false is nothing is found)
     * @see DBi::MODE_ASSOC_KEY, DBi::MODE_ASSOC, DBi::MODE_NUM, DBi::MODE_BOTH, DBi::MODE_OBJECT
     */
    public function fetch($mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null);
    /**
     * Return the next row as an associative array.
     *
     * @param resource $statement, the previously returned statement
     * @return array The resulting row (or false is nothing is found)
     */
    public function fetchAssoc($statement = null);
    /**
     * Return the next row as an object
     *
     * @param string $classname  The name of the class to instantiate, set the properties of and return.
     *                           If not specified, a stdClass object is returned. (MODE_OBJECT)
     * @param        $statement  resource, the previously returned statement
     * @return object The resulting object
     */
    public function fetchObject($classname = "stdClass", $statement = null);
    /**
     * Return the number of rows
     *
     * @param resource $statement, the previously returned statement
     * @return integer The number of rows
     */
    public function numRows($statement = null);
    /**
     * Return the number of affected rows
     *
     * @param resource $statement, the previously returned statement
     * @return integer, the number of affected rows
     */
    public function affectedRows($statement = null);
    /**
     * Returns last inserted id from database.
     *
     * @return int
     */
    public function lastId();
    /**
     * Start SQL transaction.
     *
     * @return bool
     */
    public function startTransaction();
    /**
     * Start SQL transaction.
     *
     * @return bool
     */
    public function endTransaction();
    /**
     * Ends SQL transaction.
     *
     * @return bool
     */
    public function commit();
    /**
     * Roll's back an SQL transaction.
     *
     * @return bool
     */
    public function rollBack();
    /**
     * Protect a string from SQL injection. This function should only be used when not preparing statements as PDO
     * will protect any parameters when preparing them. This function emulates the mysql_real_escape_string() function
     * which it is not available for PDO.
     *
     * @param string $param, the parameter to escape
     * @return string The escaped string
     */
    public function escape($param);
}