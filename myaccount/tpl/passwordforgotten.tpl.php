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

// Need global variable to be defined by caller (like dol_loginfunction)
// $title
// $urllogo
// $focus_element
// $message

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 */

// Protection to avoid direct call of template
if (empty($conf) || ! is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}


header('Cache-Control: Public, must-revalidate');
header("Content-type: text/html; charset=".$conf->file->character_set_client);

if (GETPOST('dol_hide_topmenu')) {
	$conf->dol_hide_topmenu=1;
}
if (GETPOST('dol_hide_leftmenu')) {
	$conf->dol_hide_leftmenu=1;
}
if (GETPOST('dol_optimize_smallscreen')) {
	$conf->dol_optimize_smallscreen=1;
}
if (GETPOST('dol_no_mouse_hover')) {
	$conf->dol_no_mouse_hover=1;
}
if (GETPOST('dol_use_jmobile')) {
	$conf->dol_use_jmobile=1;
}

// If we force to use jmobile, then we reenable javascript
if (! empty($conf->dol_use_jmobile)) {
	$conf->use_javascript_ajax=1;
}

$php_self = dol_escape_htmltag($_SERVER['PHP_SELF']);
$php_self.= dol_escape_htmltag($_SERVER["QUERY_STRING"]) ? '?'.dol_escape_htmltag($_SERVER["QUERY_STRING"]) : '';
$php_self = preg_replace('/&hashreset=[0-9a-zA-Z]+/', '', $php_self);

$arrayofjs=array();
$titleofpage=$langs->trans('SendNewPassword');

$disablenofollow=1;
if (! preg_match('/'.constant('DOL_APPLICATION_TITLE').'/', $titleofpage)) {
	$disablenofollow=0;
}

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) {
	$favicon.='.png';
}
if (getDolGlobalString('MAIN_FAVICON_URL')) {
	$favicon=getDolGlobalString('MAIN_FAVICON_URL');
}
if (empty($head)) {
	$head = '';
}
if ($favicon) {
	$href = 'img/'.$favicon;
	if (preg_match('/^http/i', $favicon)) {
		$href = $favicon;
	}
	$head.='<link rel="icon" href="'.$href.'">'."\n";
}

if (empty($message)) {
	$message = '';
}

print top_htmlhead_sellyoursaas($head, $titleofpage, 0, 0, $arrayofjs, array(), 0, $disablenofollow);

?>
<!-- BEGIN PHP TEMPLATE PASSWORDFORGOTTEN.TPL.PHP -->

<body class="body bodylogin">

<style>
div.error { background: unset; }
</style>

<?php if (empty($conf->dol_use_jmobile)) { ?>
<script type="text/javascript">
$(document).ready(function () {
	// Set focus on correct field
	<?php if ($focus_element) { ?>$('#<?php echo $focus_element; ?>').focus(); <?php } ?>		// Warning to use this only on visible element
});
</script>
<?php }


// Define the $linklogo and $homepage

$sellyoursaasdomain = getDolGlobalString('SELLYOURSAAS_MAIN_DOMAIN_NAME');
$sellyoursaasname = getDolGlobalString('SELLYOURSAAS_NAME');

$domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
$constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;
if (getDolGlobalString($constforaltname)) {
	$sellyoursaasdomain = $domainname;
	$sellyoursaasname = getDolGlobalString($constforaltname);
	//var_dump($constforaltname.' '.$sellyoursaasdomain.' '.$sellyoursaasname);   // Example: 'SELLYOURSAAS_NAME_FORDOMAIN-glpi-network.cloud glpi-network.cloud GLPI-Network'
}

