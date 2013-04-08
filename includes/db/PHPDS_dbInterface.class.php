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

// Database abstraction for database layers
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
    public function prepare($sql, $driver_options = null);
    public function execute($statement, $params = null);
    public function query($sql, $params = null);
    public function queryFetchRow($sql, $params = null, $mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null);
    public function queryFetchAssocRow($sql, $params = null);
    public function fetch($mode = self::MODE_ASSOC_KEY, $classname = "stdClass", $statement = null);
    public function fetchAssoc($statement = null);
    public function fetchObject($classname = "stdClass", $statement = null);
    public function numRows($statement = null);
    public function affectedRows($statement = null);
    public function lastId();
    public function startTransaction();
    public function commit();
    public function rollBack();
    public function escape($param);
}