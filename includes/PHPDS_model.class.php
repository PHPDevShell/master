<?php

class PHPDS_model extends PHPDS_dependant
{
    /**
     * Contains the active plugin instance object.
     * @var object
     */
    public $instance;

    /**
     * Checks if main class is extended, if not, disable master set property.
     * @var bool
     */
    public $extends = false;

    const SQL_SELECT    = 'SELECT';
    const SQL_WHERE     = 'WHERE';
    const SQL_FROM      = 'FROM';
    const SQL_UPDATE    = 'UPDATE';
    const SQL_SET       = 'SET';
    const SQL_INSERT    = 'INSERT INTO';
    const SQL_DUPLICATE = 'ON DUPLICATE KEY UPDATE';
    const SQL_VALUES    = 'VALUES';
    const SQL_DELETE    = 'DELETE FROM';

    /**
     * Executes a simple SQL SELECT statement. The SQL statement is build using the provided table name and field names.
     *
     * @param $fields       array Assoc array of column names for which values should be returned.
     *                      If a single value is given with the key, the value will be returned as a string.
     *                      First field will be used as the focused field and should contain a value.
     * @param $table_name   string The name of the table against which to run the SELECT query.
     *
     * @return array|string Either array of values if so requested or single value.
     */
    public function select($fields, $table_name)
    {
        $key_field = key($fields);
        $key       = array_shift($fields);
        $count     = count($fields);

        $sql = self::SQL_SELECT . PHP_EOL . '%1$s'      . PHP_EOL .
               self::SQL_FROM   . PHP_EOL . $table_name . PHP_EOL .
               self::SQL_WHERE  . PHP_EOL . "$key_field = :$key_field";

        if ($count > 1) {
            array_unshift($fields, $key_field);
            $sql = sprintf($sql, join(', ', $fields));
            return $this->db->queryFetchAssocRow($sql, array($key_field => $key));
        } else {
            $sql = sprintf($sql, array_shift($fields));
            return $this->db->querySingle($sql, array($key_field => $key));
        }
    }

    /**
     * Executes a simple SQL UPDATE statement.
     *
     * @param $fields      array Associative array of field names and their values that should be used in the
     *                     UPDATE query.
     * @param $table_name  string The name of the table against which to run the UPDATE query.
     *
     * @return int         Affected rows.
     */
    public function update($fields, $table_name)
    {
        $key_field = key($fields);
        $pairs     = array();

        foreach (array_keys($fields) as $key) {
            $pairs[] = "$key = :$key";
        }

        $columns = join(', ', $pairs);

        $sql = self::SQL_UPDATE . PHP_EOL . $table_name  . PHP_EOL .
               self::SQL_SET    . PHP_EOL . $columns     . PHP_EOL .
               self::SQL_WHERE  . PHP_EOL . "$key_field = :$key_field";

        return $this->db->queryAffects($sql, $fields);
    }

    /**
     * Executes a simple SQL INSERT statement.
     *
     * @param $fields      array Associative array of field names and their values that should be used in the
     *                     INSERT query.
     * @param $table_name  string The name of the table against which to run the INSERT query.
     *
     * @return int         Last inserted id.
     */
    public function insert($fields, $table_name)
    {
        $columns   = array();
        $values    = array();

        foreach (array_keys($fields) as $key) {
            $columns[] = $key;
            $values[]  = ":$key";
        }

        $columns = join(', ', $columns);
        $values  = join(', ', $values);

        $sql = self::SQL_INSERT . PHP_EOL . $table_name . PHP_EOL . "($columns)" . PHP_EOL .
               self::SQL_VALUES . PHP_EOL . "($values)";

        return $this->db->queryReturnId($sql, $fields);
    }

    /**
     * Executes a simple SQL INSERT query but UPDATES on DUPLICATE statement.
     *
     * @param $fields      array Associative array of field names and their values that should be used in the
     *                     INSERT query.
     * @param $table_name  string The name of the table against which to run the INSERT query.
     *
     * @return int         Last inserted id.
     */
    public function upsert($fields, $table_name)
    {
        $pairs     = array();
        $columns   = array();
        $values    = array();

        foreach (array_keys($fields) as $key) {
            $pairs[]   = "$key = :$key";
            $columns[] = $key;
            $values[]  = ":$key";
        }

        $pairs   = join(', ', $pairs);
        $columns = join(', ', $columns);
        $values  = join(', ', $values);

        $sql = self::SQL_INSERT    . PHP_EOL . $table_name . PHP_EOL . "($columns)" . PHP_EOL .
               self::SQL_VALUES    . PHP_EOL . "($values)" . PHP_EOL .
               self::SQL_DUPLICATE . PHP_EOL . $pairs;

        return $this->db->queryReturnId($sql, $fields);
    }

    /**
     * Executes a simple SQL DELETE statement.
     *
     * @param $field       array Associative array of field and its value that should be used in the
     *                     DELETE query.
     * @param $table_name  string The name of the table against which to run the INSERT query.
     *
     * @return int         Affected rows.
     */
    public function delete($field, $table_name)
    {
        $key_field = key($field);

        $sql = self::SQL_DELETE . PHP_EOL . $table_name . PHP_EOL .
               self::SQL_WHERE  . PHP_EOL . "$key_field =:$key_field";

        return $this->db->queryAffects($sql, $field);
    }
}