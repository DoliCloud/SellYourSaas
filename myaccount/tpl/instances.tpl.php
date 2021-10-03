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

// $initialaction can be set

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

?>
<!-- BEGIN PHP TEMPLATE instances.tpl.php -->
<?php

// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);
$forcesubdomain = GETPOST('forcesubdomain', 'alpha');

// List of available plans/products
$arrayofplans=array();
$sqlproducts = 'SELECT p.rowid, p.ref, p.label, p.price, p.price_ttc, p.duration, pe.availabelforresellers';
$sqlproducts.= ' FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'product_extrafields as pe';
$sqlproducts.= ' LEFT JOIN '.MAIN_DB_PREFIX.'packages as pa ON pe.package = pa.rowid';
$sqlproducts.= ' WHERE p.tosell = 1 AND p.entity = '.$conf->entity;
$sqlproducts.= " AND pe.fk_object = p.rowid AND pe.app_or_option = 'app'";
$sqlproducts.= " AND p.ref NOT LIKE '%DolibarrV1%'";
$sqlproducts.= " AND (pa.restrict_domains IS NULL"; // restict_domains can be empty (it's ok)
$sqlproducts.= " OR pa.restrict_domains = '".$db->escape($domainname)."'"; // can be mydomain.com
$sqlproducts.= " OR pa.restrict_domains LIKE '%.".$db->escape($domainname)."'"; // can be with.mydomain.com or the last domain of [mydomain1.com,with.mydomain2.com]
$sqlproducts.= " OR pa.restrict_domains LIKE '%.".$db->escape($domainname).",%'"; // can be the first or the middle domain of [with.mydomain1.com,with.mydomain2.com,mydomain3.com]
$sqlproducts.= " OR pa.restrict_domains LIKE '".$db->escape($domainname).",%'"; // can be the first domain of [mydomain1.com,mydomain2.com]
$sqlproducts.= " OR pa.restrict_domains LIKE '%,".$db->escape($domainname).",%'"; // can be the middle domain of [mydomain1.com,mydomain2.com,mydomain3.com]
$sqlproducts.= " OR pa.restrict_domains LIKE '%,".$db->escape($domainname)."'"; // can be the last domain of [mydomain1.com,mydomain2.com]
$sqlproducts.= ")";
$sqlproducts.= " ORDER BY pe.position ASC";
//$sqlproducts.= " AND (p.rowid = ".$planid." OR 1 = 1)";
//$sqlproducts.=' AND p.rowid = 202';
//print $sqlproducts;

$resqlproducts = $db->query($sqlproducts);
if ($resqlproducts) {
	$num = $db->num_rows($resqlproducts);

	$tmpprod = new Product($db);
	$tmpprodchild = new Product($db);
	$i=0;
	while ($i < $num) {
		$obj = $db->fetch_object($resqlproducts);
		if ($obj) {
			$tmpprod->fetch($obj->rowid, '', '', '', 1, 1, 1);
			$tmpprod->sousprods = array();
			$tmpprod->get_sousproduits_arbo();
			$tmparray = $tmpprod->get_arbo_each_prod(1, 1);

			$label = $obj->label;

			$priceinstance=array();
			$priceinstance_ttc=array();

			$priceinstance['fix'] = $obj->price;
			$priceinstance_ttc['fix'] = $obj->price_ttc;
			$priceinstance['user'] = 0;
			$priceinstance_ttc['user'] = 0;

			if (count($tmparray) > 0) {
				foreach ($tmparray as $key => $value) {
					$tmpprodchild->fetch($value['id']);
					if (preg_match('/user/i', $tmpprodchild->ref) || preg_match('/user/i', $tmpprodchild->array_options['options_resource_label'])) {
						$priceinstance['user'] += $tmpprodchild->price;
						$priceinstance_ttc['user'] += $tmpprodchild->price_ttc;
					} elseif ($tmpprodchild->array_options['options_app_or_option'] == 'system') {
						// Don't add system services to global price, these are options with calculated quantities
					} else {
						$priceinstance['fix'] += $tmpprodchild->price;
						$priceinstance_ttc['fix'] += $tmpprodchild->price_ttc;
					}
					//var_dump($tmpprodchild->id.' '.$tmpprodchild->array_options['options_app_or_option'].' '.$tmpprodchild->price_ttc.' -> '.$priceuser.' / '.$priceuser_ttc);
				}
			}

			$pricetoshow = price2num($priceinstance['fix'], 'MT');
			if (empty($pricetoshow)) $pricetoshow = 0;
			$arrayofplans[$obj->rowid]=$label.' ('.price($pricetoshow, 1, $langs, 1, 0, -1, $conf->currency);

			$tmpduration = '';
			if ($tmpprod->duration) {
				if ($tmpprod->duration == '1m') {
					$tmpduration.=' / '.$langs->trans("Month");
				} elseif ($tmpprod->duration == '1y') {
					$tmpduration.=' / '.$langs->trans("DurationYear");
				} else {
					preg_match('/^([0-9]+)([a-z]{1})$/', $tmpprod->duration, $regs);
					if (! empty($regs[1]) && ! empty($regs[2])) {
						$tmpduration.=' / '.$regs[1].' '.($regs[2] == 'm' ? $langs->trans("Month") : ($regs[2] == 'y' ? $langs->trans("DurationYear") : ''));
					}
				}
			}

			if ($tmpprod->duration) $arrayofplans[$obj->rowid].=$tmpduration;
			if ($priceinstance['user']) {
				$arrayofplans[$obj->rowid].=' + '.price(price2num($priceinstance['user'], 'MT'), 1, $langs, 1, 0, -1, $conf->currency).' / '.$langs->trans("User");
				if ($tmpprod->duration) $arrayofplans[$obj->rowid].=$tmpduration;
			}
			$arrayofplans[$obj->rowid].=')';
		}
		$i++;
	}
} else dol_print_error($db);


