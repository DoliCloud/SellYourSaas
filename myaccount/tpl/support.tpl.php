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

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

?>
<!-- BEGIN PHP TEMPLATE support.tpl.php -->
<?php
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';

$upload_dir = $conf->sellyoursaas->dir_temp."/support_thirdparty_id_".$mythirdpartyaccount->id.'.tmp';

if (!empty($_POST['addfile'])) {
	// Set tmp user directory
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_add_file_process($upload_dir, 1, 0);

	$action = "presend";
}

if (!empty($_POST["removedfile"])) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	dol_remove_file_process($_POST['removedfile'], 0, 0); // We really delete file linked to mailing

	$action = "presend";
}

// Print warning to read FAQ before
print '<!-- Message to read FAQ and get status -->'."\n";
if ($urlfaq || $urlstatus) {
	print '<div class="alert alert-success note note-success" id="supportform">'."\n";
	if ($urlfaq) {
		print '<h4 class="block">'.$langs->trans("PleaseReadFAQFirst", $urlfaq).'</h4>'."\n";
	}
	if ($urlstatus) {
		print '<br>'.$langs->trans("CurrentServiceStatus", $urlstatus)."\n";
	}
	print '</div>'."\n";
}

print '
	<div class="page-content-wrapper">
			<div class="page-content">


	     <!-- BEGIN PAGE HEADER-->
	<!-- BEGIN PAGE HEAD -->
	<div class="page-head">
	  <!-- BEGIN PAGE TITLE -->
	<div class="page-title">
	  <h1>'.$langs->trans("NewTicket").' <small>'.$langs->trans("SupportDesc").'</small></h1>
	</div>
	<!-- END PAGE TITLE -->


	</div>
	<!-- END PAGE HEAD -->
	<!-- END PAGE HEADER-->';

	$sellyoursaassupporturl = getDolGlobalString('SELLYOURSAAS_SUPPORT_URL');
if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
	$newnamekey = 'SELLYOURSAAS_SUPPORT_URL_'.strtoupper(str_replace('.', '_', $mythirdpartyaccount->array_options['options_domain_registration_page']));
	if (! empty($conf->global->$newnamekey)) {
		$sellyoursaassupporturl = $conf->global->$newnamekey;
	}
}

