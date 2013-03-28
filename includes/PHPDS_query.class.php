<?php

class PHPDS_query extends PHPDS_dependant
{
    /**
     * The explicit SQL query
     *
     * This value, if present, is used when not override by an array in the field named "fields"
     * It can be accessed from the outside world thought the sql() method.
     * @see $fields
     * @see sql()
     * @var string
     */
    protected $sql;

    /**
     * The name of the field to use as a key.
     * Use '__auto__' if you want the primary key to dictate the key of the array rows.
     * When this field is left empty the array will be build normally.
     *
     * @var string
     */
    protected $keyField = '';

    /**
     * Make a field the point of interest
     *
     * This field changes the way some arrays are returned:
     * - if $focus contains a field name, a row will be the value of this field (scalar) instead of an array of all values in the row
     * - if the row doesn't contain a field an empty value is used for the row
     * @var string
     */
    protected $focus = ''; // can be empty for 'no' or any other value for field name

    /**
     * strips any row with no content
     * @var boolean
     */
    protected $noEmptyRow = false;

    /**
     * Guidelines to typecast/forcecast the result data
     *
     * @var string | array of strings
     */
    protected $typecast;

    /**
     * The first line of the result is returned instead of a one-line array
     *
     * @var boolean
     */
    protected $singleRow = false;

    /**
     * Automatically escape bad chars for all in-parameters
     * @var boolean
     */
    protected $autoProtect = false;

    /**
     * If you want your non-numeric values to be quoted, set the quote character here
     * @var string
     */
    protected $autoQuote = null;

    /**
     * Instead of the query result, returns the last_insert_id()
     * @var boolean
     */
    protected $returnId = false;

    /**
     * Return one value from the asked field of the asked line
     * @var boolean
     */
    protected $singleValue = false;

    /**
     * A link between the query and the actual database server.
     *
     * Set this to the connector class name if you want something else than the default one
     *
     * @var string|iPHPDS_dbConnector, the connector used to carry the query (either name or instance)
     */
    protected $connector = null;

    /**
     * The list of fields to study
     *
     * If present, this associative array contains the fields which will be present in the SELECT ... clause.
     * This will override the $sql field; however if you use the sql('something') method after prebuild() the new query string will override the fields
     * @see $sql
     * @var array (optional)
     */
    protected $fields;

    /**
     * The WHERE clause
     *
     * A default WHERE clause; note you can use the addWhere() method to concatenate after this value
     * @var string (optional)
     */
    protected $where;
    protected $groupby = '';
    protected $orderby = '';
    protected $limit = '';

    /**
     * In some specific case (namely debugging) this will contain a cached version of the results
     * AVOID playing with that
     * @date 20110218 (greg) added
     * @var array
     */
    protected $cachedResult;

    /**
     * number of rows counted from fetching the result - only valid after the whole result has been fetched
     * @var int
     */
    protected $rowCount = -1;

    /**
     * number of rows affected by the query - validity depends on the DB used
     * @var int
     */
    protected $affectedRows = -1;

    /**
     * Constructor
     */
    public function construct()
    {
        if (empty($this->connector)) {
            $this->connector($this->db->connector()); // use default connector
        } else {
            $this->connector($this->connector);
        }
        // Backwards compatible variables.
        if (isset($this->no_empty_row))
            $this->noEmptyRow = $this->no_empty_row;
        if (isset($this->single_row))
            $this->singleRow = $this->single_row;
        if (isset($this->auto_protect))
            $this->autoProtect = $this->auto_protect;
        if (isset($this->return_id))
            $this->returnId = $this->return_id;
        if (isset($this->single_value))
            $this->singleValue = $this->single_value;
    }

    /**
     * Get and/or set the actual connector instance
     *
     * Note: can only be set if it was not set before
     *
     * @param string $connector
     * @return iPHPDS_dbConnector
     */
    public function connector($connector = null)
    {
        if (!is_a($this->connector, 'iPHPDS_dbConnector')) {
            if (is_string($connector)) {
                $connector = $this->db->connector($connector);
            }
            if (is_a($connector, 'iPHPDS_dbConnector')) {
                $this->connector = $connector;
            }
        }
        return $this->connector;
    }

