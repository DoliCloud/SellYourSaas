<?php
/* Copyright (C) 2022  Laurent Destailleur <eldy@users.sourceforge.net>
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
 * \file        sellyoursaas/class/sellyoursaascontract.class.php
 * \ingroup     sellyoursaas
 * \brief       This file is a CRUD class file for SellYourSaas contract (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';

/**
 * Class for SellYourSaasContract
 */
class SellYourSaasContract extends Contrat
{
	public $instance;
	public $username_web;
	public $password_web;
	public $hostname_db;
	public $port_db;
	public $username_db;
	public $password_db;
	public $database_db;
	public $deployment_host;
	public $latestbackup_date_ok;
	public $backup_frequency;
}
