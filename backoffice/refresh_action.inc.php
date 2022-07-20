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
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		if (! @ssh2_auth_password($connection, $username_web, $password_web)) {
			dol_syslog("Could not authenticate with username ".$username_web." and password ".preg_replace('/./', '*', $password_web), LOG_WARNING);
			setEventMessages("Could not authenticate with username ".$username_web." and password ".preg_replace('/./', '*', $password_web), null, 'warnings');
		} else {
			$sftp = ssh2_sftp($connection);

			// Update ssl certificate
			// Dir .ssh must have rwx------ permissions
			// File authorized_keys_support must have rw------- permissions
			$dircreated=0;
			$result=ssh2_sftp_mkdir($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh');
			if ($result) {
				$dircreated=1;	// Created
			} else {
				$dircreated=0;	// Creation fails or already exists
			}

			// Check if authorized_key exists
			//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys_support';
			$filecert="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys_support';  // With PHP 5.6.27+
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys_support');

			// Create authorized_keys_support file
			if (empty($fstat['atime'])) {		// Failed to connect or file does not exists
				$stream = fopen($filecert, 'w');
				if ($stream === false) {
					setEventMessages($langs->transnoentitiesnoconv("ErrorConnectOkButFailedToCreateFile"), null, 'errors');
				} else {
					// Add public keys
					$publickeystodeploy = $conf->global->SELLYOURSAAS_PUBLIC_KEY;
					fwrite($stream, $publickeystodeploy);
					fclose($stream);
					// File authorized_keys_support must have rw------- permissions
					ssh2_sftp_chmod($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys_support', 0600);
					$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys_support');
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
}

if ($action == 'addinstalllock') {
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
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;

	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $instance." ".$username_web." ".$password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $username_web, $password_web)) {
			dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
			//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock';
			$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock';
			$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock');
			if (empty($fstat['atime'])) {
				$stream = fopen($fileinstalllock, 'w');
				//var_dump($stream);exit;
				fwrite($stream, "// File to protect from install/upgrade.\n");
				fclose($stream);
				$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock');
				setEventMessage($langs->transnoentitiesnoconv("FileCreated"), 'mesgs');
			} else setEventMessage($langs->transnoentitiesnoconv("ErrorFileAlreadyExists"), 'warnings');

			$object->filelock=(empty($fstat['atime'])?'':$fstat['atime']);
			$object->array_options['options_filelock']=(empty($fstat['atime'])?'':$fstat['atime']);

			if (! empty($fstat['atime'])) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
}

if ($action == 'delauthorizedkey') {
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
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;
	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $instance." ".$username_web." ".$password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $username_web, $password_web)) {
			dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if authorized_keys_support exists
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/.ssh/authorized_keys_support';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"), 'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFailToDeleteFile", $username_web.'/.ssh/authorized_keys_support'), 'warnings');

			$object->fileauthorizedkey='';
			$object->array_options['options_fileauthorizekey']='';

			if ($result) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
}
if ($action == 'delinstalllock') {
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
	$username_web = $object->array_options['options_username_os'];
	$password_web = $object->array_options['options_password_os'];
	$hostname_os = $object->array_options['options_hostname_os'];

	$server=$hostname_os;
	$server_port = (! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT) ? $conf->global->SELLYOURSAAS_SSH_SERVER_PORT : 22);
	$connection = ssh2_connect($server, $server_port);
	if ($connection) {
		//print $object->instance." ".$username_web." ".$password_web."<br>\n";
		if (! @ssh2_auth_password($connection, $username_web, $password_web)) {
			dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
		} else {
			$sftp = ssh2_sftp($connection);

			// Check if install.lock exists
			$dir=preg_replace('/_([a-zA-Z0-9]+)$/', '', $database_db);
			$filetodelete=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$username_web.'/'.$dir.'/documents/install.lock';
			$result=ssh2_sftp_unlink($sftp, $filetodelete);

			if ($result) setEventMessage($langs->transnoentitiesnoconv("FileDeleted"), 'mesgs');
			else setEventMessage($langs->transnoentitiesnoconv("ErrorFailToDeleteFile", $username_web.'/'.$dir.'/documents/install.lock'), 'warnings');

			$object->filelock='';
			$object->array_options['options_filelock']='';

			if ($result) {
				$result = $object->update($user);
			}
		}
	} else setEventMessage($langs->transnoentitiesnoconv("FailedToConnectToSftp", $server), 'errors');
}


// We make a refresh of status of install.lock + authorized key, this does not update the qty into lines (this is done in doRefreshContracts or doRenewalContracts).
if ($action == 'refresh' || $action == 'setdate') {
	dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working

	$object->oldcopy=dol_clone($object, 1);

	// TODO Replace this with a
	// $result = $sellyoursaasutils->sellyoursaasRemoteAction('refresh', $contract, 'admin', '', '', '0', $comment);

	// Check remote files (install.lock and authorized_keys_support, recreate authorized_keys_support only if not found). Does not update lastcheck field.
	$ret=dolicloud_files_refresh($conf, $db, $object, $errors);

	// Count ressources and update the cache nbusers and only this. Does not update qty into lines.
	$ret=dolicloud_database_refresh($conf, $db, $object, $errors);

	$action = 'view';
}
