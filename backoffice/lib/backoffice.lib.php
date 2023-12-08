<?php
/* Copyright (C) 2004-2021 Laurent Destailleur  <eldy@users.sourceforge.net>
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

/**
 *       \file       htdocs/sellyoursaas/backoffice/lib/backoffice.lib.php
 *       \ingroup    sellyoursaas
 *       \brief      Library for sellyoursaas backoffice lib
 */


/**
 * Build tabs for admin page
 *
 * @return array
 */
function sellYourSaasBackofficePrepareHead()
{
	global $langs;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/index.php', 1);
	$head[$h][1] = $langs->trans("Home");
	$head[$h][2] = 'home';
	$h++;

	if (empty(getDolGlobalString("SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION"))) {
		$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/deployment_servers.php', 1);
		$head[$h][1] = $langs->trans("DeploymentServers");
		$head[$h][2] = 'deploymentservers';
		$h++;
	} else {
		$head[$h][0] = '/custom/sellyoursaas/deploymentserver_list.php';
		$head[$h][0] = dol_buildpath('/sellyoursaas/deploymentserver_list.php', 1);
		$head[$h][1] = $langs->trans("DeploymentServers");
		$head[$h][2] = 'deploymentservers';
		$h++;
	}

	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/setup_antispam.php', 1);
	$head[$h][1] = $langs->trans("AntiSpam");
	$head[$h][2] = 'antispam';
	$h++;

	$head[$h][0] = DOL_URL_ROOT.'/core/customreports.php?objecttype=contract&tabfamily=sellyoursaas';
	$head[$h][1] = $langs->trans("CustomReports");
	$head[$h][2] = 'customreports';
	$h++;

	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/notes.php', 1);
	$head[$h][1] = $langs->trans("Notes");
	$head[$h][2] = 'notes';
	$h++;

	return $head;
}
