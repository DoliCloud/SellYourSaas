<?php
/* Copyright (C) 2005-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This file is an example to follow to add your own email selector inside
 * the Dolibarr email tool.
 * Follow instructions given in README file to know what to change to build
 * your own emailing list selector.
 * Code that need to be changed in this file are marked by "CHANGE THIS" tag.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/mailings/modules_mailings.php';
include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';


/**
 * mailing_mailinglist_sellyousaas
 */
class mailing_mailinglist_sellyoursaas extends MailingTargets
{
	public $name = 'mailinglist_sellyoursaas';
	public $desc = 'Prospects or Customers SellYourSaas';
	public $require_admin = 0;

	public $enabled = '$conf->sellyoursaas->enabled';

	public $require_module = array();
	public $picto = 'sellyoursaas@sellyoursaas';
	public $db;


	/**
	 *	Constructor
	 *
	 * 	@param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db=$db;
	}


	/**
	 *   Affiche formulaire de filtre qui apparait dans page de selection des destinataires de mailings
	 *
	 *   @return     string      Retourne zone select
	 */
	public function formFilter()
	{
		global $langs;
		$langs->load("members");

		$form=new Form($this->db);
		$formcompany=new FormCompany($this->db);

		$arraystatus=array('processing'=>'Processing','done'=>'Done','undeployed'=>'Undeployed');

		$s='';
		$s.=$langs->trans("Type").': ';
		$s.=$formcompany->selectProspectCustomerType(GETPOST('client', 'alpha'), 'client');

		$s.=' ';

		$s.=$langs->trans("Country").': ';
		$formother=new FormAdmin($this->db);
		$s.=$form->select_country(GETPOST('country_id', 'alpha'), 'country_id');

		$s.='<br> ';

		$s.=$langs->trans("Language").': ';
		$formother=new FormAdmin($this->db);

		$s.=$formother->select_language(GETPOST('lang_id', 'array'), 'lang_id', 0, null, $langs->trans("Language"), 0, 0, '', 0, 0, 1);

		$s.=$langs->trans("NotLanguage").': ';
		$formother=new FormAdmin($this->db);
		$s.=$formother->select_language(GETPOST('not_lang_id', 'array'), 'not_lang_id', 0, null, $langs->trans("NotLanguage"), 0, 0, '', 0, 0, 1);

		$s .= '<br>';

		$s.=$langs->trans("DoNotUseDefaultStripeAccount").': ';
		$s.='<input type="checkbox" class="margintoponly marginbottomonly" value="1" name="donotusedefaultstripeaccount"'.(GETPOST('donotusedefaultstripeaccount') ? ' checked' : '').'>';

		// Filter on contracts
		$s.='<br> ';

		$s.=$langs->trans("DeploymentStatus").': ';
		$s.='<select name="filter" id="sellyoursaas_filter" class="flat">';
		$s.='<option value="none">&nbsp;</option>';
		foreach ($arraystatus as $key => $status) {
			$s.='<option value="'.$key.'"'.(GETPOST('filter', 'alpha') == $key ? ' selected':'').'>'.$status.'</option>';
		}
		$s.='</select>';
		$s .= ajax_combobox("sellyoursaas_filter");

		$s.=' ';

		$listofipwithinstances=array();
		$sql = "SELECT rowid, ref, ipaddress, status, servercountries";
		$sql .= " FROM ".MAIN_DB_PREFIX."sellyoursaas_deploymentserver";
		$resql=$this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$listofipwithinstances[$obj->rowid] = array('ref'=>$obj->ref, 'ipaddress'=>$obj->ipaddress, 'status'=>$obj->status, 'servercountries'=>$obj->servercountries);
			}
			$this->db->free($resql);
		} else dol_print_error($this->db);

		$s.=$langs->trans("DeploymentHost").': ';
		$s.='<select name="filterip" id="sellyoursaas_filterip" class="flat">';
		$s.='<option value="none">&nbsp;</option>';
		foreach ($listofipwithinstances as $key => $val) {
			$label = $val['ref'].' - '.$val['ipaddress'].(empty($val['servercountries']) ? '' : '('.$val['servercountries'].')');
			$labelhtml = $val['ref'].'<span class="opacitymedium"> - '.$val['ipaddress'].(empty($val['servercountries']) ? '' : ' ('.$val['servercountries'].')').'</span>';
			$s.='<option value="'.$val['ipaddress'].'"'.(GETPOST('filterip', 'alpha') == $val['ipaddress'] ? ' selected':'').' data-html="'.dol_escape_htmltag($labelhtml).'">'.dol_escape_htmltag($label).'</option>';
		}
		$s.='</select>';
		$s .= ajax_combobox("sellyoursaas_filterip");
		$s.='<br> ';

		/*$listofpackages=array();
		$sql = "SELECT DISTINCT ref";
		$sql .= " FROM ".MAIN_DB_PREFIX."packages";
		//$sql .= " WHERE deployment_host IS NOT NULL AND deployment_status IN ('done', 'processing')";
		$resql=$this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$listofpackages[]=$obj->ref;
			}
			$this->db->free($resql);
		} else dol_print_error($this->db);
		$s .= $langs->trans("Package").': ';
		*/

		// Filter on line of contracts
		$s .= img_picto('', 'product');
		$s .= $form->select_produits(GETPOST('productid', 'int'), 'productid', '', 20, 0, 1, 2, '', 0, array(), 0, '1', 0, '', 0, '', array(), 1);

		/*
		$s .= '<br>';
		$s .= '<input value="'.GETPOST('contractpricetotal', 'int').'" name="contractpricetotal" class="width100 right marginrightonly" placeholder="'.$langs->trans("UnitPriceOfLine").'">';
		$s .= '<input value="'.GETPOST('quantityproduct', 'int').'" name="quantityproduct" class="width100 right marginrightonly" placeholder="'.$langs->trans("Quantity").'">';
		$s .= '<input value="'.GETPOST('discountproduct', 'int').'" name="discountproduct" class="width100 right marginrightonly" placeholder="'.$langs->trans("Discount").'">';
		*/

		return $s;
	}


	/**
	 *  Renvoie url lien vers fiche de la source du destinataire du mailing
	 *
	 *  @param		int			$id		ID
	 *  @return     string      		Url lien
	 */
	public function url($id)
	{
		return '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$id.'">'.img_object('', "company").'</a>';
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  This is the main function that returns the array of emails
	 *
	 *  @param	int		$mailing_id    	Id of emailing
	 *  @param	array	$filtersarray   Requete sql de selection des destinataires
	 *  @return int           			<0 if error, number of emails added if ok
	 */
	public function add_to_target($mailing_id, $filtersarray = array())
	{
		// phpcs:enable
		global $conf;

		$target = array();
		$cibles = array();
		$j = 0;

		if (GETPOSTISSET('lang_id') && is_array($_POST['lang_id'])) {
			foreach ($_POST['lang_id'] as $key => $val) {
				if (empty($val)) {
					unset($_POST['lang_id'][$key]);
				}
			}
		}
		if (GETPOSTISSET('not_lang_id') && is_array($_POST['not_lang_id'])) {
			foreach ($_POST['not_lang_id'] as $key => $val) {
				if (empty($val)) {
					unset($_POST['not_lang_id'][$key]);
				}
			}
		}

		$productid = GETPOSTINT('productid');
		$contractpricetotal = price2num(GETPOST('contractpricetotal'));
		$quantityproduct = price2num(GETPOST('quantityproduct'));
		$discountproduct = price2num(GETPOST('discountproduct'));

		$sql = " SELECT s.rowid as id, email, nom as lastname, '' as firstname, s.default_lang, c.code as country_code, c.label as country_label,";
		$sql .= " se.stripeaccount, se.domain_registration_page";
		if ((! empty($_POST['filter']) && $_POST['filter'] != 'none') ||
			(! empty($_POST['filterip']) && $_POST['filterip'] != 'none') ||
			($productid > 0)) {
				$sql .= ", coe.deployment_host";
		}
		$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se on se.fk_object = s.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as c on s.fk_pays = c.rowid";
		if ((! empty($_POST['filter']) && $_POST['filter'] != 'none') || (! empty($_POST['filterip']) && $_POST['filterip'] != 'none') || ($productid > 0)) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat as co on co.fk_soc = s.rowid";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as coe on coe.fk_object = co.rowid";
		}
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."categorie_societe as cs ON cs.fk_soc = s.rowid AND cs.fk_categorie = ".((int) $conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG);
		if ($contractpricetotal > 0 || $productid > 0 || $quantityproduct > 0 || $discountproduct != '') {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_rec as fr on fr.fk_soc = s.rowid";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facturedet_rec as fdr on fdr.fk_facture = fr.rowid";
		}
		$sql .= " WHERE email IS NOT NULL AND email <> ''";
		if (GETPOST('lang_id') && GETPOST('lang_id') != 'none') $sql.= natural_search('default_lang', join(',', GETPOST('lang_id', 'array')), 3);
		if (GETPOST('not_lang_id') && GETPOST('not_lang_id') != 'none') $sql.= natural_search('default_lang', join(',', GETPOST('not_lang_id', 'array')), -3);
		if (GETPOST('country_id') && GETPOST('country_id') != 'none') $sql.= " AND fk_pays IN ('".$this->db->sanitize(GETPOST('country_id', 'intcomma'), 1)."')";
		if (GETPOST('filter') && GETPOST('filter') != 'none') {
			$sql.= " AND coe.deployment_status = '".$this->db->escape(GETPOST('filter'))."'";
		}
		if (GETPOST('filterip') && GETPOST('filterip') != 'none') {
			$sql.= " AND coe.deployment_host = '".$this->db->escape(GETPOST('filterip'))."'";
		}
		if (GETPOST('client') && GETPOST('client') != '-1') {
			$sql.= ' AND s.client IN ('.$this->db->sanitize(GETPOST('client', 'intcomma')).')';
		}

		if ($productid > 0) {
			$sql .= ' AND co.rowid IN (SELECT fk_contrat FROM '.MAIN_DB_PREFIX.'contratdet as cd WHERE fk_product IN ('.$this->db->sanitize($this->db->escape($productid)).'))';
		}
		if ($quantityproduct > 0) {
			$sql .= " AND fdr.qty = ".((float) $quantityproduct);
		}
		if ($productid > 0) {
			$sql .= " AND fdr.fk_product = ".((int) $productid);
		}
		if ($discountproduct != '') {
			if (empty($discountproduct)) {
				$sql .= " AND (fdr.remise_percent IS NULL or fdr.remise_percent = 0)";
			} else {
				$sql .= " AND fdr.remise_percent = ".((float) $discountproduct);
			}
		}
		if ($contractpricetotal > 0) {
			$sql .= " AND fdr.subprice = ".((float) $contractpricetotal);
		}

		if (GETPOST('donotusedefaultstripeaccount')) {
			$sql.= " AND se.stripeaccount IS NOT NULL AND se.stripeaccount <> ''";
		}

		$sql.= " ORDER BY email";
		//print $sql;

		$this->sql = $sql;

		// Stocke destinataires dans cibles
		$result=$this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$i = 0;

			dol_syslog("mailinglist_sellyoursaas.modules.php: mailing $num target found");

			$old = '';
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				if ($old <> $obj->email) {
					$cibles[$j] = array(
						'email' => $obj->email,
						'lastname' => $obj->lastname,
						'id' => $obj->id,
						'firstname' => $obj->firstname,
						'other' => 'lang='.$obj->default_lang.';country_code='.$obj->country_code.';domain_registration='.$obj->domain_registration_page.';host_instance='.$obj->deployment_host,
						'source_url' => $this->url($obj->id),
						'source_id' => $obj->id,
						'source_type' => 'thirdparty'
					);
					$old = $obj->email;
					$j++;
				}

				$i++;
			}
		} else {
			dol_syslog($this->db->error());
			$this->error=$this->db->error();
			return -1;
		}

		// You must fill the $target array with record like this
		// $target[0]=array('email'=>'email_0','name'=>'name_0','firstname'=>'firstname_0');
		// ...
		// $target[n]=array('email'=>'email_n','name'=>'name_n','firstname'=>'firstname_n');

		// Example: $target[0]=array('email'=>'myemail@mydomain.com','name'=>'Doe','firstname'=>'John');

		// ----- Your code end here -----

		return parent::addTargetsToDatabase($mailing_id, $cibles);
	}


	/**
	 *	On the main mailing area, there is a box with statistics.
	 *	If you want to add a line in this report you must provide an
	 *	array of SQL request that returns two field:
	 *	One called "label", One called "nb".
	 *
	 *	@return		array
	 */
	public function getSqlArrayForStats()
	{
		// CHANGE THIS: Optionnal

		//var $statssql=array();
		//$this->statssql[0]="SELECT field1 as label, count(distinct(email)) as nb FROM mytable WHERE email IS NOT NULL";

		return array();
	}


	/**
	 *	Return here number of distinct emails returned by your selector.
	 *	For example if this selector is used to extract 500 different
	 *	emails from a text file, this function must return 500.
	 *
	 *	@param	string	$filter		Filter
	 *	@param	string	$option		Options
	 *	@return	int					Nb of recipients
	 */
	public function getNbOfRecipients($filter = 1, $option = '')
	{
		$a = parent::getNbOfRecipients("SELECT COUNT(DISTINCT(email)) as nb FROM ".MAIN_DB_PREFIX."societe as s WHERE email IS NOT NULL AND email <> ''");
		if ($a < 0) return -1;
		return $a;
	}
}
