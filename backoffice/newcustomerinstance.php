<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *       \file       htdocs/sellyoursaas/backoffice/newcustomerinstance.php
 *       \ingroup    sellyoursaas
 *       \brief      Page to create a new SaaS customer or instance
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once(DOL_DOCUMENT_ROOT."/comm/action/class/actioncomm.class.php");
require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");
require_once(DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture-rec.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
dol_include_once("/sellyoursaas/core/lib/dolicloud.lib.php");
dol_include_once("/sellyoursaas/backoffice/lib/refresh.lib.php");		// do not use dol_buildpath to keep global of var into refresh.lib.php working
dol_include_once('/sellyoursaas/class/dolicloud_customers.class.php');

$langs->load("admin");
$langs->load("companies");
$langs->load("users");
$langs->load("other");
$langs->load("commercial");
$langs->load("bills");
$langs->load("sellyoursaas@sellyoursaas");

$mesg=''; $error=0; $errors=array();

$action		= (GETPOST('action','alpha') ? GETPOST('action','alpha') : 'view');
$confirm	= GETPOST('confirm','alpha');
$backtopage = GETPOST('backtopage','alpha');
$id			= GETPOST('id','int');
$instanceoldid = GETPOST('instanceoldid','alpha');
$instance   = GETPOST('instance','alpha');
$ref        = GETPOST('ref','alpha');
$refold     = GETPOST('refold','alpha');
$date_registration  = dol_mktime(0, 0, 0, GETPOST("date_registrationmonth",'int'), GETPOST("date_registrationday",'int'), GETPOST("date_registrationyear",'int'), 1);
$date_endfreeperiod = dol_mktime(0, 0, 0, GETPOST("endfreeperiodmonth",'int'), GETPOST("endfreeperiodday",'int'), GETPOST("endfreeperiodyear",'int'), 1);
if (empty($date_endfreeperiod) && ! empty($date_registration)) $date_endfreeperiod=$date_registration+15*24*3600;

$emailtocreate=GETPOST('emailtocreate')?GETPOST('emailtocreate'):'';
$instancetocreate=GETPOST('instancetocreate','alpha');

$error = 0; $errors = array();


// Security check
$user->rights->sellyoursaas->delete = $user->rights->sellyoursaas->write;
$result = restrictedArea($user, 'sellyoursaas', 0, '','');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array array
include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
$hookmanager=new HookManager($db);

$object=new Societe($db);

if (GETPOST('loadthirdparty')) $action='create2';
if (GETPOST('add')) $action='add';


/*
 *	Actions
 */

$parameters=array('id'=>$id, 'objcanvas'=>$objcanvas);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks

if (empty($reshook))
{
    // Cancel
    if (GETPOST('cancel','alpha') && ! empty($backtopage))
    {
        header("Location: ".$backtopage);
        exit;
    }


    // Search if a thirdparty already exists
    if (GETPOST('loadthirdparty') && (GETPOST('email') || GETPOST('instance') || GETPOST('thirdparty_id') > 0))
    {
        $emailtocreate = '';
        $instancetocreate = '';
        $nametocreate = '';
        $paymentmodetocreate = '';
        $_POST['nametocreate'] = '';
        $_POST['emailtocreate'] = '';
        $_POST['mode_reglement_id'] = '';

        $result = $object->fetch((GETPOST('thirdparty_id') > 0 ? GETPOST('thirdparty_id') : 0), '', '', '','','','','','', '', GETPOST('email'));

        if (GETPOST('instance'))
        {
            $contracttosearch=new Contrat($db);

            $instancetosearch = GETPOST('instance');

            // Add with.dolicloud.com to have a complete instance id
            if (! empty($instancetosearch) && ! preg_match('/\.dolicloud\.com$/',$instancetosearch)) $instancetosearch=$instancetosearch.'.with.dolicloud.com';
            $result = $contracttosearch->fetch(0, '', $instancetosearch);
            if ($result > 0 && $contracttosearch->socid > 0)
            {
                $result = $object->fetch($contracttosearch->socid);
            }

            // If third party not found, we try with v1 name
            if (empty($object->id))
            {
                $instancetosearch = GETPOST('instance');

                // Add on.dolicloud.com to have a complete instance id
                if (! empty($instancetosearch) && ! preg_match('/\.dolicloud\.com$/',$instancetosearch)) $instancetosearch=$instancetosearch.'.on.dolicloud.com';
                $result = $contracttosearch->fetch(0, '', $instancetosearch);
                if ($result > 0 && $contracttosearch->socid > 0)
                {
                    $result = $object->fetch($contracttosearch->socid);
                }
            }
        }
    }


    // Add customer
    if ($action == 'add' && $user->rights->sellyoursaas->write)
    {
        $db->begin();

        $object=new Societe($db);

        if (! empty($canvas)) $object->canvas=$canvas;

        $instancetocreate = GETPOST('instancetocreate','alpha');
        $productidtocreate = GETPOST('producttocreate','alpha');
        $productidtocreateforusers = GETPOST('productforuserstocreate','alpha');
        $thirdpartyidselected = GETPOST('thirdpartyidselected','int');


        // Search info v1 database to find more information
        $result = $dolicloudcustomer->fetch(0, $instancetocreate);

        if ($thirdpartyidselected > 0)
        {
            $object->fetch($thirdpartyidselected);

            // Set flag client if not set
            $object->client |= 1;

            $checkinstance=0;
            if (preg_match('/\.on\./', $instancetocreate))   { $checkinstance=1; $object->array_options['options_dolicloud']='yesv1'; }
            if (preg_match('/\.with\./', $instancetocreate)) { $checkinstance=1; $object->array_options['options_dolicloud']='yesv1'; }

            if (! $checkinstance)
            {
                $error++;
                setEventMEssages($langs->trans("ErrorBadValueForInstance", $instancetocreate), null, 'errors');
                $action = 'create2';
                $_POST['loadthirdparty']='load';
            }
            else
            {
                $object->update($object->id, $user);

                if (! $error && ($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG > 0))
                {
                    $custcats = array($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG);
                    $object->setCategories($custcats, 'customer');
                }
            }
        }
        else
        {
            // Create customer

            $object->name	= GETPOST('nametocreate');
            $object->email	= GETPOST('emailtocreate');
            $object->mode_reglement_id = GETPOST('mode_reglement_id','int');

            $checkinstance=0;
            if (preg_match('/\.on\./', $instancetocreate))   { $checkinstance=1; $object->array_options['options_dolicloud']='yesv1'; }
            if (preg_match('/\.with\./', $instancetocreate)) { $checkinstance=1; $object->array_options['options_dolicloud']='yesv2'; }

            if (! $checkinstance)
            {
                $error++;
                setEventMEssages($langs->trans("ErrorBadValueForInstance", $instancetocreate), null, 'errors');
                $action = 'create2';
                $_POST['loadthirdparty']='load';
            }

            if ($dolicloudcustomer->id > 0)
            {
                if (empty($object->name)) $object->name = $dolicloudcustomer->organization;
                if (empty($object->mode_reglement_id)) $object->mode_reglement_id = $dolicloudcustomer->mode_reglement_id;
                $object->client=1;
                $object->code_client=-1;
                $object->name_alias = $dolicloudcustomer->getFullName($langs);
                $object->address = $dolicloudcustomer->address;
                $object->zip = $dolicloudcustomer->zip;
                $object->town = $dolicloudcustomer->town;

                $country_id = dol_getIdFromCode($db, $dolicloudcustomer->country_code, 'c_country', 'code', 'rowid');
                if ($country_id > 0)
                {
                    $object->country_id = $country_id;
                    $object->country_code = $dolicloudcustomer->country_code;
                }
                else
                {
                    $object->country_id = 11;		// USA
                    $object->country_code = 'US';
                }

                $object->phone = $dolicloudcustomer->phone;
                $object->tva_intra=$dolicloudcustomer->vat_number;
                $locale=$dolicloudcustomer->locale;
                if ($locale)
                {
                    $localearray=explode('_',$locale);
                    $object->default_lang=$localearray[0].'_'.strtoupper($localearray[1]?$localearray[1]:$localearray[0]);
                }
                $object->array_options['options_date_registration']=$dolicloudcustomer->date_registration;
                $object->array_options['options_source']='BACKOFFICE';
                if ($dolicloudcustomer->status == 'ACTIVE') $object->status = 1;
                else $object->status = 0;

                $object->ref_ext = $dolicloudcustomer->customer_id;
                $object->import_id = 'doliv1_'.$dolicloudcustomer->customer_id;
            }

            // If name not defined, we choosse email
            if (empty($object->name)) $object->name = $object->email;

            /*
             if (empty($_POST["instance"]) || empty($_POST["organization"]) || empty($_POST["plan"]) || empty($_POST["email"]))
             {
             $error++; $errors[]=$langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Instance").",".$langs->transnoentitiesnoconv("Organization").",".$langs->transnoentitiesnoconv("Plan").",".$langs->transnoentitiesnoconv("EMail"));
             $action = 'create';
             }*/

            if (! $error)
            {
                $id =  $object->create($user);
                if ($id <= 0)
                {
                    $error++;
                    setEventMessages('', array_merge($errors,($object->error?array($object->error):$object->errors)), 'errors');
                    $action = 'create2';
                    $_POST['loadthirdparty']='load';
                }

                if (! $error && ($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG > 0))
                {
                    $custcats = array($conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG);
                    $object->setCategories($custcats, 'customer');
                }

                $thirdpartyidselected = $id;
            }
        }

        // Now we create new contract/instance
        if (! $error && $thirdpartyidselected > 0)
        {
            $contract = new Contrat($db);

            $contract->ref_customer = $instancetocreate;
            $contract->date_contrat = dol_now();
            $contract->socid=$thirdpartyidselected;
            $contract->commercial_suivi_id = $user->id;
            $contract->commercial_signature_id = $user->id;
            /*$sql = "SELECT rowid, statut, ref, fk_soc, mise_en_service as datemise,";
             $sql.= " ref_supplier, ref_customer,";
             $sql.= " ref_ext,";
             $sql.= " date_contrat as datecontrat,";
             $sql.= " fk_user_author, fin_validite, date_cloture,";
             $sql.= " fk_projet,";
             $sql.= " fk_commercial_signature, fk_commercial_suivi,";
             $sql.= " note_private, note_public, model_pdf, extraparams";
             $sql.= " FROM ".MAIN_DB_PREFIX."contrat";
             $sql.= " WHERE ref_ext='".$db->escape($ref)."'";
             $sql.= " AND entity IN (".getEntity('contract', 0).")";
             $sql.= " AND statut = 1";*/

            $nbusers = 0;
            $nbgb = 0;

            /*var_dump($contract->array_options);
             var_dump($instancetocreate);
             var_dump($productidtocreate);
             var_dump($thirdpartyidselected);
             exit;*/
            $idcontract = $contract->create($user);

            if ($idcontract <= 0)
            {
                $error++;
                setEventMessages('', array_merge($errors,($contract->error?array($contract->error):$contract->errors)), 'errors');
                $action = 'create2';
                $_POST['loadthirdparty']='load';
            }

            if (! $error)
            {
                // Contract for instance

                $date_start=dol_now();
                $date_end=null;
                if ($contract->array_options['options_date_endfreeperiod'] && $contract->array_options['options_date_endfreeperiod'] > $date_start)
                {
                    $date_start = dol_time_plus_duree($contract->array_options['options_date_endfreeperiod'], 1, 'd');
                }
                // If we have an ending period, we use it for start of next invoice
                if ($dolicloudcustomer->id > 0 && $dolicloudcustomer->date_current_period_end)
                {
                    $date_start = dol_time_plus_duree($dolicloudcustomer->date_current_period_end, 1, 'd');
                }
                /*
                 var_dump(dol_print_date($dolicloudcustomer->date_current_period_end,'dayhour'));
                 var_dump(dol_print_date($dolicloudcustomer->date_endfreeperiod,'dayhour'));
                 var_dump($date_start);exit;
                 */

                $product=new Product($db);
                $product->fetch($productidtocreate);
                if (empty($product->id))
                {
                    $error++;
                    setEventMessages($product->error, $product->errors, 'errors');
                }
                else
                {
                    if (empty($product->duration_value) || empty($product->duration_unit))
                    {
                        $error++;
                        setEventMessages('The product '.$product->ref.' has no default duration');
                    }
                    else
                    {
                        $frequeny_multiple = GETPOST('frequency_multiple','int');
                        $i = 1;
                        $now = dol_now();
                        while (dol_time_plus_duree($date_start, $product->duration_value * $i * $frequeny_multiple, $product->duration_unit) < $now)
                        {
                            $i++;
                        }
                        $date_end=dol_time_plus_duree($date_start, $product->duration_value * $i * $frequeny_multiple, $product->duration_unit);
                    }
                }
                $save_date_end = $date_end;
                //var_dump("$nb_user, $product->tva_tx, $product->localtax1_tx, $product->localtax2_tx, $productidtocreate, 0, ".dol_print_date($date_start, 'dayhourlog')." - ".dol_print_date($date_end, 'dayhourlog'));exit;

                $productforusers=new Product($db);
                $productforusers->fetch($productidtocreateforusers);
                if (empty($productforusers->id))
                {
                    $error++;
                    setEventMessages($productforusers->error, $productforusers->errors, 'errors');
                }


                //$discount = GETPOST('discount');
                $discount = 0;	// Discount for contracts is zero.

                // Create contract line for INSTANCE
                if (! $error)
                {
                    if (empty($object->country_code))
                    {
                        $object->country_code = dol_getIdFromCode($db, $object->country_id, 'c_country', 'rowid', 'code');
                    }
                    $qty = 1;
                    //if (! empty($contract->array_options['options_nb_users'])) $qty = $contract->array_options['options_nb_users'];
                    $vat = get_default_tva($mysoc, $object, $product->id);
                    $localtax1_tx = get_default_localtax($mysoc, $object, 1, $product->id);
                    $localtax2_tx = get_default_localtax($mysoc, $object, 2, $product->id);
                    //var_dump($mysoc->country_code);
                    //var_dump($object->country_code);
                    //var_dump($product->tva_tx);
                    //var_dump($vat);exit;

                    $price = $product->price;
                    if ($dolicloudcustomer->id > 0)
                    {
                        $price = $dolicloudcustomer->price_instance;
                        if (! preg_match('/yearly/', $dolicloudcustomer->plan)) $price = $price * 12;
                    }

                    if ($price == 0) $discount = 0;

                    $contactlineid = $contract->addline('', $price, $qty, $vat, $localtax1_tx, $localtax2_tx, $productidtocreate, $discount, $date_start, $date_end, 'HT', 0);
                    if ($contactlineid < 0)
                    {
                        $error++;
                        setEventMessages($contract->error, $contract->errors, 'errors');
                    }
                }

                //var_dump('user:'.$dolicloudcustomer->price_user);
                //var_dump('instance:'.$dolicloudcustomer->price_instance);
                //exit;

                // Create contract line for USERS
                if (! $error)
                {
                    $qty = 0;
                    //if (! empty($contract->array_options['options_nb_users'])) $qty = $contract->array_options['options_nb_users'];
                    if ($nbusers > 0) $qty = $nbusers;
                    $vat = get_default_tva($mysoc, $object, 0);
                    $localtax1_tx = get_default_localtax($mysoc, $object, 1, $productforusers->id);
                    $localtax2_tx = get_default_localtax($mysoc, $object, 2, $productforusers->id);

                    $price = $productforusers->price;
                    if ($dolicloudcustomer->id > 0)
                    {
                        $price = $dolicloudcustomer->price_user;
                        if (! preg_match('/yearly/', $dolicloudcustomer->plan)) $price = $price * 12;
                    }

                    if ($price > 0 && $qty > 0)
                    {
                        $contactlineid = $contract->addline('Additional users', $price, $qty, $vat, $localtax1_tx, $localtax2_tx, $productidtocreateforusers, $discount, $date_start, $date_end, 'HT', 0);
                        if ($contactlineid < 0)
                        {
                            $error++;
                            setEventMessages($contract->error, $contract->errors, 'errors');
                        }
                    }
                }

                // Activate all lines
                if (! $error)
                {
                    $result = $contract->activateAll($user);
                    if ($result <= 0)
                    {
                        $error++;
                        setEventMessages($contract->error, $contract->errors, 'errors');
                    }
                }
            }
            /*var_dump($dolicloudcustomer->price_instance);
             var_dump($dolicloudcustomer->price_user);
             exit;*/

            // Now create invoice draft
            $dateinvoice = $contract->array_options['options_date_endfreeperiod'];
            if (empty($dateinvoice) && $dolicloudcustomer->id > 0) $dateinvoice = $dolicloudcustomer->date_current_period_end;

            $invoice_draft = new Facture($db);

            // Create empty invoice
            if (! $error)
            {
                $invoice_draft->socid				= $thirdpartyidselected;
                $invoice_draft->type				= Facture::TYPE_STANDARD;
                $invoice_draft->number				= '';
                $invoice_draft->date				= $dateinvoice;

                $invoice_draft->note_private		= 'Created by the new instance page';

                $invoice_draft->mode_reglement_id	= (GETPOST('mode_reglement_id','int') > 0 ? GETPOST('mode_reglement_id','int') : $thirdparty->mode_reglement_id);
                $invoice_draft->cond_reglement_id	= dol_getIdFromCode($db, 'RECEP', 'c_payment_term', 'code', 'rowid');

                $invoice_draft->fk_account          = 5;												// fiducial
                if ($invoice_draft->mode_reglement_id == 100) $invoice_draft->fk_account          = $conf->global->STRIPE_BANK_ACCOUNT_FOR_PAYMENTS;	// stripe
                if ($invoice_draft->mode_reglement_id == 101) $invoice_draft->fk_account          = $conf->global->PAYPAL_BANK_ACCOUNT_FOR_PAYMENTS;	// paypal

                $invoice_draft->fetch_thirdparty();

                $origin='contrat';
                $originid=$idcontract;

                $invoice_draft->origin = $origin;
                $invoice_draft->origin_id = $originid;

                // Possibility to add external linked objects with hooks
                $invoice_draft->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;

                $idinvoice = $invoice_draft->create($user);      // This include class to add_object_linked() and add add_contact()
                if (! ($idinvoice > 0))
                {
                    setEventMessages($invoice_draft->error, $invoice_draft->errors, 'errors');
                    $error++;
                }
            }
            // Add line on invoice
            if (! $error)
            {
                // Add lines of invoice
                $srcobject = $contract;

                $lines = $srcobject->lines;
                if (empty($lines) && method_exists($srcobject, 'fetch_lines'))
                {
                    $srcobject->fetch_lines();
                    $lines = $srcobject->lines;
                }

                $date_start = false;
                $fk_parent_line=0;
                $num=count($lines);
                for ($i=0;$i<$num;$i++)
                {
                    $label=(! empty($lines[$i]->label)?$lines[$i]->label:'');
                    $desc=(! empty($lines[$i]->desc)?$lines[$i]->desc:$lines[$i]->libelle);
                    if ($invoice_draft->situation_counter == 1) $lines[$i]->situation_percent =  0;

                    // Positive line
                    $product_type = ($lines[$i]->product_type ? $lines[$i]->product_type : 0);

                    // Date start
                    $date_start = false;
                    if ($lines[$i]->date_debut_prevue)
                        $date_start = $lines[$i]->date_debut_prevue;
                        if ($lines[$i]->date_debut_reel)
                            $date_start = $lines[$i]->date_debut_reel;
                            if ($lines[$i]->date_start)
                                $date_start = $lines[$i]->date_start;

                                // Date end
                                $date_end = false;
                                if ($lines[$i]->date_fin_prevue)
                                    $date_end = $lines[$i]->date_fin_prevue;
                                    if ($lines[$i]->date_fin_reel)
                                        $date_end = $lines[$i]->date_fin_reel;
                                        if ($lines[$i]->date_end)
                                            $date_end = $lines[$i]->date_end;

                                            // Reset fk_parent_line for no child products and special product
                                            if (($lines[$i]->product_type != 9 && empty($lines[$i]->fk_parent_line)) || $lines[$i]->product_type == 9) {
                                                $fk_parent_line = 0;
                                            }

                                            // Discount
                                            $discount = $lines[$i]->remise_percent;
                                            if (empty($discount) && GETPOST('discount'))
                                            {
                                                $discount = GETPOST('discount');
                                            }

                                            // Extrafields
                                            if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED) && method_exists($lines[$i], 'fetch_optionals')) {
                                                $lines[$i]->fetch_optionals($lines[$i]->rowid);
                                                $array_options = $lines[$i]->array_options;
                                            }

                                            $tva_tx = $lines[$i]->tva_tx;
                                            if (! empty($lines[$i]->vat_src_code) && ! preg_match('/\(/', $tva_tx)) $tva_tx .= ' ('.$lines[$i]->vat_src_code.')';

                                            // View third's localtaxes for NOW and do not use value from origin.
                                            $localtax1_tx = get_localtax($tva_tx, 1, $invoice_draft->thirdparty);
                                            $localtax2_tx = get_localtax($tva_tx, 2, $invoice_draft->thirdparty);

                                            //$price_invoice_template_line = $lines[$i]->subprice * GETPOST('frequency_multiple','int');
                                            $price_invoice_template_line = $lines[$i]->subprice;

                                            $result = $invoice_draft->addline($desc, $price_invoice_template_line, $lines[$i]->qty, $tva_tx, $localtax1_tx, $localtax2_tx, $lines[$i]->fk_product, $discount, $date_start, $date_end, 0, $lines[$i]->info_bits, $lines[$i]->fk_remise_except, 'HT', 0, $product_type, $lines[$i]->rang, $lines[$i]->special_code, $invoice_draft->origin, $lines[$i]->rowid, $fk_parent_line, $lines[$i]->fk_fournprice, $lines[$i]->pa_ht, $label, $array_options, $lines[$i]->situation_percent, $lines[$i]->fk_prev_id, $lines[$i]->fk_unit);

                                            if ($result > 0) {
                                                $lineid = $result;
                                            } else {
                                                $lineid = 0;
                                                $error ++;
                                                break;
                                            }

                                            // Defined the new fk_parent_line
                                            if ($result > 0 && $lines[$i]->product_type == 9) {
                                                $fk_parent_line = $result;
                                            }
                }
            }

            // Now we convert invoice into a template
            if (! $error)
            {
                //var_dump($invoice_draft->lines);
                //var_dump(dol_print_date($date_start,'dayhour'));
                //exit;

                $frequency=1;
                $tmp=dol_getdate($date_start?$date_start:$now);
                $reyear=$tmp['year'];
                $remonth=$tmp['mon'];
                $reday=$tmp['mday'];
                $rehour=$tmp['hours'];
                $remin=$tmp['minutes'];
                $nb_gen_max=0;
                //print dol_print_date($date_start,'dayhour');
                //var_dump($remonth);

                $invoice_rec = new FactureRec($db);

                $invoice_rec->titre = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
                $invoice_rec->title = 'Template invoice for '.$contract->ref.' '.$contract->ref_customer;
                $invoice_rec->note_private = $contract->note_private;
                //$invoice_rec->note_public  = dol_concatdesc($contract->note_public, '__(Period)__ : __INVOICE_DATE_NEXT_INVOICE_BEFORE_GEN__ - __INVOICE_DATE_NEXT_INVOICE_AFTER_GEN__');
                $invoice_rec->note_public  = $contract->note_public;
                $invoice_rec->mode_reglement_id = $invoice_draft->mode_reglement_id;

                $invoice_rec->usenewprice = 0;

                $invoice_rec->frequency = GETPOST('frequency_multiple','int');
                $invoice_rec->unit_frequency = 'm';
                $invoice_rec->nb_gen_max = $nb_gen_max;
                $invoice_rec->auto_validate = 0;

                $invoice_rec->fk_project = 0;

                $date_next_execution = dol_mktime($rehour, $remin, 0, $remonth, $reday, $reyear);
                $invoice_rec->date_when = $date_next_execution;

                // Get first contract linked to invoice used to generate template
                if ($invoice_draft->id > 0)
                {
                    $srcObject = $invoice_draft;

                    $srcObject->fetchObjectLinked();

                    if (! empty($srcObject->linkedObjectsIds['contrat']))
                    {
                        $contractidid = reset($srcObject->linkedObjectsIds['contrat']);

                        $invoice_rec->origin = 'contrat';
                        $invoice_rec->origin_id = $contractidid;
                        $invoice_rec->linked_objects[$invoice_draft->origin] = $invoice_draft->origin_id;
                    }
                }

                $oldinvoice = new Facture($db);
                $oldinvoice->fetch($invoice_draft->id);

                $result = $invoice_rec->create($user, $oldinvoice->id);
                if ($result > 0)
                {
                    $sql = 'UPDATE '.MAIN_DB_PREFIX.'facturedet_rec SET date_start_fill = 1, date_end_fill = 1 WHERE fk_facture = '.$invoice_rec->id;
                    $result = $db->query($sql);
                    if (! $error && $result < 0)
                    {
                        $error++;
                        setEventMessages($db->lasterror(), null, 'errors');
                    }

                    $result=$oldinvoice->delete($user, 1);
                    if (! $error && $result < 0)
                    {
                        $error++;
                        setEventMessages($oldinvoice->error, $oldinvoice->errors, 'errors');
                    }
                }
                else
                {
                    $error++;
                    setEventMessages($invoice_rec->error, $invoice_rec->errors, 'errors');
                }
            }
        }

        if (! $error && $thirdpartyidselected > 0 && $idcontract > 0)
        {
            $db->commit();
            if (! empty($backtopage)) $url=$backtopage;
            else $url=DOL_URL_ROOT.'/contrat/card.php?id='.$idcontract;
            Header("Location: ".$url);
            exit;
        }
        else
        {
            $db->rollback();
            unset($object);
            $object=new Societe($db);
            $action='create2';
            $_POST['loadthirdparty']='load';
        }
    }


    // Add action to create file, etc...
    include 'refresh_action.inc.php';
}


