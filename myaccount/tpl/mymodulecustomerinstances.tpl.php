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

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mythirdpartyaccount
 * @var Translate $langs
 *
 * @var string $initialaction
 */

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

?>
<!-- BEGIN PHP TEMPLATE mymodulecustomerinstances.tpl.php -->
<?php

/*
$plan = GETPOST('plan', 'alpha');

$planarray = preg_split('/(,|;)/', $plan);
if (!empty($planarray[1])) {
	$productref = 'array';
}
*/

print '
	<div class="page-content-wrapper">
			<div class="page-content">


 	<!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("MyModuleCustomersInstances").'</h1>
	</div>
	<!-- END PAGE TITLE -->
	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->';


//print $langs->trans("Filters").' : ';
print '<div class="row"><div class="col-md-12"><div class="portlet light nominheight">';

print '<form name="refresh" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print $langs->trans("InstanceName").' : <input type="text" name="search_instance_name" value="'.$search_instance_name.'"><br>';
//$savsocid = $user->socid;	// Save socid of user
//$user->socid = 0;
print $langs->trans("SupplierModule").' : <input type="text" name="search_module_name" value="'.$search_module_name.'"><br>';
//.$form->select_company(GETPOST('search_customer_name', 'search_customer_name'), 'search_customer_name', 'parent = '.$mythirdpartyaccount->id, '1', 0, 1, array(), 0, 'inline-block').'</div><br>';
//$user->socid = $savsocid;	// Restore socid of user

print '<input type="hidden" name="mode" value="'.$mode.'">';
print '<div style="padding-top: 10px; padding-bottom: 10px">';
print '<input type="submit" name="submit" value="'.$langs->trans("Refresh").'">';
print ' &nbsp; ';
print '<input type="submit" name="reset" value="'.$langs->trans("Reset").'"><br>';
print '</div>';

if (count($listofcontractidmodulesupplier) > 0) {
	print $langs->trans("FirstRecord").' <input type="text" name="firstrecord" class="width50 right" value="'.$firstrecord.'">';
	print ' - ';
	print $langs->trans("LastRecord").' <input type="text" name="lastrecord" class="width50 right" value="'.$lastrecord.'">';
	print '<span class="opacitymedium"> / ';
	print count($listofcontractidmodulesupplier) .'</span><br>';
}

print '</form>';
print '</div></div></div>';

print '<br>';

