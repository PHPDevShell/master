<?php

class DB_tableExistQuery extends PHPDS_query
{
    protected $sql = "SHOW TABLES LIKE '%s'";
}

class DB_countRowsQuery extends PHPDS_query
{
    protected $sql = "SELECT %s FROM %s";

    public function invoke($parameters = null)
    {
        parent::invoke($parameters);
        return $this->count();
    }
}

class DB_doesRecordExistQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			COUNT(*)
		FROM
		%s
		%s
		%s
	";

    protected $singleValue = true;

    public function invoke($parameters = null)
    {
        list($table_name, $search_column_names, $search_field_values, $column_name_for_exclusion, $exclude_field_value) = $parameters;

        if ($column_name_for_exclusion != false && $exclude_field_value != false) {
            $WHERE_in_db = " WHERE ($column_name_for_exclusion != '$exclude_field_value') AND ";
        } else {
            $WHERE_in_db = ' WHERE ';
        }

        if (is_array($search_column_names) && is_array($search_field_values)) {

            foreach ($search_column_names as $key => $search_column_names_string) {
                $MATCH_in_db .= " $search_column_names_string = '$search_field_values[$key]' OR ";
            }

            $MATCH_in_db = $this->core->rightTrim($MATCH_in_db, ' OR ');
            $MATCH_in_db = "($MATCH_in_db)";
        } else {
            $MATCH_in_db = " $search_column_names = '$search_field_values' ";
        }

        $result = parent::invoke(array($table_name, $WHERE_in_db, $MATCH_in_db));

        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }
}

class DB_selectQuickQuery extends PHPDS_query
{
    protected $sql = "
		SELECT
			%s
		FROM
			%s
		WHERE
			%s = '%s'
	";

    protected $singleValue = true;
}

class DB_deleteQuickQuery extends PHPDS_query
{
    protected $sql = "
		DELETE FROM
			%s
		WHERE
			%s = '%s';
	";

    public function invoke($parameters = null)
    {
        list($from_table_name, $where_column_name, $is_equal_to_column_value, $return_column_value) = $parameters;
        if (!empty($return_column_value)) {
            $return_deleted = $this->db->selectQuick($from_table_name, $return_column_value, $where_column_name, $is_equal_to_column_value);
        }
        if (parent::invoke(array($from_table_name, $where_column_name, $is_equal_to_column_value))) {
            if (!empty($return_deleted)) {
                return $return_deleted;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
}