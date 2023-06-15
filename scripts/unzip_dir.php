#!/usr/bin/php
<?php
/* Copyright (C) 2023 Laurent Destailleur	<eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 *
 * Update an instance on stratus5 server with new ref version.
 */

/**
 *  \file       sellyoursaas/scripts/unzip_dir.php
 *  \ingroup    sellyoursaas
 *  \brief      Script to fix bad zips
 */


/**
 *
 * @param string	$dirName	Name of dir
 * @return string[]
 */
function dirIterator($dirName)
{
	$dirlist = array();
	$whatsInsideDir = scandir($dirName);
	foreach ($whatsInsideDir as $fileOrDir) {
		if ($fileOrDir == '.' || $fileOrDir == '..') {
			continue;
		}
		$dirlist[] = $dirName.'/'.$fileOrDir;
		if (is_dir($fileOrDir)) {
			$dirlist = array_merge($dirlist, dirIterator($dirName.'/'.$fileOrDir));
		}
	}
	return $dirlist;
}


/*
 * Main
 */

$listofdirs = dirIterator('.');


foreach ($listofdirs as $file) {
	$filebis = str_replace('\\', '/', $file);
	print "Process path $file => dir ".dirname($filebis)."\n";
	if (!preg_match('/\\\\/', $file) && !preg_match('/\//', $file)) {
		continue;
	}

	$tmparray = preg_split('/[\/\\\\]/', dirname($filebis));
	if (isset($tmparray[1])) {
		$s = "";
		foreach ($tmparray as $aaa) {
			if ($aaa == '.' || $aaa == '..') {
				continue;
			}
			$s = ($s ? $s."/" : "").$aaa;
			print "Create dir $s\n";
			@mkdir($s);
		}
		print "Dir $s has been created.";
		if (is_file($file)) {
			print " Now rename $file into ".$s.'/'.basename($filebis)."\n";
			rename($file, $s.'/'.basename($filebis));
		} else {
			print "\n";
		}
	}
}

// Delete dirs if empty
foreach ($listofdirs as $file) {
	if (is_dir($file)) {
		print "Clean ".$file." ?\n";
		if (count(scandir($file)) == 2) {
			print "Dir ".$file." is empty. We delete it\n";
			rmdir($file);
		}
	}
}

print "Complete.\n";