$linklogo = '';
$homepage = 'https://'.getDolGlobalString('SELLYOURSAAS_FORCE_MAIN_DOMAIN_NAME', $sellyoursaasdomain);
if (isset($partnerthirdparty) && $partnerthirdparty->id > 0) {     // Show logo of partner
	require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
	$ecmfile=new EcmFiles($db);
	$relativepath = $conf->societe->multidir_output[$conf->entity]."/".$partnerthirdparty->id."/logos/".$partnerthirdparty->logo;
	$relativepath = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'/', '', $relativepath);
	$relativepath = preg_replace('/[\\/]$/', '', $relativepath);
	$relativepath = preg_replace('/^[\\/]/', '', $relativepath);

	$ecmfile->fetch(0, '', $relativepath);
	if ($ecmfile->id > 0) {
		$linklogo = DOL_URL_ROOT.'/viewimage.php?modulepart=societe&hashp='.$ecmfile->share;
	}
	$homepage = '';
	if (! empty($partnerthirdparty->url)) {
		$url = preg_replace('#^https?://#', '', rtrim($partnerthirdparty->url, '/'));
		$homepage = 'https://'.$url;
	}
}
if (empty($linklogo)) {               // Show main logo of Cloud service
	// Show logo (search in order: small company logo, large company logo, theme logo, common logo)
	$linklogo = '';
	$constlogo = 'SELLYOURSAAS_LOGO';
	$constlogosmall = 'SELLYOURSAAS_LOGO_SMALL';

	$constlogoalt = 'SELLYOURSAAS_LOGO_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));
	$constlogosmallalt = 'SELLYOURSAAS_LOGO_SMALL_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));

	//var_dump($sellyoursaasdomain.' '.$constlogoalt.' '.getDolGlobalString($constlogoalt);exit;
	if (getDolGlobalString($constlogoalt)) {
		$constlogo=$constlogoalt;
		$constlogosmall=$constlogosmallalt;
	}

	if (empty($linklogo) && getDolGlobalString($constlogosmall)) {
		if (is_readable($conf->mycompany->dir_output.'/logos/thumbs/' . getDolGlobalString($constlogosmall))) {
			$linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/thumbs/' . getDolGlobalString($constlogosmall));
		}
	} elseif (empty($urllogo) && getDolGlobalString($constlogo)) {
		if (is_readable($conf->mycompany->dir_output.'/logos/' . getDolGlobalString($constlogo))) {
			$linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/' . getDolGlobalString($constlogo));
		}
	} elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png')) {
		$linklogo=DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png';
	} elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.png')) {
		$linklogo=DOL_URL_ROOT.'/theme/dolibarr_logo.png';
	} else {
		$linklogo=DOL_URL_ROOT.'/theme/login_logo.png';
	}
}

if (!GETPOSTINT('noheader')) {
	?>
<div class="page-header-top">
	<div class="container">
	  <div class="registerheader" style="display:flex; justify-content:space-between;">
		  <div class="valignmiddle" style="padding-right: 25px;">
		  <a href="<?php echo $homepage ?>"><img class="logoheader"  src="<?php echo $linklogo; ?>" id="logo" /></a><br>
		  </div>
		  <?php if (empty($mythirdparty->id)) {	?>
		  <div class="paddingtop20" style="float: right;">
			  <!--
			  <div class="btn-sm">
			  <span class="opacitymedium hideonsmartphone paddingright valignmiddle"><?php echo $langs->trans("AlreadyHaveAnAccount"); ?></span>
				<?php if (! empty($partner) || ! empty($partnerkey)) {
					print '<br class="hideonsmartphone">';
				} ?>
			  <a href="/" class="btn blue btn-sm btnalreadyanaccount valignmiddle"><?php echo $langs->trans("LoginAction"); ?></a>
			  </div>
			  -->
				<?php if (! empty($homepage)) { ?>
			  <div class="btn-sm home-page-url">
			  <span class="opacitymedium"><a class="blue btn-sm" style="padding-left: 0;" href="<?php echo $homepage ?>"><?php echo $langs->trans("BackToHomePage"); ?></a></span>
			  </div>
				<?php } ?>
		  </div>
				<?php
		  } ?>
	  </div>

	  <!-- BEGIN TOP NAVIGATION MENU -->
	  <div class="top-menu">
	  </div> <!-- END TOP NAVIGATION MENU -->

	</div>
</div>
	<?php
}
?>


<div class="login_center center">
<div class="login_vertical_align">


<form id="login" name="login" method="POST" action="<?php echo $php_self; ?>">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="buildnewpassword">


<div class="signup">

<div class="block medium">

		<header class="inverse">
		  <h1><?php echo dol_escape_htmltag($title); ?></h1>

<div class="center login_main_home divpasswordmessagedesc paddingtopbottom<?php echo getDolGlobalString('MAIN_LOGIN_BACKGROUND') ? ' backgroundsemitransparent' : ''; ?>">
<?php if ($mode == 'dolibarr' || ! $disabled) { ?>
	<span class="passwordmessagedesc opacitymedium">
	<?php
	if (empty($asknewpass) && !preg_match('/class="(ok|warning)"/', $message)) {
		echo str_replace('<br>', ' ', $langs->trans('SendNewPasswordDesc'));
	}
	?>
	</span>
<?php } else { ?>
	<div class="warning" align="center">
	<?php echo $langs->trans('AuthenticationDoesNotAllowSendNewPassword', $mode); ?>
	</div>
<?php } ?>
</div>

		</header>