/*
 *	View
 */

$help_url='';
llxHeader('',$langs->trans("SellYourSaasInstance"),$help_url);

$form = new Form($db);
$form2 = new Form($db2);
$formother = new FormOther($db);
$formcompany = new FormCompany($db);

$countrynotdefined=$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')';

print '<form mode="POST" action="'.$_SERVER["PHP_SELF"].'">';

print_fiche_titre($langs->trans("NewInstance"));

print '<br>';
print $langs->trans("ToCreateNewInstanceUseRegisterPageOrTheCustomerDashboard");
print '<br><br>';
print '<br><br>';
print '<span class="opacitymedium">'.$langs->trans("ToolForMaintenance").'</span><br><br>';

print '<div class="fichecenter">';


print '<div class="underbanner clearboth"></div>';
print '<table class="border" width="100%">';

print '<tr>';
print '<td class="titlefield">'.$langs->trans("Email").'</td><td>';
print '<input type="text" name="email" value="'.GETPOST('email','alpha').'" class="minwidth300">';
print '</td>';
print '</tr>';

print '<tr>';
print '<td class="titlefield">'.$langs->trans("Instance/Ref customer").'</td><td>';
print '<input type="text" name="instance" value="'.GETPOST('instance','alpha').'" class="minwidth300">';
print '</td>';
print '</tr>';

