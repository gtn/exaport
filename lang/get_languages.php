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
// $langPaths = array_diff($langPaths, array('de_du_utf8', 'en_utf8'));

$translations = array();

foreach ($langPaths as $langPath) {
	$strings = getTranslations($langPath);

	$translations[] = $strings['translation:language'].' ('.str_replace('_utf8', '', $langPath).')';
}

echo 'Translations: '.join(', ', $translations);
