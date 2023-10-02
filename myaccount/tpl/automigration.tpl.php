<?php
/* Copyright (C) 2011-2018 Laurent Destailleur <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

?>
<!-- BEGIN PHP TEMPLATE automigration.tpl.php -->
<?php

$idcontract = "0";
if (GETPOST('instanceselect', 'alpha')) {
	$instanceselect = GETPOST('instanceselect', 'alpha');
	$instanceselect = explode("_", $instanceselect);
	$idcontract = $instanceselect[1];
}

$upload_dir = $conf->sellyoursaas->dir_temp."/automigration_".$idcontract.'.tmp';
$filenames = array();
$fileverification = array();
$stepautomigration = 0;
$backtopagesupport = $_SERVER["PHP_SELF"].'?action=presend&mode=support&backfromautomigration=backfromautomigration&token='.newToken().'&contractid='.GETPOST('contractid', 'alpha').'&supportchannel='.GETPOST('supportchannel', 'alpha').'&ticketcategory_child_id='.(GETPOST('ticketcategory_child_id_back', 'alpha')?GETPOST('ticketcategory_child_id', 'alpha'):'').'&ticketcategory='.(GETPOST('ticketcategory_back', 'alpha')?GETPOST('ticketcategory', 'alpha'):'').'&subject'.(GETPOST('subject_back', 'alpha')?GETPOST('subject', 'alpha'):'').'#supportform';

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST" id="migrationFormbacksupport">';
	print '<input type="hidden" name="action" value="presend">';
	print '<input type="hidden" name="mode" value="support">';
	print '<input type="hidden" name="contractid" value="'.GETPOST('contractid', 'alpha').'">';
	print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel', 'alpha').'">';
	print '<input type="hidden" name="backfromautomigration" value="backfromautomigration">';
	print '<input type="hidden" name="ticketcategory_child_id" value="'.(GETPOST('ticketcategory_child_id_back', 'alpha')?:GETPOST('ticketcategory_child_id', 'alpha')).'">';
	print '<input type="hidden" name="ticketcategory" value="'.(GETPOST('ticketcategory_back', 'alpha')?:GETPOST('ticketcategory', 'alpha')).'">';
	print '<input type="hidden" name="subject" value="'.(GETPOST('subject_back', 'alpha')?:GETPOST('subject', 'alpha')).'">';
print '</form>';

if (!empty($_POST['addfile']) && empty($_POST['flowjsprocess'])) {
	// Set tmp user directory
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_add_file_process($upload_dir, 1, 0);
}

if ($action == 'fileverification') {
	$filenames = array();
	$types = array();
	if (!empty($_POST['flowjsprocess'])) {
		$filenames["sql"] = GETPOST("sqldumpfilename", "alpha");
		$filenames["document"] = GETPOST("documentdumpfilename", "alpha");
		$types["sql"] = GETPOST("sqldumpfiletype", "alpha");
		$types["document"] = GETPOST("documentdumpfiletype", "alpha");
	} else {
		$filenames = $_FILES['addedfile']['name'];
		$types = $_FILES['addedfile']['type'];
	}
	$fileverification=array(array("error"=>array()),array("error"=>array()));
	$filetoverify = array();
	foreach ($filenames as $key => $filename) {
		$pathinfo = pathinfo($upload_dir.'/'.$filename);
		if ( $types[$key] == 'application/gzip') {
			if (pathinfo($pathinfo['filename'])['extension'] == "sql") {
				$contents=file_get_contents($upload_dir.'/'.$filename);
				$uncompressed = gzuncompress($contents);
				$fileuncompressed = fopen($upload_dir.'/'.$pathinfo['filename'], 'wb');
				fwrite($fileuncompressed, $uncompressed);
				fclose($fp);
				$filetoverify["sql"] = $pathinfo['filename'];
			} else {
				if (!file_exists($upload_dir.'/'.$pathinfo['filename'])) {
					$p = new PharData($upload_dir.'/'.$filename);
					$p->decompress();
				}
				$phar = new PharData($upload_dir.'/'.$pathinfo['filename']);
				$pathinfo = pathinfo($upload_dir.'/'.$pathinfo['filename']);
				$phar->extractTo($upload_dir.'/', null, true);
				$filetoverify["dir"] = $filename;
			}
		} elseif ( $types[$key] == 'application/x-bzip') {
			$extensionfile = pathinfo($pathinfo['filename'])['extension'];
			if ($extensionfile == "sql") {
				$contents = file_get_contents($upload_dir.'/'.$filename);
				$uncompressed = bzdecompress($contents);
				$fileuncompressed = fopen($upload_dir.'/'.$pathinfo['filename'], 'wb');
				fwrite($fileuncompressed, $uncompressed);
				fclose($fp);
				$filetoverify["sql"] = $pathinfo['filename'];
			} else {
				if (!file_exists($upload_dir.'/'.$pathinfo['filename'])) {
					$p = new PharData($upload_dir.'/'.$filename);
					$p->decompress();
				}
				$phar = new PharData($upload_dir.'/'.$pathinfo['filename']);
				$pathinfo = pathinfo($upload_dir.'/'.$pathinfo['filename']);
				$phar->extractTo($upload_dir.'/', null, true);
				$filetoverify["dir"] = $filename;
			}
		} elseif ( $types[$key] == 'application/zip') {
			$result = dol_uncompress($upload_dir.'/'.$filename, $upload_dir);
			if (!empty($result)) {
				$error = array("error"=>array("errorcode" => $result));
			} else {
				$filetoverify["dir"] = $filename;
			}
		} else {
			if ($types[$key] != 'application/sql') {
				$error = array("error"=>array("errorcode" =>"WrongFileExtension","errorextension" => $types[$key],"errorfilename" => $pathinfo['basename']));
			} else {
				$filetoverify["sql"] = $pathinfo['basename'];
			}
		}
		if (!empty($error)) {
			$fileverification[$key]['error'][]=$error["error"];
		}
	}
	if (empty($error)) {
		$filecontent = file_get_contents($upload_dir.'/'.$filetoverify["sql"]);
		if (empty(preg_match('/'.preg_quote('-- Dump completed').'/i', $filecontent))) {
			$error = array("error"=>array("errorcode" =>"ErrorOnSqlDumpForge"));
		}
		/*if (empty(preg_match('/llx_/i', $filecontent))) {
			$error = array("error"=>array("errorcode" =>"ErrorOnSqlPrefix"));
		}*/
		if (!empty($error)) {
			$fileverification[0]['error'][]=$error["error"];
		}
	}
}