print '<tr>';
print '<td>'.$langs->trans("ThirdParty").'</td><td>';
print $form->select_company($object->id, 'thirdparty_id', 's.client IN (1,3)', 1);
print '</td>';
print '</tr>';

print '<tr><td></td><td>';
print '<input type="submit" name="loadthirdparty" class="button" value="'.$langs->trans("Search").'">';
print '</td></tr>';

$emailtocreate = GETPOST('email');

// If thirdparty found
if ($object->id > 0)
{
	print '<tr><td colspan="2"><hr>';
	print '<div class="titre">'.$langs->trans("ThirdPartyFound").' :</div>';
	print '<input type="hidden" name="thirdpartyidselected" value="'.$object->id.'">';
	print '</td></tr>';

	print '<tr><td class="titlefield tdtop">';
	print $langs->trans('Name').'</td><td>';
	print $object->getNomUrl(1, 'customer');
	print '</td>';
	print '</tr>';

	print '<tr><td class="titlefield tdtop">';
	print $langs->trans('Email').'</td><td>';
	print $object->email;
	print '</td>';
	print '</tr>';

	print '<tr><td class="titlefield tdtop">';
	print $langs->trans('Address').'</td><td>';
	print $object->getFullAddress(1, '<br>');
	print '</td>';
	print '</tr>';

	// Customer code
        if ($object->client)
        {
            print '<tr><td>';
            print $langs->trans('CustomerCode').'</td><td>';
            print $object->code_client;
            if ($object->check_codeclient() <> 0) print ' <font class="error">('.$langs->trans("WrongCustomerCode").')</font>';
            print '</td>';
            print '</tr>';
        }

        // Supplier code
        if (! empty($conf->fournisseur->enabled) && $object->fournisseur && ! empty($user->rights->fournisseur->lire))
        {
            print '<tr><td>';
            print $langs->trans('SupplierCode').'</td><td>';
            print $object->code_fournisseur;
            if ($object->check_codefournisseur() <> 0) print ' <font class="error">('.$langs->trans("WrongSupplierCode").')</font>';
            print '</td>';
            print '</tr>';
        }

        // Prof ids
        $i=1; $j=0;
        while ($i <= 6)
        {
            $idprof=$langs->transcountry('ProfId'.$i,$object->country_code);
            if ($idprof!='-')
            {
                //if (($j % 2) == 0) print '<tr>';
                print '<tr>';
            	print '<td>'.$idprof.'</td><td>';
                $key='idprof'.$i;
                print $object->$key;
                if ($object->$key)
                {
                    if ($object->id_prof_check($i,$object) > 0) print ' &nbsp; '.$object->id_prof_url($i,$object);
                    else print ' <font class="error">('.$langs->trans("ErrorWrongValue").')</font>';
                }
                print '</td>';
                //if (($j % 2) == 1) print '</tr>';
                print '</tr>';
                $j++;
            }
            $i++;
        }

    // Mode de reglement par defaut
    print '<tr><td class="nowrap">';
        print $langs->trans('PaymentMode');
        print '</td><td>';
        print $form->select_types_paiements($object->mode_reglement_id, 'mode_reglement_id');
        print "</td>";
    print '</tr>';

    print '<tr><td colspan="2"><hr>';
    print '</td></tr>';
}

