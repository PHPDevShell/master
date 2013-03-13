-- Create filters for search.;
CREATE TABLE `_db_core_filter` (
	`search_id` int(255) unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int(20) DEFAULT NULL,
	`node_id` varchar(64) NOT NULL,
	`filter_search` varchar(255) DEFAULT NULL,
	`filter_order` varchar(5) DEFAULT NULL,
	`filter_by` varchar(255) DEFAULT NULL,
	`exact_match` varchar(2) DEFAULT NULL,
	PRIMARY KEY (`search_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Create logs table for watchdog.;
CREATE TABLE `_db_core_logs` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`log_type` int(2) DEFAULT NULL,
	`log_description` text,
	`log_time` int(10) DEFAULT NULL,
	`user_id` int(30) DEFAULT NULL,
	`user_display_name` varchar(255) DEFAULT NULL,
	`node_id` varchar(64) NOT NULL,
	`file_name` varchar(255) DEFAULT NULL,
	`node_name` varchar(255) DEFAULT NULL,
	`user_ip` varchar(30) DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- Create table for default node items.;
CREATE TABLE `_db_core_node_items` (
	`node_id` varchar(64) NOT NULL,
	`parent_node_id` varchar(64) DEFAULT NULL,
	`node_name` varchar(255) DEFAULT NULL,
	`node_link` varchar(255) DEFAULT NULL,
	`plugin` varchar(255) DEFAULT NULL,
	`node_type` int(1) DEFAULT NULL,
	`extend` varchar(255) DEFAULT NULL,
	`new_window` int(1) DEFAULT NULL,
	`rank` int(100) DEFAULT NULL,
	`hide` int(1) DEFAULT NULL,
	`template_id` varchar(64) DEFAULT NULL,
	`alias` varchar(255) DEFAULT NULL,
	`layout` varchar(255) DEFAULT NULL,
	`params` varchar(1024) DEFAULT NULL,
	PRIMARY KEY (`node_id`),
	KEY `index` (`parent_node_id`,`node_link`,`plugin`,`alias`),
	KEY `params` (`params`(255)) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default node items.;
INSERT INTO `_db_core_node_items` VALUES ('readme', '0', 'Readme', 'readme.php', 'About', '1', null, '0', '1', '0', 'default', 'readme', null, null);
INSERT INTO `_db_core_node_items` VALUES ('plugin-admin', '0', 'Plugins', 'plugin-activation.php', 'PluginManager', '1', null, '0', '15', '0', 'default', 'plugins-admin', null, null);

-- Create node tree structure.;
CREATE TABLE `_db_core_node_structure` (
	`id` int(50) unsigned NOT NULL AUTO_INCREMENT,
	`node_id` varchar(64) NOT NULL,
	`is_parent` int(1) DEFAULT NULL,
	`type` int(1) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `index` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert node tree structure.;
INSERT INTO `_db_core_node_structure` (node_id, is_parent, type) VALUES ('readme', '0', '2');
INSERT INTO `_db_core_node_structure` (node_id, is_parent, type) VALUES ('plugin-admin', '0', '2');

-- Create plugins table.;
CREATE TABLE `_db_core_plugin_activation` (
	`plugin_folder` varchar(255) NOT NULL DEFAULT '0',
	`status` varchar(255) DEFAULT NULL,
	`version` int(16) NOT NULL,
	`use_logo` int(2) DEFAULT NULL,
	PRIMARY KEY (`plugin_folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert available default plugins.;
INSERT INTO `_db_core_plugin_activation` VALUES ('Pagination', 'install', '1000', '0');
INSERT INTO `_db_core_plugin_activation` VALUES ('Mustache', 'install', '1000', '0');
INSERT INTO `_db_core_plugin_activation` VALUES ('StandardLogin', 'install', '1000', '0');

-- Create classes available from default plugins.;
CREATE TABLE `_db_core_plugin_classes` (
	`class_id` int(10) NOT NULL AUTO_INCREMENT,
	`class_name` varchar(155) DEFAULT NULL,
	`alias` varchar(155) DEFAULT NULL,
	`plugin_folder` varchar(255) DEFAULT NULL,
	`enable` int(1) DEFAULT NULL,
	`rank` int(4) DEFAULT NULL,
	PRIMARY KEY (`class_id`),
	KEY `index` (`class_name`,`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert classes available from default plugins.;
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('views', 'PHPDS_views', 'Mustache', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('pluginManager', 'PHPDS_pluginmanager', 'PluginManager', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('StandardLogin', 'PHPDS_login', 'StandardLogin', '1', '1');

-- Create session table.;
CREATE TABLE `_db_core_session` (
	`cookie_id` int(20) unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int(20) unsigned NOT NULL,
	`id_crypt` char(6) NOT NULL,
	`pass_crypt` char(32) NOT NULL,
	`timestamp` int(10) NOT NULL,
	PRIMARY KEY (`cookie_id`),
	KEY `index` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Create settings table.;
CREATE TABLE `_db_core_settings` (
	`setting_description` varchar(100) NOT NULL DEFAULT '',
	`setting_value` text,
	`note` text,
	PRIMARY KEY (`setting_description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default settings to make system work.;
INSERT INTO `_db_core_settings` VALUES ('AdminTools_allow_remember', '1', 'Should users be allowed to login with remember.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_banned_role', '6', 'The banned role. No access allowed.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_charset', 'UTF-8', 'Site wide charset.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_charset_format', '.{charset}', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_cmod', '0777', 'Writable folder permissions');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_crypt_key', 'eDucDjodz8ZiMqFe8zeJ', 'General crypt key to protect system.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_custom_logo', '', 'Default system logo.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_date_format', 'F j, Y, g:i a O', 'Date format according to DateTime function of PHP.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_date_format_short', 'Y-m-d', 'Shorter date format.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_date_format_show', 'September 17, 2010, 12:59 pm +0000', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_date_format_show_short', '2010-09-17', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_debug_language', '', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_default_template', 'default', 'Default theme for all nodes.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_default_template_id', 'default', 'Default template id.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_default_upload_directory', 'write/upload/', 'Writable upload directory.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_demo_mode', '0', 'Should system be set into demo mode, no transactions will occur.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_footer_notes', 'PHPDevShell.org (c) 2013 GNU/GPL License.', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_from_email', 'no-reply@phphdevshell.org', 'From Email address.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_front_page_id', 'readme', 'The page to show when site is access.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_front_page_id_in', 'readme', 'The page to show when logged in and home or page is accessed.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_guest_role', '4', 'The systems guest role.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_language', 'en', 'Default language.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_languages_available', 'en', 'List of language codes available');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_locale_format', '{lang}_{region}{charset}', 'Complete locale format.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_loginandout', 'login', 'The page to use to log-in and log-out.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_login_message', '', 'a Default message to welcome users loging in.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_meta_description', 'Administrative user interface based on AdminTools and other modern technologies.', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_meta_keywords', 'administrative, administrator, AdminTools, interface, ui, user', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_printable_template', 'default', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_queries_count', '1', 'Should queries be counted and info show.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_redirect_login', 'readme', 'When a user logs in, where should he be redirected to?');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_region', 'US', 'Region settings.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_regions_available', 'US', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_root_id', '1', 'Root User.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_root_role', '1', 'Root Role.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_save', 'save', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_scripts_name_version', 'Powered by PHPDevShell', 'Footer message.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_sef_url', '0', 'Should SEF urls be enabled, not rename to .htaccess in root.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_sendmail_path', '/usr/sbin/sendmail', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_setting_admin_email', 'admin@phpdevshell.org', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_setting_support_email', 'default:System Support Query,default:General Query', 'Allows you to have multiple option for a email query.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_split_results', '30', 'When viewing paged results, how many results should be shown.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_system_down', '0', 'Is system currently down for development.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_system_down_message', '%s is currently down for maintenance. Some important features are being updated. Please return soon.', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_system_logging', '1', 'Should logs be written to database.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_system_timezone', 'UTC', 'Timezone.');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_trim_logs', '1000000', '');
INSERT INTO `_db_core_settings` VALUES ('AdminTools_url_append', '.html', 'The url extension in the end.');

-- Create tags table for tagging data.;
CREATE TABLE `_db_core_tags` (
	`tag_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`tag_object` varchar(45) DEFAULT NULL,
	`tag_name` varchar(45) DEFAULT NULL,
	`tag_target` varchar(45) DEFAULT NULL,
	`tag_value` text,
	PRIMARY KEY (`tag_id`),
	UNIQUE KEY `UNIQUE` (`tag_object`,`tag_name`,`tag_target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create themes table to store installed themes.;
CREATE TABLE `_db_core_templates` (
	`template_id` varchar(64) NOT NULL,
	`template_folder` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default themes.;
INSERT INTO `_db_core_templates` VALUES ('default', 'default');

-- Create important user table to store all users.;
CREATE TABLE `_db_core_users` (
	`user_id` int(20) unsigned NOT NULL AUTO_INCREMENT,
	`user_display_name` varchar(255) DEFAULT NULL,
	`user_name` varchar(255) DEFAULT NULL,
	`user_password` varchar(100) DEFAULT NULL,
	`user_email` varchar(100) DEFAULT NULL,
	`user_role` int(10) DEFAULT NULL,
	`date_registered` int(10) DEFAULT NULL,
	`language` varchar(10) DEFAULT NULL,
	`timezone` varchar(255) DEFAULT NULL,
	`region` varchar(10) DEFAULT NULL,
	PRIMARY KEY (`user_id`),
	UNIQUE KEY `index_user` (`user_name`,`user_email`),
	KEY `index_general` (`user_display_name`,`user_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create primary roles table a user can belong to.;
CREATE TABLE `_db_core_user_roles` (
	`user_role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`user_role_name` varchar(255) DEFAULT NULL,
	`user_role_note` tinytext,
	PRIMARY KEY (`user_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert primary roles table a user can belong to.;
INSERT INTO `_db_core_user_roles` VALUES ('1', 'Super Admin', null);
INSERT INTO `_db_core_user_roles` VALUES ('2', 'Registered User', null);
INSERT INTO `_db_core_user_roles` VALUES ('3', 'Awaiting Confirmation', null);
INSERT INTO `_db_core_user_roles` VALUES ('4', 'Guest User', null);
INSERT INTO `_db_core_user_roles` VALUES ('5', 'Disabled', null);

-- Create security role permissions table.;
CREATE TABLE `_db_core_user_role_permissions` (
  `user_role_id` int(10) NOT NULL DEFAULT '0',
  `node_id` varchar(64) NOT NULL,
  PRIMARY KEY (`user_role_id`,`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default user permissions.;
INSERT INTO _db_core_user_role_permissions VALUES ('1', 'readme');
INSERT INTO _db_core_user_role_permissions VALUES ('2', 'readme');
INSERT INTO _db_core_user_role_permissions VALUES ('4', 'readme');

INSERT INTO _db_core_user_role_permissions VALUES ('1', 'plugin-admin');

