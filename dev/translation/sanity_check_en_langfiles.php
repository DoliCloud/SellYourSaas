<?php
/* Copyright (c) 2015 Tommaso Basilici <t.basilici@19.coop>
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
*/

echo "<html>";
echo "<head>";

echo "<STYLE type=\"text/css\">

table {
	background: #f5f5f5;
	border-collapse: separate;
	box-shadow: inset 0 1px 0 #fff;
	font-size: 12px;
	line-height: 24px;
	margin: 30px auto;
	text-align: left;
	width: 800px;
}

th {
	background-color: #777;
	border-left: 1px solid #555;
	border-right: 1px solid #777;
	border-top: 1px solid #555;
	border-bottom: 1px solid #333;
	color: #fff;
  	font-weight: bold;
	padding: 10px 15px;
	position: relative;
	text-shadow: 0 1px 0 #000;
}

td {
	border-right: 1px solid #fff;
	border-left: 1px solid #e8e8e8;
	border-top: 1px solid #fff;
	border-bottom: 1px solid #e8e8e8;
	padding: 10px 15px;
	position: relative;
}


tr {
	background-color: #f1f1f1;

}

tr:nth-child(odd) td {
	background-color: #f1f1f1;
}

</STYLE>";

echo "<body>";

echo "If you call this file with the argument \"?unused=true\" it searches for the translation strings that exist in en_US but are never used.<br>";
echo "IMPORTANT: that can take quite a lot of time (up to 10 minutes), you need to tune the max_execution_time on your php.ini accordingly.<br>";
echo "Happy translating :)<br>";


// STEP 1 - Search duplicates keys


// directory containing the php and lang files
$htdocs 	= "../../htdocs/";

// directory containing the english lang files
$workdir 	= $htdocs."langs/en_US/";


$files = scandir($workdir);
if (empty($files)) {
	echo "Can't scan workdir = ".$workdir;
	exit;
}

$exludefiles = array('.','..','README');
$files = array_diff($files, $exludefiles);
$langstrings_3d = array();
$langstrings_full = array();
foreach ($files as $file) {
	$path_file = pathinfo($file);
	// we're only interested in .lang files
	if ($path_file['extension']=='lang') {
		$content = file($workdir.$file);
		foreach ($content as $line => $row) {
			// don't want comment lines
			if (substr($row, 0, 1) !== '#') {
				// don't want lines without the separator (why should those even be here, anyway...)
				if (strpos($row, '=')!==false) {
					$row_array = explode('=', $row);		// $row_array[0] = key
					$langstrings_3d[$path_file['basename']][$line+1]=$row_array[0];
					$langstrings_3dtrans[$path_file['basename']][$line+1]=$row_array[1];
					$langstrings_full[]=$row_array[0];
					$langstrings_dist[$row_array[0]]=$row_array[0];
				}
			}
		}
	}
}

foreach ($langstrings_3d as $filename => $file) {
	foreach ($file as $linenum => $value) {
		$keys = array_keys($langstrings_full, $value);
		if (count($keys)>1) {
			foreach ($keys as $key) {
				$dups[$value][$filename][$linenum] = trim($langstrings_3dtrans[$filename][$linenum]);
			}
		}
	}
}

echo "<h2>Duplicate strings in lang files in $workdir - ".count($dups)." found</h2>";

echo "<table border_bottom=1> ";
echo "<thead><tr><th align=\"center\">#</th><th>String</th><th>File and lines</th></thead>";
echo "<tbody>";
$count = 0;
foreach ($dups as $string => $pages) {
	$count++;
	echo "<tr>";
	echo "<td align=\"center\">$count</td>";
	echo "<td>$string</td>";
	echo "<td>";
	foreach ($pages as $page => $lines) {
		echo "$page ";
		foreach ($lines as $line => $translatedvalue) {
			//echo "($line - ".(substr($translatedvalue,0,20)).") ";
			echo "($line - ".htmlentities($translatedvalue).") ";
		}
		echo "<br>";
	}
	echo "</td></tr>\n";
}
echo "</tbody>";
echo "</table>";


// STEP 2 - Search key not used


if (! empty($_REQUEST['unused']) && $_REQUEST['unused'] == 'true') {
	foreach ($langstrings_dist as $value) {
		$search = '\'trans("'.$value.'")\'';
		$string =  'grep -R -m 1 -F --exclude=includes/* --include=*.php '.$search.' '.$htdocs.'*';
		exec($string, $output);
		if (empty($output)) {
			$unused[$value] = true;
			echo $value.'<br>';
		}
	}

	echo "<h2>Strings in en_US that are never used</h2>";
	echo "<pre>";
	print_r($unused);
	echo "</pre>";
}

echo "\n";
echo "</body>";
echo "</html>";
