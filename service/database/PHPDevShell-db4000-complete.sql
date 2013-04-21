-- ****************************************************************************************
-- FOR AUTOMATED ONE CLICK INSTALLATION SQL FOR SERVICES e.g CPanel Please see End of File;
-- THIS SQL FILE CAN BE EXECUTED/IMPORTED DIRECTLY INTO MYSQL DATABASE
-- ****************************************************************************************

CREATE TABLE `_db_core_cron` (
  `node_id`   VARCHAR(64) NOT NULL,
  `cron_desc` VARCHAR(255) DEFAULT NULL,
  `cron_type` INT(1) DEFAULT NULL,
  `log_cron`  INT(1) DEFAULT NULL,
  `last_execution` INT(50) DEFAULT NULL,
  `year`      INT(4) DEFAULT NULL,
  `month`     INT(2) DEFAULT NULL,
  `day`       INT(2) DEFAULT NULL,
  `hour`      INT(2) DEFAULT NULL,
  `minute`    INT(2) DEFAULT NULL,
  PRIMARY KEY (`node_id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Create filters for search.;
CREATE TABLE `_db_core_filter` (
  `search_id`     INT(255) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT(20) DEFAULT NULL,
  `node_id`       VARCHAR(64)       NOT NULL,
  `filter_search` VARCHAR(255) DEFAULT NULL,
  `filter_order`  VARCHAR(5) DEFAULT NULL,
  `filter_by`     VARCHAR(255) DEFAULT NULL,
  `exact_match`   VARCHAR(2) DEFAULT NULL,
  PRIMARY KEY (`search_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

-- Create logs table for watchdog.;
CREATE TABLE `_db_core_logs` (
  `id`                INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_type`          INT(2) DEFAULT NULL,
  `log_description`   TEXT,
  `log_time`          INT(10) DEFAULT NULL,
  `user_id`           INT(30) DEFAULT NULL,
  `user_display_name` VARCHAR(255) DEFAULT NULL,
  `node_id`           VARCHAR(64)      NOT NULL,
  `file_name`         VARCHAR(255) DEFAULT NULL,
  `node_name`         VARCHAR(255) DEFAULT NULL,
  `user_ip`           VARCHAR(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

-- Create table for default node items.;
CREATE TABLE `_db_core_node_items` (
  `node_id`        VARCHAR(64) NOT NULL,
  `parent_node_id` VARCHAR(64) DEFAULT NULL,
  `node_name`      VARCHAR(255) DEFAULT NULL,
  `node_link`      VARCHAR(255) DEFAULT NULL,
  `plugin`         VARCHAR(255) DEFAULT NULL,
  `node_type`      INT(1) DEFAULT NULL,
  `extend`         VARCHAR(255) DEFAULT NULL,
  `new_window`     INT(1) DEFAULT NULL,
  `rank`           INT(100) DEFAULT NULL,
  `hide`           INT(1) DEFAULT NULL,
  `theme_id`       VARCHAR(64) DEFAULT NULL,
  `alias`          VARCHAR(255) DEFAULT NULL,
  `layout`         VARCHAR(255) DEFAULT NULL,
  `params`         VARCHAR(1024) DEFAULT NULL,
  `route`          VARCHAR(300) DEFAULT NULL,
  PRIMARY KEY (`node_id`),
  KEY `index` (`parent_node_id`, `node_link`, `plugin`, `alias`),
  KEY `params` (`params`(255)) USING BTREE
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert default node items.;
INSERT INTO `_db_core_node_items` VALUES ('readme', '0', 'Readme', 'readme.php', 'About', '1', null, '0', '1', '0', 'default', 'readme', null, null, null);
INSERT INTO `_db_core_node_items` VALUES ('plugin-admin', '0', 'Plugins', 'plugin-admin.php', 'PluginManager', '1', null, '0', '15', '0', 'default', 'plugins-admin', null, null, null);

-- Create node tree structure.;
CREATE TABLE `_db_core_node_structure` (
  `id`        INT(50) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`   VARCHAR(64)      NOT NULL,
  `is_parent` INT(1) DEFAULT NULL,
  `type`      INT(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`node_id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert node tree structure.;
INSERT INTO `_db_core_node_structure` (node_id, is_parent, type) VALUES ('readme', '0', '2');
INSERT INTO `_db_core_node_structure` (node_id, is_parent, type) VALUES ('plugin-admin', '0', '2');

-- Create plugins table.;
CREATE TABLE `_db_core_plugin_activation` (
  `plugin_folder` VARCHAR(255) NOT NULL DEFAULT '0',
  `status`        VARCHAR(255) DEFAULT NULL,
  `version`       INT(16)      NOT NULL,
  `persistent`      INT(2) DEFAULT NULL,
  PRIMARY KEY (`plugin_folder`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert available default plugins.;
INSERT INTO `_db_core_plugin_activation` VALUES ('Mustache', 'install', '1000', '0');
INSERT INTO `_db_core_plugin_activation` VALUES ('LightModels', 'install', '1000', '0');
INSERT INTO `_db_core_plugin_activation` VALUES ('PluginManager', 'install', '1000', '0');
INSERT INTO `_db_core_plugin_activation` VALUES ('NodeHelper', 'install', '1000', '0');
INSERT INTO `_db_core_plugin_activation` VALUES ('About', 'install', '1000', '0');

-- Create classes available from default plugins.;
CREATE TABLE `_db_core_plugin_classes` (
  `class_id`      INT(10) NOT NULL AUTO_INCREMENT,
  `class_name`    VARCHAR(155) DEFAULT NULL,
  `alias`         VARCHAR(155) DEFAULT NULL,
  `plugin_folder` VARCHAR(255) DEFAULT NULL,
  `enable`        INT(1) DEFAULT NULL,
  `rank`          INT(4) DEFAULT NULL,
  PRIMARY KEY (`class_id`),
  KEY `index` (`class_name`, `alias`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert classes available from default plugins.;
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('views', 'PHPDS_views', 'Mustache', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('models', 'LightModels_models', 'LightModels', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('pluginFactory', 'PluginManager_pluginFactory', 'PluginManager', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('pluginRepository', 'PluginManager_pluginRepository', 'PluginManager', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('nodeHelper', 'NodeHelper_nodeHelper', 'NodeHelper', '1', '1');
INSERT INTO `_db_core_plugin_classes` (class_name, alias, plugin_folder, enable, rank) VALUES ('StandardLogin', 'PHPDS_login', 'StandardLogin', '1', '1');

-- Create session table.;
CREATE TABLE `_db_core_session` (
  `cookie_id`  INT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(20) UNSIGNED NOT NULL,
  `id_crypt`   CHAR(6)          NOT NULL,
  `pass_crypt` CHAR(32)         NOT NULL,
  `timestamp`  INT(10)          NOT NULL,
  PRIMARY KEY (`cookie_id`),
  KEY `index` (`user_id`)
)
  ENGINE = MyISAM
  DEFAULT CHARSET = utf8;

-- Create settings table.;
CREATE TABLE `_db_core_settings` (
  `setting_id` VARCHAR(100) NOT NULL DEFAULT '',
  `setting_value`       TEXT,
  `note`                TEXT,
  PRIMARY KEY (`setting_id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert default settings to make system work.;
INSERT INTO `_db_core_settings` VALUES ('PHPDS_allow_remember', '1', 'Should users be allowed to login with remember.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_banned_role', '4', 'The banned role. No access allowed.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_charset', 'UTF-8', 'Site wide charset.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_charset_format', '.{charset}', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_cmod', '0777', 'Writable folder permissions');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_date_format', 'F j, Y, g:i a O', 'Date format according to DateTime function of PHP.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_date_format_short', 'Y-m-d', 'Shorter date format.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_debug_language', '', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_default_theme', 'default', 'Default theme for all nodes.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_default_theme_id', 'default', 'Default theme id.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_default_upload_directory', 'write/upload/', 'Writable upload directory.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_footer_notes', 'PHPDevShell.org (c) 2013 GNU/GPL License.', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_front_page_id', 'readme', 'The page to show when site is access.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_front_page_id_in', 'readme', 'The page to show when logged in and home or page is accessed.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_guest_role', '3', 'The systems guest role.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_language', 'en', 'Default language.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_locale_format', '{lang}_{region}{charset}', 'Complete locale format.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_loginandout', 'login', 'The page to use to log-in and log-out.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_login_message', '', 'a Default message to welcome users loging in.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_meta_description', 'Administrative user interface based on PHPDS and other modern technologies.', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_meta_keywords', 'administrative, administrator, PHPDS, interface, ui, user', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_queries_count', '0', 'Should queries be counted and info show.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_redirect_login', 'readme', 'When a user logs in, where should he be redirected to?');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_region', 'US', 'Region settings.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_regions_available', 'US', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_root_id', '1', 'Root User.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_root_role', '1', 'Root Role.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_sef_url', '0', 'Should SEF urls be enabled, not rename to .htaccess in root.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_split_results', '15', 'When viewing paged results, how many results should be shown.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_system_down', '0', 'Is system currently down for development.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_system_down_message', '%s is currently down for maintenance. Some important features are being updated. Please return soon.', '');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_system_logging', '1', 'Should logs be written to database.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_system_timezone', 'UTC', 'Timezone.');
INSERT INTO `_db_core_settings` VALUES ('PHPDS_url_append', '', 'The url extension in the end.');

-- Create tags table for tagging data.;
CREATE TABLE `_db_core_tags` (
  `tag_id`     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_object` VARCHAR(45) DEFAULT NULL,
  `tag_name`   VARCHAR(45) DEFAULT NULL,
  `tag_target` VARCHAR(45) DEFAULT NULL,
  `tag_value`  TEXT,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `UNIQUE` (`tag_object`, `tag_name`, `tag_target`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Create themes table to store installed themes.;
CREATE TABLE `_db_core_themes` (
  `theme_id`     VARCHAR(64) NOT NULL,
  `theme_folder` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`theme_id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert default themes.;
INSERT INTO `_db_core_themes` VALUES ('default', 'default');

-- Create important user table to store all users.;
CREATE TABLE `_db_core_users` (
  `user_id`           INT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_display_name` VARCHAR(255) DEFAULT NULL,
  `user_name`         VARCHAR(255) DEFAULT NULL,
  `user_password`     VARCHAR(100) DEFAULT NULL,
  `user_email`        VARCHAR(100) DEFAULT NULL,
  `user_role`         INT(10) DEFAULT NULL,
  `date_registered`   INT(10) DEFAULT NULL,
  `language`          VARCHAR(10) DEFAULT NULL,
  `timezone`          VARCHAR(255) DEFAULT NULL,
  `region`            VARCHAR(10) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `index_user` (`user_name`, `user_email`),
  KEY `index_general` (`user_display_name`, `user_role`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Create primary roles table a user can belong to.;
CREATE TABLE `_db_core_user_roles` (
  `user_role_id`   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_role_name` VARCHAR(255) DEFAULT NULL,
  `user_role_note` TINYTEXT,
  PRIMARY KEY (`user_role_id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert primary roles table a user can belong to.;
INSERT INTO `_db_core_user_roles` VALUES ('1', 'Super Admin', null);
INSERT INTO `_db_core_user_roles` VALUES ('2', 'Normal User', null);
INSERT INTO `_db_core_user_roles` VALUES ('3', 'Guest User', null);
INSERT INTO `_db_core_user_roles` VALUES ('4', 'Disabled', null);

-- Create security role permissions table.;
CREATE TABLE `_db_core_user_role_permissions` (
  `user_role_id` INT(10)     NOT NULL DEFAULT '0',
  `node_id`      VARCHAR(64) NOT NULL,
  PRIMARY KEY (`user_role_id`, `node_id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

-- Insert default user permissions.;
INSERT INTO _db_core_user_role_permissions VALUES ('1', 'readme');
INSERT INTO _db_core_user_role_permissions VALUES ('2', 'readme');
INSERT INTO _db_core_user_role_permissions VALUES ('3', 'readme');

INSERT INTO _db_core_user_role_permissions VALUES ('1', 'plugin-admin');

-- IF DEFAULT INSTALLATION PHPDS SERVICE WILL NOT BE USED AND REPLACED WITH e.g CPanel ALSO RUN QUERIES:
-- Add default root user (default username/password root/root);
INSERT INTO _db_core_users
(user_id, user_display_name, user_name, user_password, user_email, user_role, date_Registered, language, timezone, region)
VALUES ('1', 'Root User', 'root', '63a9f0ea7bb98050796b649e85481845', 'admin@phpdevshell.org', '1', '1366524462', 'en', 'UTC', 'US');
-- Add extra custom settings;
INSERT INTO _db_core_settings VALUES ('PHPDS_crypt_key', 'ASKjjFHS223DjjLLPSfjsaDiEED', 'Crypt Key');
INSERT INTO _db_core_settings VALUES ('PHPDS_from_email', 'admin@phpdevshell.org', 'Where emails are shows to be sent from');
INSERT INTO _db_core_settings VALUES ('PHPDS_scripts_name_version', 'PHPDevShell', 'The name of the newly launched application');
INSERT INTO _db_core_settings VALUES ('PHPDS_setting_admin_email', 'admin@phpdevshell.org', 'Administrator email');
INSERT INTO _db_core_settings VALUES ('PHPDS_db_version', 4000, 'PHPDevShell Database Version');
-- That's it.