<div class="login_table">

<div id="login_line1">

<div id="login_right">

<?php
if (! preg_match('/class="(ok|warning)"/', $message)) {
	?>
<table class="center">
<!-- Login -->
<tr>
<td class="nowrap valignmiddle" style="text-align: center;">
	<?php if (getDolGlobalString('MAIN_OPTIMIZEFORTEXTBROWSER')) { ?><label for="username" class="hidden"><?php echo $langs->trans("Login"); ?></label><?php } ?>
<span class="span-icon-user fa fa-user"></span>
	<?php
	if (empty($asknewpass)) {
		?>
	<input type="email" id="username" maxlength="255" placeholder="<?php echo $langs->trans("LoginEmail"); ?>" <?php echo $disabled; ?> name="username" class="flat input-field input-icon-user usernamepasswordforgotten" value="<?php echo dol_escape_htmltag($username); ?>" tabindex="1"  required autofocus="autofocus" />
	<br><br>
		<?php
	} else {
		print $langs->trans("PasswordChangeRequest", $username).'<br><br>';
		print '<input type="hidden" name="action" value="confirmpasswordreset">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="hidden" name="hashreset" value="'.$hashreset.'">';

		print '<input type="password" minlength="8" maxlength="128" id="newpassword1" placeholder="'.$langs->trans("NewPassword").'" name="newpassword1" class="flat input-icon-user" tabindex="2" required autofocus="autofocus" autocomplete="new-password" spellcheck="false" autocapitalize="off" />';
		print '<br><br>';

		print '<input type="password" minlength="8" maxlength="128" id="newpassword2" placeholder="'.$langs->trans("PasswordRetype").'" name="newpassword2" class="flat input-icon-user" tabindex="3" required autocomplete="new-password" spellcheck="false" autocapitalize="off" />';
		print '<br><br>';
	} ?>
</td>
</tr>

	<?php
	/*
	if (! empty($morelogincontent)) {
	if (is_array($morelogincontent)) {
		foreach ($morelogincontent as $format => $option)
		{
			if ($format == 'table') {
				echo '<!-- Option by hook -->';
				echo $option;
			}
		}
	}
	else {
		echo '<!-- Option by hook -->';
		echo $morelogincontent;
	}
	}
	*/

	if (empty($asknewpass) && 1) {
		// Add a variable param to force not using cache (jmobile)
		$php_self = preg_replace('/[&\?]time=(\d+)/', '', $php_self);	// Remove param time
		if (preg_match('/\?/', $php_self)) {
			$php_self.='&time='.dol_print_date(dol_now(), 'dayhourlog');
		} else {
			$php_self.='?time='.dol_print_date(dol_now(), 'dayhourlog');
		} ?>
	<!-- Captcha -->
	<tr>
	<td class="nowrap none center">

	<table class="login_table_securitycode centpercent"><tr>
	<td>
	<span class="span-icon-security">
	<input id="securitycode" placeholder="<?php echo $langs->trans("SecurityCode"); ?>" class="flat input-icon-security" type="text" size="12" maxlength="5" name="code" tabindex="3" />
	</span>
	</td>
	<td> &nbsp; <i class="fa fa-arrow-left"></i> <img src="antispamimage.php" style="border: 1px solid #ddd;" width="76" id="img_securitycode" /></td>
	<td><a href="<?php echo $php_self; ?>" tabindex="4"><?php echo $captcha_refresh; ?></a></td>
	</tr></table>

	</br>
	</td></tr>
		<?php
	} ?>
</table>
	<?php
} ?>
</div> <!-- end div login-right -->

</div> <!-- end div login-line1 -->


<br>
<div id="login_line2" style="clear: both">

<?php
// Show error message if defined
if ($message) {
	?>
	<div class="center">
	<?php
	if (preg_match('/class="ok"/', $message)) {
		print '<br><font class="ok">';
	} elseif (preg_match('/class="warning"/', $message)) {
		print '<br><font class="warning">';
	} else {
		print '<font class="error">';
	}
	print $message;
	print '</font>'; ?>
	</div><br><br>
	<?php
}
?>

