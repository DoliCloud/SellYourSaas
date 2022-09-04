<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2021 Alice Adminson <aadminson@example.com>
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

use Luracast\Restler\RestException;

dol_include_once('/sellyoursaas/class/packages.class.php');


/**
 * \file    sellyoursaas/class/api_sellyoursaas.class.php
 * \ingroup sellyoursaas
 * \brief   File for API of SellYourSaas
 */


/**
 * API class for sellyoursaas
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Sellyoursaasapi extends DolibarrApi
{
	/**
	 * @var Packages $packages {@type Packages}
	 */
	public $packages;

	/**
	 * Constructor
	 *
	 * @url     GET /
	 *
	 */
	public function __construct()
	{
		global $db, $conf;
		$this->db = $db;
		$this->packages = new Packages($this->db);
	}


	/**
	 * Get setup or status information of SellYourSaas
	 *
	 * @param 	string 	$lang 	Language code
	 * @return array
	 *
	 * @url	GET setup
	 **/
	public function setup($lang = '')
	{
		global $conf;

		$return = array();

		$tmplangs = new Translate('', $conf);
		$tmplangs->setDefaultLang($lang);
		$tmplangs->load("sellyoursaas@sellyoursaas");

		if (!empty($conf->global->SELLYOURSAAS_DISABLE_NEW_INSTANCES)) {
			$return['SELLYOURSAAS_DISABLE_NEW_INSTANCES'] = $conf->global->SELLYOURSAAS_DISABLE_NEW_INSTANCES;	// Global disabling of new instance creation
		}

		$arrayofdifferentmessages = array();

		foreach ($conf->global as $key => $val) {
			if (preg_match('/^SELLYOURSAAS_ANNOUNCE_ON/', $key)) {
				if ($val) {
					$return[$key] = $val;
					$newkey = preg_replace('/_ON/', '', $key);
					if (!empty($conf->global->$newkey)) {
						$return[$newkey] = $conf->global->$newkey;
						$arrayofdifferentmessages[] = $tmplangs->trans(str_replace(array('(', ')'), '', $conf->global->$newkey));
						$return[$newkey.'_trans'] = $tmplangs->trans(str_replace(array('(', ')'), '', $conf->global->$newkey));
					}
				}
			}
		}

		$arrayofdifferentmessages = array_unique($arrayofdifferentmessages);

		$return['message'] = join(', ', $arrayofdifferentmessages);

		return $return;
	}

	/**
	 * Get properties of a packages object
	 *
	 * Return an array with packages informations
	 *
	 * @param 	int 	$id ID of packages
	 * @return 	array|mixed data without useless information
	 *
	 * @url	GET packages/{id}
	 *
	 * @throws RestException 401 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function get($id)
	{
		if (!DolibarrApiAccess::$user->rights->sellyoursaas->read) {
			throw new RestException(401);
		}

		$result = $this->packages->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Packages not found');
		}

		/*if (!DolibarrApi::_checkAccessToResource('packages', $this->packages->id, 'sellyoursaas_packages')) {
			throw new RestException(401, 'Access to instance id='.$this->packages->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}*/

		return $this->_cleanObjectDatas($this->packages);
	}


	/**
	 * List packages
	 *
	 * Get a list of packages
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException
	 *
	 * @url	GET packages/
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		global $db, $conf;

		$obj_ret = array();
		$tmpobject = new Packages($this->db);

		if (!DolibarrApiAccess::$user->rights->sellyoursaas->read) {
			throw new RestException(401);
		}

		$socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : '';

		$restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		/*if ($restrictonsocid && !DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) {
			$search_sale = DolibarrApiAccess::$user->id;
		}*/

		$sql = "SELECT t.rowid";
		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
			$sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
		}
		$sql .= " FROM ".MAIN_DB_PREFIX.$tmpobject->table_element." as t";

		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
		}
		$sql .= " WHERE 1 = 1";

		// Example of use $mode
		//if ($mode == 1) $sql.= " AND s.client IN (1, 3)";
		//if ($mode == 2) $sql.= " AND s.client IN (2, 3)";

		if ($tmpobject->ismultientitymanaged) {
			$sql .= ' AND t.entity IN ('.getEntity($tmpobject->element).')';
		}
		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
			$sql .= " AND t.fk_soc = sc.fk_soc";
		}
		if ($restrictonsocid && $socid) {
			$sql .= " AND t.fk_soc = ".$socid;
		}
		if ($restrictonsocid && $search_sale > 0) {
			$sql .= " AND t.rowid = sc.fk_soc"; // Join for the needed table to filter by sale
		}
		// Insert sale filter
		if ($restrictonsocid && $search_sale > 0) {
			$sql .= " AND sc.fk_user = ".$search_sale;
		}
		if ($sqlfilters) {
			$errormessage = '';
			if (!DolibarrApi::_checkFilters($sqlfilters, $errormessage)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';
			$sql .= " AND (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new Packages($this->db);
				if ($tmp_object->fetch($obj->rowid)) {
					$obj_ret[] = $this->_cleanObjectDatas($tmp_object);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving packages list: '.$this->db->lasterror());
		}
		if (!count($obj_ret)) {
			throw new RestException(404, 'No packages found');
		}
		return $obj_ret;
	}

	/**
	 * Create packages object
	 *
	 * @param array $request_data   Request datas
	 * @return int  ID of packages
	 *
	 * @throws RestException
	 *
	 * @url	POST packages/
	 */
	public function post($request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->sellyoursaas->write) {
			throw new RestException(401);
		}
		// Check mandatory fields
		$result = $this->_validate($request_data);

		foreach ($request_data as $field => $value) {
			$this->packages->$field = $value;
		}
		if ($this->packages->create(DolibarrApiAccess::$user)<0) {
			throw new RestException(500, "Error creating Packages", array_merge(array($this->packages->error), $this->packages->errors));
		}
		return $this->packages->id;
	}

	/**
	 * Update packages
	 *
	 * @param int   $id             Id of packages to update
	 * @param array $request_data   Datas
	 * @return int
	 *
	 * @throws RestException
	 *
	 * @url	PUT packages/{id}
	 */
	public function put($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->sellyoursaas->write) {
			throw new RestException(401);
		}

		$result = $this->packages->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Packages not found');
		}

		/*if (!DolibarrApi::_checkAccessToResource('packages', $this->packages->id, 'sellyoursaas_packages')) {
			throw new RestException(401, 'Access to instance id='.$this->packages->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}*/

		foreach ($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			$this->packages->$field = $value;
		}

		if ($this->packages->update(DolibarrApiAccess::$user, false) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->packages->error);
		}
	}

	/**
	 * Delete packages
	 *
	 * @param   int     $id   Packages ID
	 * @return  array
	 *
	 * @throws RestException
	 *
	 * @url	DELETE packages/{id}
	 */
	public function delete($id)
	{
		if (!DolibarrApiAccess::$user->rights->sellyoursaas->delete) {
			throw new RestException(401);
		}
		$result = $this->packages->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Packages not found');
		}

		/*if (!DolibarrApi::_checkAccessToResource('packages', $this->packages->id, 'sellyoursaas_packages')) {
			throw new RestException(401, 'Access to instance id='.$this->packages->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}*/

		if (!$this->packages->delete(DolibarrApiAccess::$user)) {
			throw new RestException(500, 'Error when deleting Packages : '.$this->packages->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'Packages deleted'
			)
		);
	}


	/**
	 * Get properties of a packages object
	 *
	 * Return an array with packages informations
	 *
	 * @param	string		$yearmonth	'YYYYMM' or 'last'
	 * @return 	array|mixed 			data without useless information
	 *
	 * @url	GET statistics/
	 *
	 * @throws RestException 401 Not allowed
	 */
	public function statistics($yearmonth)
	{
		include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

		$result = array();

		if (!DolibarrApiAccess::$user->rights->sellyoursaas->read) {
			throw new RestException(401);
		}

		$now = dol_now();
		$tmparray = dol_getdate($now);
		$tmpprev = dol_get_prev_month($tmparray['mon'], $tmparray['year']);

		$sql = 'SELECT name, x, y, tms FROM '.MAIN_DB_PREFIX."sellyoursaas_stats";

		if ($yearmonth == 'last') {
			$sql .= " WHERE x = '".$this->db->escape(dol_print_date($now, '%Y%m'))."' OR x = '".sprintf('%04d%02d', $tmpprev['year'], $tmpprev['month'])."'";
		} else {
			$sql .= " WHERE x = '".$this->db->escape($yearmonth)."'";
		}
		$sql .= ' ORDER BY x DESC';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if (empty($result[$obj->name])) {
					$result[$obj->name] = array('x' => $obj->x, 'y' => $obj->y, 'tms'=>$obj->tms);
				}
			}
		} else {
			throw new RestException(500, 'Error  sql : '.$this->db->lasterror());
		}

		return $result;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	protected function _cleanObjectDatas($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->rowid);
		unset($object->canvas);

		/*unset($object->name);
		unset($object->lastname);
		unset($object->firstname);
		unset($object->civility_id);
		unset($object->statut);
		unset($object->state);
		unset($object->state_id);
		unset($object->state_code);
		unset($object->region);
		unset($object->region_code);
		unset($object->country);
		unset($object->country_id);
		unset($object->country_code);
		unset($object->barcode_type);
		unset($object->barcode_type_code);
		unset($object->barcode_type_label);
		unset($object->barcode_type_coder);
		unset($object->total_ht);
		unset($object->total_tva);
		unset($object->total_localtax1);
		unset($object->total_localtax2);
		unset($object->total_ttc);
		unset($object->fk_account);
		unset($object->comments);
		unset($object->note);
		unset($object->mode_reglement_id);
		unset($object->cond_reglement_id);
		unset($object->cond_reglement);
		unset($object->shipping_method_id);
		unset($object->fk_incoterms);
		unset($object->label_incoterms);
		unset($object->location_incoterms);
		*/

		// If object has lines, remove $db property
		if (isset($object->lines) && is_array($object->lines) && count($object->lines) > 0) {
			$nboflines = count($object->lines);
			for ($i = 0; $i < $nboflines; $i++) {
				$this->_cleanObjectDatas($object->lines[$i]);

				unset($object->lines[$i]->lines);
				unset($object->lines[$i]->note);
			}
		}

		return $object;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validate($data)
	{
		$packages = array();
		foreach ($this->packages->fields as $field => $propfield) {
			if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
				continue; // Not a mandatory field
			}
			if (!isset($data[$field])) {
				throw new RestException(400, "$field field missing");
			}
			$packages[$field] = $data[$field];
		}
		return $packages;
	}
}