if ($sellyoursaassupporturl) {
	$sellyoursaassupporturl = str_replace('__EMAIL__', $mythirdpartyaccount->email, $sellyoursaassupporturl);
	$sellyoursaassupporturl = str_replace('__FIRSTNAME__', $mythirdpartyaccount->array_options['options_firstname'], $sellyoursaassupporturl);
	$sellyoursaassupporturl = str_replace('__LASTNAME__', $mythirdpartyaccount->array_options['options_lastname'], $sellyoursaassupporturl);

	print '<div class="row" id="supporturl"><div class="col-md-12"><div class="portlet light">';
	print $langs->trans("SupportURLExternal", $sellyoursaassupporturl).'<br />'."\n";
	print '</div></div></div>';
} else {
	print '
			    <div class="row" id="choosechannel">
			      <div class="col-md-12">

					<div class="portlet light">

				      <div class="portlet-title">
				        <div class="caption">';
	if (!empty(getDolGlobalString('SELLYOURSAAS_SUPPORT_SHOW_MESSAGE'))) {
		print '<span>'.$langs->trans(getDolGlobalString('SELLYOURSAAS_SUPPORT_SHOW_MESSAGE')).'</span><br><br>';
	} else {
		print '<span class="opacitymedium"><br>'.$langs->trans("AskForSupport").'...</span><br><br>';
	}

	print '<!-- form to select channel -->'."\n";
	print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="mode" value="support">';
	print '<input type="hidden" name="page_y" value="">';
	print '<input type="hidden" name="action" value="presend">';

	print '<span class="supportemailfield bold">'.$langs->trans("SupportChannel").'</span>'."\n";

	print '<select id="supportchannel" name="supportchannel" class="minwidth600">';
	print '<option value="">&nbsp;</option>';
	if (count($listofcontractid) == 0) {
		// Should not happen
	} else {
		$atleastonehigh=0;
		$atleastonefound=0;

		foreach ($listofcontractid as $id => $contract) {
			$planref = $contract->array_options['options_plan'];
			$statuslabel = $contract->array_options['options_deployment_status'];
			$instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

			$dbprefix = empty($contract->array_options['options_prefix_db']) ? '' : $contract->array_options['options_prefix_db'];
			if (empty($dbprefix)) {
				$dbprefix = 'llx_';
			}

			if ($statuslabel == 'undeployed') {
				continue;
			}

			// Get info about PLAN of Contract
			$planlabel = $planref;		// By default but we will take ref and label of service of type 'app' later

			$planid = 0;
			$freeperioddays = 0;
			$directaccess = 0;

			$tmpproduct = new Product($db);
			foreach ($contract->lines as $keyline => $line) {
				if ($line->statut == 5 && $contract->array_options['options_deployment_status'] != 'undeployed') {
					$statuslabel = 'suspended';
				}

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

			$ispaid = sellyoursaasIsPaidInstance($contract);

			$color = "green";
			if ($statuslabel == 'processing') {
				$color = 'orange';
			}
			if ($statuslabel == 'suspended') {
				$color = 'orange';
			}
			if ($statuslabel == 'undeployed') {
				$color = 'grey';
			}
			if (preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
				$color = 'lightgrey';
			}

			if ($tmpproduct->array_options['options_typesupport'] != 'none'
				&& !preg_match('/^http/i', $contract->array_options['options_suspendmaintenance_message'])) {
				if (! $ispaid) {	// non paid instances
					$priority = 'low';
					$prioritylabel = '<span class="prioritylow">'.$langs->trans("Priority").' '.$langs->trans("Low").'</span> <span class="opacitymedium">'.$langs->trans("Trial").'</span>';
				} else {
					if ($ispaid) {	// paid with level Premium
						if ($tmpproduct->array_options['options_typesupport'] == 'premium') {
							$priority = 'high';
							$prioritylabel = '<span class="priorityhigh">'.$langs->trans("Priority").' '.$langs->trans("High").'</span>';
							$atleastonehigh++;
						} else {	// paid with level Basic
							$priority = 'medium';
							$prioritylabel = '<span class="prioritymedium">'.$langs->trans("Priority").' '.$langs->trans("Medium").'</span>';
						}
					}
				}

				$optionid = $priority.'_'.$id;
				$labeltoshow = '';
				$labeltoshow .= $langs->trans("Instance").' <strong>'.$contract->ref_customer.'</strong> ';
				//$labeltoshow = $tmpproduct->label.' - '.$contract->ref_customer.' ';
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				//$labeltoshow .= $tmpproduct->array_options['options_typesupport'];
				$labeltoshow .= ' - ';
				$labeltoshow .= $prioritylabel;

				print '<option value="'.$optionid.'"'.(GETPOST('supportchannel', 'alpha') == $optionid ? ' selected="selected"' : '').'" data-html="'.dol_escape_htmltag($labeltoshow).'">';
				print dol_escape_htmltag($labeltoshow);
				print '</option>';
				//print ajax_combobox('supportchannel');

				$atleastonefound++;
			}
		}
	}

	// Add link other or miscellaneous
	if (! $atleastonefound) {
		$labelother = $langs->trans("Miscellaneous");
	} else {
		$labelother = $langs->trans("Other");
	}

	$labelother .= ' &nbsp; <span class="prioritylow">'.$langs->trans("Priority").' '.$langs->trans("Low").'</span>';

	print '<option value="low_other"'.(GETPOST('supportchannel', 'alpha') == 'low_other' ? ' selected="selected"' : '').' data-html="'.dol_escape_htmltag($labelother).'">'.dol_escape_htmltag($labelother).'</option>';
	if (empty($atleastonehigh)) {
		$labeltoshow = $langs->trans("PremiumSupport").' <span class="priorityhigh">'.$langs->trans("Priority").' '.$langs->trans("High").'</span> ('.$langs->trans("NoPremiumPlan").')';
		print '<option value="high_premium" disabled="disabled" data-html="'.dol_escape_htmltag('<strike>'.$labeltoshow).'</strike>">'.dol_escape_htmltag($labeltoshow).'</option>';
	}
	print '</select>';
	print ajax_combobox("supportchannel");

	print ' <input type="submit" name="choosechannel" value="'.$langs->trans("Choose").'" class="btn green-haze btn-circle margintop marginbottom marginleft marginright reposition">';

	print '</form>';


	if (($action == 'presend' && GETPOST('supportchannel', 'alpha')) || getDolGlobalInt('SELLYOURSAAS_ONLY_NON_PROFIT_ORGA')) {
		$trackid = '';
		dol_init_file_process($upload_dir, $trackid);

		if (GETPOST('choosechannel')) {
			$counttodelete = 0;
			dol_delete_dir_recursive($upload_dir, $counttodelete);
		}

		// List of files
		$listofpaths = dol_dir_list($upload_dir, 'files', 0, '', '', 'name', SORT_ASC, 0);

		$out = '';
		$out .= '<input type="hidden" class="removedfilehidden" name="removedfile" value="">'."\n";
		$out .= '<script type="text/javascript" language="javascript">';
		$out .= 'jQuery(document).ready(function () {';
		$out .= '    jQuery(".removedfile").click(function() {';
		$out .= '        console.log("click on .removedfile");';
		$out .= '        jQuery(".removedfilehidden").val(jQuery(this).val());';
		$out .= '    });';
		$out .= '})';
		$out .= '</script>'."\n";
		if (count($listofpaths)) {
			foreach ($listofpaths as $key => $val) {
				$out .= '<div id="attachfile_'.$key.'" class="margintop">';
				$out .= img_mime($listofpaths[$key]['name']).' '.$listofpaths[$key]['name'];
				$out .= ' <input type="image" style="border: 0px;" src="'.img_picto($langs->trans("Search"), 'delete.png', '', '', 1).'" value="'.($key + 1).'" class="removedfile" id="removedfile_'.$key.'" name="removedfile_'.$key.'" />';
				$out .= '</div>';
			}
		} else {
			$out .= '<br>';
		}

		$tmpcontractid = $id;	// The last contract id found
		if (GETPOST('supportchannel', 'alpha')) {
			$tmparray = explode('_', GETPOST('supportchannel', 'alpha'));
			if (!empty($tmparray[1]) && $tmparray[1] > 0) {
				$tmpcontractid = $tmparray[1];
				// TODO Check that $tmpcontractid is into list of own contract ids.
			}
		}

		print '<!-- form to send a ticket -->'."\n";
		print '<form id="mailform" class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="send">';
		print '<input type="hidden" name="page_y" value="">';
		print '<input type="hidden" name="mode" value="support">';
		print '<input type="hidden" name="contractid" value="'.$tmpcontractid.'">';
		print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel', 'alpha').'">';

		$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
		if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
		&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
			if (! empty($conf->global->$newnamekey)) {
				$sellyoursaasemail = $conf->global->$newnamekey;
			}
		}

		if (! empty($conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM) && preg_match('/high/', GETPOST('supportchannel', 'alpha'))) {
			// We must use the prioritary email
			$sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM;
			if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
			&& $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
				$newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_PREMIUM_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
				if (getDolGlobalString($newnamekey)) {
					$sellyoursaasemail = getDolGlobalString($newnamekey);
				}
			}
		}

		$subject = (GETPOST('subject', 'none') ? GETPOST('subject', 'none') : '');

		print '<input type="hidden" name="to" value="'.$sellyoursaasemail.'">';

		// Combobox for Group of ticket
		$formticket = new FormTicket($db);

		$atleastonepublicgroup = 0;
		$ticketstat = new Ticket($db);
		$ticketstat->loadCacheCategoriesTickets();
		if (is_array($ticketstat->cache_category_tickets) && count($ticketstat->cache_category_tickets)) {
			foreach ($ticketstat->cache_category_tickets as $tg) {
				if (!empty($tg['public'])) {
					$atleastonepublicgroup++;
				}
			}
		}
		//$atleastonepublicgroup = 0;

		//$atleastonepublicgroup = 0;
		print $atleastonepublicgroup > 1 ? '<br>' : "";

		$stringtoprint = '';
		if ($atleastonepublicgroup > 1 && GETPOST('supportchannel', 'alpha')) {
			//$stringtoprint = '<br>';
			$stringtoprint .= $formticket->selectGroupTickets(GETPOST('ticketcategory', 'int'), 'ticketcategory', 'public=1', 0, 0, 1, 0, '', 1, $langs);
			$stringtoprint .= '<br>';
		}

		$stringtoprint .= "<!-- Script to manage change of ticket group -->
		<script>
		var preselectedticketcategory = '".GETPOST('ticketcategory', 'alpha')."';
		var automigrationcode = '".getDolGlobalString('SELLYOURSAAS_AUTOMIGRATION_CODE', '0')."'
		";

		$stringtoprint .= '
		jQuery(document).ready(function() {
			function groupticketchange(){
				idgroupticket = $("#ticketcategory").val();
				console.log("We called groupticketchange and have selected id="+idgroupticket+", so we try to load list KM linked to event");

				$("#KWwithajax").html("");

				if (idgroupticket != "") {
					$.ajax({ url: \'/ajax/fetchKnowledgeRecord.php\',
						 data: { action: \'getKnowledgeRecord\', idticketgroup: idgroupticket, token: \''.newToken().'\', lang:\''.dol_escape_htmltag($langs->defaultlang).'\', public:1 },
						 type: \'GET\',
						 success: function(response) {
							var urllist = \'\';
							console.log("We received response "+response);
							response = JSON.parse(response)
							for (key in response) {
								console.log(response[key])
								urllist += \'<li><a href="\'+ response[key].url + \'" target="_blank">\'+response[key].title+\'</a></li>\';
							}
							if (urllist != "") {
								console.log(urllist)
								$("#KWwithajax").html(\'<div class="opacitymedium margintoponly">'.dol_escape_htmltag($langs->trans("KMFoundForTicketGroup")).':</div><ul class="kmlist">\'+urllist+\'<ul><br>\');
								$("#KWwithajax").show();
							}
							$("#form").focus();
						 },
						 error : function(output) {
							console.log("error");
						 },
					});
				}
			};

			$("#ticketcategory").bind("change",function() {
				console.log("We change group of ticket");
				groupticketchange();';

		$stringtoprint .= '
				tmp = $("#ticketcategory_select_child_id").val();
				$("#ticketcategory_child_id_back").val(tmp);
				tmp = $("#ticketcategory_select").val();
				console.log($("#ticketcategory_back"));
				$("#ticketcategory_back").val(tmp);
				';
		$stringtoprint .= '
					if ("' . getDolGlobalString('SELLYOURSAAS_AUTOMIGRATION_CODE').'" == $("#ticketcategory").val()){
						console.log("We hide for automigration");
						$(".hideforautomigration").hide();
						$(".showforautoupgrade").hide();
						$(".showforautomigration").show();
						$("#modeforchangemmode").val("automigration")
					} else if ("'.getDolGlobalString('SELLYOURSAAS_AUTOUPGRADE_CODE', '0').'" == $("#ticketcategory").val()){
						console.log("We hide for autoupgrade");
						$(".hideforautomigration").hide();
						$(".showforautomigration").hide();
						$(".showforautoupgrade").show();
						$("#modeforchangemmode").val("autoupgrade")
					} else {';

		$stringtoprint .= '
						if ($("#ticketcategory").val() != "") {
							console.log("We show full form");
							$(".hideforautomigration").show();
							$(".showforautomigration").hide();
							$(".hideforautoupgrade").show();
							$(".showforautoupgrade").hide();
							$("#from").focus();
						} else if($("#ticketcategory").val() == "") {
							console.log("We hide all");
							$(".hideforautomigration").hide();
							$(".showforautomigration").hide();
							$(".hideforautoupgrade").hide();
							$(".showforautoupgrade").hide();
						}
						$("#buttonforautomigrationwithhidden").hide();
					}';
		$stringtoprint .= '
			});';

		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE)) {
			if (GETPOST('backfromautomigration', 'alpha')) {
				$stringtoprint .= '
				console.log("We show for automigration");
				$(".hideforautomigration").show();
				$(".showforautomigration").hide();
				$("#buttonforautomigrationwithhidden").show();';
			}
			$stringtoprint .= 'if ("' . getDolGlobalString('SELLYOURSAAS_AUTOMIGRATION_CODE').'" == $("#ticketcategory").val()){
				console.log("We hide for automigration");
				$(".hideforautomigration").show();
				$(".showforautomigration").hide();
				$("#buttonforautomigrationwithhidden").show();
			}';
			$stringtoprint .= '
			$("#hideautomigrationdiv").on("click",function(){
				console.log("We cancel the automigration");
				$(".hideforautomigration").show();
				$(".showforautomigration").hide();
				$("#buttonforautomigrationwithhidden").show();
				$("#form").focus();
			})';

			$stringtoprint .= '
			$("input[name=\'subject\']").on("change",function(){
				$("#subject_back").val($(this).val());
			})';
		}
		if (!empty($conf->global->SELLYOURSAAS_AUTOUPGRADE_CODE)) {
			$stringtoprint .= '
			$("#hideautoupgradediv").on("click",function(){
				console.log("We cancel the autoupgrade");
				$(".hideforautomigration").show();
				$(".showforautoupgrade").hide();
				$("#form").focus();
			})';
		}

		$stringtoprint .= "
			/* If we have something selected */
			console.log('supportchannel = ".GETPOST('supportchannel', 'alpha')."');
			console.log('ticketcategory = ".GETPOST('ticketcategory', 'alpha')."');
			if (('".GETPOST('supportchannel', 'alpha')."' == '' || ('".GETPOST('ticketcategory')."' == '')) && (".$atleastonepublicgroup." > 1) && (preselectedticketcategory == '' || preselectedticketcategory == automigrationcode)) {
				$('.hideforautomigration').hide();
			}
		});
		</script>"."\n";

		$stringtoprint .= '<div class="supportemailfield paddingtop" id="KWwithajax"></div>';
		$stringtoprint .= '<br>';

		print $stringtoprint;

		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE)) {
			print '<div id="showforautomigration" class="showforautomigration" style="display:none;">';
			print '<br><br>';
			print '<div style="display:flex;justify-content: space-evenly;">';
			print '<button id="hideautomigrationgoto" type="submit" form="changemodeForm" class="btn green-haze btn-circle margintop marginbottom marginleft marginright whitespacenowrap flexitem50">'.$langs->trans("GoToAutomigration").'</button>&ensp;';
			print '<button id="hideautomigrationdiv" type="button" class="btn green-haze btn-circle margintop marginbottom marginleft marginright whitespacenowrap flexitem50">'.$langs->trans("AutomigrationErrorOrNoAutomigration").'</button>';
			print '</div>';
			print '<br>';
			print '</div>';
		}

		if (!empty($conf->global->SELLYOURSAAS_AUTOUPGRADE_CODE)) {
			print '<div id="showforautoupgrade" class="showforautoupgrade" style="display:none;">';
			print '<br>';
			print '<div style="display:flex;justify-content: space-evenly;">';
			print '<button id="hideautoupgradegoto" type="submit" form="changemodeForm" class="btn green-haze btn-circle margintop marginbottom marginleft marginright whitespacenowrap flexitem50">'.$langs->trans("GoToAutoUpgrade").'</button>&ensp;';
			print '<button id="hideautoupgradediv" type="button" class="btn green-haze btn-circle margintop marginbottom marginleft marginright whitespacenowrap flexitem50">'.$langs->trans("AutoupgradeErrorOrNoAutoupgrade").'</button>';
			print '</div>';
			print '<br>';
			print '</div>';
		}

		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE) || !empty($conf->global->SELLYOURSAAS_AUTOUPGRADE_CODE)) {
			print '<div id="hideforautomigration" class="hideforautomigration"><div>';
		}

		print '<div class="hideforautomigration">';

		// From
		print '<span class="supportemailfield inline-block bold">'.$langs->trans("MailFrom").'</span> <input type="text"'.(GETPOST('addfile') ? '' : ' autofocus').' class="minwidth300" id="from" name="from" value="'.(GETPOST('from', 'none') ? GETPOST('from', 'none') : $mythirdpartyaccount->email).'" placeholder="email@domain.com"><br><br>';

		// Topic
		print '<span class="supportemailfield inline-block bold">'.$langs->trans("MailTopic").'</span> <input type="text" class="minwidth500" id="formsubject" name="subject" value="'.$subject.'"><br><br>';

		print '<input type="file" class="flat" id="addedfile" name="addedfile[]" multiple value="'.$langs->trans("Upload").'" />';
		print ' ';
		print '<input type="submit" class="btn green-haze btn-circle reposition" id="addfile" name="addfile" value="'.$langs->trans("MailingAddFile").'" />';

		print $out;

		print '<br>';
		// Description
		print '<textarea rows="6" placeholder="'.$langs->trans("YourText").'" style="border: 1px solid #888" name="content" class="centpercent">'.GETPOST('content', 'none').'</textarea><br><br>';

		// Button to send ticket/email
		print '<center><input type="submit" name="submit" value="'.$langs->trans("SendMail").'" class="btn green-haze btn-circle marginrightonly reposition"';
		if ($conf->use_javascript_ajax) {
			print ' onClick="if (document.forms.mailform.addedfile.value != \'\') { alert(\''.dol_escape_js($langs->trans("FileWasNotUploaded")).'\'); return false; } else { return true; }"';
		}
		print '>';
		print ' ';
		print '<input type="submit" name="cancel" formnovalidate value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle marginleftonly">';
		print '</center>';

		print '</div>';

		print '</form>';

		if (!empty($conf->global->SELLYOURSAAS_AUTOMIGRATION_CODE) || !empty($conf->global->SELLYOURSAAS_AUTOUPGRADE_CODE)) {
			print '<form action="'.$_SERVER["PHP_SELF"].'#Step1" method="get" id="changemodeForm">';
			print '<input type="hidden" id="modeforchangemmode" name="mode" value="automigration">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="contractid" value="'.$tmpcontractid.'">';
			print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel', 'alpha').'">';
			print '<input type="hidden" id="ticketcategory_child_id_back" name="ticketcategory_child_id_back" value="'.GETPOST('ticketcategory_child_id', 'alpha').'">';
			print '<input type="hidden" id="ticketcategory_back" name="ticketcategory_back" value="'.GETPOST('ticketcategory', 'alpha').'">';
			if (!empty($subject)) {
				print '<input type="hidden" id="subject_back" name="subject_back" value="'.$subject.'">';
			}
			print '<input type="hidden" name="action" value="view">';
			print '</form>';
		}
	}

	print ' 	</div></div>

					</div> <!-- END PORTLET -->



			      </div> <!-- END COL -->


			    </div> <!-- END ROW -->
			';
}