print '
	<div class="page-content-wrapper">
			<div class="page-content">


 	<!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("MyInstances").'</h1>
	</div>
	<!-- END PAGE TITLE -->
	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->';


if (count($listofcontractid) == 0) {				// Should not happen
	print '<span class="opacitymedium">'.$langs->trans("None").'</span>';
} else {
	$sellyoursaasutils = new SellYourSaasUtils($db);

	$arrayforsort = array();
	foreach ($listofcontractid as $id => $contract) {
		$position = 20;
		if ($contract->array_options['options_deployment_status'] == 'processing') $position = 1;
		if ($contract->array_options['options_deployment_status'] == 'suspended')  $position = 10;	// This is not a status
		if ($contract->array_options['options_deployment_status'] == 'done')       $position = 20;
		if ($contract->array_options['options_deployment_status'] == 'undeployed') $position = 100;

		$arrayforsort[$id] = array('position'=>$position, 'id'=>$id, 'contract'=>$contract);
	}
	$arrayforsort = dol_sort_array($arrayforsort, 'position');

	foreach ($arrayforsort as $id => $tmparray) {
		$id = $tmparray['id'];
		$contract = $tmparray['contract'];

		$planref = $contract->array_options['options_plan'];
		$statuslabel = $contract->array_options['options_deployment_status'];
		$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

		$dbprefix = $contract->array_options['options_db_prefix'];
		if (empty($dbprefix)) $dbprefix = 'llx_';

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
		$color = "green"; $displayforinstance = "";
		if ($statuslabel == 'processing') { $color = 'orange'; }
		if ($statuslabel == 'suspended') { $color = 'orange'; }
		if ($statuslabel == 'undeployed') { $color = 'grey'; $displayforinstance='display:none;'; }
		if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) { $color = 'lightgrey'; $displayforinstance='display:none;'; }


		// Update resources of instance
		if (in_array($statuslabel, array('suspended', 'done')) && ! in_array($initialaction, array('changeplan')) && !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			$comment = 'Refresh contract '.$contract->ref.' after entering dashboard';
			$result = $sellyoursaasutils->sellyoursaasRemoteAction('refresh', $contract, 'admin', '', '', '0', $comment);
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


		print '
				<!-- card for instance -->
			    <div class="row" id="contractid'.$contract->id.'" data-contractref="'.$contract->ref.'">
			      <div class="col-md-12">

					<div class="portlet light">

				      <div class="portlet-title">
				        <div class="caption">';

		print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';

		// Instance status
		print '<span class="caption-helper floatright clearboth">';
		//print $langs->trans("Status").' : ';
		print '<!-- status = '.dol_escape_htmltag($statuslabel).' -->';
		print '<span class="bold uppercase badge-myaccount-status" style="background-color:'.$color.'; border-radius: 5px; padding: 10px; color: #fff;">';
		if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
			print $langs->trans("Redirection");
		} elseif ($statuslabel == 'processing') print $langs->trans("DeploymentInProgress");
		elseif ($statuslabel == 'done') print $langs->trans("Alive");
		elseif ($statuslabel == 'suspended') print $langs->trans("Suspended").' '.img_warning('default', 'style="color: #fff"', 'pictowarning');
		elseif ($statuslabel == 'undeployed') print $langs->trans("Undeployed");
		elseif ($statuslabel == 'unreachable') print $langs->trans("Unreachable").' '.img_warning('default', 'style="color: #fff"', 'pictowarning');
		else print $statuslabel;
		print '</span></span>';

		// Instance name
		print '<span class="bold uppercase">'.$instancename.'</span>';
		print '<span class="caption-helper"> - '.$planlabel.'</span>	<!-- This is product ref -->';

		print '<br>';

		print '<p style="padding-top: 8px;'.($statuslabel == 'undeployed'?' margin-bottom: 0px':'').'" class="clearboth">';

		// ID
		print '<span class="caption-helper small"><span class="opacitymedium">'.$langs->trans("ID").' : </span><span class="font-green-sharp">'.$contract->ref.'</span></span><br>';

		// URL
		if ($statuslabel != 'undeployed') {
			print '<span class="caption-helper"><span class="opacitymedium">';
			if ($conf->dol_optimize_smallscreen) print $langs->trans("URL");
			else print $langs->trans("YourURLToGoOnYourAppInstance");
			print ' : </span>';
			print '<a class="font-green-sharp linktoinstance" href="https://'.$contract->ref_customer.'" target="blankinstance">';
			print 'https://'.$contract->ref_customer;
			print img_picto($langs->trans("YourURLToGoOnYourAppInstance"), 'globe', 'class="paddingleft"');
			print '</a>';
			print '</span><br>';
		}

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

		$foundtemplate=0; $datenextinvoice='';
		$pricetoshow = ''; $priceinvoicedht = 0;
		$freqlabel = array('d'=>$langs->trans('Day'), 'm'=>$langs->trans('Month'), 'y'=>$langs->trans('Year'));
		if (is_array($contract->linkedObjects['facturerec'])) {
			foreach ($contract->linkedObjects['facturerec'] as $idtemplateinvoice => $templateinvoice) {
				$foundtemplate++;
				if ($templateinvoice->suspended && $contract->array_options['options_deployment_status'] == 'undeployed') $pricetoshow = '';
				else {
					if ($templateinvoice->unit_frequency == 'm' && $templateinvoice->frequency == 1) {
						$pricetoshow = price($templateinvoice->total_ht, 1, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans("HT").' / '.$langs->trans("Month");
						$priceinvoicedht = $templateinvoice->total_ht;
					} elseif ($templateinvoice->unit_frequency == 'y' && $templateinvoice->frequency == 1) {
						$pricetoshow = price($templateinvoice->total_ht, 1, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans("HT").' / '.$langs->trans("Year");
						$priceinvoicedht = $templateinvoice->total_ht;
					} else {
						$pricetoshow  = $templateinvoice->frequency.' '.$freqlabel[$templateinvoice->unit_frequency];
						$pricetoshow .= ', ';
						$pricetoshow .= price($templateinvoice->total_ht, 1, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans("HT");
						$priceinvoicedht = $templateinvoice->total_ht;
					}
					if ($templateinvoice->suspended && $contract->array_options['options_deployment_status'] != 'done') $pricetoshow = $langs->trans("InvoicingSuspended"); // Replace price
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
		print '<div class="portlet-body" style="'.$displayforinstance.'">

				        <div class="tabbable-custom nav-justified">
				          <ul class="nav nav-tabs nav-justified">
				            <li><a id="a_tab_resource_'.$contract->id.'" href="#tab_resource_'.$contract->id.'" data-toggle="tab"'.(! in_array($action, array('updateurlxxx')) ? ' class="active"' : '').'>'.$langs->trans("ResourcesAndOptions").'</a></li>';
							print '<li><a id="a_tab_domain_'.$contract->id.'" href="#tab_domain_'.$contract->id.'" data-toggle="tab"'.($action == 'updateurlxxx' ? ' class="active"' : '').'>'.$langs->trans("Domain").'</a></li>';
							if (in_array($statuslabel, array('done','suspended')) && $directaccess) print '<li><a id="a_tab_ssh_'.$contract->id.'" href="#tab_ssh_'.$contract->id.'" data-toggle="tab">'.$langs->trans("SSH").' / '.$langs->trans("SFTP").'</a></li>';
							if (in_array($statuslabel, array('done','suspended')) && $directaccess) print '<li><a id="a_tab_db_'.$contract->id.'" href="#tab_db_'.$contract->id.'" data-toggle="tab">'.$langs->trans("Database").'</a></li>';
							if (in_array($statuslabel, array('done','suspended'))) print '<li><a id="a_tab_danger_'.$contract->id.'" href="#tab_danger_'.$contract->id.'" data-toggle="tab">'.$langs->trans("DangerZone").'</a></li>';
							print '
				          </ul>

				          <div class="tab-content">

				            <div class="tab-pane active" id="tab_resource_'.$contract->id.'">
								<!-- <p class="opacitymedium" style="padding: 15px; margin-bottom: 5px;">'.$langs->trans("YourResourceAndOptionsDesc").' :</p> -->
					            <div class="areaforresources" style="padding-bottom: 12px;">';

								$arrayoflines = $contract->lines;
								//var_dump($arrayoflines);

								// Loop on each service / option enabled
		foreach ($arrayoflines as $keyline => $line) {
			//if ($line->statut != ContratLigne::STATUS_OPEN) continue;     // We need to show even if closed for the dashboard

			//var_dump($line);
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

				print '<span class="opacitymedium small">'.$labelprod.'</span><br>';

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

				if ($line->price_ht) {
					print '<span class="opacitymedium small">'.price($line->price_ht, 1, $langs, 0, -1, -1, $conf->currency);
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
					if (empty($conf->global->SELLYOURSAAS_HIDE_PRODUCT_PRICE_IF_NULL)) {
						print '<span class="opacitymedium small">'.price($line->price_ht, 1, $langs, 0, -1, -1, $conf->currency);
						// TODO
						print $tmpduration;
						print '</span>';
					} else {
						// TODO
						if (! empty($conf->global->SELLYOURSAAS_TRANSKEY_WHEN_PRODUCT_PRICE_IF_NULL)) {
							print '<span class="opacitymedium small">';
							print $langs->trans($conf->global->SELLYOURSAAS_TRANSKEY_WHEN_PRODUCT_PRICE_IF_NULL);
							print '</span>';
						}
					}
				}
			} else // If there is no product, this is a free product
			{
				print '<!--no photo defined -->';
				print '<table width="100%" valign="top" align="center" border="0" cellpadding="2" cellspacing="2"><tr><td width="100%" class="photo">';
				print '<img class="photo photowithmargin" border="0" height="'.$maxHeight.'" src="'.DOL_URL_ROOT.'/public/theme/common/nophoto.png" title="'.dol_escape_htmltag($alt).'">';
				print '</td></tr></table>';

				// Label
				$labelprod = $line->description;
				/*if (preg_match('/instance/i', $tmpproduct->ref) || preg_match('/instance/i', $tmpproduct->label))
				 {
				 $labelprod = $langs->trans("Application");
				 }
				 elseif (preg_match('/user/i', $tmpproduct->ref) || preg_match('/user/i', $tmpproduct->label))
				 {
				 $labelprod = $langs->trans("Users");
				 }*/

				print '<span class="opacitymedium small">'.$labelprod.'</span><br>';

				print '<span class="font-green-sharp counternumber">'.$line->qty.'</span>';
				print '<br>';

				if ($line->price_ht) {
					$priceforline = $line->price_ht * $line->qty;
					print '<span class="opacitymedium small">'.price($priceforline, 1, $langs, 0, -1, -1, $conf->currency);
					//if (preg_match('/users/i', $line->description)) print ' / '.$langs->trans("User");
					print ' / '.$langs->trans("Month");
					print '</span>';
				} else {
					print '<span class="opacitymedium small">'.price($line->price_ht, 1, $langs, 0, -1, -1, $conf->currency);
					// TODO
					print ' / '.$langs->trans("Month");
					print '</span>';
				}
			}

			print '</div>';
		}

		// Add new option
		if ($statuslabel != 'processing' && $statuslabel != 'undeployed') {
			print '<div class="resource inline-block boxresource opacitymedium small">';
			print '<br><br><br>';
			print $langs->trans("SoonMoreOptionsHere");
			print '</div>';
		}

		print '<br><br>';

		// Show the current Plan (with link to change it)
		print '<span class="caption-helper"><span class="opacitymedium">'.$langs->trans("YourSubscriptionPlan").' : </span>';
		if ($action == 'changeplan' && $planid > 0 && $id == GETPOST('id', 'int')) {
			print '<input type="hidden" name="mode" value="instances"/>';
			print '<input type="hidden" name="action" value="updateplan" />';
			print '<input type="hidden" name="contractid" value="'.$contract->id.'" />';

			// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
			$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);

			// List of available plans/products
			$arrayofplanstoswitch=array();
			$sqlproducts = 'SELECT p.rowid, p.ref, p.label FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'product_extrafields as pe';
			$sqlproducts.= ' LEFT JOIN '.MAIN_DB_PREFIX.'packages as pa ON pe.package = pa.rowid';
			$sqlproducts.= ' WHERE p.tosell = 1 AND p.entity = '.$conf->entity;
			$sqlproducts.= " AND pe.fk_object = p.rowid AND pe.app_or_option = 'app'";
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
				$i=0;
				while ($i < $num) {
					$obj = $db->fetch_object($resqlproducts);
					if ($obj) {
						$arrayofplanstoswitch[$obj->rowid]=$obj->label;
					}
					$i++;
				}
			}
			print $form->selectarray('planid', $arrayofplanstoswitch, $planid, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
			print '<input type="submit" class="btn btn-warning default change-plan-link" name="changeplan" value="'.$langs->trans("ChangePlan").'">';
		} else {
			print '<span class="bold">'.$planlabel.'</span>';
			if ($statuslabel != 'undeployed') {
				if ($foundtemplate == 0 || $priceinvoicedht == $contract->total_ht) {
					print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=instances&action=changeplan&id='.$contract->id.'#contractid'.$contract->id.'">'.$langs->trans("ChangePlan").'</a>';
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
				$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
				if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
					&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
					$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
					if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
				}

				print '<span style="color:orange">'.$langs->trans("WarningFoundMoreThanOneInvoicingTemplate", $sellyoursaasemail).'</span>';
			} else {
				// Invoice maount line
				if ($foundtemplate != 0 && $priceinvoicedht != $contract->total_ht) {
					if ($pricetoshow != '') print $langs->trans("FlatOrDiscountedPrice").' = ';
				}
				print '<span class="bold">'.$pricetoshow.'</span>';

				// Discount and next invoice line
				if ($foundtemplate == 0) {	// foundtemplate means there is at least one template invoice (so contract is a paying contract)
					if ($contract->array_options['options_date_endfreeperiod'] < $now) $color='orange';

					print ' <span style="color:'.$color.'">';
					if ($contract->array_options['options_date_endfreeperiod'] > 0) print $langs->trans("TrialUntil", dol_print_date($contract->array_options['options_date_endfreeperiod'], 'day'));
					else print $langs->trans("Trial");
					print '</span>';
					if ($contract->array_options['options_date_endfreeperiod'] < $now) {
						if ($statuslabel == 'suspended') print ' - <span style="color: orange">'.$langs->trans("Suspended").'</span>';
						//else print ' - <span style="color: orange">'.$langs->trans("SuspendWillBeDoneSoon").'</span>';
					}
					if ($statuslabel == 'suspended') {
						if (empty($atleastonepaymentmode)) {
							print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("AddAPaymentModeToRestoreInstance").'</a>';
						} else {
							print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("FixPaymentModeToRestoreInstance").'</a>';
						}
					} else {
						if (empty($atleastonepaymentmode)) {
							print ' - <a href="'.$_SERVER["PHP_SELF"].'?mode=registerpaymentmode&backtourl='.urlencode($_SERVER["PHP_SELF"].'?mode='.$mode).'">'.$langs->trans("AddAPaymentMode").'</a>';
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
					}
				} elseif ($datenextinvoice) {
					// Discount code entered
					if ($templateinvoice->array_options['options_discountcode']) {
						print '<br><span class="opacitymedium">'.$langs->trans("DiscountCode").'</span> : <span class="bold">';
						print $templateinvoice->array_options['options_discountcode'];
						print '</span>';
					}
					// Date of next invoice
					print '<br><span class="opacitymedium">'.$langs->trans("NextInvoice").'</span> : <span class="bold">'.dol_print_date($datenextinvoice, 'day').'</span>';
				}
			}
			print '</span>';
		}

		print '
								  </div>
				              </div>

							<!-- tab domain -->
				            <div class="tab-pane" id="tab_domain_'.$contract->id.'">
								<form class="form-group" action="'.$_SERVER["PHP_SELF"].'" method="POST">
                                    <input type="hidden" name="token" value="'.newToken().'">
									<input type="hidden" name="mode" value="instances"/>
									<input type="hidden" name="action" value="updateurl" />
									<input type="hidden" name="contractid" value="'.$contract->id.'" />
									<input type="hidden" name="tab" value="domain_'.$contract->id.'" />

								<div class="col-md-9">
					                <div class="opacitymedium" style="padding-top: 5px">'.$langs->trans("TheURLDomainOfYourInstance").' :</div>
									<input type="text" class="urlofinstance minwidth400" disabled="disabled" value="https://'.$contract->ref_customer.'">
								';

		if (! empty($contract->array_options['options_custom_url'])) {
			print '
										<br><br>
										<div class="opacitymedium" style="padding-top: 5px">'.$langs->trans("YourCustomUrl").' :</div>
										<input type="text" class="urlofinstancecustom minwidth400" disabled="disabled" value="https://'.$contract->array_options['options_custom_url'].'">
									';
		}

								//print '<input type="submit" class="btn btn-warning default change-domain-link" name="changedomain" value="'.$langs->trans("ChangeDomain").'">';
								print '
								</div>

							  	</form>
				            </div>

				            <div class="tab-pane" id="tab_ssh_'.$contract->id.'">
				                <p class="opacitymedium" style="padding: 15px">'.$langs->trans("SSHFTPDesc").' :</p>
                                ';

		if ($directaccess == 1 || ($directaccess == 2 && empty($foundtemplate)) || ($directaccess == 3 && ! empty($foundtemplate))) {
			$ssh_server_port = ($contract->array_options['options_port_os']?$contract->array_options['options_port_os']:(! empty($conf->global->SELLYOURSAAS_SSH_SERVER_PORT)?$conf->global->SELLYOURSAAS_SSH_SERVER_PORT:22));
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
		} else {
			print '<!-- directaccess = '.$directaccess.' foundtemplate = '.$foundtemplate.' -->';
			if ($directaccess == 3 && empty($foundtemplate)) {
				print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SorryFeatureNotAvailableDuringTestPeriod").'</p>';
			} else {
				print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SorryFeatureNotAvailableInYourPlan").'</p>';
			}
		}

		print '
				              </div> <!-- END TAB SSH PANE -->

				              <div class="tab-pane" id="tab_db_'.$contract->id.'">
				                <p class="opacitymedium" style="padding: 15px">'.$langs->trans("DBDesc").' :</p>
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
		} else {
			print '<!-- directaccess = '.$directaccess.' foundtemplate = '.$foundtemplate.' -->';
			if ($directaccess == 3 && empty($foundtemplate)) {
				print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SorryFeatureNotAvailableDuringTestPeriod").'</p>';
			} else {
				print '<p class="opacitymedium" style="padding: 15px">'.$langs->trans("SorryFeatureNotAvailableInYourPlan").'</p>';
			}
		}

		print '
				              </div> <!-- END TAB DB PANE -->

				            <div class="tab-pane" id="tab_danger_'.$contract->id.'">

							<form class="form-group" action="'.$_SERVER["PHP_SELF"].'" method="POST">
                            <input type="hidden" name="token" value="'.newToken().'">

				              <div class="">
								';
								$hasopeninvoices = sellyoursaasHasOpenInvoices($contract);
		if ($hasopeninvoices) {
			print '<span class="opacitymedium">'.$langs->trans("CantCloseBecauseOfOpenInvoices").'</span><br><br>';
		} else {
			print '
					                <p class="opacitymediumbis" style="padding: 15px">
					                    '.$langs->trans("PleaseBeSure", $contract->ref_customer).'
					                </p>
									<p class="center" style="padding-bottom: 15px">
										<input type="text" required="required" class="urlofinstancetodestroy" name="urlofinstancetodestroy" value="'.GETPOST('urlofinstancetodestroy', 'alpha').'" placeholder="'.$langs->trans("NameOfInstanceToDestroy").'" autofocus>
									</p>';
		}
		print '
								<p class="center">
									<input type="hidden" name="mode" value="instances"/>
									<input type="hidden" name="action" value="undeploy" />
									<input type="hidden" name="contractid" value="'.$contract->id.'" />
									<input type="hidden" name="tab" value="danger_'.$contract->id.'" />
									<input type="submit" '.($hasopeninvoices?' disabled="disabled"':'').' class="btn btn-danger'.($hasopeninvoices?' disabled':'').'" name="undeploy" value="'.$langs->trans("UndeployInstance").'">
								</p>
				              </div>

							</form>

				            </div> <!-- END TAB PANE -->

				          </div> <!-- END TAB CONTENT -->
				        </div> <!-- END TABABLE CUSTOM-->

				      </div><!-- END PORTLET-BODY -->


					</div> <!-- END PORTLET -->

			      </div> <!-- END COL -->

			    </div> <!-- END ROW -->';
	}		// End loop contract
}


	// Section to add/create a new instance
	print '
    	<!-- Add a new instance -->
    	<div class="portlet-body" style=""><br>
    	';

	print '<a href="#addanotherinstance" id="addanotherinstance" class="valignmiddle">';
	print '<span class="fa fa-plus-circle valignmiddle" style="font-size: 1.5em; padding-right: 4px;"></span><span class="valignmiddle text-plus-circle">'.$langs->trans("AddAnotherInstance").'...</span><br>';
	print '</a>';

	print '<script type="text/javascript" language="javascript">
        function applyDomainConstraints( domain )
        {
            domain = domain.replace(/ /g, "");
            domain = domain.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            // allow  "a-z", "A-Z", "0-9" and "-"
            domain = domain.replace(/[^\w\-]/g, "");
            domain = domain.replace(/\_/g, "");
            domain = domain.replace(/^[^a-z0-9\-]+/ig, "");		// We accept the - at start during input to avoid to have it to be removed automatically during typing
            domain = domain.replace(/[^a-z0-9\-]+$/ig, "");		// We accept the - at end during input to avoid to have it to be removed automatically during typing
            domain = domain.toLowerCase();
            if (!isNaN(domain)) {
              return ""
            }
            while ( domain.length > 1 && !isNaN( domain.charAt(0))  ){
              domain=domain.substr(1)
            }
            return domain
        }
    	jQuery(document).ready(function() {
            /* Apply constraints in sldAndSubdomain field */
            jQuery("#formaddanotherinstance").on("change keyup", "#sldAndSubdomain", function() {
                console.log("Update sldAndSubdomain field in instances.tpl.php");
        	    $(this).val( applyDomainConstraints( $(this).val() ) );
            });
    		jQuery("#addanotherinstance").click(function() {
    			console.log("Click on addanotherinstance");
    			jQuery("#formaddanotherinstance").toggle();
    		});


            jQuery("#formaddanotherinstance").submit(function() {
                console.log("We clicked on submit on instance.tpl.php")

                jQuery(document.body).css({ \'cursor\': \'wait\' });
                jQuery("div#waitMask").show();
                jQuery("#waitMask").css("opacity"); // must read it first
                jQuery("#waitMask").css("opacity", "0.7");

				return true;	/* Use return false to show the hourglass without submitting the page (for debug) */
            });
    	});
    		</script>';

	print '<br>';

	print '<!-- Form to add an instance -->'."\n";
	print '<form id="formaddanotherinstance" class="form-group reposition" style="display: none;" action="register_instance.php" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="deployall" />';
	print '<input type="hidden" name="fromsocid" value="0" />';
	print '<input type="hidden" name="reusesocid" value="'.$socid.'" />';
	print '<!-- thirdpartyidinsession = '.$_SESSION['dol_loginsellyoursaas'].' -->';

	print '<div class="row">
    	<div class="col-md-12">

    	<div class="portlet light">';

	//var_dump($arrayofplans);
	//natcasesort($arrayofplans);

	$MAXINSTANCES = ((empty($mythirdpartyaccount->array_options['options_maxnbofinstances']) && $mythirdpartyaccount->array_options['options_maxnbofinstances'] != '0') ? (empty($conf->global->SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT) ? 4 : $conf->global->SELLYOURSAAS_MAX_INSTANCE_PER_ACCOUNT) : $mythirdpartyaccount->array_options['options_maxnbofinstances']);
if ($MAXINSTANCES && count($listofcontractid) < $MAXINSTANCES) {
	if (! empty($conf->global->SELLYOURSAAS_DISABLE_NEW_INSTANCES)) {
		print '<!-- RegistrationSuspendedForTheMomentPleaseTryLater -->'."\n";
		print '<div class="alert alert-warning" style="margin-bottom: 0px">';
		print $langs->trans("RegistrationSuspendedForTheMomentPleaseTryLater");
		print '</div>';
	} else {
		print '<div class="group">';

		print '<div class="horizontal-fld centpercent marginbottomonly">';
		print '<strong>'.$langs->trans("YourSubscriptionPlan").'</strong> ';
		print $form->selectarray('service', $arrayofplans, $planid, 0, 0, 0, '', 0, 0, 0, '', 'minwidth500');
		print '<br>';
		print '</div>';
		//print ajax_combobox('service');

		print '

        			<div class="horizontal-fld clearboth margintoponly">
        			<div class="control-group required">
        			<label class="control-label" for="password" trans="1">'.$langs->trans("Password").'</label><input name="password" type="password" maxlength="128" required />
        			</div>
        			</div>
        			<div class="horizontal-fld margintoponly">
        			<div class="control-group required">
        			<label class="control-label" for="password2" trans="1">'.$langs->trans("ConfirmPassword").'</label><input name="password2" type="password" maxlength="128" required />
        			</div>
        			</div>
        			</div> <!-- end group -->

        			<section id="selectDomain margintoponly" style="margin-top: 20px;">
        			<div class="fld select-domain required">
        			<label trans="1">'.$langs->trans("ChooseANameForYourApplication").'</label>
        			<div class="linked-flds">
        			<span class="opacitymedium">https://</span>
        			<input class="sldAndSubdomain" type="text" name="sldAndSubdomain" id="sldAndSubdomain" value="" maxlength="29" required />
        			<select name="tldid" id="tldid" >';
		// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
		$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);

		$listofdomain = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);
		foreach ($listofdomain as $val) {
			$newval=$val;
			$reg = array();
			if (preg_match('/:(.*)$/', $newval, $reg)) {      // If this domain must be shown only if domain match
				$newval = preg_replace('/:.*$/', '', $newval);
				if ($reg[1] != $domainname && $newval != GETPOST('forcesubdomain', 'alpha')) continue;
			}
			// $newval is subdomain (with.mysaasdomainname.com for example)

			if (! preg_match('/^\./', $newval)) $newval='.'.$newval;
			print '<option value="'.$newval.'"'.(($newval == '.'.GETPOST('forcesubdomain', 'alpha')) ? ' selected="selected"':'').'>'.$newval.'</option>';
		}
		print '</select>
        			<br class="unfloat" />
        			</div>
        			</div>
        			</section>';

		if (GETPOST('admin', 'alpha')) {
			print '<div class="horizontal-fld clearboth margintoponly">';
			print '<input type="checkbox" name="disablecustomeremail" /> '.$langs->trans("DisableEmailToCustomer");
			print '</div>';
		}

		print '<br><input type="submit" class="btn btn-warning default change-plan-link" name="changeplan" value="'.$langs->trans("Create").'">';
	}
} else {
	// Max number of instances reached
	print '<!-- Max number of instances reached -->';

	$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
	if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
		$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
		if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
	}

	print '<div class="warning">'.$langs->trans("MaxNumberOfInstanceReached", $MAXINSTANCES, $sellyoursaasemail).'</div>';
}

	print '</div></div></div>';

	print '</form>';


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
<!-- END PHP TEMPLATE instances.tpl.php -->
