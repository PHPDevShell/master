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

    public $fields = array();
    public $defaults = array();
    public $table_name = "";
    public $primary_key = "";

    const SQL_SELECT         = 'SELECT %1$s FROM %2$s WHERE %3$s';
    const SQL_UPDATE         = 'UPDATE %1$s SET %2$s WHERE %3$s';
    const SQL_INSERT         = 'INSERT INTO %1$s (%2$s) VALUES (%3$s)';
    const SQL_DELETE         = 'DELETE FROM %1$s WHERE %2$s = :%2$s';
    const SQL_DUP_EDIT_QUERY = 'SELECT COUNT(*) count FROM %1$s WHERE UPPER(%2$s) = :%2$s AND %3$s != :%3$s';
    const SQL_DUP_ADD_QUERY  = 'SELECT COUNT(*) count FROM %1$s WHERE UPPER(%2$s) = :%2$s';

    /**
     * Executes a simple SQL SELECT statement. The SQL statement is build using the provided table name and field names. The WHERE
     * clause is build from the params variable. This function is usually used internally by the DBModel class itself. You should
     * use the load() function to load data instead.
     *
     * @param $table_name   string The name of the table against which to run the SELECT query.
     * @param $fields       array Associative array of field names and their values that should be used in the SELECT query.
     * @param $params       array Array of string based field names that should be used in the WHERE clause. The values is pulled from
     *                      the field values.
     */
    public function select($table_name, $fields, $params)
    {
        $where = "";
        foreach (array_keys($params) as $key) {
            if (empty($where)) {
                $where = sprintf('%1$s = :%1$s', $key);
            } else {
                $where .= sprintf(' AND %1$s = :%1$s', $key);
            }
        }

        $sql = sprintf(self::SQL_SELECT, implode(', ', array_keys($fields)), $table_name, $where);
        $this->db->query($sql, $params);
    }

    /**
     * Executes a simple SQL UPDATE statement. The SQL statement is build using the provided table name and field names. The WHERE
     * clause is build from the params variable. This function is usually used internally by the DBModel class itself. You should
     * use the save() function to save data instead.
     *
     * @param $table_name        string The name of the table against which to run the UPDATE query.
     * @param $fields            array Associative array of field names and their values that should be used in the UPDATE query.
     * @param $primary_key       string Name of primary key.
     */
    public function update($table_name, $fields, $primary_key)
    {
        $update = "";
        foreach (array_keys($fields) as $key) {
            if ($key != $primary_key) {
                if (empty($update)) {
                    $update = sprintf('%1$s = :%1$s', $key);
                } else {
                    $update .= sprintf(', %1$s = :%1$s', $key);
                }
            }
        }

        $where = sprintf('%1$s = :%1$s', $primary_key);

        $sql = sprintf(self::SQL_UPDATE, $table_name, $update, $where);
        $this->db->query($sql, $fields);
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

    /**
     * Fetches the next row in the query results as an associative array. Will return false if no more rows are available.
     *
     * @return mixed Associative array of row data or False if there is no more rows.
     */
    public function fetchNextRow()
    {
        return $this->db->fetchAssoc();
    }


    /**
     * Returns the value of the primary key field. Will return the new id of an inserted record.
     *
     * @return integer Value of the primary key field
     */
    public function getID()
    {
        return $this->fields[$this->primary_key];
    }


    /**
     * Resets all the fields of the model to its default values.
     *
     */
    public function reset()
    {
        $this->fields = $this->defaults;
    }
}