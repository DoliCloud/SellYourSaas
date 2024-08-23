<?php
/* Copyright (C) 2005      Christophe
 * Copyright (C) 2005-2013 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2013      Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2015      Frederic France      <frederic.france@free.fr>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/core/boxes/box_sellyoursaas_backup_errors.php
 *      \ingroup    sellyoursaas
 *      \brief      Box to display backups in errors
 */
include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
include_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';


/**
 * Class to manage the box to show bank accounts
 */
class box_sellyoursaas_backup_errors extends ModeleBoxes
{
	public $boxcode  = "sellyoursaasbackuperrors";
	public $boximg   = "sellyoursaas";
	public $boxlabel = "BoxSellyoursaasBackupErrors";
	public $depends  = array("sellyoursaas"); // Box active if module sellyoursaas active

	public $enabled = 1;

	/**
	 *  Constructor
	 *
	 *  @param  DoliDB	$db      	Database handler
	 *  @param	string	$param		More parameters
	 */
	public function __construct($db, $param = '')
	{
		global $conf, $user;

		$this->db = $db;

		$this->hidden = !$user->hasRight('sellyoursaas', 'read');
	}

	/**
	 *  Load data into info_box_contents array to show array later.
	 *
	 *  @param	int		$max        Maximum number of records to load
	 *  @return	void
	 */
	public function loadBox($max = 5)
	{
		global $user, $langs, $conf;

		$langs->loadLangs(array("sellyoursaas@sellyoursaas", "other"));

		$this->max = $max;

		$this->info_box_head = array('text' => $langs->trans("BoxTitleSellyoursaasBackupErrors"));

		if ($user->hasRight('sellyoursaas', 'read')) {
			$nbinstancebackuperror = 0;
			$nbinstanceremotebackuperror = 0;
			$modearray = array("","remote");
			foreach ($modearray as $mode) {
				$sql = "SELECT c.ref_customer as status,";
				$sql .= " MIN(ce.latestbackup".$mode."_date) as mintry, MIN(ce.latestbackup".$mode."_date_ok) as minok,";
				$sql .= " MAX(ce.latestbackup".$mode."_date) as maxtry, MAX(ce.latestbackup".$mode."_date_ok) as maxok";
				$sql .= " FROM ".$this->db->prefix()."contrat as c, ".$this->db->prefix()."contrat_extrafields as ce";
				$sql .= " WHERE ce.fk_object = c.rowid";
				$sql .= " AND ce.deployment_status IN ('done', 'processing')";
				$sql .= " AND ce.latestbackup".$mode."_status = 'KO'";
				$sql .= " GROUP BY c.ref_customer";
				
				
				dol_syslog(get_class($this)."::loadBox", LOG_DEBUG);
				
				$result = $this->db->query($sql);
				if ($result) {
					while ($obj = $this->db->fetch_object($resql)) {
						// Faire le test pour savoir si oui ou non > 48 dernière reussite
					}
				}
			}
			if ($result) {
				$line = 0;
				$num = $this->db->num_rows($result);

				$this->info_box_contents[$line][] = array(
					'td' => '',
					'text' => $langs->trans("NbPersistantErrorLocalBackup"),
					'asis' => 1,
				);

				$this->info_box_contents[$line][] = array(
					'td' => '',
					'text' => $objp->number,
				);
				$line++;

				$this->info_box_contents[$line][] = array(
					'td' => '',
					'text' => $langs->trans("NbPersistantErrorRemoteBackup"),
					'asis' => 1,
				);

				$this->info_box_contents[$line][] = array(
					'td' => '',
					'text' => $objp->number,
				);

				$this->db->free($result);
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => '',
					'maxlength'=>500,
					'text' => ($this->db->error().' sql='.$sql),
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="nohover left"',
				'text' => '<span class="opacitymedium">'.$langs->trans("ReadPermissionNotAllowed").'</span>'
			);
		}
	}

	/**
	 *  Method to show box
	 *
	 *  @param  array   $head       Array with properties of box title
	 *  @param  array   $contents   Array with properties of box lines
	 *  @param  int     $nooutput   No print, only return string
	 *  @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