if (isModEnabled("ticket") && empty($sellyoursaassupporturl) && ($action != 'presend' || !GETPOST('supportchannel', 'alpha'))) {
	print '
    				<!-- BEGIN PAGE HEADER-->
    				<!-- BEGIN PAGE HEAD -->
    				<div class="page-head">
    				<!-- BEGIN PAGE TITLE -->
    				<div class="page-title">
					<h1>'.$langs->trans("OldTickets").' <small>'.$langs->trans("OldTicketsDesc").'</small></h1>
    				</div>
    				<!-- END PAGE TITLE -->


    				</div>
    				<!-- END PAGE HEAD -->
    				<!-- END PAGE HEADER-->';

	print '
    		<div class="row">
    		<div class="col-md-12">

    		<div class="portlet light" id="planSection">

    		<div class="portlet-title">
    		<div class="caption">
    		<!--<span class="caption-subject font-green-sharp bold uppercase">'.$langs->trans("Tickets").'</span>-->
    		</div>
    		</div>';

	print '
    					<!-- <div class="row" id="contractid'.$contract->id.'" data-contractref="'.$contract->ref.'"> -->
    					<div class="col-md-12">';



	require_once DOL_DOCUMENT_ROOT.'/ticket/class/actions_ticket.class.php';
	require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticketstats.class.php';
	$staticticket = new Ticket($db);

	$sql = "SELECT t.rowid, t.ref, t.track_id, t.datec, t.subject, t.fk_statut";
	$sql .= " FROM ".MAIN_DB_PREFIX."ticket as t";
	$sql .= " WHERE t.fk_soc = '".$db->escape($socid)."'";		// $socid is id of third party account
	$sql .= $db->order('t.fk_statut', 'ASC');

	$resql=$db->query($sql);
	if ($resql) {
		print '<div class="div-table-responsive-no-min">';
		$num_rows = $db->num_rows($resql);
		if ($num_rows) {
			print '<table class="noborder centpercent">';
			$i = 0;
			while ($i < $num_rows) {
				$obj = $db->fetch_object($resql);

				$staticticket->id = $obj->rowid;
				$staticticket->ref = $obj->ref;
				$staticticket->track_id = $obj->track_id;
				$staticticket->fk_statut = $obj->fk_statut;
				$staticticket->status = $obj->fk_statut;
				$staticticket->progress = $obj->progress;
				$staticticket->subject = $obj->subject;

				print '<tr class="oddeven">';

				// Ref
				print '<td class="nowraponall">';
				print $staticticket->getNomUrl(1);
				print "</td>\n";

				// Creation date
				print '<td class="left">';
				print dol_print_date($db->jdate($obj->datec), 'dayhour');
				print "</td>";

				// Subject
				print '<td class="nowrap">';
				print $obj->subject;
				print "</td>\n";

				print '<td class="nowraponall right">';
				print $staticticket->getLibStatut(5);
				print "</td>";

				print "</tr>\n";
				$i++;
			}
			print "</table>";
		} else {
			print $langs->trans("SoonAvailable");
		}
		print '</div>';
	} else {
		dol_print_error($db);
	}
	print '</div></div>';


	print '</div></div>';
}

	print '
	    </div>
		</div>
    ';


