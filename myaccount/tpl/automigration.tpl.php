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

$upload_dir = $conf->sellyoursaas->dir_temp."/automigration_".$mythirdpartyaccount->id.'.tmp';
$filenames = array();
$fileverification = array();
$stepautomigration = GETPOST("stepautomigration","int");

if (!empty($_POST['addfile'])) {
	// Set tmp user directory
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_add_file_process($upload_dir, 1, 0);
}

if ($action == 'fileverification') {
	$filenames = $_FILES['addedfile']['name'];
	$types = $_FILES['addedfile']['type'];
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
				$error = array("error"=>array("errorcode" =>"WrongFileExtension","errorextension" => $types[$key]));
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
		if (empty(preg_match('/llx_/i', $filecontent))) {
			$error = array("error"=>array("errorcode" =>"ErrorOnSqlPrefix"));
		}
		if (!empty($error)) {
			$fileverification[0]['error'][]=$error["error"];
		}
	}
}

if ($action == 'automigration') {
	require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
	include_once DOL_DOCUMENT_ROOT."/core/class/utils.class.php";
	dol_include_once('/sellyoursaas/class/sellyoursaasutils.class.php');
	$utils = new Utils($db);

	$sellyoursaasutils = new SellYourSaasUtils($db);
	$instanceselect = GETPOST('instanceselect', 'alapha');
	$instanceselect = explode("_", $instanceselect);
	$idcontract = $instanceselect[1];
	$object = new Contrat($db);
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
	$object->hostname_web = $hostname_os;

	$sqlfiletomigrate = GETPOST('sqlfilename', 'alpha');
	$dirfiletomigrate = GETPOST('dirfilename', 'alpha');
	$object->array_options['automigrationdocumentarchivename'] = $dirfiletomigrate;
	$exitcode = 0;

	//Backup old database
	$mysqlbackupfilename=$upload_dir.'/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now(), 'dayhourlog').'.sql';
	$mysqlbackupcommand='mysqldump -C -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' '.$object->database_db;
	$result = $utils->executeCli($mysqlbackupcommand, "", 0, $mysqlbackupfilename);

	if ($result["result"] != 0) {
		if (empty($result["output"])) {
			$result["output"] = $langs->trans("ErrorOnDatabaseBackup");
		}
		setEventMessages($langs->trans("ErrorOnDatabaseBackup"), null, "errors");
	}

	//Drop llx_accounting_system and llx_accounting_account to prevent load error
	if ($result["result"] == 0) {
		$mysqlcommand='echo "drop table llx_accounting_system;" | mysql -A -C -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' '.$object->database_db;
		$result = $utils->executeCli($mysqlcommand, "", 0, null);
		if ($result["result"] != 0) {
			if (empty($result["output"])) {
				$result["output"] = $langs->trans("ErrorOnDropingTables");
			}
			setEventMessages($langs->trans("ErrorOnDropingTables"), null, "errors");
		} else {
			$mysqlcommand='echo "drop table llx_accounting_account;" | mysql -A -C -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' '.$object->database_db;
			$result = $utils->executeCli($mysqlcommand, "", 0, null);
			if ($result["result"] != 0) {
				if (empty($result["output"])) {
					$result["output"] = $langs->trans("ErrorOnDropingTables");
				}
				setEventMessages($langs->trans("ErrorOnDropingTables"), null, "errors");
			}
		}
	}

	if ($result["result"] == 0) {
		$mysqlcommand='mysql -C -A -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' -D '.$object->database_db.' < '.escapeshellcmd(dol_sanitizePathName($upload_dir).'/'.dol_sanitizeFileName($sqlfiletomigrate));
		$result = $utils->executeCli($mysqlcommand, "", 0, null, 1);
		if ($result["result"] != 0) {
			if (empty($result["output"])) {
				$result["output"] = $langs->trans("ErrorOnDatabaseMigration");
			}
			setEventMessages($langs->trans("ErrorOnDatabaseMigration"), null, "errors");
		}
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
		$mysqlcommand='mysql -C -A -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' -D '.$object->database_db.' < '.$mysqlbackupfilename;
		//$utils->executeCli($mysqlcommand,"",0,null,1);
	}
}