// If criteria to search were provided
if (GETPOST('email') || GETPOST('instance') || GETPOST('thirdparty_id') > 0 || $action == 'create2')
{
	// No thirdparty found in v1
	if (empty($object->id))
	{
		print '<tr><td colspan="2"><hr>';
		print '<div class="titre">'.$langs->trans("NoThirdPartyFoundForThisEmailOrInstance").'.</div>';
		print '<input type="hidden" name="thirdpartyidselected" value="tocreate">';
		print '</td></tr>';

		print '<tr><td class="titlefield">';
		print $langs->trans('Name').'</td><td>';
		print '<input type="text" name="nametocreate" class="minwidth300" value="'.$nametocreate.'">';
		print '</td>';
		print '</tr>';

		print '<tr><td class="fieldrequired">';
		print $langs->trans('Email').'</td><td>';
		print '<input type="text" name="emailtocreate" class="minwidth300" value="'.$emailtocreate.'">';
		print '</td>';
		print '</tr>';

		// Mode de reglement par defaut
		print '<tr><td class="nowrap">';
		print $langs->trans('PaymentMode');
		print '</td><td>';
		print $form->select_types_paiements($paymentmodetocreate,'mode_reglement_id',$filtertype,0,0,0,0,1);
		print "</td>";
		print '</tr>';

		print '<tr><td colspan="2"><hr>';
		print '</td></tr>';
	}

	if ($action == 'create2')
	{
		$contractfound='';
		if ($object->id > 0)
		{
			// Check if a contract exists
			$sql='SELECT rowid, ref FROM '.MAIN_DB_PREFIX."contrat WHERE fk_soc = '".$object->id."'";
			$resql=$db->query($sql);
			if ($resql)
			{
				if ($obj = $db->fetch_object($resql))
				{
					$contractfound=$obj->ref;
				}
			}
			else
			{
				dol_print_error($db);
			}
		}

		print '<tr><td colspan="2">';
		print '<div class="titre">'.$langs->trans("ProductsToIncludeInContract").'</div>';
		print '</td></tr>';

		if (empty($contractfound))
		{
			if (empty($instancetocreate)) $instancetocreate = 'xxx.yyy.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;

			print '<tr><td class="fieldrequired">';
			print $langs->trans('Instance').' (ex: myinstance.on.dolicloud.com)</td><td>';
			print '<input type="text" name="instancetocreate" value="'.$instancetocreate.'" class="minwidth300">';
			print '</td>';
			print '</tr>';

			print '<tr><td class="fieldrequired">';
			print $langs->trans('ProductForInstance').'</td><td>';
			$defaultproductid=$conf->global->SELLYOURSAAS_DEFAULT_PRODUCT;
			print $form->select_produits($defaultproductid, 'producttocreate');
			print '</td>';
			print '</tr>';

			print '<tr><td class="fieldrequired">';
			print $langs->trans('ProductForUsers').'</td><td>';
			$defaultproductforusersid=$conf->global->SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS;
			print $form->select_produits($defaultproductforusersid, 'productforuserstocreate');
			print '</td>';
			print '</tr>';

			$frequency_multiple_default = 1;
			if ($dolicloudcustomer->id > 0)
			{
				if (preg_match('/yearly/', $dolicloudcustomer->plan)) $frequency_multiple_default = 12;
			}

			print '<tr><td class="fieldrequired">';
			print $langs->trans('FrequencyMultiple').'</td><td>';
			print '<input type="text" size="2" name="frequency_multiple" value="'.(GETPOST('frequency_multiple','int')!=''?GETPOST('frequency_multiple','int'):$frequency_multiple_default).'">';
			print '</td>';
			print '</tr>';

			print '<tr><td>';
			print $langs->trans('DiscountOnInvoice').'</td><td>';
			print '<input type="text" size="2" name="discount" value="'.GETPOST('discount','int').'">';
			print '</td>';
			print '</tr>';

			/*
			print '<tr><td class="fieldrequired">';
			print $langs->trans('ProductForUsers').'</td><td>';
			$defaultproductid=$conf->global->SELLYOURSAAS_DEFAULT_PRODUCT_FOR_USERS;
			print $form->select_produits($defaultproductid, 'productforuserstocreate');
			print '</td>';
			print '</tr>';
			*/
		}
		else
		{
			print '<tr><td colspan="2">';
			print 'A contract already exists.'."<br>\n";
			print 'TODO Manage 2 contracts on same customer from this interface...';
			print '</td></tr>';
		}
	}
}

print "</table><br>";

if (GETPOST('email') || GETPOST('thirdparty_id') > 0 || $action == 'create2')
{
	if ($action == 'create2' && empty($contractfound))
	{
		print '<center>';
		print '<input type="submit" name="add" class="button" value="'.$langs->trans("AddContractInstance").'">';
		print '</center>';
	}
}

print "</div>";	//  End fiche=center

print '</form>';

llxFooter();

$db->close();
