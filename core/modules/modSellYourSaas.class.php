<?php
/* Copyright (C) 2008-2022 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * Licensed under the GNU GPL v3 or higher (See file gpl-3.0.html)
 */

/**     \defgroup   sellyoursaas     Module SellYourSaas
 *      \brief      Module SellYourSaas
 */

/**
 *      \file       htdocs/sellyoursaas/core/modules/modSellYourSaas.class.php
 *      \ingroup    sellyoursaas
 *      \brief      Description and activation file for module SellYourSaas
 */
include_once DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php";


/**
 * Description and activation class for module SellYourSaas
 */
class modSellYourSaas extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param		DoliDB		$db		Database handler
	 */
	public function __construct($db)
	{
		global $langs,$conf;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used module id).
		$this->numero = 101050;
		// Key text used to identify module (for permission, menus, etc...)
		$this->rights_class = 'sellyoursaas';

		// Family can be 'crm','financial','hr','projects','product','technic','other'
		// It is used to group modules in module setup page
		$this->family = "other";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description used if translation string 'ModuleXXXDesc' not found (XXX is value SellYourSaas)
		$this->description = "Module to sell SaaS applications";
		$this->editor_name = 'SellYourSaas team';
		$this->editor_url = 'https://www.sellyoursaas.org';
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '2.0';
		// Key used in llx_const table to save module status enabled/disabled (where SELLYOURSAAS is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='sellyoursaas@sellyoursaas';

		// Data directories to create when module is enabled
		// Note: Directory "/sellyoursaas" is shared between servers, "/sellyoursaas_local" is unique for each server
		// The directory crt is shared but a copy is done locally to avoid dependency on master and avoid interuption of service if NFS is out.
		$this->dirs = array('/sellyoursaas/temp','/sellyoursaas/packages','/sellyoursaas/git','/sellyoursaas/spam', '/sellyoursaas/crt', '/sellyoursaas_local/crt', '/sellyoursaas_local/spam');

		// Config pages. Put here list of php page names stored in admmin directory used to setup module
		$this->config_page_url = array("setup.php@sellyoursaas");

		// Dependencies
		$this->depends = array('modAgenda', 'modFacture', 'modService', 'modBanque', 'modCron', 'modCategorie', 'modContrat', 'modGeoIPMaxmind', 'modStripe');		// List of modules class names that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->phpmin = array(4,1);						// Minimum version of PHP required by module
		$this->langfiles = array("sellyoursaas@sellyoursaas");
		$this->need_dolibarr_version = array(13,0,-5);	// Minimum version of Dolibarr required by module

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array('triggers' => 1,
									'substitutions' => 1,
									'menus' => 0,
									'models' => 1,
									'login' => 1,
									'css' => array(),
									'hooks' => array('thirdpartycard','thirdpartycomm','thirdpartysupplier','thirdpartycontact','contactthirdparty','thirdpartyticket','thirdpartynote','thirdpartydocument','thirdpartypartnership',
													'projectthirdparty','consumptionthirdparty','thirdpartybancard','thirdpartymargins','ticketlist','thirdpartynotification','agendathirdparty',
													'thirdpartydao','formmail','searchform','thirdpartylist','customerlist','prospectlist','contractcard','contractdao','contractlist',
													'pdfgeneration','odtgeneration','customreport'));

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, 'desc', visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('SELLYOURSAAS_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('SELLYOURSAAS_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array(
			0=>array('NLTECHNO_NOTE', 'chaine',
				'Welcome to SellYourSaas Home page<br><br>
		        Link to the specification: https://framagit.org/eldy/sell-your-saas<br><br>
		        ...You can enter content on this page to save any notes/information of your choices.', 'This is another constant to add', 0, 'allentities', 0),
			1=>array('CONTRACT_SYNC_PLANNED_DATE_OF_SERVICES', 'chaine', 1, 'Sync the planned date of services to the same value for all lines in the same contract', 0, 'current', 1),
			2=>array('THIRDPARTY_LOGO_ALLOW_EXTERNAL_DOWNLOAD', 'chaine', 1, 'Allow to access thirdparty logo from external link', 0, 'current', 0),
			3=>array('PRODUIT_SOUSPRODUITS', 'chaine', 1, 'Enable virtual products', 0, 'current', 0),
			4=>array('STRIPE_ALLOW_LOCAL_CARD', 'chaine', 1, 'Allow to save stripe credit card locally', 0, 'current', 1),
			5=>array('SELLYOURSAAS_NAME', 'chaine', 'SellYourSaas', 'Name of your SellYouSaaS service', 0, 'current', 0),
			6=>array('CONTRACT_DISABLE_AUTOSET_AS_CLIENT_ON_CONTRACT_VALIDATION', 'chaine', '1', 'Disable autoset of client status on contract validation', 0, 'current', 0),
			7=>array('INVOICE_ALLOW_EXTERNAL_DOWNLOAD', 'chaine', '1', 'Invoice can be downloaded with a public link', 0, 'current', 0),
			8=>array('SELLYOURSAAS_NBHOURSBETWEENTRIES', 'chaine', 49, 'Nb hours minium between each try', 1, 'current', 0),
			9=>array('SELLYOURSAAS_NBDAYSBEFOREENDOFTRIES', 'chaine', 35, 'Nb days before stopping invoice payment try', 1, 'current', 0),
			10=>array('AUDIT_ENABLE_PREFIX_SESSION', 'chaine', 1, 'Enable column prefix session in audit view', 1, 'current', 0),
		);

		if (! isset($conf->sellyoursaas) || ! isset($conf->sellyoursaas->enabled)) {
			$conf->sellyoursaas=new stdClass();
			$conf->sellyoursaas->enabled=0;
		}


		// Array to add new pages in new tabs
		// Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@sellyoursaas:$user->rights->sellyoursaas->read:/sellyoursaas/mynewtab1.php?id=__ID__',  					// To add a new tab identified by code tabname1
		//                              'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@sellyoursaas:$user->rights->othermodule->read:/sellyoursaas/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		//                              'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname
		// Can also be:	$this->tabs = array('data'=>'...', 'entity'=>0);
		//
		// where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view
		$this->tabs = array();
		$this->tabs[] = array('data'=>'contract:+upgrade:UsefulLinks:sellyoursaas@sellyoursaas:$user->rights->sellyoursaas->read:/sellyoursaas/backoffice/instance_links.php?id=__ID__');
		$this->tabs[] = array('data'=>'contract:+users:Users:sellyoursaas@sellyoursaas:$user->rights->sellyoursaas->read:/sellyoursaas/backoffice/instance_users.php?id=__ID__');
		$this->tabs[] = array('data'=>'contract:+backup:SUBSTITUTION_BackupInstanceTabTitle:sellyoursaas@sellyoursaas:$user->rights->sellyoursaas->read:/sellyoursaas/backoffice/instance_backup.php?id=__ID__');


		// Dictionaries
		$this->dictionaries=array();


		// Boxes
		$this->boxes = array();			// List of boxes
		$r=0;

		// Add here list of php file(s) stored in includes/boxes that contains class to show a box.
		// Example:
		//$this->boxes[$r][1] = "myboxa.php";
		//$r++;
		//$this->boxes[$r][1] = "myboxb.php";
		//$r++;


		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$statusatinstall=1;
		$arraydate=dol_getdate(dol_now());
		$datestart=dol_mktime(21, 15, 0, $arraydate['mon'], $arraydate['mday'], $arraydate['year']);

		$this->cronjobs = array(
			// Generation of draft invoices is done with priority 50

			0 =>array('priority'=>61, 'label'=>'SellYourSaasValidateDraftInvoices',             'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doValidateDraftInvoices',             'parameters'=>'',      'comment'=>'Search draft invoices on sellyoursaas customers and check they are linked to a not closed contract. Validate it if not and if there is not another validated invoice, do nothing if closed. You can set the id of a thirdparty as parameter to restrict the batch to a given thirdparty.', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),

			1 =>array('priority'=>62, 'label'=>'SellYourSaasAlertSoftEndTrial',                 'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doAlertSoftEndTrial',                 'parameters'=>'',      'comment'=>'Search contracts of sellyoursaas customers that are deployed + with open lines + about to expired (= date between (end date - SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT) and (end date - SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT + 7)) + not yet already warned (date_softalert_endfreeperiod is null), then send email remind', 'frequency'=>30, 'unitfrequency'=>60, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),

			2 =>array('priority'=>65, 'label'=>'SellYourSaasAlertCreditCardExpiration',         'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doAlertCreditCardExpiration',         'parameters'=>'1, 20', 'comment'=>'Send warning to sellyoursaas customers with an active recurring invoice when default payment mode is credit card and it will expire at end of month', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
			//3 =>array('priority'=>66, 'label'=>'SellYourSaasAlertPaypalExpiration',             'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doAlertPaypalExpiration',             'parameters'=>'1, 20', 'comment'=>'Send warning when paypal preapproval will expire to sellyoursaas customers', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")'),

			4 =>array('priority'=>75, 'label'=>'SellYourSaasTakePaymentStripe',                 'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doTakePaymentStripe',                 'parameters'=>'0, 0',  'comment'=>'Loop on invoice for customer with default payment mode Stripe and take payment/send email. Unsuspend if it was suspended and all payments are now ok (done by trigger BILL_CANCEL or BILL_PAYED). First parameter is the maximum number of payments processed in one run. Second parameter is 1 to disable email to customer if payment fails.', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
			//5 =>array('priority'=>76, 'label'=>'SellYourSaasTakePaymentPaypal',                 'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doTakePaymentPaypal',                 'parameters'=>'',      'comment'=>'Loop on invoice for customer with default payment mode Paypal and take payment. Unsuspend if it was suspended.', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")'),

			6 =>array('priority'=>77, 'label'=>'SellYourSaasRefreshContracts',                  'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doRefreshContracts',                  'parameters'=>'',      'comment'=>'Loop on each contract. If it is a paid contract, and there is no unpaid invoice for contract, and line not suspended and end date < or = today + 2 days (so expired or soon expired, we must be sure to make refresh before new generation of invoice), we update qty on contract + qty on linked template invoice', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
			7 =>array('priority'=>78, 'label'=>'SellYourSaasRenewalContracts',                  'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doRenewalContracts',                  'parameters'=>'',      'comment'=>'Loop on each contract. If it is a paid contract, and there is no unpaid invoice for contract, and line not suspended and end date < or = today - 1 day (so expired, we must be sure to make renewal after generation of invoice with 2 chances of invoice generation to not make renewal if payment error), we update qty on contract + qty on linked template invoice + the running contract service end date to end at next period', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),

			8 =>array('priority'=>81, 'label'=>'SellYourSaasSuspendExpiredTestInstances',       'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doSuspendExpiredTestInstances',       'parameters'=>'0, 25', 'comment'=>'Suspend expired services of test instances (a test instance = instance without template neither standard invoice) if it is not a redirect instance and if we are after the planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_SUSPEND). Note that if a payment mode exists for customer, we do NOT suspend instance but create a template invoice instead. 1st parameter can be set to 1 to disable apache reload. 2nd parameter is maximum number of instance to suspend.', 'frequency'=>4, 'unitfrequency'=>3600, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
			9 =>array('priority'=>82, 'label'=>'SellYourSaasUndeployOldSuspendedTestInstances', 'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doUndeployOldSuspendedTestInstances', 'parameters'=>'',      'comment'=>'Undeployed test instances if we are after planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT)', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
			10=>array('priority'=>85, 'label'=>'SellYourSaasSuspendExpiredRealInstances',       'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doSuspendExpiredRealInstances',       'parameters'=>'',      'comment'=>'Suspend expired services of paid instances if we are after planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_SUSPEND)', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
			11=>array('priority'=>86, 'label'=>'SellYourSaasUndeployOldSuspendedRealInstances', 'jobtype'=>'method', 'class'=>'/sellyoursaas/class/sellyoursaasutils.class.php', 'objectname'=>'SellYourSaasUtils', 'method'=>'doUndeployOldSuspendedRealInstances', 'parameters'=>'',      'comment'=>'Undeployed paid instances if we are after planned end date (+ grace offset in SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT)', 'frequency'=>1, 'unitfrequency'=>86400, 'status'=>$statusatinstall, 'test'=>'isModEnabled("sellyoursaas")', 'datestart'=>$datestart),
		);
		// Example: $this->cronjobs=array(0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>true),
		//                                1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>true)
		// );


		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;

		// Add here list of permission defined by an id, a label, a boolean and two constant strings.
		$this->rights[$r][0] = 101051; 				// Permission id (must not be already used)
		$this->rights[$r][1] = 'See SellYourSaas Home area';	// Permission label
		$this->rights[$r][2] = 'r'; 					// Permission by default for new user (0/1)
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'liens';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'voir';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;
		/*
		$this->rights[$r][0] = 101052; 				// Permission id (must not be already used)
		$this->rights[$r][1] = 'Voir page annonces';	// Permission label
		$this->rights[$r][2] = 'r'; 					// Permission by default for new user (0/1)
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'annonces';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'voir';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;
		$this->rights[$r][0] = 101053; 				// Permission id (must not be already used)
		$this->rights[$r][1] = 'Voir page emailings';	// Permission label
		$this->rights[$r][2] = 'r'; 					// Permission by default for new user (0/1)
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'emailings';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'voir';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;
		*/

		// Add here list of permission defined by an id, a label, a boolean and two constant strings.
		// Example:
		$this->rights[$r][0] = 101060; 				// Permission id (must not be already used)
		$this->rights[$r][1] = 'Read SellYourSaaS data';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = 101061; 				// Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/edit SellYourSaaS data (package, ...)';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'write';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = 101062; 				// Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete SellYourSaaS data (package, ...)';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'delete';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = '';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;


		// Main menu entries
		$this->menus = array();			// List of menus to add
		$r=0;

		$this->menu[$r]=array(	'fk_menu'=>0,
								'type'=>'top',
								'titre'=>'__[SELLYOURSAAS_NAME]__',
								'prefix' => img_picto('', 'object_'.$this->picto, 'class="paddingright2imp pictofixedwidth valignmiddle"'),
								'mainmenu'=>'sellyoursaas',
								'url'=>'/sellyoursaas/backoffice/index.php',
								'langs'=>'',
								'position'=>200,
								'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
								'perms'=>'$user->hasRight("sellyoursaas", "liens", "voir")',
								'target'=>'',
								'user'=>0);
		$r++;

		// Summary
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',        // Use r=value where r is index key used for the parent menu entry (higher parent must be a top menu entry)
			'type'=>'left',         // This is a Left menu entry
			'titre'=>'Summary',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_summary',
			'url'=>'/sellyoursaas/backoffice/index.php',
			'langs'=>'sellyoursaas@sellyoursaas',  // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>100,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',           // Use 'perms'=>'$user->rights->NewsSubmitter->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>0);             // 0=Menu for internal users, 1=external users, 2=both
		$r++;

		// Packages
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'Packages',
			'prefix' => img_picto('', 'label', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_packages',
			'url'=>'/sellyoursaas/packages_list.php',
			'langs'=>'',
			'position'=>210,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_packages',
			'type'=>'left',
			'titre'=>'NewPackage',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_createpackage',
			'url'=>'/sellyoursaas/packages_card.php?action=create',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>211,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_packages',
			'type'=>'left',
			'titre'=>'LiveRefsInstances',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_live',
			'url'=>'__[SELLYOURSAAS_REFS_URL]__',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>212,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'_refs',
			'user'=>0);
		$r++;


		// Products - Services
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'Services',
			'prefix' => img_picto('', 'service', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_products',
			'url'=>'/product/list.php?type=1&search_category_product_list[]=__[SELLYOURSAAS_DEFAULT_PRODUCT_CATEG]__',
			'langs'=>'',
			'position'=>220,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_products',
			'type'=>'left',
			'titre'=>'NewService',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_createproduct',
			'url'=>'/product/card.php?type=1&action=create&categories[]=__[SELLYOURSAAS_DEFAULT_PRODUCT_CATEG]__',
			'langs'=>'',
			'position'=>221,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;


		// Link to registration form page
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_products',
			'type'=>'left',
			'titre'=>'RegistrationPages',
			'prefix' => img_picto('', 'generic', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_registration',
			'url'=>'/sellyoursaas/registrationlinks_list.php',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>229,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0);
		$r++;


		// Prospects / Customers
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'ProspectsOrCustomers',
			'prefix' => img_picto('', 'company', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_customers',
			'url'=>'/societe/list.php?search_categ_cus=__[SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG]__&sortfield=s.tms&sortorder=desc',
			'langs'=>'',
			'position'=>230,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_customers',
			'type'=>'left',
			'titre'=>'NewCustomer',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_customers_create',
			'url'=>'/societe/card.php?action=create&type=c&custcats[]=__[SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG]__',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>231,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_customers',
			'type'=>'left',
			'titre'=>'Prospects',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_customers_prospects',
			'url'=>'/societe/list.php?search_categ_cus=__[SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG]__&search_type=2,3&sortfield=s.tms&sortorder=desc',
			'langs'=>'',
			'position'=>233,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_customers',
			'type'=>'left',
			'titre'=>'Customers',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_customers_customers',
			'url'=>'/societe/list.php?search_categ_cus=__[SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG]__&search_type=1,3&sortfield=s.tms&sortorder=desc',
			'langs'=>'',
			'position'=>234,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;


		// Instances
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'Instances',
			'prefix' => img_picto('', 'contract', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_instances',
			'url'=>'/contrat/list.php?leftmenu=contracts&contextpage=sellyoursaasinstances&search_product_category=__[SELLYOURSAAS_DEFAULT_PRODUCT_CATEG]__',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>240,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_instances',
			'type'=>'left',
			'titre'=>'NewInstance',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_instances_create',
			'url'=>'/sellyoursaas/backoffice/newcustomerinstance.php?action=create',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>241,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_instances',
			'type'=>'left',
			'titre'=>'OnlineInstancesWithBackupErrors',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_instances_backup',
			'url'=>'/contrat/list.php?leftmenu=contracts&contextpage=sellyoursaasinstancesbackup&sortfield=ef.latestbackup_date_ok&sortorder=asc&search_options_deployment_status=done&search_options_latestbackup_status=KO',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>245,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		// Link to customers dashboard
		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'CustomerPortal',
			'prefix' => img_picto('', 'globe', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'website',
			'url'=>'__[SELLYOURSAAS_ACCOUNT_URL]__',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>501,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'_sellyoursaas_customer',
			'user'=>0
		);
		$r++;



		// Reseller

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'Resellers',
			'prefix' => img_picto('', 'company', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_resellerlist',
			'url'=>'/societe/list.php?search_categ_sup=__[SELLYOURSAAS_DEFAULT_RESELLER_CATEG]__',
			'langs'=>'',
			'position'=>601,
			'enabled'=>'isModEnabled("sellyoursaas") && getDolGlobalInt(\'SELLYOURSAAS_ALLOW_RESELLER_PROGRAM\')',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=mysaas_resellerlist',
			'type'=>'left',
			'titre'=>'NewReseller',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'mysaas_createreseller',
			'url'=>'/societe/card.php?action=create&type=f&options_dolicloud=no&suppcats[]=__[SELLYOURSAAS_DEFAULT_RESELLER_CATEG]__',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>602,
			'enabled'=>'isModEnabled("sellyoursaas") && getDolGlobalInt(\'SELLYOURSAAS_ALLOW_RESELLER_PROGRAM\')',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "write")',
			'target'=>'',
			'user'=>0
		);
		$r++;


		// Security tools

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas',
			'type'=>'left',
			'titre'=>'SecurityTools',
			'prefix' => img_picto('', 'fa-ban', 'class="paddingright pictofixedwidth"'),
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklist',
			'url'=>'',
			'langs'=>'',
			'position'=>601,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'EvilInstances',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_evilinstances',
			'url'=>'/contrat/list.php?leftmenu=contracts&contextpage=sellyoursaasevilinstances&search_options_spammer=1&search_product_category=__[SELLYOURSAAS_DEFAULT_PRODUCT_CATEG]__',
			'langs'=>'sellyoursaas@sellyoursaas',
			'position'=>651,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'WhitelistIP',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_whitelistip',
			'url'=>'/sellyoursaas/whitelistip_list.php',
			'langs'=>'',
			'position'=>651,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'BlacklistIP',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklistip',
			'url'=>'/sellyoursaas/blacklistip_list.php',
			'langs'=>'',
			'position'=>652,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'BlacklistFrom',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklistfrom',
			'url'=>'/sellyoursaas/blacklistfrom_list.php',
			'langs'=>'',
			'position'=>653,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'BlacklistTo',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklistto',
			'url'=>'/sellyoursaas/blacklistto_list.php',
			'langs'=>'',
			'position'=>654,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'BlacklistDir',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklistdir',
			'url'=>'/sellyoursaas/blacklistdir_list.php',
			'langs'=>'',
			'position'=>655,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'BlacklistContent',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklistcontent',
			'url'=>'/sellyoursaas/blacklistcontent_list.php',
			'langs'=>'',
			'position'=>656,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;

		$this->menu[$r]=array(
			'fk_menu'=>'fk_mainmenu=sellyoursaas,fk_leftmenu=sellyoursaas_blacklist',
			'type'=>'left',
			'titre'=>'BlacklistMails',
			'prefix' => '',
			'mainmenu'=>'sellyoursaas',
			'leftmenu'=>'sellyoursaas_blacklistmails',
			'url'=>'/sellyoursaas/blacklistmail_list.php',
			'langs'=>'',
			'position'=>657,
			'enabled'=>'isModEnabled("sellyoursaas")',         // Define condition to show or hide menu entry. Use '$conf->NewsSubmitter->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->hasRight("sellyoursaas", "read")',
			'target'=>'',
			'user'=>0
		);
		$r++;
	}

	/**
	 *	Function called when module is enabled.
	 *	The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *	It also creates data directories
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result=$this->_load_tables('/sellyoursaas/sql/');

		// Create extrafields
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);

		// Product
		$param=array('options'=>array(1=>1));
		$resultx=$extrafields->addExtraField('separatorproduct',          "SELLYOURSAAS_NAME", 'separate',   100,     '',  'product', 0, 1, '',   $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('app'=>'Application','system'=>'System','option'=>'Option'));
		$resultx=$extrafields->addExtraField('app_or_option',                   "AppOrOption",   'select',   110,     '',  'product', 0, 0,   '', $param, 1, '',  1, 'HelpOnAppOrOption',         '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('option_condition',            "OptionCondition",  'varchar',   111,  '200',  'product', 0, 0,   '', $param, 1, '',  1, 'HelpOnOptionCondition',     '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('availabelforresellers', "AvailableForResellers",  'boolean',   111,     '',  'product', 0, 0,   '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('Packages:sellyoursaas/class/packages.class.php'=>null));
		$resultx=$extrafields->addExtraField('package', 	        	            "Package",     'link',   111,     '',  'product', 0, 0,   '', $param, 1, '',  1, 'IfSomethingMustBeDeployed', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('resource_formula', "QuantityCalculationFormula",     'text',   112, '8192',  'product', 0, 0,   '',     '', 1, '', -1, 'QtyFormulaExamples',        '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('resource_label',            "ResourceUnitLabel",  'varchar',   112,   '32',  'product', 0, 0,   '',     '', 1, '', -1, 'ResourceUnitLabelDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		$resultx=$extrafields->addExtraField('freeperioddays', 	          "DaysForFreePeriod",      'int',   113,    '6',  'product', 0, 0,   '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('0'=>'No','1'=>'Yes','2'=>'DuringTestPeriodOnly','3'=>'AfterTestPeriodOnly','4'=>'OnDemand'));
		$resultx=$extrafields->addExtraField('directaccess', 	          "AccessToResources",   'select',   114,     '',  'product', 0, 0,   '', $param, 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('0'=>'SystemDefault','1'=>'CommonUserJail','2'=>'PrivateUserJail'));
		$resultx=$extrafields->addExtraField('sshaccesstype', 	              "SshAccessType",   'select',   114,     '',  'product', 0, 0,   '', $param, 1, '',  '($conf->global->SELLYOURSAAS_SSH_JAILKIT_ENABLED?1:0)', 'HelpOnSshAccessType', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('basic'=>'Basic','premium'=>'Premium','none'=>'None'));
		$resultx=$extrafields->addExtraField('typesupport', 	              "TypeOfSupport",   'select',   115,     '',  'product', 0, 0,   '', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('register_text', 	               "RegisterText",   'varchar',  120,  '255',  'product', 0, 0,   '',     '', 1, '', -1, 'EnterHereTranslationKeyToUseOnRegisterPage', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('register_discountcode', 	      "DiscountCodes",   'varchar',  121,  '255',  'product', 0, 0,   '',     '', 1, '', -1, 'EnterHereListOfDiscountCodes', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas") && !empty($conf->global->SELLYOURSAAS_ACCEPT_DISCOUNTCODE)');
		$resultx=$extrafields->addExtraField('email_template_trialreminder', "EmailTemplateTrialExpiringReminder",     'int',  123,     '',  'product', 0, 0,   '',     '', 1, '', -1, 'EmailTemplateTrialExpiringReminderHelp', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		//$resultx=$extrafields->addExtraField('email_template_trialend',               "EmailTemplateEndOfTrial",     'int',  124,     '',  'product', 0, 0,   '',     '', 1, '', -1, 'EmailTemplateEndOfTrialHelp', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('email_template_trialsuspended',       "EmailTemplateSuspendedTrial",     'int',  125,     '',  'product', 0, 0,   '',     '', 1, '', -1, 'EmailTemplateSuspendedTrialHelp', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('position', 	                       "Position",       'int',  150,    '5',  'product', 0, 0,   '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		//$resultx=$extrafields->addExtraField('separatorproductend',                   "Other", 'separate',   199,     '',  'product', 0, 1,   '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		// Thirdparty
		$param=array('options'=>array(1=>1));
		$resultx=$extrafields->addExtraField('separatorthirdparty',       "SELLYOURSAAS_NAME", 'separate',  100,    '', 'thirdparty', 0, 0,   '', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$arrayoptions=array(
			'no'=>'No',
			'yesv2'=>'Yes'
		);
		$param=array('options'=>$arrayoptions);
		$resultx=$extrafields->addExtraField('dolicloud',                            "SaasCustomer",   'select', 102,   '3', 'thirdparty', 0, 1, 'no', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('date_registration',                "RegistrationDate", 'datetime', 103,    '', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('domain_registration_page', "DomainOfRegistrationPage",  'varchar', 103, '128', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('ip_confirm_email',                   "IPConfirmEmail",       'ip', 180,    '', 'thirdparty', 0, 0, '',     '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('source',                                     "Source",  'varchar', 104,  '64', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")', 0, 0, array('csslist'=>'tdoverflowmax200'));
		$resultx=$extrafields->addExtraField('source_utm',                              "SourceUtm",  'varchar', 104,  '64', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")', 0, 0, array('csslist'=>'tdoverflowmax150'));
		$resultx=$extrafields->addExtraField('firstname',                               "FirstName",  'varchar', 105,  '64', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('lastname',                                 "LastName",  'varchar', 106,  '64', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('auto'=>null));
		$resultx=$extrafields->addExtraField('password',                        "DashboardPassword", 'password', 150, '128', 'thirdparty', 0, 0, '', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('oldpassword',                  "OldDashboardPassword",  'varchar', 151, '128', 'thirdparty', 0, 0, '',     '', 0, '', -2, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('pass_temp',                    "HashForPasswordReset",  'varchar', 152, '128', 'thirdparty', 0, 0, '',     '', 1, '',  0, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('email_temp',                      "NewEmailToConfirm",  'varchar', 153, '128', 'thirdparty', 0, 0, '',     '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('flagdelsessionsbefore',      "LastPasswordChangeDate", 'datetimegmt', 155, '', 'thirdparty', 0, 0, '',     '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('optinmessages',          "OptinForCommercialMessages",  'boolean', 160,    '', 'thirdparty', 0, 0, '',     '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('emailccinvoice',                    "EmailCCInvoices",  'varchar', 180, '255', 'thirdparty', 0, 0, '',     '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('manualcollection',                 "ManualCollection",  'boolean', 194,    '', 'thirdparty', 0, 0, '',     '', 1, '',  1, 'If checked, the batch SellYourSaasValidateDraftInvoices will never validate invoices of this customer', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('commission',                      "PartnerCommission",      'int', 195,   '3', 'thirdparty', 0, 0, '', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('stripeaccount',                       "StripeAccount",  'varchar', 197, '255', 'thirdparty', 0, 0, '',     '', 1, '', -1, 'StripeAccountForCustomerHelp', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('maxnbofinstances',                 "MaxNbOfInstances",      'int', 198,   '3', 'thirdparty', 0, 0, '4',    '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$arrayoptions=array(
			'profit'=>'ProfitOrganization',
			'nonprofit'=>'NonProfitOrganization'
		);
		$param=array('options'=>$arrayoptions);
		$resultx=$extrafields->addExtraField('checkboxnonprofitorga',       "NonProfitOrganization",   'select', 199,   '10', 'thirdparty', 0, 0, '', $param, 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		//$resultx=$extrafields->addExtraField('separatorthirdpartyend',                      "Other", 'separate',199,    '', 'thirdparty', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		// Contract
		$param=array('options'=>array(1=>1));
		$resultx=$extrafields->addExtraField('separatorcontract',                "SELLYOURSAAS_NAME", 'separate', 100,     '',    'contrat', 0, 0,    '',  $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('plan',                                          "Plan",  'varchar', 102,   '64',    'contrat', 0, 0,    '',      '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")', 0, 0, array('csslist'=>'tdoverflowmax150'));
		$param=array('options'=>array('processing'=>'Processing','done'=>'Done','undeployed'=>'Undeployed'));
		$resultx=$extrafields->addExtraField('deployment_init_adminpass',   "DeploymentInitPassword",  'varchar', 103,   '64',    'contrat', 0, 0,    '',      '', 1, '', -2, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_status',                 "DeploymentStatus",   'select', 104,     '',    'contrat', 0, 0,    '',  $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		//$param=array('options'=>array('Deploymentserver:sellyoursaas/class/deploymentserver.class.php:status=1'=>null));
		//$resultx=$extrafields->addExtraField('deployment_server',                  "DeploymentServer",	  'link', 105,     '',    'contrat', 0, 0,    '',  $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_host',                     "DeploymentHost",  'varchar', 105,  '128',    'contrat', 0, 0,    '',      '', 1, '', -1, 'DeploymentHostDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_date_start',          "DeploymentDateStart", 'datetime', 106,     '',    'contrat', 0, 0,    '',      '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_date_end',              "DeploymentDateEnd", 'datetime', 107,     '',    'contrat', 0, 0,    '',      '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_ip',                         "DeploymentIP",       'ip', 108,     '',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_vpn_proba',          "DeploymentIPVPNProba",   'double', 109,  '8,4',    'contrat', 0, 0,    '',      '', 1, '', -1, 'DeploymentIPVPNProbaDesc:ipvpnprob', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_ipquality',          "DeploymentIPQuality",   'varchar', 110,  '255',    'contrat', 0, 0,    '',      '', 1, '', -1,    'DeploymentIPQualityDesc:ipquality', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_emailquality',    "DeploymentEmailQuality",   'varchar', 110,  '255',    'contrat', 0, 0,    '',      '', 1, '', -1, 'DeploymentEmailQualityDesc:ipquality', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('deployment_ua',                  "DeploymentUserAgent",  'varchar', 111,  '255',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('date_softalert_endfreeperiod', "DateSoftAlertEndTrial", 'datetime', 112,     '',    'contrat', 0, 0,    '',      '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('date_endfreeperiod',                    "DateEndTrial", 'datetime', 113,     '',    'contrat', 0, 0,    '',      '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('undeployment_date',                 "UndeploymentDate", 'datetime', 114,     '',    'contrat', 0, 0,    '',      '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('undeployment_ip',                     "UndeploymentIP",  'varchar', 115,  '128',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('custom_url',                               "CustomURL",  'varchar', 117,  '128',    'contrat', 0, 0,    '',      '', 1, '', -1, 'CustomUrlDesc:tooltipcustomurl', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('custom_virtualhostline',       "CustomVirtualHostLine",  'varchar', 118,  '255',    'contrat', 0, 0,    '',      '', 1, '', -1, 'EnterAVirtualHostLine:virthostline', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('custom_virtualhostdir',         "CustomVirtualHostDir",  'varchar', 119,  '255',    'contrat', 0, 0,    '',      '', 1, '', -1, 'EnterAVirtualHostDirLine:virthostdirline', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('hostname_os',                            "Hostname OS",  'varchar', 120,  '128',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('username_os',                            "Username OS",  'varchar', 121,   '32',    'contrat', 1, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('password_os',                            "Password OS",  'varchar', 122,   '32',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('0'=>'SystemDefault','1'=>'CommonUserJail','2'=>'PrivateUserJail'));
		$resultx=$extrafields->addExtraField('sshaccesstype', 	                     "SshAccessType",   'select', 123,     '',    'contrat', 0, 0,    '',  $param, 1, '', '($conf->global->SELLYOURSAAS_SSH_JAILKIT_ENABLED?1:0)', 'HelpOnSshAccessType', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('hostname_db',                            "Hostname DB",  'varchar', 124,  '128',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('database_db',                            "Database DB",  'varchar', 125,   '32',    'contrat', 1, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('port_db',                                    "Port DB",  'varchar', 126,    '8',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('username_db',                            "Username DB",  'varchar', 127,   '32',    'contrat', 1, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('password_db',                            "Password DB",  'varchar', 128,   '64',    'contrat', 0, 0,    '',      '', 1, '', -1, 'ToUpdateDBPassword', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('username_ro_db',               "Read-only Username DB",  'varchar', 129,   '32',    'contrat', 1, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('password_ro_db',               "Read-only Password DB",  'varchar', 130,   '64',    'contrat', 0, 0,    '',      '', 1, '', -1, 'ToCreateDBUserManualy', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('prefix_db',                  "Special table prefix DB",  'varchar', 140,   '64',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('timezone',                                  "TimeZone",  'varchar', 141,   '64',    'contrat', 0, 0,    '',      '', 1, '', -1, 'SellYourSaasTimeZoneDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('fileauthorizekey',              "DateFileauthorizekey", 'datetime', 150,     '',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('filelock',                              "DateFilelock", 'datetime', 151,     '',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('fileinstallmoduleslock',  "DateFileInstallmoduleslock", 'datetime', 152,     '',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('latestresupdate_date',           "LatestResUpdateDate", 'datetime', 155,     '',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('latestbackup_date',                 "LatestBackupDate", 'datetime', 159,     '',    'contrat', 0, 0,    '',      '', 1, '', -2, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('latestbackup_date_ok',            "LatestBackupDateOK", 'datetime', 160,     '',    'contrat', 0, 0,    '',      '', 1, '', -2, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('latestbackup_status',             "LatestBackupStatus",  'varchar', 161,    '2',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('latestbackup_message',           "LatestBackupMessage",     'text', 162, '8192',    'contrat', 0, 0,    '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('backup_frequency',                   "BackupFrequency",     'int',  165,    '3',    'contrat', 0, 0,   '1',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('maxperday',                           "MaxEmailPerDay",     'int',  166,    '6',    'contrat', 0, 0,    '',      '', 1, '', -1, 'MaxEmailPerDayDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('cookieregister_counter',                    "RegistrationCounter",     'int', 170,  '10',    'contrat', 0, 0,  '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")', 1);
		$resultx=$extrafields->addExtraField('cookieregister_previous_instance', "RegistrationPreviousInstance", 'varchar', 171, '128',    'contrat', 0, 0,  '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('discountcode', 	    	 			             "DiscountCode", 'varchar', 200, '255',    'contrat', 0, 0,  '',      '', 1, '',  1, 'DiscountCodeDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('suspendmaintenance_message', 		          "Maintenance message", 'varchar', 210, '255',    'contrat', 0, 0,  '',      '', 1, '', -2, '', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('commentonqty', 	    	                         "CommentOnQty",  'text',   220, '8192',   'contrat', 0, 0,  '',      '', 1, '', -1, 'CommentOnQtyDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")', 0, 0, array('csslist'=>'tdoverflowmax150'));
		$resultx=$extrafields->addExtraField('spammer',                                              "EvilUser", 'varchar', 300,   '8',    'contrat', 0, 0,  '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('reasonundeploy',                                "ReasonUninstall", 'varchar', 300,  '255',   'contrat', 0, 0,  '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('commentundeploy',                            "CommentOfUninstall", 'text',    300, '8192',   'contrat', 0, 0,  '',      '', 1, '', -1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		// Invoice
		$param=array('options'=>array(1=>1));
		$resultx=$extrafields->addExtraField('separatorinvoice',        "SELLYOURSAAS_NAME", 'separate', 1000,    '', 'facture', 0, 0, '', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('commission', "PartnerCommissionForThisInvoice",     'int', 1020,   '3', 'facture', 0, 0, '',     '', 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$param=array('options'=>array('Societe:societe/class/societe.class.php'=>null));
		$resultx=$extrafields->addExtraField('reseller',                         "Reseller",     'link', 1030,    '', 'facture', 0, 0, '', $param, 1, '',  1, 0, '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('delayautopayment',    "DelayAutomaticPayment",     'date', 1035,    '', 'facture', 0, 0, '',     '', 1, '', -1, 'DelayAutomaticPaymentDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('invoicepaymentdisputed', "InvoicePaymentDisputed", 'boolean', 1040,   '', 'facture', 0, 0, '',    '', 1, '', -1, 'InvoicePaymentDisputedDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		// Invoice rec
		$resultx=$extrafields->addExtraField('discountcode', 	    	     "DiscountCode",  'varchar',  200,  '255', 'facture_rec', 0, 0,  '',      '', 1, '',  1, 'DiscountCodeDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('commentonqty', 	    	     "CommentOnQty",  'text',     220, '8192', 'facture_rec', 0, 0,  '',      '', 1, '', -1, 'CommentOnQtyDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		// Users
		$resultx=$extrafields->addExtraField('rsapublicmain', 	    	     "PublicRSAKey",  'text',     100,    '2000', 'user', 0, 0,  '',      '', 1, '',  1, 'PublicRSAKeyDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');
		$resultx=$extrafields->addExtraField('ippublicmain', 	    	     "IPPublicMain",  'varchar',  105,     '255', 'user', 0, 0,  '',      '', 1, '',  1, 'IPPublicMainDesc', '', '', 'sellyoursaas@sellyoursaas', 'isModEnabled("sellyoursaas")');

		// Routine to transform SUB_DOMAIN_NAMES and SUB_DOMAIN_IP constants into object
		if ($result > 0 && (empty(getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION')))) {
			$errors = 0;
			$now = dol_now();
			$listofdomains = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
			$listofips = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_IP'));
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."sellyoursaas_deploymentserver ( ref, entity, ipaddress, status, fromdomainname, date_creation)";
			$sql .= " VALUES ";
			$nbrecords = 0;
			$entity = $_SESSION["dol_entity"];
			foreach ($listofips as $key => $value) {
				$valuesql = "(";
				$tmparraydomain = explode(':', $listofdomains[$key]);
				$valuesql .= "'".$this->db->escape($tmparraydomain[0])."',";
				$valuesql .= $this->db->escape($entity).",";
				$valuesql .= "'".$this->db->escape($value)."',";

				if (! empty($tmparraydomain[1])) {
					if (in_array($tmparraydomain[1], array('bidon', 'hidden', 'closed'))) {
						$valuesql .= "'".$this->db->escape(0)."',";
						$valuesql .= "NULL,";
					} else {
						if (! empty($tmparraydomain[2])) {
							$valuesql .= "'".$this->db->escape(0)."',";
						} else {
							$valuesql .= "'".$this->db->escape(1)."',";
						}
						$valuesql .= "'".$this->db->escape($tmparraydomain[1])."',";
					}
				} else {
					$valuesql .= "'".$this->db->escape(1)."',";
					$valuesql .= "NULL,";
				}
				$valuesql .= "'".$this->db->idate($now)."'";
				$nbrecords++;
				if ($nbrecords == count($listofips)) {
					$valuesql .= ")";
				} else {
					$valuesql .= "),";
				}
				$sql .= $valuesql;
			}
			$resql = $this->db->query($sql);
			$resql ? : $errors++;
			if (!$errors) {
				dolibarr_set_const($this->db, "SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION", 1, 'int', 0, '', $conf->entity);
			}
		}
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
