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

    public $fields           = array();
    public $defaults         = array();
    public $table_name       = '';
    public $primary_key      = '';

    const SQL_SELECT         = 'SELECT';
    const SQL_FROM           = 'FROM';
    const SQL_UPDATE         = 'UPDATE';
    const SQL_SET            = 'SET';
    const SQL_INSERT         = 'INSERT';
    const SQL_VALUES         = 'VALUES';
    const SQL_DELETE         = 'DELETE';

    /**
     * Executes a simple SQL SELECT statement. The SQL statement is build using the provided table name and field names.
     * The WHERE+AND+OR clause can be build from the params variable.
     *
     * @param $table_name   string The name of the table against which to run the SELECT query.
     * @param $fields       array Array of field names and their values that should be used in the SELECT query.
     * @param $params       array Array of string based field names that should be used in the WHERE clause.
     *                      The values is pulled from the field values.
     * @param $join         string The string that will be used to join the array
     *                      example = :example AND foo = :foo AND bar...
     * @param $where        string Adds a string WHERE on how the sql should be joined with rest of query.
     *
     * @return resource|string Can return the statement resource or complete SQL string.
     */
    public function select($table_name, $fields, $params = null, $join = 'AND', $where = 'WHERE')
    {
        $sql = self::SQL_SELECT . PHP_EOL . implode(', ', $fields) . PHP_EOL . self::SQL_FROM . PHP_EOL . $table_name;
        return $this->db->queryBuild($sql, $fields, $params, $join, $where);
    }

    /**
     * Executes a simple SQL UPDATE statement. The SQL statement is build using the provided table name and field names.
     * The WHERE clause is build from the params variable.
     *
     * @param $table_name        string The name of the table against which to run the UPDATE query.
     * @param $fields            array Associative array of field names and their values that should be used in the UPDATE query.
     * @param $primary_key       string Name of primary key.
     * @return bool|int          Affected rows.
     */
    public function update($table_name, $fields, $primary_key)
    {
        $sql = self::SQL_UPDATE . PHP_EOL . $table_name . PHP_EOL . self::SQL_SET . PHP_EOL;
        $update = '';

        foreach (array_keys($fields) as $key) {
            if ($key != $primary_key) {
                if (empty($update)) {
                    $update = $key . ' = :' . $key;
                } else {
                    $update .= ', ' . $key . ' = :' . $key;
                }
            }
        }
        $where = PHP_EOL . $primary_key . ' = :' . $primary_key . PHP_EOL;
        $sql   = $sql . $where;

        return $this->db->queryAffects($sql, $fields);
    }

    /**
     * Executes a simple SQL INSERT statement. The SQL statement is build using the provided table name and field names. This
     * function is usually used internally by the DBModel class itself. You should use the save() function to save new data instead.
     *
     * @param $table_name         string The name of the table against which to run the INSERT query.
     * @param $fields             array Associative array of field names and their values that should be used in the INSERT query.
     * @param $primary_key        string The primary key of the table. This is required to filter out the primary key before the INSERT
     *                            is performed. The function assumes that a unique key will automatically be incremented, hence
     *                            the reason for not adding it to the INSERT statement.
     */
    public function insert($table_name, $fields, $primary_key)
    {
        $names = "";
        foreach (array_keys($fields) as $key) {
            if ($key != $primary_key) {
                if (empty($names)) {
                    $names = $key;
                } else {
                    $names .= sprintf(', %1$s', $key);
                }
            }
        }

        $values = "";
        foreach (array_keys($fields) as $key) {
            if ($key != $primary_key) {
                if (empty($values)) {
                    $values = sprintf(':%1$s', $key);
                } else {
                    $values .= sprintf(', :%1$s', $key);
                }
            }
        }

        $sql = sprintf(self::SQL_INSERT, $table_name, $names, $values);
        $this->db->query($sql, $fields);
        $this->fields[$primary_key] = $this->db->lastId();
    }


    /**
     * Executes a simple SQL DELETE statement. The SQL statement is build using the provided table name and field names. This
     * function is usually used internally by the DBModel class itself. You should use the remove() function to delete a record instead.
     *
     * @param $table_name  string The name of the table against which to run the DELETE query.
     * @param $primary_key string The primary key field name, used in the WHERE clause.
     * @param $id          string The primary key value (unique id) of the record you wish to delete.
     * @return mixed
     */
    public function delete($table_name, $primary_key, $id)
    {
        $sql = sprintf(self::SQL_DELETE, $table_name, $primary_key);
        $this->db->query($sql, array($primary_key => $id));
        return $id;
    }
}