// Code to manage reposition
print '<script>';
print "\n/* JS CODE TO ENABLE reposition management (does not work if a redirect is done after action of submission) */\n";
print '
	jQuery(document).ready(function() {
				/* If page_y set, we set scollbar with it */
				page_y=getParameterByName(\'page_y\', 0);				/* search in GET parameter */
				if (page_y == 0) page_y = jQuery("#page_y").text();		/* search in POST parameter that is filed at bottom of page */
				if (page_y > 0)
				{
					console.log("page_y found is "+page_y);
					$(\'html, body\').scrollTop(page_y);
				}

				/* Set handler to add page_y param on output (click on href links or submit button) */
				jQuery(".reposition").click(function() {
					var page_y = $(document).scrollTop();

					if (page_y > 0)
					{
						if (this.href)
						{
							console.log("We click on tag with .reposition class. this.ref was "+this.href);
							var hrefarray = this.href.split("#", 2);
							hrefarray[0]=hrefarray[0].replace(/&page_y=(\d+)/, \'\');		/* remove page_y param if already present */
							this.href=hrefarray[0]+\'&page_y=\'+page_y;
							console.log("We click on tag with .reposition class. this.ref is now "+this.href);
						}
						else
						{
							console.log("We click on tag with .reposition class but element is not an <a> html tag, so we try to update input form field with name=page_y with value "+page_y);
							jQuery("input[type=hidden][name=page_y]").val(page_y);
						}
					}
				});
	});
	</script>'."\n";

?>
<!-- END PHP TEMPLATE support.tpl.php -->
