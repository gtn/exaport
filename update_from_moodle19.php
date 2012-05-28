<?php

require dirname(__FILE__) . '/inc.php';

require_login();

// TODO check admin!

// test database
/*
DROP TABLE IF EXISTS `mdl_block_exabeporcate`;
CREATE TABLE `mdl_block_exabeporcate` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` bigint(10) unsigned DEFAULT '0',
  `userid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `timemodified` bigint(10) unsigned NOT NULL DEFAULT '0',
  `courseid` bigint(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `mdl_blocexab_use_ix` (`userid`),
  KEY `mdl_blocexab_pid_ix` (`pid`),
  KEY `mdl_blocexab_cou_ix` (`courseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='categories for e-portfolio items';

INSERT INTO `mdl_block_exabeporcate` (`id`, `pid`, `userid`, `name`, `timemodified`, `courseid`) VALUES
(1,	0,	2,	'main category',	1338120965,	0),
(2,	1,	2,	'subcategory',	1338120952,	0),
(3,	0,	2,	'main category 2',	1338120980,	0);

DROP TABLE IF EXISTS `mdl_block_exabeporitem`;
CREATE TABLE `mdl_block_exabeporitem` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `type` enum('link','file','note') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'note',
  `categoryid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `intro` text COLLATE utf8_unicode_ci NOT NULL,
  `attachment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `timemodified` bigint(10) unsigned NOT NULL DEFAULT '0',
  `courseid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `shareall` smallint(3) unsigned NOT NULL DEFAULT '0',
  `externaccess` smallint(3) unsigned NOT NULL DEFAULT '0',
  `externcomment` smallint(3) unsigned NOT NULL DEFAULT '0',
  `sortorder` bigint(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mdl_blocexab_use2_ix` (`userid`),
  KEY `mdl_blocexab_cou2_ix` (`courseid`),
  KEY `mdl_blocexab_cat_ix` (`categoryid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='User items';

INSERT INTO `mdl_block_exabeporitem` (`id`, `userid`, `type`, `categoryid`, `name`, `url`, `intro`, `attachment`, `timemodified`, `courseid`, `shareall`, `externaccess`, `externcomment`, `sortorder`) VALUES
(1,	2,	'file',	1,	'a file to test',	'',	'',	'pic_146.jpg',	1338120860,	1,	0,	0,	0,	NULL),
(2,	2,	'link',	2,	'link 六號',	'http://exabis.at/',	'<div>description</div>',	'',	1338121044,	1,	0,	0,	0,	NULL),
(3,	2,	'note',	3,	'this is a note',	'',	'NOTE\r\n',	'',	1338121040,	1,	0,	0,	0,	NULL);

DROP TABLE IF EXISTS `mdl_block_exabeporitemcomm`;
CREATE TABLE `mdl_block_exabeporitemcomm` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `itemid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `userid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `entry` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `timemodified` bigint(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `mdl_blocexab_use4_ix` (`userid`),
  KEY `mdl_blocexab_ite2_ix` (`itemid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='comments for the items';


DROP TABLE IF EXISTS `mdl_block_exabeporitemshar`;
CREATE TABLE `mdl_block_exabeporitemshar` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `itemid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `userid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `original` bigint(10) unsigned NOT NULL DEFAULT '0',
  `courseid` bigint(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `mdl_blocexab_use3_ix` (`userid`),
  KEY `mdl_blocexab_cou3_ix` (`courseid`),
  KEY `mdl_blocexab_ite_ix` (`itemid`),
  KEY `mdl_blocexab_ori_ix` (`original`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='block_exabeporitemshar table retrofitted from MySQL';


DROP TABLE IF EXISTS `mdl_block_exabeporuser`;
CREATE TABLE `mdl_block_exabeporuser` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(10) unsigned NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `persinfo_timemodified` bigint(10) unsigned NOT NULL DEFAULT '0',
  `persinfo_externaccess` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `itemsort` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `user_hash` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mdl_blocexab_use_uix` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='user in the e-portfolio';

INSERT INTO `mdl_block_exabeporuser` (`id`, `user_id`, `description`, `persinfo_timemodified`, `persinfo_externaccess`, `itemsort`, `user_hash`) VALUES
(1,	2,	'personal info 我是誰？',	1338120808,	0,	'date.desc',	'25523590');

DROP TABLE IF EXISTS `mdl_block_exabeporview`;
CREATE TABLE `mdl_block_exabeporview` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` bigint(10) unsigned DEFAULT NULL,
  `name` text COLLATE utf8_unicode_ci,
  `description` longtext COLLATE utf8_unicode_ci,
  `timemodified` bigint(10) unsigned DEFAULT NULL,
  `shareall` smallint(3) unsigned DEFAULT NULL,
  `externaccess` smallint(3) unsigned DEFAULT NULL,
  `externcomment` smallint(3) unsigned DEFAULT NULL,
  `hash` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mdl_blocexab_has_uix` (`hash`),
  KEY `mdl_blocexab_use5_ix` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='views';

INSERT INTO `mdl_block_exabeporview` (`id`, `userid`, `name`, `description`, `timemodified`, `shareall`, `externaccess`, `externcomment`, `hash`) VALUES
(1,	2,	'a test view',	'description',	1338121079,	1,	1,	0,	'cf2d2441');

DROP TABLE IF EXISTS `mdl_block_exabeporviewblock`;
CREATE TABLE `mdl_block_exabeporviewblock` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `viewid` bigint(10) unsigned DEFAULT NULL,
  `positionx` bigint(10) unsigned DEFAULT NULL,
  `positiony` bigint(10) unsigned DEFAULT NULL,
  `type` text COLLATE utf8_unicode_ci,
  `itemid` bigint(10) unsigned DEFAULT NULL,
  `text` longtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `mdl_blocexab_vie_ix` (`viewid`),
  KEY `mdl_blocexab_ite3_ix` (`itemid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Blocks on the view';

INSERT INTO `mdl_block_exabeporviewblock` (`id`, `viewid`, `positionx`, `positiony`, `type`, `itemid`, `text`) VALUES
(4,	1,	1,	1,	'headline',	NULL,	'HEADLINE'),
(5,	1,	1,	2,	'item',	1,	NULL),
(6,	1,	2,	1,	'item',	3,	NULL);

DROP TABLE IF EXISTS `mdl_block_exabeporviewshar`;
CREATE TABLE `mdl_block_exabeporviewshar` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `viewid` bigint(20) unsigned DEFAULT NULL,
  `userid` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mdl_blocexab_vie2_ix` (`viewid`),
  KEY `mdl_blocexab_use6_ix` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='information to which users the view is shared';


INSERT INTO `mdl_block_exaportcate` (`id`, `pid`, `userid`, `name`, `timemodified`, `courseid`) VALUES
(1,	0,	2,	'main category',	1338120965,	0),
(2,	1,	2,	'subcategory',	1338120952,	0),
(3,	0,	2,	'main category 2',	1338120980,	0);
*/