    /**
     * The usual process of a query: check the parameters, send the query to the server, check the results
     *
     * Return the results as an array (for SELECT queries), true for other successfull queries, false on failure
     *
     * @param mixed $parameters
     * @return array|boolean
     * @throws PHPDS_databaseException
     */
    public function invoke($parameters = null)
    {
        try {
            if ($this->checkParameters($parameters)) {
                $res = $this->query($parameters);
                // Fix to prevent invoke from returning false if INSERT REPLACE DELETE etc... is executed on success.
                if ($res === true && empty($this->returnId))
                    return $this->connector->affectedRows();
                if (!empty($res)) {
                    $results = $this->getResults();
                    if ($this->checkResults($results))
                        return $results;
                } else return $res;
            }
            return false;
        } catch (Exception $e) {
            $msg = '<p>The faulty query source sql is:<br /><pre class="ui-state-highlight ui-corner-all">' . $this->sql() . '</pre><br />';
            if (!empty($parameters)) $msg .= '<tt>' . PU_dumpArray($parameters, _('The query parameters were:')) . '</tt>';
            throw new PHPDS_databaseException($msg, 0, $e);
        }
    }

    /**
     * Build and send the query to the database
     *
     * @param mixed $parameters (optional)array, the parameters to inject into the query
     * @return mixed
     */
    public function query($parameters = null)
    {
        $sql = $this->build($parameters);
        return $this->querySQL($sql);
    }

    /**
     * Direclty send the query to the database
     *
     * @param string $sql the sql request
     * @return mixed
     * @throws PHPDS_queryException
     */
    public function querySQL($sql)
    {
        try {
            $this->rowCount     = -1;
            $result             = $this->connector->query($sql);
            $this->affectedRows = $this->connector->numrows();

            $this->queryDebug($sql);
            return $result;
        } catch (Exception $e) {
            throw new PHPDS_queryException($sql, 0, $e);
        }
    }

    /**
     * Firephp-specific debug display of the query
     *
     * @param string $sql
     */
    public function queryDebug($sql)
    {
        $debug   = $this->debugInstance();
        $firephp = $this->errorHandler->getFirePHP();
        if ($debug->enable() && $firephp && !headers_sent()) {

            $flags =
                ($this->singleRow ? ' singleRow' : '') . ($this->singleValue ? ' singleValue' : '') . ($this->noEmptyRow ? ' noEmptyRow ' : '')
                    . (empty($this->focus) ? '' : ' focus=' . $this->focus) . (empty($this->keyField) ? '' : ' keyField=' . $this->keyField) . (empty($this->typeCast) ? '' : ' typeCast=' . $this->typeCast);

            $table   = array();
            $table[] = array('', '');
            $table[] = array('SQL', $sql);
            $table[] = array('count', $this->affectedRows . ' rows');
            $table[] = array('flags', $flags);

            $firephp->table('Query: ' . get_class($this), $table);
        }
    }

    /**
     * Build a query combination of columns and rows specifically designed to write rows of data to the database.
     *
     * @param array $parameters Holds columns in the order they need to be written.
     * @return array|bool
     */
    public function rows($parameters = null)
    {
        $r     = '';
        $build = '';
        if (empty($parameters)) return false;
        foreach ($parameters as $col) {
            foreach ($col as $row) {
                $r .= "'" . $row . "',";
            }
            $r = rtrim($r, ',');
            $build .= "($r),";
            $r = '';
        }
        $parameters = rtrim($build, ',');
        if (!empty($parameters)) {
            return $parameters;
        } else {
            return false;
        }
    }

    /**
     * Get/set actual sql string.
     *
     * You may want to override this to alter the sql string as whole, and/or build it from various sources.
     * Note this is only the first part of the query (SELECT ... FROM ...), NOT including WHERE, GROUP BY, ORDER BY, LIMIT
     *
     * @param string $sql (optional) if given, stored into the object's sql string
     * @return string the sql text
     */
    public function sql($sql = null)
    {
        if (!empty($sql)) $this->sql = $sql;
        return $this->sql;
    }

    /**
     * Build the query based on the private sql and the parameters
     *
     * @param array $parameters (optional)array, the parameters to inject into the query
     * @return string the sql query string
     * @throws PHPDS_databaseException
     */
    public function build($parameters = null)
    {
        $sql = '';

        try {
            $this->preBuild();
            $sql = $this->sql() . $this->extraBuild($parameters);

            if (!empty($parameters)) {
                if (is_scalar($parameters)) {
                    $parameters = array($parameters);
                }
                if (is_array($parameters)) {
                    if ($this->autoProtect) {
                        $parameters = $this->protectArray($parameters, $this->autoQuote);
                    }
                    $sql = PU_sprintfn($sql, $parameters);
                }
                //TODO is parameters is neither scalar nor array what should we do?
            }
        } catch (Exception $e) {
            throw new PHPDS_databaseException('Error building sql for <tt>' . get_class() . '</tt>', 0, $e);
        }
        return $sql;
    }

