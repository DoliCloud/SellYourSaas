<?php


// Do actions on object $object


// Avoid errors onto ssh2 and stats function warning
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

$langs->load("errors");

if ($action == 'addauthorizedkey') {
	// SSH connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('', 'ssh2_connect function does not exists'); exit;
	}

	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);

	dol_syslog("ssh2_connect $server $server_port");
	$connection = ssh2_connect($server, $server_port);

	if ($connection) {
		if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
			dol_syslog("Could not authenticate with username ".$username_os." and password ".preg_replace('/./', '*', $password_os), LOG_WARNING);
			setEventMessages("Could not authenticate with username ".$username_os." and password ".preg_replace('/./', '*', $password_os), null, 'warnings');
		} else {
			$sftp = ssh2_sftp($connection);

			// Update ssl certificate
			// Dir .ssh must have rwx------ permissions
			// File authorized_keys_support must have rw------- permissions
			$dircreated=0;
			$result=ssh2_sftp_mkdir($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh');
			if ($result) {
				$dircreated=1;	// Created
			} else {
				$dircreated=0;	// Creation fails or already exists
			}

			// Check if authorized_key exists
			//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh/authorized_keys_support';
			$filecert="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh/authorized_keys_support';  // With PHP 5.6.27+
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh/authorized_keys_support');

			// Create authorized_keys_support file
			if (empty($fstat['atime'])) {		// Failed to connect or file does not exists
				//dol_syslog("filecert=".$filecert);
				$stream = fopen($filecert, 'w');
				if ($stream === false) {
					setEventMessages($langs->transnoentitiesnoconv("ErrorConnectOkButFailedToCreateFile"), null, 'errors');
				} else {
					// Add public keys
					$publickeystodeploy = $conf->global->SELLYOURSAAS_PUBLIC_KEY;
					fwrite($stream, $publickeystodeploy);
					fclose($stream);
					// File authorized_keys_support must have rw------- permissions
					ssh2_sftp_chmod($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh/authorized_keys_support', 0600);
					$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh/authorized_keys_support');
					setEventMessages($langs->transnoentitiesnoconv("FileCreated"), null, 'mesgs');
				}
			} else setEventMessages($langs->transnoentitiesnoconv("ErrorFileAlreadyExists"), null, 'warnings');

			$object->fileauthorizedkey=(empty($fstat['atime'])?'':$fstat['atime']);
			$object->array_options['options_fileauthorizekey']=(empty($fstat['atime'])?'':$fstat['atime']);

			if (! empty($fstat['atime'])) {
				$result = $object->update($user);
			}
		}
	} else {
		setEventMessages($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server.' - port '.$server_port), null, 'errors');
	}
} elseif ($action == 'addinstalllock') {
	// SSH connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('', 'ssh2_connect function does not exists'); exit;
	}

	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $instance." ".$username_os." ".$password_os."<br>\n";
		if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
			dol_syslog("Could not authenticate with username ".$username_os." . and password ".preg_replace('/./', '*', $password_os), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
			//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/install.lock';
			$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/install.lock';
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/install.lock');
			if (empty($fstat['atime'])) {
				$stream = fopen($fileinstalllock, 'w');
				//var_dump($stream);exit;
				fwrite($stream, "// File to protect from install/upgrade.\n");
				fclose($stream);
				$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/install.lock');
				setEventMessage($langs->transnoentitiesnoconv("FileCreated"), 'mesgs');
			} else setEventMessage($langs->transnoentitiesnoconv("ErrorFileAlreadyExists"), 'warnings');

			$object->filelock=(empty($fstat['atime'])?'':$fstat['atime']);
			$object->array_options['options_filelock']=(empty($fstat['atime'])?'':$fstat['atime']);

			if (! empty($fstat['atime'])) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
} elseif ($action == 'addinstallmoduleslock') {
	// SSH connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('', 'ssh2_connect function does not exists'); exit;
	}

	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $instance." ".$username_os." ".$password_os."<br>\n";
		if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
			dol_syslog("Could not authenticate with username ".$username_os." . and password ".preg_replace('/./', '*', $password_os), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if installmodules.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
			//$fileinstallmoduleslock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/installmodules.lock';
			$fileinstallmoduleslock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/installmodules.lock';
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/installmodules.lock');
			if (empty($fstat['atime'])) {
				$stream = fopen($fileinstallmoduleslock, 'w');
				//var_dump($stream);exit;
				fwrite($stream, "// File to protect from install/upgrade external modules.\n");
				fclose($stream);
				$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/installmodules.lock');
				setEventMessage($langs->transnoentitiesnoconv("FileCreated"), 'mesgs');
			} else setEventMessage($langs->transnoentitiesnoconv("ErrorFileAlreadyExists"), 'warnings');

			$object->fileinstallmoduleslock=(empty($fstat['atime'])?'':$fstat['atime']);
			$object->array_options['options_fileinstallmoduleslock']=(empty($fstat['atime'])?'':$fstat['atime']);

			if (! empty($fstat['atime'])) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
} elseif ($action == 'delauthorizedkey') {
	// SSH connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('', 'ssh2_connect function does not exists'); exit;
	}

	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;
	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $instance." ".$username_os." ".$password_os."<br>\n";
		if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
			dol_syslog("Could not authenticate with username ".$username_os." . and password ".preg_replace('/./', '*', $password_os), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if authorized_keys_support exists
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/.ssh/authorized_keys_support';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"), 'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFailToDeleteFile", $username_os.'/.ssh/authorized_keys_support'), 'warnings');

			$object->fileauthorizedkey='';
			$object->array_options['options_fileauthorizekey']='';

			if ($result) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
} elseif ($action == 'delinstalllock') {
	// SSH connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('', 'ssh2_connect function does not exists'); exit;
	}

	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;
	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $object->instance." ".$username_os." ".$password_os."<br>\n";
		if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
			dol_syslog("Could not authenticate with username ".$username_os." . and password ".preg_replace('/./', '*', $password_os), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/install.lock';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"), 'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFailToDeleteFile", $username_os.'/'.$dir.'/documents/install.lock'), 'warnings');

			$object->filelock='';
			$object->array_options['options_filelock']='';

			if ($result) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
} elseif ($action == 'delinstallmoduleslock') {
	// SSH connect
	if (! function_exists("ssh2_connect")) {
		dol_print_error('', 'ssh2_connect function does not exists'); exit;
	}

	$type_db = $conf->db->type;

	$instance = $object->ref_customer;
	$hostname_db = $object->array_options['options_hostname_db'];
	$username_db = $object->array_options['options_username_db'];
	$password_db = $object->array_options['options_password_db'];
	$database_db = $object->array_options['options_database_db'];
	$port_db     = $object->array_options['options_port_db'];
	$username_os = $object->array_options['options_username_os'];
	$password_os = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;
	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $object->instance." ".$username_os." ".$password_os."<br>\n";
		if (! @ssh2_auth_password($connection, $username_os, $password_os)) {
			dol_syslog("Could not authenticate with username ".$username_os." . and password ".preg_replace('/./', '*', $password_os), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if installmodules.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_os.'/'.$dir.'/documents/installmodules.lock';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"), 'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFailToDeleteFile", $username_os.'/'.$dir.'/documents/installmodules.lock'), 'warnings');

			$object->fileinstallmoduleslock='';
			$object->array_options['options_fileinstallmoduleslock']='';

			if ($result) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
}


// We make a refresh of status of install.lock + authorized key, this does not update the qty into lines (this is done in doRefreshContracts or doRenewalContracts).
if ($action == 'setdate') {
	dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working

	$object->oldcopy=dol_clone($object, 1);

	// Check remote files (authorized_keys_support + install.lock + installmodules.lock, recreate authorized_keys_support only if not found). Does not update lastcheck field.
	$ret=dolicloud_files_refresh($conf, $db, $object, $errors);

	// Count ressources and update the cache nbusers and only this. Does not update qty into lines.
	$ret=dolicloud_database_refresh($conf, $db, $object, $errors);

	$action = 'view';
}