echo "upgrading<br />\n";

try {
	$transaction = $DB->start_delegated_transaction();
	
	$tables = array(
		// old => new
		// we use full sql queries here, so if we update the tables (add columns, we don't have to update this upgrade script!
		'block_exabeporcate' => array(
			'block_exaportcate',
			'id, pid, userid, name, timemodified, courseid'),
		'block_exabeporitem' => array(
			'block_exaportitem',
			'id, userid, type, categoryid, name, url, intro, attachment, timemodified, courseid, shareall, externaccess, externcomment, sortorder'),
		'block_exabeporitemcomm' => array(
			'block_exaportitemcomm',
			'id, itemid, userid, entry, timemodified'),
		'block_exabeporitemshar' => array(
			'block_exaportitemshar',
			'id, itemid, userid, original, courseid'),
		'block_exabeporuser' => array(
			'block_exaportuser',
			'id, user_id, description, persinfo_timemodified, persinfo_externaccess, itemsort, user_hash'),
		'block_exabeporview' => array(
			'block_exaportview',
			'id, userid, name, description, timemodified, shareall, externaccess, externcomment, hash'),
		'block_exabeporviewblock' => array(
			'block_exaportviewblock',
			'id, viewid, positionx, positiony, type, itemid, text'),
		'block_exabeporviewshar' => array(
			'block_exaportviewshar',
			'id, viewid, userid'),
	);
	
	foreach ($tables as $oldTable=>$options) {
		$newTable = $options[0];
		$columns = $options[1];
		
		echo "now table ".$newTable.": ";
		$num = $DB->get_field_sql('SELECT COUNT(*) FROM {'.$newTable.'}');
		if ($num) {
			echo "<span style='color: red'>table already filled</span><br />\n";
		} else {
			$sql = 'INSERT INTO {'.$newTable.'} ('.$columns.') SELECT '.$columns.' FROM {'.$oldTable.'}';
			$DB->execute($sql);
			echo "OK!<br />\n";
		}
	}
	
	// update files
	$fs = get_file_storage();

	foreach ($files = $DB->get_records('block_exaportitem', array('type'=>'file')) as $file) {
		if ($file->attachment && !preg_match('!^[0-9]+$!', $file->attachment)) {
			// import if it's not already a number (number means it is a file id and not a name)
			
			$filepath = $CFG->dataroot . '/exabis_eportfolio/files/'.$file->userid.'/'.$file->id.'/'.$file->attachment;
			if (!file_exists($filepath)) {
				echo "file not found: ".$filepath."<br />\n";
				continue;
			}
		 
			// Prepare file record object
			$fileinfo = array(
				'contextid' => get_context_instance(CONTEXT_USER, $file->userid)->id,    // ID of context
				'component' => 'block_exaport', // usually = table name
				'filearea' => 'item_file',     // usually = table name
				'itemid' => $file->id,          // usually = ID of row in table
				'filepath' => '/',              // any path beginning and ending in /
				'filename' => $file->attachment,
				'userid' => $file->userid);
 
			$ret = $fs->create_file_from_pathname($fileinfo, $filepath);
			
			$update = new stdClass();
			$update->id         = $file->id;
			$update->attachment = '';
			$DB->update_record('block_exaportitem', $update);

			echo "file imported: ".$filepath."<br />\n";
		}
	}
 
	// Assuming the both inserts work, we get to the following line.
	$transaction->allow_commit();
	
	echo "upgrade done<br />\n";
	
} catch(Exception $e) {
	var_dump($e);
	die($e->getMessage());
	$transaction->rollback($e);
}

 