    /**
     * Construct the extra part of the query (WHERE ... GROUP BY ... ORDER BY...)
     * Does'nt change $this->sql
     *
     * @param array $parameters
     * @return string (sql)
     */
    public function extraBuild($parameters = null)
    {
        $extra_sql = '';

        if (!empty($this->where)) $extra_sql .= ' WHERE ' . $this->where . ' ';
        if (!empty($this->groupby)) $extra_sql .= ' GROUP BY ' . $this->groupby . ' ';
        if (!empty($this->orderby)) $extra_sql .= ' ORDER BY ' . $this->orderby . ' ';
        if (!empty($this->limit)) $extra_sql .= ' LIMIT ' . $this->limit . ' ';

        return $extra_sql;
    }

    /**
     * If the fields list has been set, construct the SELECT statement (or else do nothing)
     */
    public function preBuild()
    {
        $fields = $this->fields;
        if (!empty($fields)) {
            $sql = '';
            $key = $this->getKey();
            if ($key && !in_array($key, $fields)) $fields[$key] = true;
            foreach (array_keys($fields) as $key) if (!is_numeric($key)) $sql .= $key . ', ';
            $sql = 'SELECT ' . rtrim($sql, ', ');

            if (!empty($this->tables)) $sql .= ' FROM ' . $this->tables;
            $this->sql = $sql;
        }
    }

    /**
     * Add a subclause to the main WHERE clause of the query
     *
     * @param string $sql
     * @param string $mode
     * @return $this
     */
    public function addWhere($sql, $mode = 'AND')
    {
        if (empty($this->where)) $this->where = '1';
        $this->where .= " $mode $sql ";
        return $this;
    }

    /**
     * Protect a array of strings from possible hacker (i.e. escape possible harmfull chars)
     *
     * @param $a     array, the strings to protect
     * @param $quote string, the quotes to add to each non-numerical scalar value
     * @return array the same string but safe
     */
    public function protectArray(array $a, $quote = '')
    {
        return $this->db->protectArray($a, $quote);
    }

    /**
     * Protect a strings from possible hacker (i.e. escape possible harmfull chars)
     *
     * @param string $string the strings to protect
     * @return string the same string but safe
     */
    public function protectString($string)
    {
        $clean = $this->connector->protect($string);
        return $clean;
    }

    /**
     * Try to figure out which is the key field.
     *
     * @TODO: we assume first column is a key field, this is wrong!!!
     *
     * @param array $row, a sample row to study
     * @return string (or null), the key field name
     */
    public function getKey($row = null)
    {
        $key = $this->keyField;
        if (is_array($row)) {
            if ('__auto__' == $key) {
                $keys = array_keys($row);
                $key  = array_shift($keys);
            }
            return ($key && !empty($row[$key])) ? $row[$key] : null;
        } else {
            return '__auto__' != $key ? $key : null;
        }
    }

    /**
     * Returns all lines from the result as a big array of arrays
     *
     * @return array all the lines as arrays
     */
    public function asWhole()
    {
        $result = array();
        $count  = 0;

        while ($row = $this->asLine()) {
            $count++;
            $key = $this->getKey($row);
            if (!empty($this->focus)) {
                $row = (isset($row[$this->focus])) ? $row[$this->focus] : null;
            }
            if ($row || !empty($this->noEmptyRow)) {
                if ($key) {
                    $result[$key] = $row;
                } else {
                    $result[] = $row;
                }
            }
        }
        $this->rowCount = $count;
        return $result;
    }

