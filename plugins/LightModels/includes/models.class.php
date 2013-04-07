<?php

class models extends PHPDS_dependant
{
    /**
     * Loads the record with the specified unique id from the database into the model's fields. The field names,
     * default values, table name and primary key name must all already be specified using the DBModel::fields,
     * DBModel::defaults, DBModel::table_name and DBModel::primary_key arrays and values before calling this function.
     *
     * @param $id integer The value of the primary key (unique id) of the record to load.
     * @return array An array containing the field names and their loaded values (or default values).
     */
    public function load($id)
    {
        $this->select($this->table_name, $this->defaults, array($this->primary_key => $id));
        $this->fields = $this->db->fetchAssoc();

        if (!$this->fields) {
            // Record does not exist or no records found.
            $this->core->addWarning(sprintf("No records were found having the specified id [%d].", $id), false);
        }

        return $this->fields;
    }


    /**
     * Save the current model to database as a record in a table. If the primary key value is 0 then
     * a new record is inserted, otherwise the existing record will be updated.
     *
     * @return integer The existing primary key value if a record was updated or a new id if a new
     *                 record was inserted.
     */
    public function save()
    {
        if ($this->fields[$this->primary_key] > 0) {
            // Updating existing record
            $this->update($this->table_name, $this->fields, $this->primary_key);
        } else {
            // Adding new record
            $this->insert($this->table_name, $this->fields, $this->primary_key);
            $this->fields[$this->primary_key] = $this->db->lastId();
        }
        return $this->getID();
    }

    /**
     * Removes a record from the database using the given id.
     *
     * @param $id integer The value of the primary key (unique id) of the record to delete.
     * @return integer The id was of the deleted record.
     */
    public function remove($id)
    {
        return $this->delete($this->table_name, $this->primary_key, $id);
    }

    /**
     * Checks to see if a given field's value already exists in the database and is typically used just
     * before a save() is performed. If the primary key value is greater than 0 the function will assume
     * that an existing record is going to be updated and it won't see the existing record as a duplicate.
     *
     * @param $field_name string The name of the field if which duplicate values are not allowed in the database.
     * @return boolean True if there is another record where $field_name is duplicated.
     */
    public function duplicateExists($field_name)
    {
        if ($this->getID() > 0) {
            // Updated record
            $sql = sprintf(self::SQL_DUP_EDIT_QUERY, $this->table_name, $field_name, $this->primary_key);
            $this->db->query($sql, array($this->primary_key => $this->fields[$this->primary_key], $field_name => $this->fields[$field_name]));
        } else {
            // New record
            $sql = sprintf(self::SQL_DUP_ADD_QUERY, $this->table_name, $field_name);
            $this->db->query($sql, array($field_name => $this->fields[$field_name]));
        }

        $result = $this->db->fetchAssoc();
        if ($result) {
            return ($result['count'] > 0);
        } else {
            return false;
        }
    }
}