<section id="formActions">
<div class="form-actions center">

<?php
if (empty($asknewpass) && ! preg_match('/class="(ok|warning)"/', $message)) {
	?>
<!-- Button SendNewPassword -->
<input type="submit" style="margin-top: 8px;" class="btn btn-primary" name="password" value="&nbsp; <?php echo $langs->trans('SendNewPasswordLink'); ?> &nbsp;" tabindex="4" />
	<?php
} elseif (! empty($asknewpass) && $asknewpass == 1) {
	?>
<!-- Button ConfirmReset -->
<input type="submit" style="margin-top: 8px;" class="btn btn-primary" name="confirmpasswordreset" value="&nbsp; <?php echo $langs->trans('ConfirmPasswordReset'); ?> &nbsp;" tabindex="4" />
	<?php
}
?>

<div align="center" style="margin-top: 8px; margin-bottom: 8px;"">
	<?php
	$moreparam='';
	if (!empty($conf->dol_hide_topmenu)) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_hide_topmenu='.$conf->dol_hide_topmenu;
	}
	if (!empty($conf->dol_hide_leftmenu)) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_hide_leftmenu='.$conf->dol_hide_leftmenu;
	}
	if (!empty($conf->dol_no_mouse_hover)) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_no_mouse_hover='.$conf->dol_no_mouse_hover;
	}
	if (!empty($conf->dol_use_jmobile)) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_use_jmobile='.$conf->dol_use_jmobile;
	}
	if (!empty($username)) {
		$moreparam .= (strpos($moreparam, '?')===false ? '?' : '&').'username='.urlencode($username);
	}

	print '<a class="alogin" href="'.$dol_url_root.'/index.php'.$moreparam.'">('.((empty($asknewpass) || $asknewpass == 2) ? $langs->trans('BackToLoginPage') : $langs->trans("Cancel")).')</a>';
	?>
</div>

</div>
</section>

</div> <!-- end login line 2 -->

</div> <!-- end login table -->

</div>
</div>

</form>

<br>
<br>

<!-- authentication mode = <?php echo $conf->file->main_authentication ?> -->
<!-- cookie name used for this session = <?php echo empty($session_name) ? '' : $session_name ?> -->
<!-- urlfrom in this session = <?php echo isset($_SESSION["urlfrom"]) ? $_SESSION["urlfrom"] : ''; ?> -->

<!-- Common footer is not used for login page, this is same than footer but inside login tpl -->

<?php


if (getDolGlobalString('MAIN_HTML_FOOTER')) {
	print getDolGlobalString('MAIN_HTML_FOOTER');
}

if (! empty($morelogincontent) && is_array($morelogincontent)) {
	foreach ($morelogincontent as $format => $option) {
		if ($format == 'js') {
			echo "\n".'<!-- Javascript by hook -->';
			echo $option."\n";
		}
	}
} elseif (! empty($moreloginextracontent)) {
	echo '<!-- Javascript by hook -->';
	echo $moreloginextracontent;
}

// Google Analytics (need Google module)
if (! empty($conf->google->enabled) && getDolGlobalString('MAIN_GOOGLE_AN_ID')) {
	if (empty($conf->dol_use_jmobile)) {
		print "\n";
		print '<script type="text/javascript">'."\n";
		print '  var _gaq = _gaq || [];'."\n";
		print '  _gaq.push([\'_setAccount\', \'' . getDolGlobalString('MAIN_GOOGLE_AN_ID').'\']);'."\n";
		print '  _gaq.push([\'_trackPageview\']);'."\n";
		print ''."\n";
		print '  (function() {'."\n";
		print '    var ga = document.createElement(\'script\'); ga.type = \'text/javascript\'; ga.async = true;'."\n";
		print '    ga.src = (\'https:\' == document.location.protocol ? \'https://ssl\' : \'http://www\') + \'.google-analytics.com/ga.js\';'."\n";
		print '    var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(ga, s);'."\n";
		print '  })();'."\n";
		print '</script>'."\n";
	}
}
?>

</div>
</div>	<!-- end of center -->

<?php
if (getDolGlobalString('SELLYOURSAAS_MYACCOUNT_FOOTER')) {
	print getDolGlobalString('SELLYOURSAAS_MYACCOUNT_FOOTER');
}
?>

</body>
</html>
<!-- END PHP TEMPLATE -->