    /**
     * Converts values to its correct type.
     *
     * @param  mixed $values
     * @param  mixed $key
     * @return mixed
     */
    public function typeCast($values, $key = null)
    {
        if (!empty($this->typecast)) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $values[$key] = $this->typeCast($value, $key);
                }
            } else {
                $type = is_array($this->typecast) ? (!empty($this->typecast[$key]) ? $this->typecast[$key] : null) : $this->typecast;
                switch ($type) {
                    case 'string':
                        $values = (string)$values;
                        break;
                    case 'int':
                    case 'integer':
                        $values = (int)$values;
                        break;
                    case 'bool':
                    case 'boolean':
                        $values = (bool)$values;
                        break;
                    case 'float':
                    case 'double':
                        $values = (float)$values;
                        break;
                    // default is to NOT change the $value
                }
            }
        }
        return $values;
    }

    /**
     * Deal with all special cases (i.e flags) regarding how results should be returned
     *
     * The special cases handled are these (in order of precedence):
     * - returnId (instead of the actual result, lastId is returned)
     * - singleValue (only the first value is returned as a scalar)
     * - singleRow (the first row is returned as a an one-dimension array)
     *
     * Cell-specific handling is done elsewhere
     *
     * In the absence of special case, the whole result is returned as an array of arrays (by calling as_whole() )
     *
     * @return array|bool usually an array, although can be false, or int for an ID
     */
    public function getResults()
    {
        if (!empty($this->returnId)) {
            return $this->connector->lastId();
        }

        if (!empty($this->singleValue)) {
            return $this->asOne();
        }

        if (!empty($this->singleRow)) {
            return $this->asLine();
        }
        return $this->asWhole();
    }

    /**
     * Returns a single field from every line, resulting in an array of values (ie some kind of "vertical" fetching)
     *
     * Note: this is different from as_whole, since only ONE value is present in each line
     *
     * @param $field string, the field to extract on each line
     * @return array all the values
     */
    public function asArray($field)
    {
        $a     = array();
        $count = 0;

        while ($row = $this->connector->fetchAssoc()) {
            $count++;
            if (!empty($row[$field])) {
                $value = $row[$field];
                $key   = $this->getKey($row);

                if (!empty($key) && !empty($row[$key])) {
                    $a[$row[$key]] = $value;
                } else {
                    $a[] = $value;
                }
            }
        }

        $this->rowCount = $count;
        return $a;
    }

    /**
     * Returns the asked line as an array
     *
     * You can either ask for the next line (no parameter) or given a row number in the result.
     *
     * Note: the row number is based on the result, it may not be same as the row number in the complete table
     *
     * @param integer $row_number (optional) - NOT USED ANYMORE
     * @return array|null the line or null if the resultset is empty
     */
    public function asLine($row_number = null)
    {
        if ($this->count() != 0) {
            $row = $this->connector->fetchAssoc();
            return $this->typeCast($row);
        }
        return null;
    }

    /**
     * Return one value from the asked field of the asked line
     *
     * @param integer $row_number (optional)
     * @param string  $field      field name (optional)
     * @return string|null
     */
    public function asOne($row_number = null, $field = null)
    {
        if ($this->count() != 0) {
            $row = $this->asLine($row_number);
            if (!is_array($row)) {
                return null;
            }
            if (!empty($field)) {
                $field = $this->focus;
            }
            if (!empty($field)) {
                return (isset($row[$field]) ? $row[$field] : null);
            } else {
                return array_shift($row);
            }
        }
        return null;
    }

    /**
     * Return the number of lines in a result
     *
     * @return integer the number of rows, or -1 if it cannot be evaluated
     */
    public function count()
    {
        return $this->rowCount;
    }

    /**
     * Total number of affected rows.
     * @return int
     */
    public function total()
    {
        return $this->affectedRows;
    }

    /**
     * Limits query.
     *
     * @param int $limit
     */
    public function limit($limit)
    {
        // TODO: check parameter
        $this->limit = $limit;
    }

    /* THESE METHODS ARE MEANT TO BE OVERRIDE */

    /**
     * Allows daughter classes to check the parameters array before the query is sent
     *
     * @param $parameters array, the unprotected parameters
     * @return boolean true is it's ok to sent, false otherwise
     */
    public function checkParameters(&$parameters = null)
    {
        return true;
    }

    /**
     * Allows daughter classes to check the results array before it's sent back
     *
     * @param $results array the unprotected results
     * @return boolean true is it's ok to sent, false otherwise
     */
    public function checkResults(&$results = null)
    {
        return true;
    }


    /**
     * @param null $domain
     * @return PHPDS_debug
     */
    public function debugInstance($domain = null)
    {
        return parent::debugInstance(empty($domain) ? 'QUERY%' . get_class($this) : $domain);
    }

    /**
     * Returns the desired charset for the db link
     * @return string
     */
    public function charset()
    {
        return $this->connector()->Charset;
    }
}





