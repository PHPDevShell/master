<?php
class CONFIG_getSettingsQuery extends PHPDS_query
{
    protected $sql = "
		SELECT SQL_CACHE
			setting_id, setting_value
		FROM
			_db_core_settings
		WHERE
			setting_id	%s
	";

    public function invoke($parameters = null)
    {
        list($settings_required, $custom_prefix) = $parameters;
        if ($custom_prefix == '*') {
            $prefix = '%%';
        } else {
            $prefix = $this->config->settingsPrefix($custom_prefix);
        }

        if (is_array($settings_required)) {

            $db_get_query = false;

            foreach ($settings_required as $setting_from_db) {
                if (!empty($setting_from_db)) {
                    $db_get_query .= "'$prefix" . "$setting_from_db',";
                    $settings[$setting_from_db] = null;
                }
            }
            $db_get_query = rtrim($db_get_query, ",");

            $db_get_query = " IN ($db_get_query) ";
        } else {
            $db_get_query = " LIKE '$prefix%%' ";
        }

        if (!empty($db_get_query)) {
            $settings_db = parent::invoke($db_get_query);
        }


        if (!empty($settings_db) && is_array($settings_db)) {

            foreach ($settings_db as $fetch_setting_array) {
                $description = $fetch_setting_array['setting_id'];
                $value       = $fetch_setting_array['setting_value'];

                $description = preg_replace("/$prefix/", '', $description);

                $settings[$description] = $value;
            }
            return $settings;
        } else {
            return false;
        }
    }
}


class CONFIG_writeSettingsQuery extends PHPDS_query
{
    protected $sql = "
		REPLACE INTO
			_db_core_settings (setting_id, setting_value, note)
		VALUES
			%s
	";

    public function invoke($parameters = null)
    {
        $db     = $this->db;
        $config = $this->config;
        list($write_settings, $custom_prefix, $notes) = $parameters;

        if ($custom_prefix == '*') {
            $prefix = '%';
        } else {
            $prefix = $config->settingsPrefix($custom_prefix);
        }

        $db_replace = false;
        if (is_array($write_settings)) {

            foreach ($write_settings as $settings_id => $settings_value) {

                if (!empty($notes[$settings_id])) {
                    $note = $db->protect(trim($notes[$settings_id]));
                } else {
                    $note = '';
                }
                $settings_id    = $db->protect(trim($prefix . $settings_id));
                $settings_value = $db->protect(trim($settings_value));
                $db_replace .= "('$settings_id', '$settings_value', '$note'),";
            }
            $db_replace = rtrim($db_replace, ",");
            if (!empty($db_replace))
                $insert_settings = parent::invoke($db_replace);

            if (!empty($insert_settings)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}

class CONFIG_deleteSettingsQuery extends PHPDS_query
{
    protected $sql = "
		DELETE FROM
			_db_core_settings
		WHERE
			setting_id %s
	";

    public function invoke($parameters = null)
    {
        list($settings_to_delete, $custom_prefix) = $parameters;

        if ($custom_prefix == '*') {
            $prefix = '%%';
        } else {
            $prefix = $this->config->settingsPrefix($custom_prefix);
        }

        $db_delete_query = false;

        if (is_array($settings_to_delete)) {

            foreach ($settings_to_delete as $setting_from_db) {
                $db_delete_query .= "'$prefix" . "$setting_from_db',";
            }
            $db_delete_query = rtrim($db_delete_query, ",");

            $db_delete_query = " IN ($db_delete_query) ";
        } else if ($settings_to_delete == '*') {
            $db_delete_query = " LIKE '$prefix%%' ";
        }
        if (!empty($db_delete_query))
            $delete_settings = parent::invoke($db_delete_query);

        if (!empty($delete_settings)) {
            return true;
        } else {
            return false;
        }
    }
}