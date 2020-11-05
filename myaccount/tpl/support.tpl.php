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
if (empty($conf) || ! is_object($conf))
{
	print "Error, template page can't be called as URL";
	exit;
}

?>
<!-- BEGIN PHP TEMPLATE support.tpl.php -->
<?php

    // Print warning to read FAQ before
    print '<!-- Message to read FAQ and get status -->'."\n";
    if ($urlfaq || $urlstatus)
    {
        print '<div class="alert alert-success note note-success">'."\n";
        if ($urlfaq)
        {
            print '<h4 class="block">'.$langs->trans("PleaseReadFAQFirst", $urlfaq).'</h4><br>'."\n";
        }
        if ($urlstatus)
        {
            print $langs->trans("CurrentServiceStatus", $urlstatus).'<br>'."\n";
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

    $sellyoursaassupporturl = $conf->global->SELLYOURSAAS_SUPPORT_URL;
    if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
        && $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME)
    {
        $newnamekey = 'SELLYOURSAAS_SUPPORT_URL-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
        if (! empty($conf->global->$newnamekey)) $sellyoursaassupporturl = $conf->global->$newnamekey;
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

                        print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
                        print '<input type="hidden" name="token" value="'.newToken().'">';
                        print '<input type="hidden" name="mode" value="support">';
                        print '<input type="hidden" name="action" value="presend">';

                        print $langs->trans("SelectYourSupportChannel").'<br>';

                        print '<select id="supportchannel" name="supportchannel" class="maxwidth500 minwidth500" style="width: auto">';
                        print '<option value=""></option>';
                        if (count($listofcontractid) == 0)
                        {
                            // Should not happen
                        }
                        else
                        {
                            $atleastonehigh=0;
                            $atleastonefound=0;

                            foreach ($listofcontractid as $id => $contract)
                            {
                                $planref = $contract->array_options['options_plan'];
                                $statuslabel = $contract->array_options['options_deployment_status'];
                                $instancename = preg_replace('/\..*$/', '', $contract->ref_customer);

                                $dbprefix = $contract->array_options['options_db_prefix'];
                                if (empty($dbprefix)) $dbprefix = 'llx_';

                                if ($statuslabel == 'undeployed')
                                {
                                    continue;
                                }

                                // Get info about PLAN of Contract
                                $planlabel = $planref;		// By default but we will take ref and label of service of type 'app' later

                                $planid = 0;
                                $freeperioddays = 0;
                                $directaccess = 0;

                                $tmpproduct = new Product($db);
                                foreach($contract->lines as $keyline => $line)
                                {
                                    if ($line->statut == 5 && $contract->array_options['options_deployment_status'] != 'undeployed')
                                    {
                                        $statuslabel = 'suspended';
                                    }

                                    if ($line->fk_product > 0)
                                    {
                                        $tmpproduct->fetch($line->fk_product);
                                        if ($tmpproduct->array_options['options_app_or_option'] == 'app')
                                        {
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
                                if ($statuslabel == 'processing') $color = 'orange';
                                if ($statuslabel == 'suspended') $color = 'orange';
                                if ($statuslabel == 'undeployed') $color = 'grey';

                                if ($tmpproduct->array_options['options_typesupport'] != 'none')
                                {
                                    if (! $ispaid)
                                    {
                                        $priority = 'low';
                                        $prioritylabel = $langs->trans("Trial").'-'.$langs->trans("Low");
                                    }
                                    else
                                    {
                                        if ($ispaid)
                                        {
                                            if ($tmpproduct->array_options['options_typesupport'] == 'premium')
                                            {
                                                $priority = 'high';
                                                $prioritylabel = $langs->trans("High");
                                                $atleastonehigh++;
                                            }
                                            else
                                            {
                                                $priority = 'medium';
                                                $prioritylabel = $langs->trans("Medium");
                                            }
                                        }
                                    }
                                    $optionid = $priority.'_'.$id;
                                    print '<option value="'.$optionid.'"'.(GETPOST('supportchannel','alpha') == $optionid ? ' selected="selected"':'').'">';
                                    //print $langs->trans("Instance").' '.$contract->ref_customer.' - ';
                                    print $tmpproduct->label.' - '.$contract->ref_customer.' ';
                                    //print $tmpproduct->array_options['options_typesupport'];
                                    //print $tmpproduct->array_options['options_typesupport'];
                                    print ' ('.$langs->trans("Priority").': ';
                                    print $prioritylabel;
                                    print ')';
                                    print '</option>';
                                    //print ajax_combobox('supportchannel');

                                    $atleastonefound++;
                                }
                            }
                        }

                    if (! $atleastonefound) $labelother = $langs->trans("Miscellaneous");
                    else $labelother = $langs->trans("Other");

                    print '<option value="low_other"'.(GETPOST('supportchannel','alpha') == 'low_other' ? ' selected="selected"':'').'>'.$labelother.' ('.$langs->trans("Priority").': '.$langs->trans("Low").')</option>';
                    if (empty($atleastonehigh))
                    {
                        print '<option value="high_premium" disabled="disabled">'.$langs->trans("PremiumSupport").' ('.$langs->trans("Priority").': '.$langs->trans("High").') - '.$langs->trans("NoPremiumPlan").'</option>';
                    }
                    print '</select>';

                    print '&nbsp;
                						<input type="submit" name="submit" value="'.$langs->trans("Choose").'" class="btn green-haze btn-circle">
                						';

                    print '</form>';

                    if ($action == 'presend' && GETPOST('supportchannel','alpha'))
                    {
                        print '<br><br>';
                        print '<form class="inline-block centpercent" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
                        print '<input type="hidden" name="token" value="'.newToken().'">';
                        print '<input type="hidden" name="mode" value="support">';
                        print '<input type="hidden" name="contractid" value="'.$id.'">';
                        print '<input type="hidden" name="action" value="send">';
                        print '<input type="hidden" name="supportchannel" value="'.GETPOST('supportchannel','alpha').'">';

                        $sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL;
                        if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
                            && $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME)
                        {
                            $newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
                            if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
                        }

                        if (! empty($conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM) && preg_match('/high/', GETPOST('supportchannel','alpha')))
                        {
                            // We must use the prioritary email
                            $sellyoursaasemail = $conf->global->SELLYOURSAAS_MAIN_EMAIL_PREMIUM;
                            if (! empty($mythirdpartyaccount->array_options['options_domain_registration_page'])
                                && $mythirdpartyaccount->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME)
                            {
                                $newnamekey = 'SELLYOURSAAS_MAIN_EMAIL_PREMIUM_FORDOMAIN-'.$mythirdpartyaccount->array_options['options_domain_registration_page'];
                                if (! empty($conf->global->$newnamekey)) $sellyoursaasemail = $conf->global->$newnamekey;
                            }
                        }

                        $subject = (GETPOST('subject','none')?GETPOST('subject','none'):'');

                        print '<input type="hidden" name="to" value="'.$sellyoursaasemail.'">';
                        print $langs->trans("MailFrom").' : <input type="text" required name="from" value="'.(GETPOST('from','none')?GETPOST('from','none'):$mythirdpartyaccount->email).'"><br><br>';
                        print $langs->trans("MailTopic").' : <input type="text" required class="minwidth500" name="subject" value="'.$subject.'"><br><br>';
                        print '<textarea rows="6" required placeholder="'.$langs->trans("YourText").'" style="border: 1px solid #888" name="content" class="centpercent">'.GETPOST('content','none').'</textarea><br><br>';

                        print '<center><input type="submit" name="submit" value="'.$langs->trans("SendMail").'" class="btn green-haze btn-circle">';
                        print ' ';
                        print '<input type="submit" name="cancel" formnovalidate value="'.$langs->trans("Cancel").'" class="btn green-haze btn-circle">';
                        print '</center>';

                        print '</form>';
                    }

                    print ' 	</div></div>

					</div> <!-- END PORTLET -->



			      </div> <!-- END COL -->


			    </div> <!-- END ROW -->
			';
    }

    if (empty($sellyoursaassupporturl) && $action != 'presend')
    {
        print '
    				<!-- BEGIN PAGE HEADER-->
    				<!-- BEGIN PAGE HEAD -->
    				<div class="page-head">
    				<!-- BEGIN PAGE TITLE -->
    				<div class="page-title">
    				<h1>'.$langs->trans("Tickets").' </h1>
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
    					<div class="row" id="contractid'.$contract->id.'" data-contractref="'.$contract->ref.'">
    					<div class="col-md-12">';


        print $langs->trans("SoonAvailable");

        print '</div></div>';


        print '</div></div>';
    }

    print '
	    </div>
		</div>
	';

?>
<!-- END PHP TEMPLATE support.tpl.php -->
