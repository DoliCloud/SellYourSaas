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
// Caller can also set 	$morelogincontent = array(['options']=>array('js'=>..., 'table'=>...);

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
if (! preg_match('/mainmenu=/', $php_self)) {
	$php_self.=(preg_match('/\?/', $php_self) ? '&' : '?').'mainmenu=home';
}

// Javascript code on logon page only to detect user tz, dst_observed, dst_first, dst_second
$arrayofjs=array(
	'/core/js/dst.js'.(empty($conf->dol_use_jmobile) ? '' : '?version='.urlencode(DOL_VERSION))
);
$titleofpage=$langs->trans('Login').' @ '.$titletruedolibarrversion;	// $titletruedolibarrversion is defined by dol_loginfunction in security2.lib.php. We must keep the @, some tools use it to know it is login page and find true dolibarr version.

$disablenofollow=1;
if (! preg_match('/'.constant('DOL_APPLICATION_TITLE').'/', $titleofpage)) {
	$disablenofollow=0;
}

$head = '';

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) {
	$favicon.='.png';
}
if (getDolGlobalString('MAIN_FAVICON_URL')) {
	$favicon=getDolGlobalString('MAIN_FAVICON_URL');
}
if ($favicon) {
	$href = 'img/'.$favicon;
	if (preg_match('/^http/i', $favicon)) {
		$href = $favicon;
	}
	$head .= '<link rel="icon" href="'.$href.'">'."\n";
}

print top_htmlhead_sellyoursaas($head, $titleofpage, 0, 0, $arrayofjs, array(), 0, $disablenofollow);

// Disable captcha
$_SESSION['dol_bypass_antispam'] = 1;

?>
<!-- BEGIN PHP TEMPLATE LOGIN.TPL.PHP -->

<body class="body bodylogin">

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
<input type="hidden" name="action" value="login">
<input type="hidden" name="actionlogin" value="login">
<input type="hidden" name="loginfunction" value="loginfunction">
<!-- Add fields to send local user information -->
<input type="hidden" name="tz" id="tz" value="">
<input type="hidden" name="tz_string" id="tz_string" value="">
<input type="hidden" name="dst_observed" id="dst_observed" value="">
<input type="hidden" name="dst_first" id="dst_first" value="">
<input type="hidden" name="dst_second" id="dst_second" value="">
<!-- Add fields to send other param on browsing environment -->
<input type="hidden" name="screenwidth" id="screenwidth" value="">
<input type="hidden" name="screenheight" id="screenheight" value="">
<input type="hidden" name="dol_hide_topmenu" id="dol_hide_topmenu" value="<?php echo $dol_hide_topmenu; ?>">
<input type="hidden" name="dol_hide_leftmenu" id="dol_hide_leftmenu" value="<?php echo $dol_hide_leftmenu; ?>">
<input type="hidden" name="dol_optimize_smallscreen" id="dol_optimize_smallscreen" value="<?php echo $dol_optimize_smallscreen; ?>">
<input type="hidden" name="dol_no_mouse_hover" id="dol_no_mouse_hover" value="<?php echo $dol_no_mouse_hover; ?>">
<input type="hidden" name="dol_use_jmobile" id="dol_use_jmobile" value="<?php echo $dol_use_jmobile; ?>">


<div class="signup">

<?php
// Show global announce
if (getDolGlobalString('SELLYOURSAAS_ANNOUNCE_ON') && getDolGlobalString('SELLYOURSAAS_ANNOUNCE')) {
	$sql = "SELECT tms from ".MAIN_DB_PREFIX."const where name = 'SELLYOURSAAS_ANNOUNCE'";
	$resql=$db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$datemessage = $db->jdate($obj->tms);

		print '
    		<div class="containermessage"><br><div class="note note-warning">';
		print '<b>'.dol_print_date($datemessage, 'dayhour').'</b> : ';
		$reg=array();
		if (preg_match('/^\((.*)\)$/', getDolGlobalString('SELLYOURSAAS_ANNOUNCE'), $reg)) {
			$texttoshow = $langs->trans($reg[1]);
		} else {
			$texttoshow = getDolGlobalString('SELLYOURSAAS_ANNOUNCE');
		}
		print '<h5 class="block">'.$texttoshow.'</h5>
    		</div></div>
    	';
	} else {
		dol_print_error($db);
	}
}
?>

<div class="block medium">

		<header class="inverse">
		  <h1><?php echo dol_escape_htmltag($title); ?></h1>
		  <span class="opacitymedium" style="font-size: 0.85em; margin-top: 4px; line-height: 1;"><?php echo $langs->trans("MyAcountDesc", $homepage, $sellyoursaasname); ?></span>
		</header>