if ($action == 'automigration') {
	require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
	require_once DOL_DOCUMENT_ROOT."/core/class/utils.class.php";
	dol_include_once('sellyoursaas/class/sellyoursaasutils.class.php');
	dol_include_once('sellyoursaas/class/sellyoursaascontract.class.php');

	$utils = new Utils($db);

	$sellyoursaasutils = new SellYourSaasUtils($db);
	$instanceselect = GETPOST('instanceselect', 'alapha');
	$instanceselect = explode("_", $instanceselect);
	$idcontract = $instanceselect[1];

	$object = new SellYourSaasContract($db);
	$object->fetch($idcontract);

	$hostname_db  = $object->array_options['options_hostname_db'];
	$username_db  = $object->array_options['options_username_db'];
	$password_db  = $object->array_options['options_password_db'];
	$database_db  = $object->array_options['options_database_db'];
	$port_db      = (!empty($object->array_options['options_port_db']) ? $object->array_options['options_port_db'] : 3306);
	$prefix_db    = (!empty($object->array_options['options_prefix_db']) ? $object->array_options['options_prefix_db'] : 'llx_');
	$hostname_os  = $object->array_options['options_hostname_os'];
	$username_os  = $object->array_options['options_username_os'];
	$password_os  = $object->array_options['options_password_os'];
	$username_web = $object->thirdparty->email;
	$password_web = $object->thirdparty->array_options['options_password'];

	$tmp = explode('.', $object->ref_customer, 2);
	$object->instance = $tmp[0];

	$object->hostname_db  = $hostname_db;
	$object->username_db  = $username_db;
	$object->password_db  = $password_db;
	$object->database_db  = $database_db;
	$object->port_db      = $port_db;
	$object->prefix_db    = $prefix_db;
	$object->username_os  = $username_os;
	$object->password_os  = $password_os;
	$object->hostname_os  = $hostname_os;
	$object->username_web = $username_web;
	$object->password_web = $password_web;

	$sqlfiletomigrate = GETPOST('sqlfilename', 'alpha');
	$dirfiletomigrate = GETPOST('dirfilename', 'alpha');
	$object->array_options['automigrationdocumentarchivename'] = $dirfiletomigrate;
	$exitcode = 0;

	//Sql prefix process To test
	$sqlfilepath = dol_sanitizePathName($upload_dir).'/'.dol_sanitizeFileName($sqlfiletomigrate);
	$sqlcontent = file_get_contents($sqlfilepath);
	$matches = array();
	$result = array();
	$result["result"] = preg_match('/table `([a-zA-Z0-9]+_)/i', $sqlcontent, $matches);
	if ($result["result"] <= 0) {
		setEventMessages($langs->trans("ErrorOnSqlPrefixProcess"), null, "errors");
	} else {
		if ($matches[1] != $prefix_db) {
			$oldprefix = $matches[1];
			$sqlcontentnew = preg_replace('/`'.$oldprefix.'/i', '`'.$prefix_db, $sqlcontent);
			if (empty($sqlcontentnew) || $sqlcontentnew == $sqlcontent) {
				$result["result"] = -1;
				$result["output"] = $langs->trans("ErrorOnSqlPrefixProcessReplace");
				setEventMessages($langs->trans("ErrorOnSqlPrefixProcessReplace"), null, "errors");
			} else {
				$fhandle = @fopen($sqlfilepath, 'w');
				if ($fhandle) {
					$result["result"] = fwrite($fhandle, $sqlcontentnew);
					fclose($fhandle);
				} else {
					$result["result"] = -1;
					$result["output"] = $langs->trans("ErrorOnSqlPrefixProcessWrite");
					setEventMessages($langs->trans("ErrorOnSqlPrefixProcessWrite"), null, "errors");
				}
			}
		}
		//Backup old database
		$mysqlbackupfilename=$upload_dir.'/mysqldump_'.$database_db.'_'.dol_print_date(dol_now(), 'dayhourlog').'.sql';
		$param = array();
		$command = "mysqldump";
		$param[] = "--column-statistics=0"; // Remove a new flag with mysqldump v8
		$param[] = "--no-tablespaces";
		$param[] = "-C";
		$param[] = "-h";
		$param[] = $hostname_db;
		$param[] = "-P";
		$param[] = (! empty($port_db) ? $port_db : "3306");
		$param[] = "-u";
		$param[] = $username_db;
		$param[] = '-p"'.str_replace(array('"','`'), array('\"','\`'), $password_db).'"';
		$param[] = $database_db;
		$mysqlbackupcommand=$command." ".join(" ", $param);

		$result = $utils->executeCLI($mysqlbackupcommand, "", 0, $mysqlbackupfilename);

		if ($result["result"] != 0) {
			if (empty($result["output"])) {
				$result["output"] = $langs->trans("ErrorOnDatabaseBackup");
			}
		}

		//Drop llx_accounting_system and llx_accounting_account to prevent load error
		if ($result["result"] == 0) {
			$mysqlcommand='echo "drop table llx_accounting_system;" | mysql -A -C -u '.$username_db.' -p\''.$password_db.'\' -h '.$hostname_db.' '.$database_db;
			$result = $utils->executeCLI($mysqlcommand, "", 0, null);
			if ($result["result"] != 0) {
				if (empty($result["output"])) {
					$result["output"] = $langs->trans("ErrorOnDatabaseBackup");
				}
				setEventMessages($langs->trans("ErrorOnDropingTables"), null, "errors");
			} else {
				$mysqlcommand='echo "drop table llx_accounting_account;" | mysql -A -C -u '.$username_db.' -p\''.$password_db.'\' -h '.$hostname_db.' '.$database_db;
				$result = $utils->executeCLI($mysqlcommand, "", 0, null);
				if ($result["result"] != 0) {
					if (empty($result["output"])) {
						$result["output"] = $langs->trans("ErrorOnDropingTables");
					}
					setEventMessages($langs->trans("ErrorOnDropingTables"), null, "errors");
				} else {
					$mysqlcommand='echo "drop table llx_accounting_account;" | mysql -A -C -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' '.$object->database_db;
					$result = $utils->executeCLI($mysqlcommand, "", 0, null);
					if ($result["result"] != 0) {
						if (empty($result["output"])) {
							$result["output"] = $langs->trans("ErrorOnDropingTables");
						}
						setEventMessages($langs->trans("ErrorOnDropingTables"), null, "errors");
					}
				}
			}

			if ($result["result"] == 0) {
				$mysqlcommand='mysql -C -A -u '.$username_db.' -p\''.$password_db.'\' -h '.$hostname_db.' -D '.$database_db.' < '.escapeshellcmd(dol_sanitizePathName($upload_dir).'/'.dol_sanitizeFileName($sqlfiletomigrate));
				$result = $utils->executeCLI($mysqlcommand, "", 0, null, 1);
				if ($result["result"] != 0) {
					if (empty($result["output"])) {
						$result["output"] = $langs->trans("ErrorOnDatabaseMigration");
					}
					setEventMessages($langs->trans("ErrorOnDatabaseMigration"), null, "errors");
				}

				if ($result["result"] == 0) {
					$exitcode = $sellyoursaasutils->sellyoursaasRemoteAction("migrate", $object);
					if ($exitcode < 0) {
						$result["result"] = $exitcode;
						$result["output"] = $langs->trans("ErrorOnDocumentMigration");
						setEventMessages($langs->trans("ErrorOnDocumentMigration"), null, "errors");
					}
				}

				if ($result["result"] != 0) {
					$mysqlcommand='mysql -C -A -u '.$username_db.' -p\''.$password_db.'\' -h '.$hostname_db.' -D '.$database_db.' < '.$mysqlbackupfilename;
					$utils->executeCLI($mysqlcommand, "", 0, null, 1);
				}
			}
		}
	}
}
$linkstep1img="img/sellyoursaas_automigration_step1.png";
$linkstep2img="img/sellyoursaas_automigration_step2.png";
print '
<div id="Step1"></div>
<div class="page-content-wrapper">
    <div class="page-content">
    <!-- BEGIN PAGE HEADER-->
    <!-- BEGIN PAGE HEAD -->
        <div class="page-head">
        <!-- BEGIN PAGE TITLE -->
            <div class="page-title topmarginstep">
            <h1>'.$langs->trans("Automigration").' <small>'.$langs->trans("AutomigrationDesc").'</small></h1>
            </div>
        <!-- END PAGE TITLE -->
        </div>
    <!-- END PAGE HEAD -->
    <!-- END PAGE HEADER-->';

