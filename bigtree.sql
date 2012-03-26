DROP TABLE IF EXISTS `bigtree_404s`;
CREATE TABLE `bigtree_404s` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`broken_url` varchar(255) NOT NULL DEFAULT '',`redirect_url` varchar(255) NOT NULL DEFAULT '',`requests` int(11) unsigned NOT NULL DEFAULT '0',`ignored` char(2) NOT NULL DEFAULT '',PRIMARY KEY (`id`),KEY `broken_url` (`broken_url`),KEY `requests` (`requests`),KEY `ignored` (`ignored`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_api_tokens`;
CREATE TABLE `bigtree_api_tokens` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`token` varchar(255) NOT NULL,`user` int(11) unsigned NOT NULL,`expires` datetime NOT NULL,`temporary` char(2) NOT NULL,
`readonly` char(2) NOT NULL,PRIMARY KEY (`id`),KEY `token` (`token`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_audit_trail`;
CREATE TABLE `bigtree_audit_trail` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`table` varchar(255) NOT NULL,`entry` varchar(255) NOT NULL DEFAULT '',`type` varchar(255) NOT NULL,`date` datetime NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_callouts`;
CREATE TABLE `bigtree_callouts` (`id` varchar(255) NOT NULL,`name` varchar(255) NOT NULL DEFAULT '',`description` text NOT NULL,`resources` text NOT NULL,`level` int(11) unsigned NOT NULL,`position` int(11) unsigned NOT NULL,`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_feeds`;
CREATE TABLE `bigtree_feeds` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`route` varchar(255) NOT NULL,`name` varchar(255) NOT NULL,`description` text NOT NULL,`type` varchar(255) NOT NULL,`table` varchar(255) NOT NULL,`fields` text NOT NULL,`options` text NOT NULL,`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `route` (`route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_field_types`;
CREATE TABLE `bigtree_field_types` (`id` varchar(255) NOT NULL DEFAULT '',`name` varchar(255) NOT NULL,`pages` char(2) NOT NULL,`modules` char(2) NOT NULL,`callouts` char(2) NOT NULL DEFAULT '',`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_locks`;
CREATE TABLE `bigtree_locks` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`table` varchar(255) NOT NULL,`item_id` varchar(255) NOT NULL,`last_accessed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`title` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `table` (`table`),KEY `item_id` (`item_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_messages`;
CREATE TABLE `bigtree_messages` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`sender` int(11) unsigned NOT NULL,`recipients` text NOT NULL,`read_by` text NOT NULL,`subject` varchar(255) NOT NULL,`message` text NOT NULL,`response_to` int(11) unsigned NOT NULL,`date` datetime NOT NULL,PRIMARY KEY (`id`),KEY `sender` (`sender`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_module_actions`;
CREATE TABLE `bigtree_module_actions` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`module` int(11) unsigned NOT NULL DEFAULT '0',`name` varchar(255) NOT NULL DEFAULT '',`route` varchar(255) NOT NULL DEFAULT '',`in_nav` char(2) NOT NULL DEFAULT '',`form` int(11) unsigned NOT NULL,`view` int(11) unsigned NOT NULL,`class` varchar(255) NOT NULL,`position` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `module` (`module`),KEY `route` (`route`),KEY `in_nav` (`in_nav`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_module_forms`;
CREATE TABLE `bigtree_module_forms` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`title` varchar(255) NOT NULL,`javascript` varchar(255) NOT NULL,`css` varchar(255) NOT NULL,`callback` varchar(255) NOT NULL,`table` varchar(255) NOT NULL,`fields` text NOT NULL,`positioning` text NOT NULL,`default_position` varchar(255) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_module_groups`;
CREATE TABLE `bigtree_module_groups` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`name` varchar(255) NOT NULL,`route` varchar(255) NOT NULL,`in_nav` char(2) NOT NULL,`position` int(11) unsigned NOT NULL DEFAULT '0',`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `route` (`route`),KEY `in_nav` (`in_nav`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_module_view_cache`;
CREATE TABLE `bigtree_module_view_cache` (`view` int(11) unsigned NOT NULL,`id` varchar(255) NOT NULL,`gbp_field` text NOT NULL,`group_field` text NOT NULL,`group_sort_field` text NOT NULL,`position` int(11) unsigned NOT NULL,`approved` char(2) NOT NULL,`archived` char(2) NOT NULL,`featured` char(2) NOT NULL,`status` char(1) NOT NULL DEFAULT '',`pending_owner` int(11) unsigned NOT NULL,`column1` text NOT NULL,`column2` text NOT NULL,`column3` text NOT NULL,`column4` text NOT NULL,`column5` text NOT NULL,KEY `view` (`view`),KEY `group_field` (`group_field`(200)),KEY `group_sort_field` (`group_sort_field`(200)),KEY `id` (`id`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_module_views`;
CREATE TABLE `bigtree_module_views` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`title` varchar(255) NOT NULL DEFAULT '',`description` text NOT NULL,`type` varchar(255) NOT NULL DEFAULT '',`table` varchar(255) NOT NULL DEFAULT '',`fields` text NOT NULL,`options` text NOT NULL,`actions` text NOT NULL,`suffix` varchar(255) NOT NULL,`uncached` char(2) NOT NULL,`preview_url` varchar(255) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_modules`;
CREATE TABLE `bigtree_modules` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`group` int(11) unsigned NOT NULL DEFAULT '0',`name` varchar(255) NOT NULL DEFAULT '',`description` text NOT NULL,`image` varchar(255) NOT NULL DEFAULT '',`route` varchar(255) NOT NULL DEFAULT '',`class` varchar(255) NOT NULL DEFAULT '',`gbp` text NOT NULL,`position` int(11) unsigned NOT NULL,`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `group` (`group`),KEY `route` (`route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_page_revisions`;
CREATE TABLE `bigtree_page_revisions` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`page` int(11) unsigned NOT NULL DEFAULT '0',`title` varchar(255) NOT NULL DEFAULT '',`meta_keywords` text NOT NULL,
`meta_description` text NOT NULL,`template` varchar(255) NOT NULL DEFAULT '',`external` varchar(255) NOT NULL DEFAULT '',`new_window` varchar(5) NOT NULL DEFAULT '',`resources` longtext NOT NULL,`callouts` longtext NOT NULL,`author` int(11) unsigned NOT NULL,`saved` char(2) NOT NULL,`saved_description` text NOT NULL,`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `page` (`page`),KEY `saved` (`saved`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_pages`;
CREATE TABLE `bigtree_pages` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`parent` int(11) NOT NULL DEFAULT '0',`in_nav` varchar(5) NOT NULL DEFAULT 'on',`nav_title` varchar(255) NOT NULL DEFAULT '',`route` varchar(30) NOT NULL,`path` text NOT NULL,`title` varchar(255) NOT NULL DEFAULT '',`meta_keywords` text NOT NULL,`meta_description` text NOT NULL,`template` varchar(255) NOT NULL DEFAULT '',`external` varchar(255) NOT NULL DEFAULT '',`new_window` varchar(5) NOT NULL DEFAULT '',`resources` longtext NOT NULL,`callouts` longtext NOT NULL,`archived` char(2) NOT NULL,`archived_inherited` char(2) OT NULL,`locked` char(2) NOT NULL,`publish_at` date DEFAULT NULL,`expire_at` date DEFAULT NULL,`max_age` int(11) unsigned NOT NULL,`last_edited_by` int(11) unsigned NOT NULL,`ga_page_views` int(11) unsigned NOT NULL,`position` int(11) NOT NULL DEFAULT '0',`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),KEY `parent` (`parent`),KEY `in_nav` (`in_nav`),KEY `route` (`route`),KEY `path` (`path`(200)),KEY `publish_at` (`publish_at`),KEY `expire_at` (`expire_at`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_pending_changes`;
CREATE TABLE `bigtree_pending_changes` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,`title` varchar(255) NOT NULL,`comments` text NOT NULL,`table` varchar(255) NOT NULL,`changes` longtext NOT NULL,`mtm_changes` longtext NOT NULL,`tags_changes` longtext NOT NULL,`item_id` int(11) unsigned NOT NULL,
`type` varchar(15) NOT NULL,`module` varchar(10) NOT NULL,`pending_page_parent` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `item_id` (`item_id`),KEY `table` (`table`),KEY `pending_page_parent` (`pending_page_parent`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_resource_folders`;
CREATE TABLE `bigtree_resource_folders` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`parent` int(11) unsigned NOT NULL,`name` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `parent` (`parent`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_resources`;
CREATE TABLE `bigtree_resources` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`folder` int(11) unsigned NOT NULL,`file` varchar(255) NOT NULL,`date` datetime NOT NULL,`name` varchar(255) NOT NULL DEFAULT '',`type` varchar(255) NOT NULL DEFAULT '',`is_image` char(2) NOT NULL DEFAULT '',`height` int(11) unsigned NOT NULL DEFAULT '0',`width` int(11) unsigned NOT NULL DEFAULT '0',`crops` text NOT NULL,`thumbs` text NOT NULL,`list_thumb_margin` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `folder` (`folder`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_route_history`;
CREATE TABLE `bigtree_route_history` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`old_route` varchar(255) NOT NULL,`new_route` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `old_route` (`old_route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_settings`;
CREATE TABLE `bigtree_settings` (`id` varchar(255) NOT NULL DEFAULT '',`value` text NOT NULL,`type` varchar(255) NOT NULL,`name` varchar(255) NOT NULL DEFAULT '',`description` text NOT NULL,`locked` char(2) NOT NULL,`system` char(2) NOT NULL,`encrypted` char(2) NOT NULL,`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
INSERT INTO `bigtree_settings` (`id`, `value`, `type`, `name`, `description`, `locked`, `module`, `system`, `encrypted`, `package`) VALUES ('resource-thumbnail-sizes','\"{\\\"Small\\\":{\\\"width\\\":\\\"150\\\",\\\"height\\\":\\\"100\\\",\\\"prefix\\\":\\\"small_\\\"},\\\"Medium\\\":{\\\"width\\\":\\\"300\\\",\\\"height\\\":\\\"200\\\",\\\"prefix\\\":\\\"medium_\\\"},\\\"Large\\\":{\\\"width\\\":\\\"800\\\",\\\"height\\\":\\\"600\\\",\\\"prefix\\\":\\\"large_\\\"}}\"','textarea','Resource Thumbnail Sizes','<p>A JSON-encoded array of different thumbnail sizes to make for images uploaded through the file browser.&nbsp; Each key in the array is the description of the crop (i.e. \"Small\") which points to an array with the keys \"width\" and \"height\" (for the thumbnail\'s max width and height) and \"prefix\" (i.e \"small_\").</p>','on',0,'','',0);

DROP TABLE IF EXISTS `bigtree_tags`;
CREATE TABLE `bigtree_tags` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`tag` varchar(255) NOT NULL,`metaphone` varchar(255) NOT NULL,`route` varchar(255) DEFAULT NULL,PRIMARY KEY (`id`),KEY `route` (`route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_tags_rel`;
CREATE TABLE `bigtree_tags_rel` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`module` int(11) unsigned NOT NULL,`tag` int(11) unsigned NOT NULL,`entry` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `module` (`module`),KEY `tag` (`tag`),KEY `entry` (`entry`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_templates`;
CREATE TABLE `bigtree_templates` (`id` varchar(255) NOT NULL DEFAULT '',`name` varchar(255) NOT NULL DEFAULT '',`description` text NOT NULL,`routed` char(2) NOT NULL,`image` varchar(255) NOT NULL DEFAULT '',`resources` text NOT NULL,`callouts_enabled` char(2) NOT NULL DEFAULT '',`module` int(11) unsigned NOT NULL,`level` int(11) unsigned NOT NULL,`position` int(11) unsigned NOT NULL DEFAULT '0',`package` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `routed` (`routed`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `bigtree_users`;
CREATE TABLE `bigtree_users` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`email` varchar(255) CHARACTER SET latin1 NOT NULL DEFAULT '',`password` varchar(255) CHARACTER SET latin1 NOT NULL,`name` varchar(255) CHARACTER SET latin1 NOT NULL DEFAULT '',`company` varchar(255) CHARACTER SET latin1 NOT NULL DEFAULT '',`level` int(11) unsigned NOT NULL DEFAULT '0',`permissions` text CHARACTER SET latin1 NOT NULL,`alerts` text NOT NULL,`daily_digest` char(2) NOT NULL,`change_password_hash` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `email` (`email`),KEY `password` (`password`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `bigtree_pages` (`id`, `parent`, `in_nav`, `nav_title`, `route`, `path`, `title`, `meta_keywords`, `meta_description`, `template`, `external`, `new_window`, `resources`, `callouts`, `archived`, `archived_inherited`, `locked`, `position`, `created_at`, `updated_at`, `publish_at`, `expire_at`, `max_age`, `last_edited_by`, `ga_page_views`) VALUES (0,-1,'on','BigTree Site','','','BigTree Site','','','home','','','{}','[]','','','',0,'0000-00-00 00:00:00','0000-00-00 00:00:00',NULL,NULL,0,0,0);
INSERT INTO `bigtree_settings` ('id','value','system') VALUES ('bigtree-internal-upload-service','{"service":"local"}','on');
INSERT INTO `bigtree_templates` (`id`, `name`, `image`, `module`, `resources`, `position`, `description`, `callouts_enabled`, `level`, `package`, `routed`) VALUES ('home', 'Home', 'page.png', 0, '[]', 2, 'Home Page', '', 0, 0, ''), ('content', 'Content', 'page.png', 0, '[{"id":"page_header","title":"Page Header","subtitle":"","type":"text","validation":"required","seo_h1":"on","sub_type":"","wrapper":"","name":""},{"id":"page_content","title":"Page Content","subtitle":"","type":"html","validation":"required","seo_body":"on","wrapper":"","name":""}]', 1, 'Master Content', 'on', 0, 0, '');