<div class="login_table">

<div id="login_line1">

<div id="login_right">

<table class="left centpercent" title="<?php echo $langs->trans("EnterLoginDetail"); ?>">
<!-- Login -->
<tr>
<td class="nowrap center valignmiddle">
<?php
if (getDolGlobalString('MAIN_OPTIMIZEFORTEXTBROWSER')) {
	?><label for="username" class="hidden"><?php echo $langs->trans("Login"); ?></label><?php
}
if (GETPOST('usernamebis', 'alpha')) {
	$login=GETPOST('usernamebis', 'alpha');
}
?>
<span class="span-icon-user fa fa-user">
<input type="email" id="username" maxlength="255" placeholder="<?php echo $langs->trans("LoginEmail"); ?>" name="username" class="flat input-field input-icon-user" value="<?php echo dol_escape_htmltag($login); ?>" tabindex="1" autofocus="autofocus" />
</span>
</td>
</tr>
<!-- Password -->
<tr>
<td class="nowrap center valignmiddle">
<br>
<?php if (getDolGlobalString('MAIN_OPTIMIZEFORTEXTBROWSER')) { ?><label for="password" class="hidden"><?php echo $langs->trans("Password"); ?></label><?php } ?>
<span class="span-icon-password fa fa-lock">
<input type="password" id="password" maxlength="128" placeholder="<?php echo $langs->trans("Password"); ?>" name="password" class="flat input-field input-icon-password" value="<?php echo dol_escape_htmltag($password); ?>" tabindex="2" autocomplete="<?php echo getDolGlobalString('MAIN_LOGIN_ENABLE_PASSWORD_AUTOCOMPLETE') ? 'on' : 'off'; ?>" />
</span>
<br>
</td></tr>
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

if (0) {
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
	<br>
	<table class="login_table_securitycode centpercent"><tr>
	<td>
	<span class="span-icon-security">
	<input id="securitycode" placeholder="<?php echo $langs->trans("SecurityCode"); ?>" class="flat input-icon-security" type="text" size="12" maxlength="5" name="code" tabindex="3" />
	</span>
	</td>
	<td><img src="<?php echo DOL_URL_ROOT ?>/core/antispamimage.php" border="0" width="80" height="32" id="img_securitycode" /></td>
	<td><a href="<?php echo $php_self; ?>" tabindex="4"><?php echo $captcha_refresh; ?></a></td>
	</tr></table>

	</td></tr>
	<?php
} ?>

<tr><td><br><br></td></tr>
</table>

</div> <!-- end div login-right -->

</div> <!-- end div login-line1 -->


<div id="login_line2" style="clear: both">

<?php
// Show error message if defined
if (! empty($_SESSION['dol_loginmesg'])) {
	?>
	<div class="center"><font class="error">
	<?php echo $_SESSION['dol_loginmesg']; ?>
	</font></div><br>
	<?php
}
?>

<!-- Button Connection -->
<section id="formActions">
<div class="form-actions center">
<input type="submit" class="btn btn-primary" value="&nbsp; <?php echo $langs->trans('LogIn'); ?> &nbsp;" tabindex="5" />

<?php
if ($forgetpasslink || $helpcenterlink) {
	$moreparam='';
	if ($dol_hide_topmenu) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_hide_topmenu='.$dol_hide_topmenu;
	}
	if ($dol_hide_leftmenu) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_hide_leftmenu='.$dol_hide_leftmenu;
	}
	if ($dol_no_mouse_hover) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_no_mouse_hover='.$dol_no_mouse_hover;
	}
	if ($dol_use_jmobile) {
		$moreparam.=(strpos($moreparam, '?')===false ? '?' : '&').'dol_use_jmobile='.$dol_use_jmobile;
	}

	echo '<br>';
	echo '<div class="center" style="margin-top: 8px;">';
	echo '<a class="alogin" href="./passwordforgotten.php'.$moreparam.'">';
	echo $langs->trans('PasswordForgotten');
	echo '</a>';
	echo ' - ';
	echo '<a class="alogin" href="register.php">';
	echo $langs->trans('NoAccount');
	echo '</a>';
	echo '</div>';
}

?>
</div>
</section>

</div> <!-- end login line 2 -->

</div> <!-- end login table -->

</div>
</div>

</form>


<!-- authentication mode = <?php echo $main_authentication ?> -->
<!-- cookie name used for this session = <?php echo $session_name ?> -->
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