if (count($listofcontractidmodulesupplier) == 0) {
	//print '<span class="opacitymedium">'.$langs->trans("NoneF").'</span>';
} else {
	$sellyoursaasutils = new SellYourSaasUtils($db);

	$arrayforsort = array();
	foreach ($listofcontractidmodulesupplier as $id => $contract) {
		$position = 20;
		if ($contract->array_options['options_deployment_status'] == 'processing') {
			$position = 1;
		}
		if ($contract->array_options['options_deployment_status'] == 'suspended') {
			$position = 10;
		}	// This is not a status
		if ($contract->array_options['options_deployment_status'] == 'done') {
			$position = 20;
		}
		if ($contract->array_options['options_deployment_status'] == 'undeployed') {
			$position = 100;
		}

		$arrayforsort[$id] = array('position'=>$position, 'id'=>$id, 'contract'=>$contract);
	}
	$arrayforsort = dol_sort_array($arrayforsort, 'position');

	$i=0;
	foreach ($arrayforsort as $id => $tmparray) {
		$i++;

		if ($i < $firstrecord) {
			continue;
		}
		if ($i > $lastrecord) {
			break;
		}

		$id = $tmparray['id'];
		$contract = $tmparray['contract'];

		$planref = $contract->array_options['options_plan'];
		$statuslabel = $contract->array_options['options_deployment_status'];
		$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

		$dbprefix = $contract->array_options['options_prefix_db'];
		if (empty($dbprefix)) {
			$dbprefix = 'llx_';
		}

		// Get info about PLAN of Contract
		$planlabel = $planref;			// By default, but we will take the name of service of type 'app' just after

		$planid = 0;
		$freeperioddays = 0;
		$directaccess = 0;
		foreach ($contract->lines as $keyline => $line) {
			if ($line->statut == ContratLigne::STATUS_CLOSED && $contract->array_options['options_deployment_status'] != 'undeployed') {
				$statuslabel = 'suspended';
			}

			$tmpproduct = new Product($db);
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
		$color = "#4DB3A2";
		$displayforinstance = "";
		if ($statuslabel == 'processing') {
			$color = 'orange';
		}
		if ($statuslabel == 'suspended') {
			$color = 'orange';
		}
		if ($statuslabel == 'undeployed') {
			$color = 'grey';
			$displayforinstance='display:none;';
		}
		if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			$color = 'lightgrey';
			$displayforinstance='display:none;';
		}

		// Update resources of instance
		/*
		if (in_array($statuslabel, array('suspended', 'done')) && ! in_array($initialaction, array('changeplan')) && !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			$comment = 'Refresh contract '.$contract->ref.' after entering dashboard';
			$result = $sellyoursaasutils->sellyoursaasRemoteAction('refreshmetrics', $contract, 'admin', '', '', '0', $comment);
			if ($result <= 0) {
				$error++;

				if ($result == -2) {
					// We overwrite status 'suspended' and status 'done' with 'unreachable' (a status only for screen output)
					$statuslabel = 'unreachable';
					$color = 'orange';
				} else {
					setEventMessages($langs->trans("ErrorRefreshOfResourceFailed", $contract->ref_customer).' : '.$sellyoursaasutils->error, $sellyoursaasutils->errors, 'warnings');
				}
			}
		}
		 */

		print '
                    <!-- Card for instance of customer -->
    			    <div class="row" id="contractid'.$contract->id.'" data-contractref="'.$contract->ref.'">
    			      <div class="col-md-12">

    					<div class="portlet light">

    				      <div class="portlet-title">
    				        <div class="caption">';

		// Customer
		$tmpcustomer = new Societe($db);
		$tmpcustomer->fetch($contract->socid);

		print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';

		// Instance status
		print '<span class="caption-helper floatright clearboth">';
		print '<!-- status = '.dol_escape_htmltag($statuslabel).' -->';
		print '<span class="bold uppercase badge-myaccount-status" style="background-color:'.$color.'; border-radius: 5px; padding: 10px; color: #fff;">';
		if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			print $langs->trans("Redirection");
		} elseif ($statuslabel == 'processing') {
			print $langs->trans("DeploymentInProgress");
		} elseif ($statuslabel == 'done') {
			print $langs->trans("Alive");
		} elseif ($statuslabel == 'suspended') {
			print $langs->trans("Suspended").' '.img_warning('default', 'style="color: #fff"', 'pictowarning');
		} elseif ($statuslabel == 'undeployed') {
			print $langs->trans("Undeployed");
		} elseif ($statuslabel == 'unreachable') {
			print $langs->trans("Unreachable").' '.img_warning('default', 'style="color: #fff"', 'pictowarning');
		} else {
			print $statuslabel;
		}
		print '</span></span>';

		// Instance name
		print '<span class="bold uppercase">'.$instancename.'</span>';
		print '<span class="caption-helper"> - '.$planlabel.'</span>	<!-- This is product ref -->';

		print '<br>';

		print '<p style="padding-top: 8px;'.($statuslabel == 'undeployed' ? ' margin-bottom: 0px' : '').'" class="clearboth">';

		// ID
		print '<span class="caption-helper small"><span class="opacitymedium">'.$langs->trans("ID").' : </span><span class="font-green-sharp">'.$contract->ref.'</span></span><br>';

		// Customer (link to login on customer dashboard)
		print '<span class="opacitymedium">'.$langs->trans("Customer").' : </span>'.$tmpcustomer->name;
		print ' &nbsp; - &nbsp; '.dol_print_email($tmpcustomer->email, 0, 0, 1, 0, 1, 1);
		if ($tmpcustomer->phone) {
			print ' &nbsp; - &nbsp; '.dol_print_phone($tmpcustomer->phone, $tmpcustomer->country_code, 0, $tmpcustomer->id, '', '&nbsp;', 'phone');
		}
		print '<br>';

		// URL
		if ($statuslabel != 'undeployed') {
			print '<span class="caption-helper"><span class="opacitymedium">';
			if ($conf->dol_optimize_smallscreen) {
				print $langs->trans("URL");
			} else {
				print $langs->trans("YourURLToGoOnYourAppInstance");
			}
			print ' : </span>';
			print '<a class="font-green-sharp linktoinstance" href="https://'.$contract->ref_customer.'" target="blankinstance">';
			print img_picto($langs->trans("YourURLToGoOnYourAppInstance"), 'globe', 'class="paddingright"');
			print 'https://'.$contract->ref_customer;
			print '</a>';
			print '</span><br>';
		}

		print '<!-- <span class="caption-helper"><span class="opacitymedium">'.$langs->trans("ID").' : '.$contract->ref.'</span></span><br> -->';
		print '<span class="caption-helper">';
		print "\n";
		if ($contract->array_options['options_deployment_status'] == 'processing') {
			print '<span class="opacitymedium">'.$langs->trans("DateStart").' : </span><span class="bold">'.dol_print_date($contract->array_options['options_deployment_date_start'], 'dayhour').'</span>';
			if (($now - $contract->array_options['options_deployment_date_start']) > 120) {	// More than 2 minutes ago
				print ' - <a href="register_instance.php?reusecontractid='.$contract->id.'">'.$langs->trans("Restart").'</a>'; // Link to redeploy / restart deployment
			}
		} elseif ($contract->array_options['options_deployment_status'] == 'done') {
			print '<span class="opacitymedium">'.$langs->trans("DeploymentDate").' : </span><span class="bold">'.dol_print_date($contract->array_options['options_deployment_date_end'], 'dayhour').'</span>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans("DeploymentDate").' : </span><span class="bold">'.dol_print_date($contract->array_options['options_deployment_date_end'], 'dayhour').'</span>';
			print '<br>';
			print '<span class="opacitymedium">'.$langs->trans("UndeploymentDate").' : </span><span class="bold">'.dol_print_date($contract->array_options['options_undeployment_date'], 'dayhour').'</span>';
		}
		print "\n";
		print '</span><br>';

		// Calculate price on invoicing
		$contract->fetchObjectLinked();

		$foundtemplate=0;
		$datenextinvoice='';
		$pricetoshow = '';
		$priceinvoicedht = 0;
		$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
		if (is_array($contract->linkedObjects['facturerec'])) {
			foreach ($contract->linkedObjects['facturerec'] as $idtemplateinvoice => $templateinvoice) {
				$foundtemplate++;
				if ($templateinvoice->suspended && $contract->array_options['options_deployment_status'] == 'undeployed') {
					$pricetoshow = '';
				} else {
					$total_ht = 0;
					foreach ($templateinvoice->lines as $key => $templateline) {
						if (array_key_exists($templateline->fk_product, $mythirdpartyaccount->context['isamoduleprovider'])) {
							$total_ht += $templateline->total_ht;
						}
					}
					if ($templateinvoice->unit_frequency == 'm' && $templateinvoice->frequency == 1) {
						$pricetoshow = price($total_ht, 1, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans("HT").' / '.$langs->trans("Month");
						$priceinvoicedht = $total_ht;
					} elseif ($templateinvoice->unit_frequency == 'y' && $templateinvoice->frequency == 1) {
						$pricetoshow = price($total_ht, 1, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans("HT").' / '.$langs->trans("Year");
						$priceinvoicedht = $total_ht;
					} else {
						$pricetoshow  = $templateinvoice->frequency.' '.$freqlabel[$templateinvoice->unit_frequency];
						$pricetoshow .= ', ';
						$pricetoshow .= price($total_ht, 1, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans("HT");
						$priceinvoicedht = $total_ht;
					}
					if ($templateinvoice->suspended && $contract->array_options['options_deployment_status'] != 'done') {
						$pricetoshow = $langs->trans("InvoicingSuspended");
					}	// Replace price
				}
				if ((! $templateinvoice->suspended) && $contract->array_options['options_deployment_status'] == 'done') {
					$datenextinvoice = $templateinvoice->date_when;
				}
			}
		}

		print '
    				          </p>';
		print '</form>';
		print '</div>';
		print '</div>';

		print '<!-- tabs for instance -->'."\n";
		print '
    				      <div class="portlet-body" style="'.$displayforinstance.'">

    				        <div class="tabbable-custom nav-justified">
    				          <ul class="nav nav-tabs nav-justified centpercent">
    				            <li><a id="a_tab_resource_'.$contract->id.'" href="#tab_resource_'.$contract->id.'" data-toggle="tab"'.(! in_array($action, array('updateurlxxx')) ? ' class="active"' : '').'>'.$langs->trans("ResourcesAndOptions").'</a></li>';
		//print '<li><a id="a_tab_domain_'.$contract->id.'" href="#tab_domain_'.$contract->id.'" data-toggle="tab"'.($action == 'updateurlxxx' ? ' class="active"' : '').'>'.$langs->trans("Domain").'</a></li>';
		if (in_array($statuslabel, array('done','suspended')) && $directaccess) {
			print '<li><a id="a_tab_ssh_'.$contract->id.'" href="#tab_ssh_'.$contract->id.'" data-toggle="tab">'.$langs->trans("SSH").' / '.$langs->trans("SFTP").'</a></li>';
		}
		if (in_array($statuslabel, array('done','suspended')) && $directaccess) {
			print '<li><a id="a_tab_db_'.$contract->id.'" href="#tab_db_'.$contract->id.'" data-toggle="tab">'.$langs->trans("Database").'</a></li>';
		}
		//if (in_array($statuslabel, array('done','suspended')) ) print '<li><a id="a_tab_danger_'.$contract->id.'" href="#tab_danger_'.$contract->id.'" data-toggle="tab">'.$langs->trans("CancelInstance").'</a></li>';
		print '
    				          </ul>

    				          <div class="tab-content">

    				            <div class="tab-pane active" id="tab_resource_'.$contract->id.'">
    								<!-- <p class="opacitymedium" style="padding: 15px; margin-bottom: 5px;">'.$langs->trans("YourCustomersResourceAndOptionsDesc").' :</p> -->
						            <div class="areaforresources" style="padding-bottom: 12px;">';
		foreach ($contract->lines as $keyline => $line) {
			//var_dump($line);
			if ($line->fk_product > 0 && !array_key_exists($line->fk_product, $mythirdpartyaccount->context['isamoduleprovider'])) {
				continue;
			}

			print '<div class="resource inline-block boxresource">';
			$resourceformula='';
			$tmpproduct = new Product($db);
			if ($line->fk_product > 0) {

				$tmpproduct->fetch($line->fk_product);

				$maxHeight=40;
				$maxWidth=40;
				$alt='';
				$htmlforphoto = $tmpproduct->show_photos('product', $conf->product->dir_output, 1, 1, 1, 0, 0, $maxHeight, $maxWidth, 1, 1, 1);

				if (empty($htmlforphoto) || $htmlforphoto == '<!-- Photo -->' || $htmlforphoto == '<!-- Photo -->'."\n") {
					print '<!--no photo defined -->';
					print '<table width="100%" valign="top" align="center" border="0" cellpadding="2" cellspacing="2"><tr><td width="100%" class="photo">';
					print '<img class="photo photowithmargin" border="0" height="'.$maxHeight.'" src="'.DOL_URL_ROOT.'/public/theme/common/nophoto.png" title="'.dol_escape_htmltag($alt).'">';
					print '</td></tr></table>';
				} else {
					print $htmlforphoto;
				}

				//var_dump($tmpproduct->array_options);
				/*if ($tmpproduct->array_options['options_app_or_option'] == 'app')
				 {
				 print '<span class="opacitymedium small">'.'&nbsp;'.'</span><br>';
				 }
				 if ($tmpproduct->array_options['options_app_or_option'] == 'system')
				 {
				 print '<span class="opacitymedium small">'.'&nbsp;'.'</span><br>';
				 }
				 if ($tmpproduct->array_options['options_app_or_option'] == 'option')
				 {
				 print '<span class="opacitymedium small">'.$langs->trans("Option").'</span><br>';
				 }*/

				// Label
				$labelprod = $tmpproduct->label;
				if (preg_match('/instance/i', $tmpproduct->ref) || preg_match('/instance/i', $tmpproduct->label)) {
					$labelprod = $langs->trans("Application");
				} elseif ($tmpproduct->array_options['options_resource_label'] == 'User' && preg_match('/User/i', $tmpproduct->label)) {
					$labelprod = $langs->trans("Users");
				}

				print '<span class="opacitymedium small labelprod">'.$labelprod.'</span><br>';
				// Qty
				$resourceformula = $tmpproduct->array_options['options_resource_formula'];
				if (preg_match('/SQL:/', $resourceformula)) {
					$resourceformula = preg_match('/__d__/', $dbprefix, $resourceformula);
				}
				if (preg_match('/DISK:/', $resourceformula)) {
					$resourceformula = $resourceformula;
				}

				print '<span class="font-green-sharp counternumber">'.$line->qty.'</span>';
				print '<br>';

				$tmpduration = '';
				if ($tmpproduct->duration) {
					if ($tmpproduct->duration == '1m') {
						$tmpduration.=' / '.$langs->trans("Month");
					} elseif ($tmpproduct->duration == '1y') {
						$tmpduration.=' / '.$langs->trans("DurationYear");
					} else {
						preg_match('/^([0-9]+)([a-z]{1})$/', $tmpproduct->duration, $regs);
						if (! empty($regs[1]) && ! empty($regs[2])) {
							$tmpduration.=' / '.$regs[1].' '.($regs[2] == 'm' ? $langs->trans("Month") : ($regs[2] == 'y' ? $langs->trans("DurationYear") : ''));
						}
					}
				}

				if ($line->subprice) {
					print '<span class="opacitymedium small">'.price($line->subprice, 1, $langs, 0, -1, -1, $conf->currency);
					//if ($line->qty > 1 && $labelprodsing) print ' / '.$labelprodsing;
					if ($tmpproduct->array_options['options_resource_label']) {
						print ' / '.$langs->trans($tmpproduct->array_options['options_resource_label']);	// Label of units
					} elseif (preg_match('/users/i', $tmpproduct->ref)) {
						print ' / '.$langs->trans("User");	// backward compatibility
					}
					// TODO
					print $tmpduration;
					print '</span>';
				} else {
					if (!getDolGlobalString('SELLYOURSAAS_HIDE_PRODUCT_PRICE_IF_NULL')) {
						print '<span class="opacitymedium small">'.price($line->subprice, 1, $langs, 0, -1, -1, $conf->currency);
						// TODO
						print $tmpduration;
						print '</span>';
					} else {
						// TODO
						if (getDolGlobalString('SELLYOURSAAS_TRANSKEY_WHEN_PRODUCT_PRICE_IF_NULL')) {
							print '<span class="opacitymedium small">';
							print $langs->trans(getDolGlobalString('SELLYOURSAAS_TRANSKEY_WHEN_PRODUCT_PRICE_IF_NULL'));
							print '</span>';
						}
					}
				}
			} else { // If there is no product, this is a free product
				print '<span class="opacitymedium small">';
				print ($line->description ? $line->description : ($line->label ? $line->label : $line->libelle));
				// TODO
				print ' / '.$langs->trans("Month");
				print '</span>';
			}

			print '</div>';
		}

		print '<br><br>';

		// Show the current Plan (with link to change it)
		print '<span class="caption-helper"><span class="opacitymedium">'.$langs->trans("YourSubscriptionPlan").' : </span>';
		if (1 == 2 && $initialaction == 'changeplan' && $planid > 0 && $id == GETPOST('id', 'int')) {
			print '<input type="hidden" name="mode" value="instances"/>';
			print '<input type="hidden" name="action" value="updateplan" />';
			print '<input type="hidden" name="contractid" value="'.$contract->id.'" />';

			// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
			$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);

			// List of available plans/products
			$arrayofplanstoswitch=array();
			$sqlproducts = 'SELECT p.rowid, p.ref, p.label FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'product_extrafields as pe';
			$sqlproducts.= ' LEFT JOIN '.MAIN_DB_PREFIX.'packages as pa ON pe.package = pa.rowid';
			$sqlproducts.= ' WHERE p.tosell = 1 AND p.entity = '.((int) $conf->entity);
			$sqlproducts.= " AND pe.fk_object = p.rowid AND pe.app_or_option = 'app'";
			$sqlproducts.= " AND pe.availabelforresellers > 0";		// available in dashboard (customers + resellers)
			$sqlproducts.= " AND p.ref NOT LIKE '%DolibarrV1%'";
			$sqlproducts.= " AND (pa.restrict_domains IS NULL"; // restict_domains can be empty (it's ok)
			$sqlproducts.= " OR pa.restrict_domains = '".$db->escape($domainname)."'"; // can be mydomain.com
			$sqlproducts.= " OR pa.restrict_domains LIKE '%.".$db->escape($domainname)."'"; // can be with.mydomain.com or the last domain of [mydomain1.com,with.mydomain2.com]
			$sqlproducts.= " OR pa.restrict_domains LIKE '%.".$db->escape($domainname).",%'"; // can be the first or the middle domain of [with.mydomain1.com,with.mydomain2.com,mydomain3.com]
			$sqlproducts.= " OR pa.restrict_domains LIKE '".$db->escape($domainname).",%'"; // can be the first domain of [mydomain1.com,mydomain2.com]
			$sqlproducts.= " OR pa.restrict_domains LIKE '%,".$db->escape($domainname).",%'"; // can be the middle domain of [mydomain1.com,mydomain2.com,mydomain3.com]
			$sqlproducts.= " OR pa.restrict_domains LIKE '%,".$db->escape($domainname)."'"; // can be the last domain of [mydomain1.com,mydomain2.com]
			$sqlproducts.= ")";
			$sqlproducts.= " AND (p.rowid = ".$planid." OR 1 = 1)";		// TODO Restrict on plans compatible with current plan...
			$sqlproducts.= " ORDER BY pe.position ASC";
			$resqlproducts = $db->query($sqlproducts);
			if ($resqlproducts) {
				$num = $db->num_rows($resqlproducts);
				$j=0;
				while ($j < $num) {
					$obj = $db->fetch_object($resqlproducts);
					if ($obj) {
						$arrayofplanstoswitch[$obj->rowid]=$obj->label;
					}
					$j++;
				}
			}
			print $form->selectarray('planid', $arrayofplanstoswitch, $planid, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
			print '<input type="submit" class="btn btn-warning default change-plan-link" name="changeplan" value="'.$langs->trans("ChangePlan").'">';
		} else {
			print '<span class="bold">'.$planlabel.'</span>';
			if ($statuslabel != 'undeployed') {
				if ($priceinvoicedht == $contract->total_ht) {
					// Disabled on "My customer invoices" view
					//print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=mycustomerinstances&action=changeplan&id='.$contract->id.'#contractid'.$contract->id.'">'.$langs->trans("ChangePlan").'</a>';
				}
			}
		}
		print '</span>';
		print '<br>';

		// Billing
		if ($statuslabel != 'undeployed') {
			print '<!-- Billing information of contract -->'."\n";
			print '<span class="caption-helper spanbilling"><span class="opacitymedium">'.$langs->trans("Billing").' : </span>';
			if ($foundtemplate > 1) {
				$sellyoursaasemail = getDolGlobalString('SELLYOURSAAS_MAIN_EMAIL');
				if (! empty($tmpcustomer->array_options['options_domain_registration_page'])
					&& $tmpcustomer->array_options['options_domain_registration_page'] != getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME')) {
					$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$tmpcustomer->array_options['options_domain_registration_page'];
					if (getDolGlobalString($newnamekey)) {
						$sellyoursaasemail = getDolGlobalString($newnamekey);
					}
				}

				print '<span style="color:orange">'.$langs->trans("WarningFoundMoreThanOneInvoicingTemplate", $sellyoursaasemail).'</span>';
			} else {
				// Invoice amount line
				if ($foundtemplate != 0 && $priceinvoicedht != $contract->total_ht) {
					if ($pricetoshow != '') {
						print $langs->trans("FlatOrDiscountedPrice").' = ';
					}
				}
				print '<span class="bold">'.$pricetoshow.'</span>';

				// Discount and next invoice line
				if ($foundtemplate == 0) {	// foundtemplate means there is at least one template invoice (so contract is a paying contract)
					if ($contract->array_options['options_date_endfreeperiod'] < $now) {
						$color='orange';
					}

					print ' <span style="color:'.$color.'">';
					if ($contract->array_options['options_date_endfreeperiod'] > 0) {
						/*if ($action == "editfreeperiod" && GETPOST("idcontract", "int") == $contract->id) {
							print '<form name="formupdatefreedate">';
							print '<input type="hidden" name="token" value="'.newToken().'">';
							print '<input type="hidden" name="action" value="confirmeditfreeperiod">';
							print '<input type="hidden" name="mode" value="mycustomerinstances">';
							print '<input type="hidden" name="backtourl" value="'.$_SERVER["PHP_SELF"].'?mode=mycustomerinstances">';
							print '<input type="hidden" name="contractid" value="'.$contract->id.'">';

							print $langs->trans("TrialUntil");
							print $form->selectDate($contract->array_options['options_date_endfreeperiod'], "freeperioddate");
							print $form->buttonsSaveCancel("Save", "Cancel", array(), true);
							print "</form>";
						} else {
							print $langs->trans("TrialUntil", dol_print_date($contract->array_options['options_date_endfreeperiod'], 'day'));
							print '<a href="'.$_SERVER["PHP_SELF"].'?mode=mycustomerinstances&action=editfreeperiod&idcontract='.$contract->id.'&token='.newToken().'#contractid'.$contract->id.'"> '.img_edit().'</a>';
						}*/
						print $langs->trans("TrialUntil", dol_print_date($contract->array_options['options_date_endfreeperiod'], 'day'));
					} else {
						print $langs->trans("Trial");
					}
					print '</span>';
					if ($contract->array_options['options_date_endfreeperiod'] < $now) {
						if ($statuslabel == 'suspended') {
							print ' - <span style="color: orange">'.$langs->trans("Suspended").'</span>';
						}
						//else print ' - <span style="color: orange">'.$langs->trans("SuspendWillBeDoneSoon").'</span>';
					}
					/*if ($statuslabel == 'suspended') {
						if (empty($atleastonepaymentmode))
						 {
						 print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("AddAPaymentModeToRestoreInstance").'</a>';
						 }
						 else
						 {
						 print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("FixPaymentModeToRestoreInstance").'</a>';
						 }
					} else {
						// Fill array of company payment modes
						$arrayofcompanypaymentmodeforthiscustomer = array();
						$sqlpaymentmodes = 'SELECT rowid, default_rib FROM '.MAIN_DB_PREFIX."societe_rib";
						$sqlpaymentmodes.= " WHERE type in ('ban', 'card', 'paypal')";
						$sqlpaymentmodes.= " AND fk_soc = ".$tmpcustomer->id;
						$sqlpaymentmodes.= " AND (type = 'ban' OR (type = 'card' AND status = ".((int) $servicestatusstripe).") OR (type = 'paypal' AND status = ".((int) $servicestatuspaypal)."))";
						$sqlpaymentmodes.= " ORDER BY default_rib DESC, tms DESC";

						$resqlpaymentmodes = $db->query($sqlpaymentmodes);
						if ($resqlpaymentmodes) {
							$num_rowspaymentmodes = $db->num_rows($resqlpaymentmodes);
							if ($num_rowspaymentmodes) {
								$j=0;
								while ($j < $num_rowspaymentmodes) {
									$objpaymentmodes = $db->fetch_object($resqlpaymentmodes);
									if ($objpaymentmodes) {
										if ($objpaymentmodes->default_rib != 1) {
											continue;
										}	// Keep the default payment mode only

										$companypaymentmodetemp = new CompanyPaymentMode($db);
										$companypaymentmodetemp->fetch($objpaymentmodes->rowid);

										$arrayofcompanypaymentmodeforthiscustomer[] = $companypaymentmodetemp;
									}
									$j++;
								}
							}
						}
						$atleastonepaymentmodeforthiscustomer = (count($arrayofcompanypaymentmodeforthiscustomer) > 0 ? 1 : 0);
						$nbpaymentmodeokforthiscustomer = count($arrayofcompanypaymentmodeforthiscustomer);


						if (empty($atleastonepaymentmodeforthiscustomer)) {
							//print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("AddAPaymentMode").'</a>';
						} else {
							// If at least one payment mode already recorded
							if (sellyoursaasIsPaymentKo($contract)) {
								print ' - '.$langs->trans("ActivePaymentError");
							} else {
								print ' - '.$langs->trans("APaymentModeWasRecorded");

								// Discount code entered
								if ($contract->array_options['options_discountcode']) {
									print '<br><span class="opacitymedium">'.$langs->trans("DiscountCode").'</span> : <span class="bold">';
									print $contract->array_options['options_discountcode'];
									print '</span>';
								}
							}
						}
					}*/
				}
			}
			print '</span>';
		}

		print '
    								  </div>
    				              </div>';

		print '
        <div class="tab-pane" id="tab_ssh_'.$contract->id.'">
			<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SSHFTPDesc");

		print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("AccessIsReservedTofinalCustomerOnly").'</p>';
		/*
		if ($directaccess == 1 || ($directaccess == 2 && empty($foundtemplate)) || ($directaccess == 3 && !empty($foundtemplate))) {
			// Show message "To connect, you will need the following information:"
			print '<br>'.$langs->trans("SSHFTPDesc2").' :';
		}
		print '</p>';

		if ($directaccess == 1 || ($directaccess == 2 && empty($foundtemplate)) || ($directaccess == 3 && ! empty($foundtemplate))) {
			$ssh_server_port = ($contract->array_options['options_port_os'] ? $contract->array_options['options_port_os'] : getDolGlobalInt('SELLYOURSAAS_SSH_SERVER_PORT', 22));
			print '

                                <form class="form-horizontal" role="form">
                                <input type="hidden" name="token" value="'.newToken().'">

				                <div class="form-body">
				                  <div class="form-group col-md-12 row">
				                    <label class="col-md-3 control-label">'.$langs->trans("Hostname").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_hostname_os'].'">
				                    </div>
				                    <label class="col-md-3 control-label">'.$langs->trans("Port").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$ssh_server_port.'">
				                    </div>
				                  </div>
				                  <div class="form-group col-md-12 row">
				                    <label class="col-md-3 control-label">'.$langs->trans("SFTP Username").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_username_os'].'">
				                    </div>
				                    <label class="col-md-3 control-label">'.$langs->trans("Password").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_password_os'].'">
				                    </div>
				                  </div>
				                </div>

				                </form>
								';
		} elseif ($directaccess == 4) {
			print '<!-- directaccess = '.$directaccess.' foundtemplate = '.$foundtemplate.' -->';
			print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("PleaseOpenATicketToRequestYourCredential").'</p>';
		} else {
			print '<!-- directaccess = '.$directaccess.' foundtemplate = '.$foundtemplate.' -->';
			if ($directaccess == 3 && empty($foundtemplate)) {
				print '<p class="opacitymedium" style="padding: 15px">'.img_warning('default', '', 'pictowarning pictofixedwidth').$langs->trans("SorryFeatureNotAvailableDuringTestPeriod", $langs->transnoentitiesnoconv("MyBilling")).'...</p>';
			} else {
				print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SorryFeatureNotAvailableInYourPlan").'</p>';
			}
		}
		*/

		print '
				              </div> <!-- END TAB SSH PANE -->';

		print '
        <div class="tab-pane" id="tab_db_'.$contract->id.'">
			<p class="opacitymedium" style="padding: 15px">'.$langs->trans("DBDesc");

		print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("AccessIsReservedTofinalCustomerOnly").'</p>';

		/*
		if ($directaccess == 1 || ($directaccess == 2 && empty($foundtemplate)) || ($directaccess == 3 && !empty($foundtemplate))) {
			// Show message "To connect, you will need the following information:"
			print '<br>'.$langs->trans("DBDesc2").' :';
		}
		print '</p>
                                ';

		if ($directaccess == 1 || ($directaccess == 2 && empty($foundtemplate)) || ($directaccess == 3 && ! empty($foundtemplate))) {
			print '
                                <form class="form-horizontal" role="form">
                                <input type="hidden" name="token" value="'.newToken().'">

				                <div class="form-body">
				                  <div class="form-group col-md-12 row">
				                    <label class="col-md-3 control-label">'.$langs->trans("Hostname").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_hostname_db'].'">
				                    </div>
				                    <label class="col-md-3 control-label">'.$langs->trans("Port").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_port_db'].'">
				                    </div>
				                  </div>
				                  <div class="form-group col-md-12 row">
				                    <label class="col-md-3 control-label">'.$langs->trans("DatabaseName").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_database_db'].'">
				                    </div>
				                  </div>
				                  <div class="form-group col-md-12 row">
				                    <label class="col-md-3 control-label">'.$langs->trans("DatabaseLogin").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_username_db'].'">
				                    </div>
				                    <label class="col-md-3 control-label">'.$langs->trans("Password").'</label>
				                    <div class="col-md-3">
				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_password_db'].'">
				                    </div>
    				                  </div>';

			if (! empty($contract->array_options['options_username_ro_db'])) {
				print '
	    				                  <div class="form-group col-md-12 row">
	    				                    <label class="col-md-3 control-label">'.$langs->trans("DatabaseLoginReadOnly").'</label>
	    				                    <div class="col-md-3">
	    				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_username_ro_db'].'">
	    				                    </div>
	    				                    <label class="col-md-3 control-label">'.$langs->trans("PasswordReadOnly").'</label>
	    				                    <div class="col-md-3">
	    				                      <input type="text" disabled="disabled" class="form-control input-medium" value="'.$contract->array_options['options_password_ro_db'].'">
	    				                    </div>
	    				                  </div>';
			}

			print '
				                    	</div>

				                </form>
					           ';
		} elseif ($directaccess == 4) {
			print '<!-- directaccess = '.$directaccess.' foundtemplate = '.$foundtemplate.' -->';
			print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("PleaseOpenATicketToRequestYourCredential").'</p>';
		} else {
			print '<!-- directaccess = '.$directaccess.' foundtemplate = '.$foundtemplate.' -->';
			if ($directaccess == 3 && empty($foundtemplate)) {
				print '<p class="opacitymedium" style="padding: 15px">'.img_warning('default', '', 'pictowarning pictofixedwidth').$langs->trans("SorryFeatureNotAvailableDuringTestPeriod", $langs->transnoentitiesnoconv("MyBilling")).'...</p>';
			} else {
				print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SorryFeatureNotAvailableInYourPlan").'</p>';
			}
		}
		*/

		print '
                            </div> <!-- END TAB DB PANE -->
				          </div> <!-- END TAB CONTENT -->
				        </div> <!-- END TABABLE CUSTOM-->

				      </div><!-- END PORTLET-BODY -->


					</div> <!-- END PORTLET -->

			      </div> <!-- END COL -->

			    </div> <!-- END ROW -->';
	}		// End loop contract
}

print '
    		</div>
			</div>
   	';

if (GETPOST('tab', 'alpha')) {
	print '<script type="text/javascript" language="javascript">
    		jQuery(document).ready(function() {
    			console.log("Click on '.GETPOST('tab', 'alpha').'");
    			jQuery("#a_tab_'.GETPOST('tab', 'alpha').'").click();
    		});
    		</script>';
}

?>
<!-- END PHP TEMPLATE mycustomerinstances.tpl.php -->