$linkstep1img="img/sellyoursaas_automigration_step1.png";
$linkstep2img="img/sellyoursaas_automigration_step2.png";
print '
<div class="page-content-wrapper">
    <div class="page-content">
    <!-- BEGIN PAGE HEADER-->
    <!-- BEGIN PAGE HEAD -->
        <div class="page-head">
        <!-- BEGIN PAGE TITLE -->
            <div class="page-title">
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
        <h2>'.$langs->trans("Step", 5).' - '.$langs->trans("FileVerification").'</small></h1><br>
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
				print $langs->trans($errorcode["errorcode"], !empty($errorcode["errorextension"])?$errorcode["errorextension"]:"");
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

		print '<br><div class="center">
            <h3 style="color:red;"><strong>
            '.$langs->trans("AutomigrationStep3Warning").'
            </strong></h3><br>
        <input id="confirmmigration" type="submit" class="btn green-haze btn-circle" value="'.$langs->trans("ConfirmMigration").'" onclick="applywaitMask()">
        <button type="submit" form="migrationFormbacksupport" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</button>
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
			$migrationerrormessage .= $langs->trans($errorcode["errorcode"], !empty($errorcode["errorextension"])?$errorcode["errorextension"]:"")." ";
		}
		$migrationerrormessage .= "\nErrorsDocument: ";
		foreach ($fileverification[1]['error'] as $key => $errorcode) {
			$migrationerrormessage .= $langs->trans($errorcode["errorcode"], !empty($errorcode["errorextension"])?$errorcode["errorextension"]:"")." ";
		}
		print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<input type="hidden" name="action" value="presend">';
		print '<div class="center">';
		print '<h3 style="color:red;"><strong>'.$langs->trans("ErrorOnMigration").'</strong></h3><br>';
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
	print '<form id="formautomigration" name="formautomigration" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="mode" value="automigration">';
	print '<input type="hidden" id="actionautomigration" name="action" value="view">';
	print '<input type="hidden" id="stepautomigration" name="stepautomigration" value="2">';

	print'<!-- BEGIN STEP1-->
        <div class="portlet light" id="Step1">
                <h2>'.$langs->trans("Step", 1).' - '.$langs->trans("BackupOldDatabase").'</small></h1><br>
                <div>
                    '.$langs->trans("AutomigrationStep1Text").'
                </div>
                <div class="center" style="padding-top:10px">
                    <img src="'.$linkstep1img.'">
                </div>
                <div class="note note-info"><small>
                '.$langs->trans("AutomigrationStep1Note").'
                <div class="portlet dark" style="margin-bottom:10px">
                    mysqldump -h ip_of_old_mysql_server -P port_of_old_mysql_server -u user_database -ppassword_database > mysqldump_YYYYMMDDHHMMSS.sql
                </div>
                '.$langs->trans("AutomigrationStep1Note2").'
                <div class="portlet dark" style="color:green;margin-bottom:0px">
                    -- Dump completed on YYYY-MM-DD HH:mm:
                </div>
                </small>
                </div>
                <div class="center">
                <button id="buttonstep_2" type="button" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button>
                <button type="submit" form="migrationFormbacksupport" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</button>
                </div>
        </div>
        <!-- END STEP1-->';

		print '<!-- BEGIN STEP2-->
        <div class="portlet light divstep" id="Step2">
                <h2>'.$langs->trans("Step", 2).' - '.$langs->trans("BackupOldDocument").'</small></h1><br>
                <div>
                    '.$langs->trans("AutomigrationStep2Text").' 
                </div>
                <div class="center" style="padding-top:10px">
                    <img src="'.$linkstep2img.'">
                </div><br>
                <div class="center">
                <button id="buttonstep_3" type="button" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button>
                <button type="submit" form="migrationFormbacksupport" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</button>
                </div>
        </div>
        <!-- END STEP2-->';

		print '<!-- BEGIN STEP3-->
        <div class="portlet light divstep" id="Step3">
                <h2>'.$langs->trans("Step", 3).' - '.$langs->trans("InstanceConfirmation").'</small></h1><br>
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

							$dbprefix = $contract->array_options['options_db_prefix'];
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

			if ($tmpproduct->array_options['options_typesupport'] != 'none'
				&& !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
				if (! $ispaid) {	// non paid instances
					$priority = 'low';
					$prioritylabel = '<span class="prioritylow">'.$langs->trans("Priority").' '.$langs->trans("Low").'</span> <span class="opacitymedium">'.$langs->trans("Trial").'</span>';
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
            <h3 style="color:red;"><strong>
            '.$langs->trans("AutomigrationStep3Warning").'
            </strong></h3>
            </div><br>
            <div id="buttonstep4migration" class="center" '.($stepautomigration <= 3 && !GETPOST('instanceselect', 'alpha') ?'style="display:none;':'').'">
            <button id="buttonstep_4" type="button" class="btn green-haze btn-circle btnstep">'.$langs->trans("NextStep").'</button>
            <button type="submit" form="migrationFormbacksupport" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</button>
            </div>
        </div>
        <!-- END STEP3-->';

		print '<!-- BEGIN STEP4-->
        <div class="portlet light divstep" id="Step4">
            <h2>'.$langs->trans("Step", 4).' - '.$langs->trans("FileUpload").'</small></h1><br>
            <div class="grid-wrapper-automigration">
                <div class="grid-boxes-automigration-left">
                <h4>Upload here your database data :</h4>
                </div>
                <div class="grid-boxes-automigration">
                    <input type="file" id="databasedumpfile" name="addedfile[]" accept=".sql,.sql.bz2,.sql.gz" required="required">
                </div>
                <div class="grid-boxes-automigration-left">
                <h4>Upload here your document directory data :</h4>
                </div>
                <div class="grid-boxes-automigration">
                    <input type="file" id="documentdumpfile" name="addedfile[]" accept=".zip,.tar.gz,.tar.bz2" required="required">
                </div>
            </div><br>

            <div class="center">
                <input id="sumbmitfiles" style="display:none;" type="submit" name="addfile" value="'.$langs->trans("SubmitFiles").'" class="btn green-haze btn-circle margintop marginbottom marginleft marginright">
                <button type="submit" form="migrationFormbacksupport" class="btn green-haze btn-circle">'.$langs->trans("Cancel").'</button>
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
                var hash = $(location).attr("hash").substr(5);
                hash = parseInt(hash) +1;
            }
            if(hash > 0 && hash < 5){
                let i = 1;
                while(i<=hash){
                    step = "Step"+i;
                    console.log($(step));
                    $("#"+step).show();
                    i++;
                }
            }
        }
        $(".btnstep").on("click",function(){
            //showStepAnchor();
			stepautomigration = $(this).attr("id").split("_")[1];
			$("#stepautomigration").val(stepautomigration);
			$("#formautomigration").submit();
        })
		$("#sumbmitfiles").on("click",function(){
			$("#actionautomigration").val(\'fileverification\');
		})
        var hash = '.$stepautomigration.';
        if(hash != "4"){
			$(".divstep").hide();
			showStepAnchor(hash);
        }
		step = "Step"+hash;
		$("html, body").scrollTop($("#"+step).offset().top);
    })
    </script>';
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
		$migrationerrormessage .= "\n\nMysqldump: ".$sqlfiletomigrate;
		$migrationerrormessage .= "\nDocumentDump: ".$dirfiletomigrate;
		$migrationerrormessage .= "\nTimestamp: ".dol_print_date(dol_now(), "%d/%m/%Y %H:%M:%S");
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
} else {
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
}
	print'
    </div>
    </div>
    </div>
</div>
</div>';
?>
<!-- END PHP TEMPLATE automigration.tpl.php -->