print'
    <div class="page-body">
    <div class="row" id="choosechannel">
    <div class="col-md-12">';

if ($action == 'fileverification') {
	print '<!-- BEGIN STEP5-->';
	print '<div class="portlet light" id="Step5">
        <h2>'.$langs->trans("Step", 5).' - '.$langs->trans("FileVerification").'</h2><br>
        <div style="display:flex;justify-content:space-evenly;">';
	for ($i=0; $i < 2; $i++) {
		if ($i == 0) {
			print '<div style="width:50%;margin-right:20px">';
		} else {
			print '<div style="width:50%;margin-left:20px">';
		}

		if ($i == 0) {
			print $langs->trans('DatabaseFile').':<br> '.$filenames[$i];
		} else {
			print $langs->trans('DirectoryFile').':<br> '.$filenames[$i];
		}
		if (empty($fileverification[$i]['error'])) {
			print '<div class="center" style="color:green">';
			print $langs->trans('Success');
			print '</div>';
		} else {
			print '<div class="center" style="color:red">';
			print $langs->trans('Error');
			print '</div><br>';
			print '<div class="portlet light">';
			print $langs->trans("ErrorList");
			print '<ul style="list-style-type:\'-\';">';
			foreach ($fileverification[$i]['error'] as $key => $errorcode) {
				print '<li>';
				print $langs->trans($errorcode["errorcode"], !empty($errorcode["errorextension"])?$errorcode["errorextension"]:"", !empty($errorcode["errorfilename"])?$errorcode["errorfilename"]:"");
				print '</li>';
			}
			print '</ul>';
			print '</div>';
		}
		print '</div>';
	}
	print'</div>';

	if (empty($fileverification[0]['error']) && empty($fileverification[1]['error'])) {
		print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<input type="hidden" name="action" value="automigration">';
		print '<input type="hidden" name="sqlfilename" value="'.$filetoverify["sql"].'">';
		print '<input type="hidden" name="dirfilename" value="'.$filetoverify["dir"].'">';
		print '<input type="hidden" name="mode" value="automigration">';
		print '<input type="hidden" name="subject" value="'.GETPOST('subject', 'alpha').'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';

		print '<br><div class="center">
            <h4 style="color:red;"><strong>
            '.$langs->trans("AutomigrationStep3Warning").'
            </strong></h4><br>
        <input id="confirmmigration" type="submit" class="btn green-haze btn-circle" value="'.$langs->trans("ConfirmMigration").'" onclick="applywaitMask()">
		<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelAutomigrationAndBacktoSupportPage").'</button></a>
        </div>';
		print '<script>
        function applywaitMask(){
            $(\'#waitMask\').children().first().contents()[0].nodeValue = "'.$langs->trans("MigrationInProgress").'"
            $(\'#waitMask\').show();
            $(\'#waitMask\').attr(\'style\',\'opacity:0.8\');
        }
        </script>';
	} else {
		$migrationerrormessage = $langs->trans("MigrationErrorContent");
		$migrationerrormessage .= "\n\nMysqldump: ".(!empty($filetoverify["sql"]) ? $filetoverify["sql"] : "null");
		$migrationerrormessage .= "\nDocumentDump: ".(!empty($filetoverify["dir"]) ? $filetoverify["dir"] : "null");
		$migrationerrormessage .= "\nTimestamp: ".dol_print_date(dol_now(), "%d/%m/%Y %H:%M:%S");
		$migrationerrormessage .= "\nErrorsSql: ";
		foreach ($fileverification[0]['error'] as $key => $errorcode) {
			$migrationerrormessage .= $langs->trans($errorcode["errorcode"], !empty($errorcode["errorextension"])?$errorcode["errorextension"]:"", !empty($errorcode["errorfilename"])?$errorcode["errorfilename"]:"")." ";
		}
		$migrationerrormessage .= "\nErrorsDocument: ";
		foreach ($fileverification[1]['error'] as $key => $errorcode) {
			$migrationerrormessage .= $langs->trans($errorcode["errorcode"], !empty($errorcode["errorextension"])?$errorcode["errorextension"]:"", !empty($errorcode["errorfilename"])?$errorcode["errorfilename"]:"")." ";
		}
		print '<form action="'.$_SERVER["PHP_SELF"].'" method="GET">';
		print '<input type="hidden" name="action" value="presend">';
		print '<div class="center">';
		print '<h4 style="color:red;"><strong>'.$langs->trans("ErrorOnMigration").'</strong></h4><br>';
		print '<input type="submit" class="btn green-haze btn-circle" value="'.$langs->trans("BackToSupport").'">';
		print '<input type="hidden" name="backfromautomigration" value="backfromautomigration">';
		print '<input type="hidden" name="subject" value="'.$langs->trans("MigrationErrorSubject").'">';
		print '<input type="hidden" name="content" value="'.$migrationerrormessage.'">';
		print '<input type="hidden" name="contractid" value="'.$object->id.'">';
		print '<input type="hidden" name="mode" value="support">';
		print '</div>';
	}
	print '<input type="hidden" name="contractid" value="'.GETPOST('contractid', 'alpha').'">';
	print '<input type="hidden" name="instanceselect" value="'.GETPOST('instanceselect', 'alpha').'">';
	print '<input type="hidden" name="ticketcategory" value="'.GETPOST('ticketcategory', 'alpha').'">';
	print '<input type="hidden" name="ticketcategory_child_id" value="'.GETPOST('ticketcategory_child_id', 'alpha').'">';
	print '<input type="hidden" name="supportchannel" value="'.GETPOST('instanceselect', 'alpha').'">';
	print '</form>';
	print '</div>
    <!-- END STEP5-->';
}
if ($action == 'view') {
	print '<form id="formautomigration" name="formautomigration" action="'.$_SERVER["PHP_SELF"].'#step4" method="POST" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="mode" value="automigration">';
	print '<input type="hidden" id="actionautomigration" name="action" value="view">';

	print'<!-- BEGIN STEP1-->
        <div class="portlet light" id="step1">
                <h2>'.$langs->trans("Step", 1).' - '.$langs->trans("BackupOldDatabase").'</h2><br>
                <div>
                    '.$langs->trans("AutomigrationStep1Text", $langs->transnoentitiesnoconv("Home"), $langs->transnoentitiesnoconv("Setup"), $langs->transnoentitiesnoconv("Backup"), $langs->transnoentitiesnoconv("GenerateBackup")).'
					<br><br>
					'.$langs->trans("DownloadThisFile").'.
                </div>
                <div class="center" style="padding-top:10px">
                    <img src="'.$linkstep1img.'">
                </div>
				<br>
                <div class="note note-info"><small>
                '.$langs->trans("AutomigrationStep1Note").'
                <div class="portlet dark" style="margin-bottom:10px">
                    mysqldump -h ip_of_old_mysql_server -P port_of_old_mysql_server -u user_database -ppassword_database > mysqldump_YYYYMMDDHHMMSS.sql
                </div>
                </small>
                </div>
                <div class="containerflexautomigration">
					<div class="right" style="margin-right:10px">
						<a href="'.$_SERVER["REQUEST_URI"].'#Step2"><button id="buttonstep_2" type="button" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button></a>
					</div>
					<div>
						<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelAutomigrationAndBacktoSupportPage").'</button></a>
					</div>
                </div>
        </div>
        <!-- END STEP1-->';

		print '<!-- BEGIN STEP2-->
		<div id="Step2"></div>
        <div class="portlet light divstep topmarginstep" id="step2">
                <h2>'.$langs->trans("Step", 2).' - '.$langs->trans("BackupOldDocument").'</h2><br>
                <div>
                    '.$langs->trans("AutomigrationStep2Text", $langs->transnoentitiesnoconv("Home"), $langs->transnoentitiesnoconv("Setup"), $langs->transnoentitiesnoconv("Backup"), $langs->transnoentitiesnoconv("GenerateBackup")).'
					<br><br>
					'.$langs->trans("DownloadThisFile").'
                </div>
                <div class="center" style="padding-top:10px">
                    <img src="'.$linkstep2img.'">
                </div><br>
                <div class="center">
                <div class="containerflexautomigration">
					<div class="right" style="margin-right:10px">
						<a href="'.$_SERVER["REQUEST_URI"].'#Step3"><button id="buttonstep_3" type="button" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button></a>
					</div>
					<div>
						<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelAutomigrationAndBacktoSupportPage").'</button></a>
					</div>
				</div>
        </div>
        </div>
        <!-- END STEP2-->';

		print '<!-- BEGIN STEP3-->
        <div class="portlet light divstep topmarginstep" id="step3">
                <h2 id="Step3">'.$langs->trans("Step", 3).' - '.$langs->trans("InstanceConfirmation").'</h2><br>
                <div style="padding-left:25px">
                '.$langs->trans("AutomigrationStep3Text").'<br><br>
                </div>
                <div class="center" style="padding-top:10px">';
				print '<select id="instanceselect" name="instanceselect" class="minwidth600" required="required">';
				print '<option value="">&nbsp;</option>';
	if (count($listofcontractid) == 0) {
		// Should not happen
	} else {
		$atleastonehigh=0;
		$atleastonefound=0;

		foreach ($listofcontractid as $id => $contract) {
			$planref = $contract->array_options['options_plan'];
			$statuslabel = $contract->array_options['options_deployment_status'];
			$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

			$dbprefix = $contract->array_options['options_prefix_db'];
			if (empty($dbprefix)) $dbprefix = 'llx_';

			if ($statuslabel == 'undeployed') {
				continue;
			}

			// Get info about PLAN of Contract
			$planlabel = $planref;		// By default but we will take ref and label of service of type 'app' later

			$planid = 0;
			$freeperioddays = 0;
			$directaccess = 0;

			$tmpproduct = new Product($db);
			foreach ($contract->lines as $keyline => $line) {
				if ($line->statut == 5 && $contract->array_options['options_deployment_status'] != 'undeployed') {
					$statuslabel = 'suspended';
				}

				if ($line->fk_product > 0) {
					$tmpproduct->fetch($line->fk_product);
					if ($tmpproduct->array_options['options_app_or_option'] == 'app') {
						$planref = $tmpproduct->ref;			// Warning, ref is in language of user
						$planlabel = $tmpproduct->label;		// Warning, label is in language of user
						$planid = $tmpproduct->id;
						$freeperioddays = $tmpproduct->array_options['options_freeperioddays'];
						$directaccess = $tmpproduct->array_options['options_directaccess'];
						break;
					}
				}
			}

			$ispaid = sellyoursaasIsPaidInstance($contract);

			$color = "green";
			if ($statuslabel == 'processing') { $color = 'orange'; }
			if ($statuslabel == 'suspended') { $color = 'orange'; }
			if ($statuslabel == 'undeployed') { $color = 'grey'; }
			if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) { $color = 'lightgrey'; }

			if (!preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
				if (! $ispaid) {	// non paid instances
					$priority = 'low';
					$prioritylabel = '<span class="opacitymedium">'.$langs->trans("Trial").'</span>';
				} else {
					if ($ispaid) {	// paid with level Premium
						if ($tmpproduct->array_options['options_typesupport'] == 'premium') {
							$priority = 'high';
							$prioritylabel = '<span class="priorityhigh">'.$langs->trans("Priority").' '.$langs->trans("High").'</span>';
							$atleastonehigh++;
						} else {	// paid with level Basic
							$priority = 'medium';
							$prioritylabel = '<span class="prioritymedium">'.$langs->trans("Priority").' '.$langs->trans("Medium").'</span>';
						}
					}
				}

				$optionid = $priority.'_'.$id;
				$labeltoshow = '';
				$labeltoshow .= $langs->trans("Instance").' <strong>'.$contract->ref_customer.'</strong> ';
				//$labeltoshow = $tmpproduct->label.' - '.$contract->ref_customer.' ';
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				$labeltoshow .= ' - ';
				$labeltoshow .= $prioritylabel;

				print '<option value="'.$optionid.'"'.(GETPOST('instanceselect', 'alpha') == $optionid ? ' selected="selected"':'').'" data-html="'.dol_escape_htmltag($labeltoshow).'">';
				print dol_escape_htmltag($labeltoshow);
				print '</option>';
				print ajax_combobox('instanceselect', array(), 0, 0, 'off');

				$atleastonefound++;
			}
		}
	}
		print'</select><br><br>';
		print'</div>
            <div class="center">
            <h4><div class="note note-warning">
			'.$langs->trans("AutomigrationStep3Warning").'
			</div></h4>
            </div><br>
			<div id="buttonstep4migration" class="containerflexautomigration" '.(!GETPOST('instanceselect', 'alpha') ?'style="display:none;"':'').'>
				<div class="right" style="margin-right:10px">
					<button id="buttonstep_4" type="submit" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button>
				</div>
				<div>
					<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle">'.$langs->trans("CancelAutomigrationAndBacktoSupportPage").'</button></a>
				</div>
			</div>
        </div>
        <!-- END STEP3-->';

		// @TODO LMR Replace the upload of file with a simple form with
		// flow.js + a PHP file called flowjs-server.php (to add inside Dolibarr) inspired from https://github.com/flowjs/flow.js/blob/master/samples/Backend%20on%20PHP.md (not the flow-php-server that is too heavy for our need)
		// So we an upload very large files and stay on main page.
		print '<!-- BEGIN STEP4-->
        <div class="portlet light divstep topmarginstep" id="step4">
            <h2 id="Step4">'.$langs->trans("Step", 4).' - '.$langs->trans("FileUpload").'</small></h1><br>
            <div class="grid-wrapper-automigration">
                <div class="grid-boxes-automigration-left valignmiddle">
                	<span class="opacitymedium nobold">'.$langs->trans("UploadYourDatabaseDumpFile").' ('.$langs->trans('FileEndingWith').' .sql, .sql.bz2 '.$langs->trans("or").' .sql.gz):</span>
                </div>
                <div class="grid-boxes-automigration">';
				/*$maxfilesizearray = getMaxFileSizeArray();
				$maxmin = $maxfilesizearray['maxmin'];
	if ($maxmin > 0) {
		print '<input type="hidden" name="MAX_FILE_SIZE" value="'.($maxmin * 1024).'">';	// MAX_FILE_SIZE must precede the field type=file
	}*/
				print '<input type="file" class="nobrowserflowjssupport" id="databasedumpfile" name="addedfile[]" accept=".sql,.sql.bz2,.sql.gz">';
				print '<span class="browserflowjssupport"><button type="button" data-inputfile="sqldumpfile" class="browsefileinput" id="browseButtonsqldump">Browse...</button>';
				print '&nbsp;<span id="sqldumpfilespan">No file selected.</span>';
				print '<input type="hidden" id="sqldumpfilename" name="sqldumpfilename">';
				print '<input type="hidden" id="sqldumpfiletype" name="sqldumpfiletype">';
				print '<br><div class="progress-bar sqldumpfilepgbar taligncenter" role="progressbar" style="width:1%;display:none"><span class="small valigntop">0%</span></div>';
				print '<button type="button" style="display:none;" data-inputfile="sqldumpfile" data-fileidentifier="" class="btn green-haze btn-circle cancelfileinput" id="cancelsqldumpfile">Cancel</button>';
				print '</span>';
				print '</div>
                <div class="grid-boxes-automigration-left valignmiddle">
                	<span class="opacitymedium nobold">'.$langs->trans("UploadYourDocumentArchiveFile").' ('.$langs->trans('FileEndingWith').' .zip, .tar.gz '.$langs->trans("or").' .tar.bz2):</span>
                </div>
                <div class="grid-boxes-automigration">';
				/*$maxmin = $maxfilesizearray['maxmin'];
	if ($maxmin > 0) {
		print '<input type="hidden" name="MAX_FILE_SIZE" value="'.($maxmin * 1024).'">';	// MAX_FILE_SIZE must precede the field type=file
	}*/
				print '<input type="file" class="nobrowserflowjssupport" id="documentdumpfile" name="addedfile[]" accept=".zip,.tar.gz,.tar.bz2">';
				print '<span class="browserflowjssupport"><button type="button" data-inputfile="documentdumpfile" class="browsefileinput" id="browseButtondocument">Browse...</button>';
				print '&nbsp;<span id="documentdumpfilespan">No file selected.</span>';
				print '<input type="hidden" id="documentdumpfilename" name="documentdumpfilename">';
				print '<input type="hidden" id="documentdumpfiletype" name="documentdumpfiletype">';
				print '<br><div class="progress-bar documentdumpfilepgbar taligncenter" role="progressbar" style="width:1%;display:none"><span class="small valigntop">0%</span></div>';
				print '<button type="button" style="display:none;" data-inputfile="documentdumpfile" data-fileidentifier="" class="btn green-haze btn-circle cancelfileinput" id="canceldocumentdumpfile">Cancel</button>';
				print '</span>';
				print '</div>
            </div><br>
			<input type="hidden" class="flowjsprocess" id="flowjsprocess" name="flowjsprocess" value="true">

			<div id="sumbmitfiles" class="containerflexautomigration" style="display:none;">
				<div class="right containerflexautomigrationitem" style="margin-right:10px">
                	<input type="submit" name="addfile" value="'.$langs->trans("SubmitFiles").'" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">
				</div>
				<div class="left containerflexautomigrationitem">
					<a href="'.$backtopagesupport.'"><button type="button" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">'.$langs->trans("CancelAutomigrationAndBacktoSupportPage").'</button></a>
				</div>
			</div>
        </div>
        <!-- END STEP4-->';
	print '<input type="hidden" name="contractid" value="'.GETPOST('contractid', 'alpha').'">';
	print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel', 'alpha').'">';
	print '<input type="hidden" name="backfromautomigration" value="backfromautomigration">';
	print '<input type="hidden" name="ticketcategory_child_id" value="'.(GETPOST('ticketcategory_child_id_back', 'alpha')?:GETPOST('ticketcategory_child_id', 'alpha')).'">';
	print '<input type="hidden" name="ticketcategory" value="'.(GETPOST('ticketcategory_back', 'alpha')?:GETPOST('ticketcategory', 'alpha')).'">';
	print '<input type="hidden" name="subject" value="'.(GETPOST('subject_back', 'alpha')?:GETPOST('subject', 'alpha')).'">';
	print'</form>';

	print '
	<script>
	jQuery(document).ready(function() {
		var flow = new Flow({
			target:"source/core/ajax/flowjs-server.php",
			query:{module:"sellyoursaas",upload_dir:"'.$upload_dir.'"},
			testChunks:false
		});
		if(flow.support){
			// Only if the browser support flowjs
			var focusinputfile = "";
			var filessubmitted = 0;
			console.log("We remove and hide html inputs for flowjs process")
			$(".nobrowserflowjssupport").remove();
			$(".browsefileinput").on("click", function(){
				focusinputfile = $(this).data("inputfile");
				console.log("focusinputfile = "+focusinputfile)
			})
			$(".cancelfileinput").on("click", function(){
				filename = $(this).data("fileidentifier");
				file = flow.getFromUniqueIdentifier(filename);
				file.cancel();
				$("#"+file.uniqueIdentifier+"pgbar").hide();
				console.log("We remove file "+filename);
				$("#"+$(this).data("inputfile")+"span").text("No file selected.");
				$(this).hide();
				filessubmitted--;
				$("#sumbmitfiles").hide();
				$("#"+focusinputfile+"name").val("");
				$("#"+focusinputfile+"type").val("");
			})
			flow.assignBrowse(document.getElementById("browseButtonsqldump"), false, true, {"accept":".sql, .sql.bz2, .sql.gz"});
			flow.assignBrowse(document.getElementById("browseButtondocument"), false, true, {"accept":".zip, .tar.gz, .tar.bz2"});
			flow.on("fileAdded", function(file, event){
				console.log("Trigger event file added", file, event);
				$("#"+focusinputfile+"span").text(file.name);
				$("#cancel"+focusinputfile).data("fileidentifier", file.uniqueIdentifier)
				console.log($("#cancel"+focusinputfile).data("fileidentifier"));
				$("#cancel"+focusinputfile).show()
				$("."+focusinputfile+"pgbar").show();
				$("."+focusinputfile+"pgbar").attr("id",file.uniqueIdentifier+"pgbar")
				$("#"+focusinputfile+"name").val(file.name)
				$("#"+focusinputfile+"type").val(file.file.type)
			});
			flow.on("filesSubmitted", function(array,message){
				console.log("Trigger event file submitted");
				flow.upload()
			});
			flow.on("progress", function(){
				console.log("testprogress",flow.files);
				flow.files.forEach(function(element){
					console.log(element.progress());
					width = Math.round(element.progress()*100)
					width = width.toString()
					$("#"+element.uniqueIdentifier+"pgbar").width(width+"%")
					$("#"+element.uniqueIdentifier+"pgbar").children("span").text(width+"%")
				});
			});
			flow.on("fileSuccess", function(file,message){
				console.log("The file has been uploaded successfully",file,message);
				filessubmitted++;
				if(filessubmitted >= 2){
					$("#sumbmitfiles").show();
				} else {
					$("#sumbmitfiles").hide();
				}
			});
			flow.on("fileError", function(file, message){
				console.log("Error on file upload",file, message);
			});
		} else {
			console.log("We remove flowjs inputs")
			$(".browserflowjssupport").remove();
			$(".flowjsprocess").remove();
		}
	})
	</script>';

	print'<script>
    jQuery(document).ready(function() {
        $("#instanceselect").on("change",function(){
            if($(this).val() != ""){
                $("#buttonstep4migration").show();
            }else{
                $("#buttonstep4migration").hide();
            }
        });
        $("#databasedumpfile").on("change",function(){
            if($(this).val() != "" && $("#documentdumpfile").val() != ""){
                $("#sumbmitfiles").show();
            }
        });
        $("#documentdumpfile").on("change",function(){
            if($(this).val() != "" && $("#databasedumpfile").val() != ""){
                $("#sumbmitfiles").show();
            }
        });

        function showStepAnchor(hash = 0){
            if(hash == 0){
				console.log($(location).attr("hash"));
                var hash = $(location).attr("hash").substr(5);
                hash = parseInt(hash) + 1;
            }
            if(hash > 0 && hash < 5){
                let i = 1;
                while(i<=hash){
                    step = "step"+i;
                    console.log("Show "+$("#"+step).attr("id"));
                    $("#"+step).show();
                    i++;
                }
            }
        }

        $(".btnstep").on("click",function(){
			hash = $(this).attr("id").split("_")[1];
			console.log("We click on button "+hash);
            showStepAnchor(hash);
        })

		$("#sumbmitfiles").on("click",function(){
			console.log("We click on sumbmitfiles, we replace the action=redirectautomigrationget by action=fileverification");
			$("#actionautomigration").val(\'fileverification\');
		})

        var hash = $(location).attr("hash").substr(5);
        if(hash != "4"){
			$(".divstep").hide();
			showStepAnchor(hash);
        }
		step = "Step"+hash;
    })
    </script>';

	print "<style>
	* {
		scroll-behavior: smooth !important;
	}
	.topmarginstep{
		margin-top:100px;
	}
	.containerflexautomigration {
		display: flex;
		justify-content:center;
		flex-wrap: wrap;
	}
	.containerflexautomigrationitem {
		padding-bottom: 10px;
	}
	</style>";
}
if ($action == "automigration") {
	print '<div class="portlet light">';
	if ($result["result"] != 0) {
		print '<div class="center" style="color:red">';
		print '<h2>'.$langs->trans("MigrationError").'</h2><br><br><strong>';
		print $langs->trans("ErrorOnMigration");
		print'</strong><br><br></div>';
		print '<div class="center">';

		$migrationerrormessage = $langs->trans("MigrationErrorContent");
		$migrationerrormessage .= "\n";
		$migrationerrormessage .= "\n-------------------";
		$migrationerrormessage .= "\nMysqldump: ".$sqlfiletomigrate;
		$migrationerrormessage .= "\nDocumentDump: ".$dirfiletomigrate;
		$migrationerrormessage .= "\nTimestamp: ".dol_print_date(dol_now(), "standard", 'gmt').' UTC';
		$migrationerrormessage .= "\nErrors: ".(!empty($result["output"])?$result["output"]:$result["result"]);
		print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST" id="migrationFormbacksupport">';
		print '<input type="submit" class="btn green-haze btn-circle" value="'.$langs->trans("BackToSupport").'">';
		print '<input type="hidden" name="action" value="presend">';
		print '<input type="hidden" name="mode" value="support">';
		print '<input type="hidden" name="contractid" value="'.$object->id.'">';
		print '<input type="hidden" name="supportchannel" value="'.GETPOST('instanceselect', 'alpha').'">';
		print '<input type="hidden" name="backfromautomigration" value="backfromautomigration">';
		print '<input type="hidden" name="ticketcategory_child_id" value="'.(GETPOST('ticketcategory_child_id_back', 'alpha')?:GETPOST('ticketcategory_child_id', 'alpha')).'">';
		print '<input type="hidden" name="ticketcategory" value="'.(GETPOST('ticketcategory_back', 'alpha')?:GETPOST('ticketcategory', 'alpha')).'">';
		print '<input type="hidden" name="subject" value="'.$langs->trans("MigrationErrorSubject").'">';
		print '<input type="hidden" name="content" value="'.$migrationerrormessage.'">';
		print '</form>';
		print'</div>';
	} else {
		print '<div class="center" style="color:green">';
		print '<h2>'.$langs->trans("MigrationSuccess").'</h2>';
		print'</div>';
		print '<div><br><br>';
		print $langs->trans("MigrationWasSuccess").'<br>';
		print $langs->trans("CallDolibarrInstance").' : <a href="https://'.$object->ref_customer.'">'.$object->ref_customer.'</a>';
		print '<br><br><div class="note note-info" style="color:#bbaf01">';
		print $langs->trans("MigrationSuccessNote");
		print '</div><small>';
		print $langs->trans("MigrationSuccessText1");
		print "<br><br>";
		print $langs->trans("MigrationSuccessText2");
		print'</small></div>';
	}
	print'</div>';
}
print'
</div>
</div>
</div>
</div>
</div>';
?>
<!-- END PHP TEMPLATE automigration.tpl.php -->