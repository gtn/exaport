<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

$defaultLang = 'en_utf8';

function getTranslationStructure($language)
{
	$content = file_get_contents($language.'/block_exaport.php');
	$content = str_replace("\r", "", trim($content));

	$allGroups = preg_split("!\n\n+!", $content);
	array_shift($allGroups); // delete first group
	$groups = array();

	foreach ($allGroups as $group) {
		$group = array(
			'name' => '',
			'strings' => array(),
			'content' => $group,
		);

		if (preg_match('!^//([^\$]*)!', $group['content'], $matches)) {
			$group['name'] = trim($matches[1]);
		}

		$string = array();
		eval($group['content']);
		$group['strings'] = array_keys($string);

		if (empty($group['strings']))
			continue;

		$groups[] = $group;
	}

	return $groups;
}

function getTranslations($language)
{
	$string = array();
	$stringNotUsed = array();

	if (file_exists($language.'/block_exaport.php')) {
		require ($language.'/block_exaport.php');
	} else {
		require ($language.'/block_exaport.orig.php');
	}

	return $string + $stringNotUsed;
}




$langPaths = glob('*_utf8');

// ignore these paths
$langPaths = array_diff($langPaths, array('de_du_utf8'));


$translationGroups = getTranslationStructure($defaultLang);

$fileStart = '<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 GTN - Global Training Network GmbH <office@gtn-solutions.com>
*  All rights reserved
*
*  You can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This module is based on the Collaborative Moodle Modules from
*  NCSA Education Division (http://www.ncsa.uiuc.edu)
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
';

foreach ($langPaths as $langPath) {
	$strings = getTranslations($langPath);

	echo 'now: '.$langPath."<br />\n";

	$fileContent = $fileStart;

	foreach ($translationGroups as $group) {
		$fileContent .= "\n// ".$group['name']."\n";
		foreach ($group['strings'] as $groupString) {
			$fileContent .= '$string[\''.$groupString.'\'] = '.var_export($strings[$groupString], true).";\n";
			unset($strings[$groupString]);
		}
	}

	if ($strings) {
		$fileContent .= "\n// ".'Not Used Anymore'."\n";
		foreach ($strings as $key => $value) {
			if (!$value) {
				continue;
			}
			$fileContent .= '$stringNotUsed[\''.$key.'\'] = '.var_export($value, true).";\n";
		}
	}

	/*
	if (!file_exists($langPath.'/block_exaport.php.tmp')) {
		rename($langPath.'/block_exaport.php', $langPath.'/block_exaport.php.tmp');
	}
	*/

	file_put_contents($langPath.'/block_exaport.php', $fileContent);
}
