<?php
if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER', '1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC', '1');
if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN', '1');
if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK', '1');			// Do not check anti CSRF attack test
if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK', '1');			// Do not check style html tag into posted data
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');		// Do not check anti POST attack test
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');			// If there is no need to load and show top and left menu
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');			// If we don't need to load the html.form.class.php
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');
if (! defined("NOLOGIN"))        define("NOLOGIN", '1');				    // If this page is public (can be called outside logged session)
if (! defined('NOIPCHECK'))      define('NOIPCHECK', '1');				// Do not check IP defined into conf $dolibarr_main_restrict_ip
if (! defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');


include './mainmyaccount.inc.php';

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");


// Define css type
top_httphead('text/css');
// Important: Following code is to avoid page request by browser and PHP CPU at each Dolibarr page access.
if (empty($dolibarr_nocache)) header('Cache-Control: max-age=3600, public, must-revalidate');
else header('Cache-Control: no-cache');


print "/* CSS content (all pages) */

body.bodywebsite { margin: 0; }


.centpercent { width: 100%; }

.floatleft { float: left; }

.floatright { float: right; }

.navbar-brand { margin-left: 15px; }

.passwordmessagedesc {
	font-size: 0.85em;
}

.opacitymedium { opacity: 0.5; }
.opacityhigh { opacity: 0.2; }
/* input[type='text'],input[type='password'] { width: 250px; } */

input[name='firstName'], input[name='lastName'] { width: 200px; }

#securitycode { width: 150px; }

input#urlforpartner {
    border: 1px solid #ccc;
    border-radius: 3px;
}

label {
	margin-bottom: .1rem;
	margin-top: .4rem;
}

input#discountcode {
    text-transform: uppercase;
}
span.discountcodeok {
	color: #080;
}
span.discountcodeko {
	color: #800;
}

.badge-status1 {
    background-color: #bc9526 !important;
}
.badge-status4 {
    background-color: #277d1e !important;
}
.badge-status8 {
    background-color: #be3013 !important;
}

.marginrightonlyimp { margin-right: 5px !important; }

label,input,button,select,textarea {font-weight: normal;line-height: 20px;}
input,button,select,textarea {font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;}label {display: block;margin-bottom: 5px;}

.usernamepasswordforgotten {
	width: 290px;
}

select,textarea,input[type='text'],input[type='password'],input[type='datetime'],input[type='datetime-local'],input[type='date'],input[type='month'],input[type='time'],input[type='week'],input[type='number'],input[type='email'],input[type='url'],input[type='search'],input[type='tel'],input[type='color'],.uneditable-input
{
display: inline-block;padding: 4px 6px;
line-height: 20px;color: #555555;
}

.pictofixedwidth {
    width: 22px;
}

input,textarea,.uneditable-input { width: 256px;}
textarea {height: auto;}
textarea,input[type='text'],input[type='password'],input[type='datetime'],input[type='datetime-local'],input[type='date'],input[type='month'],input[type='time'],input[type='week'],input[type='number'],input[type='email'],input[type='url'],input[type='search'],input[type='tel'],input[type='color'],.uneditable-input
{
background-color: #ffffff;
border-bottom: 1px solid #cccccc;
border-top: none;border-left: none;border-right: none;
-webkit-transition: border linear .2s, box-shadow linear .2s;-moz-transition: border linear .2s, box-shadow linear .2s;-o-transition: border linear .2s, box-shadow linear .2s;transition: border linear .2s, box-shadow linear .2s;
}

textarea:focus,input[type='text']:focus,input[type='password']:focus,input[type='datetime']:focus,input[type='datetime-local']:focus,input[type='date']:focus,input[type='month']:focus,input[type='time']:focus,input[type='week']:focus,input[type='number']:focus,input[type='email']:focus,input[type='url']:focus,input[type='search']:focus,input[type='tel']:focus,input[type='color']:focus,.uneditable-input:focus {border-color: rgba(82, 168, 236, 0.8);outline: 0;outline: thin dotted \9;}

input[type='radio'],input[type='checkbox'] {margin: 4px 0 0; margin-top: 0; line-height: normal;cursor: pointer;}input[type='file'],input[type='image'],input[type='submit'],input[type='reset'],input[type='button'],input[type='radio'],input[type='checkbox'] {width: auto;}

select {width: 220px;border: 1px solid #cccccc;background-color: #ffffff;}select[multiple],select[size] {height: auto;}

select:focus,input[type='file']:focus,input[type='radio']:focus,input[type='checkbox']:focus {outline: thin dotted #333;outline: 5px auto -webkit-focus-ring-color;}

.uneditable-input,.uneditable-textarea {color: #999999;background-color: #fcfcfc;border-color: #cccccc;-webkit-box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.025);-moz-box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.025);box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.025);cursor: not-allowed;}.uneditable-input {overflow: hidden;white-space: nowrap;}.uneditable-textarea {width: auto;height: auto;}input:-moz-placeholder,textarea:-moz-placeholder {color: #999999;}input:-ms-input-placeholder,textarea:-ms-input-placeholder {color: #999999;}input::-webkit-input-placeholder,textarea::-webkit-input-placeholder {color: #999999;}.radio,.checkbox {min-height: 18px;padding-left: 18px;}.radio input[type='radio'],.checkbox input[type='checkbox'] {float: left;margin-left: -18px;}.controls > .radio:first-child,.controls > .checkbox:first-child {padding-top: 5px;}.radio.inline,.checkbox.inline {display: inline-block;padding-top: 5px;margin-bottom: 0;vertical-align: middle;}.radio.inline + .radio.inline,.checkbox.inline + .checkbox.inline {margin-left: 10px;}.input-mini {width: 60px;}.input-small {width: 90px;}.input-medium {width: 150px;}.input-large {width: 210px;}.input-xlarge {width: 270px;}.input-xxlarge {width: 530px;}
input[class*='span'],select[class*='span'],textarea[class*='span'],.uneditable-input[class*='span'],.row-fluid input[class*='span'],.row-fluid select[class*='span'],.row-fluid textarea[class*='span'],.row-fluid .uneditable-input[class*='span'] {float: none;margin-left: 0;}.input-append input[class*='span'],.input-append .uneditable-input[class*='span'],.input-prepend input[class*='span'],.input-prepend .uneditable-input[class*='span'],.row-fluid input[class*='span'],.row-fluid select[class*='span'],.row-fluid textarea[class*='span'],.row-fluid .uneditable-input[class*='span'],.row-fluid .input-prepend [class*='span'],.row-fluid .input-append [class*='span'] {display: inline-block;}input,textarea,.uneditable-input {margin-left: 0;}.controls-row [class*='span'] + [class*='span'] {margin-left: 20px;}input.span12, textarea.span12, .uneditable-input.span12 {width: 926px;}input.span11, textarea.span11, .uneditable-input.span11 {width: 846px;}input.span10, textarea.span10, .uneditable-input.span10 {width: 766px;}input.span9, textarea.span9, .uneditable-input.span9 {width: 686px;}input.span8, textarea.span8, .uneditable-input.span8 {width: 606px;}input.span7, textarea.span7, .uneditable-input.span7 {width: 526px;}input.span6, textarea.span6, .uneditable-input.span6 {width: 446px;}input.span5, textarea.span5, .uneditable-input.span5 {width: 366px;}input.span4, textarea.span4, .uneditable-input.span4 {width: 286px;}input.span3, textarea.span3, .uneditable-input.span3 {width: 206px;}input.span2, textarea.span2, .uneditable-input.span2 {width: 126px;}input.span1, textarea.span1, .uneditable-input.span1 {width: 46px;}.controls-row {*zoom: 1;}
.controls-row:before,.controls-row:after {display: table;content: '';line-height: 0;}.controls-row:after {clear: both;}.controls-row [class*='span'] {float: left;}input[disabled],select[disabled],textarea[disabled],input[readonly],select[readonly],textarea[readonly] {cursor: not-allowed;background-color: #eeeeee;}input[type='radio'][disabled],input[type='checkbox'][disabled],input[type='radio'][readonly],input[type='checkbox'][readonly] {background-color: transparent;}.control-group.warning > label,.control-group.warning .help-block,.control-group.warning .help-inline {color: #c09853;}.control-group.warning .checkbox,.control-group.warning .radio,.control-group.warning input,.control-group.warning select,.control-group.warning textarea {color: #c09853;}.control-group.warning input,.control-group.warning select,.control-group.warning textarea {border-color: #c09853;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);}.control-group.warning input:focus,.control-group.warning select:focus,.control-group.warning textarea:focus {border-color: #a47e3c;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #dbc59e;-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #dbc59e;box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #dbc59e;}.control-group.warning .input-prepend .add-on,.control-group.warning .input-append .add-on {color: #c09853;background-color: #fcf8e3;border-color: #c09853;}.control-group.error > label,.control-group.error .help-block,.control-group.error .help-inline {color: #b94a48;}.control-group.error .checkbox,.control-group.error .radio,.control-group.error input,.control-group.error select,.control-group.error textarea {color: #b94a48;}.control-group.error input,.control-group.error select,.control-group.error textarea {border-color: #b94a48;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);}.control-group.error input:focus,.control-group.error select:focus,.control-group.error textarea:focus {border-color: #953b39;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #d59392;-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #d59392;box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #d59392;}.control-group.error .input-prepend .add-on,.control-group.error .input-append .add-on {color: #b94a48;background-color: #f2dede;border-color: #b94a48;}.control-group.success > label,.control-group.success .help-block,.control-group.success .help-inline {color: #468847;}.control-group.success .checkbox,.control-group.success .radio,.control-group.success input,.control-group.success select,.control-group.success textarea {color: #468847;}.control-group.success input,.control-group.success select,.control-group.success textarea {border-color: #468847;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);}.control-group.success input:focus,.control-group.success select:focus,.control-group.success textarea:focus {border-color: #356635;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #7aba7b;-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #7aba7b;box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #7aba7b;}.control-group.success .input-prepend .add-on,.control-group.success .input-append .add-on {color: #468847;background-color: #dff0d8;border-color: #468847;}.control-group.info > label,.control-group.info .help-block,.control-group.info .help-inline {color: #3a87ad;}.control-group.info .checkbox,.control-group.info .radio,.control-group.info input,.control-group.info select,.control-group.info textarea {color: #3a87ad;}.control-group.info input,.control-group.info select,.control-group.info textarea {border-color: #3a87ad;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);}.control-group.info input:focus,.control-group.info select:focus,.control-group.info textarea:focus {border-color: #2d6987;-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #7ab5d3;-moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #7ab5d3;box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px #7ab5d3;}.control-group.info .input-prepend .add-on,.control-group.info .input-append .add-on {color: #3a87ad;background-color: #d9edf7;border-color: #3a87ad;}

input:focus:required:invalid,textarea:focus:required:invalid,select:focus:required:invalid
{
color: #b94a48;border-bottom-color: #ee5f5b;
}

input:focus:required:invalid:focus,textarea:focus:required:invalid:focus,select:focus:required:invalid:focus
{
border-bottom-color: rgba(82, 168, 236, 0.8);
}

.form-actions {
padding: 19px 20px 20px;margin-top: 20px;margin-bottom: 20px;background-color: #f5f5f5;
border: 1px solid #e5e5e5;*zoom: 1;
}

.form-actions:before,.form-actions:after {display: table;content: '';line-height: 0;}.form-actions:after {clear: both;}.help-block,.help-inline {color: #595959;}.help-block {display: block;margin-bottom: 10px;}.help-inline {display: inline-block;*display: inline;*zoom: 1;vertical-align: middle;padding-left: 5px;}.input-append,.input-prepend {margin-bottom: 5px;font-size: 0;white-space: nowrap;}.input-append input,.input-prepend input,.input-append select,.input-prepend select,.input-append .uneditable-input,.input-prepend .uneditable-input {position: relative;margin-bottom: 0;*margin-left: 0;font-size: 14px;vertical-align: top;-webkit-border-radius: 0 3px 3px 0;-moz-border-radius: 0 3px 3px 0;border-radius: 0 3px 3px 0;}.input-append input:focus,.input-prepend input:focus,.input-append select:focus,.input-prepend select:focus,.input-append .uneditable-input:focus,.input-prepend .uneditable-input:focus {z-index: 2;}.input-append .add-on,.input-prepend .add-on {display: inline-block;width: auto;height: 20px;min-width: 16px;padding: 4px 5px;font-size: 14px;font-weight: normal;line-height: 20px;text-align: center;text-shadow: 0 1px 0 #ffffff;background-color: #eeeeee;border: 1px solid #ccc;}.input-append .add-on,.input-prepend .add-on,.input-append .btn,.input-prepend .btn {vertical-align: top;-webkit-border-radius: 0;-moz-border-radius: 0;border-radius: 0;}.input-append .active,.input-prepend .active {background-color: #a9dba9;border-color: #46a546;}.input-prepend .add-on,.input-prepend .btn {margin-right: -1px;}.input-prepend .add-on:first-child,.input-prepend .btn:first-child {-webkit-border-radius: 3px 0 0 3px;-moz-border-radius: 3px 0 0 3px;border-radius: 3px 0 0 3px;}.input-append input,.input-append select,.input-append .uneditable-input {-webkit-border-radius: 3px 0 0 3px;-moz-border-radius: 3px 0 0 3px;border-radius: 3px 0 0 3px;}.input-append .add-on,.input-append .btn {margin-left: -1px;}.input-append .add-on:last-child,.input-append .btn:last-child {-webkit-border-radius: 0 3px 3px 0;-moz-border-radius: 0 3px 3px 0;border-radius: 0 3px 3px 0;}.input-prepend.input-append input,.input-prepend.input-append select,.input-prepend.input-append .uneditable-input {-webkit-border-radius: 0;-moz-border-radius: 0;border-radius: 0;}.input-prepend.input-append .add-on:first-child,.input-prepend.input-append .btn:first-child {margin-right: -1px;-webkit-border-radius: 3px 0 0 3px;-moz-border-radius: 3px 0 0 3px;border-radius: 3px 0 0 3px;}.input-prepend.input-append .add-on:last-child,.input-prepend.input-append .btn:last-child {margin-left: -1px;-webkit-border-radius: 0 3px 3px 0;-moz-border-radius: 0 3px 3px 0;border-radius: 0 3px 3px 0;}input.search-query {padding-right: 14px;padding-right: 4px \9;padding-left: 14px;padding-left: 4px \9;margin-bottom: 0;-webkit-border-radius: 15px;-moz-border-radius: 15px;border-radius: 15px;}

.termandcondition { opacity: 0.7; }

.clearfix {*zoom: 1;}.clearfix:before,.clearfix:after {display: table;content: '';line-height: 0;}.clearfix:after {clear: both;}.hide-text {font: 0/0 a;color: transparent;text-shadow: none;background-color: transparent;border: 0;}.input-block-level {display: block;width: 100%;min-height: 30px;-webkit-box-sizing: border-box;-moz-box-sizing: border-box;box-sizing: border-box;}article,aside,details,figcaption,figure,footer,header,hgroup,nav,section {display: block;}audio,canvas,video {display: inline-block;*display: inline;*zoom: 1;}audio:not([controls]) {display: none;}html {font-size: 100%;-webkit-text-size-adjust: 100%;-ms-text-size-adjust: 100%;}a:focus {outline: thin dotted #333;outline: 5px auto -webkit-focus-ring-color;outline-offset: -2px;}a:hover,a:active {outline: 0;}sub,sup {position: relative;font-size: 75%;line-height: 0;vertical-align: baseline;}sup {top: -0.5em;}sub {bottom: -0.25em;}img {max-width: 100%;vertical-align: middle;border: 0;-ms-interpolation-mode: bicubic;}#map_canvas img {max-width: none;}button,input,select,textarea {margin: 0;font-size: 100%;vertical-align: middle;}button,input {*overflow: visible;line-height: normal;}button::-moz-focus-inner,input::-moz-focus-inner {padding: 0;border: 0;}button,input[type='button'],input[type='reset'],input[type='submit'] {cursor: pointer;-webkit-appearance: button;}input[type='search'] {-webkit-box-sizing: content-box;-moz-box-sizing: content-box;box-sizing: content-box;-webkit-appearance: textfield;}input[type='search']::-webkit-search-decoration,input[type='search']::-webkit-search-cancel-button {-webkit-appearance: none;}textarea {overflow: auto;vertical-align: top;}body {margin: 0;font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;font-size: 14px;line-height: 20px;color: #333333;background-color: #ffffff;}a {color: #0088cc;text-decoration: none;}a:hover {color: #005580;text-decoration: underline;}.img-rounded {-webkit-border-radius: 6px;-moz-border-radius: 6px;border-radius: 6px;}.img-polaroid {padding: 4px;background-color: #fff;border: 1px solid #ccc;border: 1px solid rgba(0, 0, 0, 0.2);-webkit-box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);-moz-box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);}.img-circle {-webkit-border-radius: 500px;-moz-border-radius: 500px;border-radius: 500px;}.row {margin-left: -20px;*zoom: 1;}
.row:before,.row:after {display: table;content: '';line-height: 0;}.row:after {clear: both;}
.span12 {width: 940px;}.span11 {width: 860px;}.span10 {width: 780px;}.span9 {width: 700px;}.span8 {width: 620px;}.span7 {width: 540px;}.span6 {width: 460px;}.span5 {width: 380px;}.span4 {width: 300px;}.span3 {width: 220px;}.span2 {width: 140px;}.span1 {width: 60px;}.offset12 {margin-left: 980px;}.offset11 {margin-left: 900px;}.offset10 {margin-left: 820px;}.offset9 {margin-left: 740px;}.offset8 {margin-left: 660px;}.offset7 {margin-left: 580px;}.offset6 {margin-left: 500px;}.offset5 {margin-left: 420px;}.offset4 {margin-left: 340px;}.offset3 {margin-left: 260px;}.offset2 {margin-left: 180px;}.offset1 {margin-left: 100px;}.row-fluid {width: 100%;*zoom: 1;}
.row-fluid:before,.row-fluid:after {display: table;content: '';line-height: 0;}.row-fluid:after {clear: both;}.row-fluid [class*='span'] {display: block;width: 100%;min-height: 30px;-webkit-box-sizing: border-box;-moz-box-sizing: border-box;box-sizing: border-box;float: left;margin-left: 2.127659574468085%;*margin-left: 2.074468085106383%;}.row-fluid [class*='span']:first-child {margin-left: 0;}.row-fluid .span12 {width: 100%;*width: 99.94680851063829%;}.row-fluid .span11 {width: 91.48936170212765%;*width: 91.43617021276594%;}.row-fluid .span10 {width: 82.97872340425532%;*width: 82.92553191489361%;}.row-fluid .span9 {width: 74.46808510638297%;*width: 74.41489361702126%;}.row-fluid .span8 {width: 65.95744680851064%;*width: 65.90425531914893%;}.row-fluid .span7 {width: 57.44680851063829%;*width: 57.39361702127659%;}.row-fluid .span6 {width: 48.93617021276595%;*width: 48.88297872340425%;}.row-fluid .span5 {width: 40.42553191489362%;*width: 40.37234042553192%;}.row-fluid .span4 {width: 31.914893617021278%;*width: 31.861702127659576%;}.row-fluid .span3 {width: 23.404255319148934%;*width: 23.351063829787233%;}.row-fluid .span2 {width: 14.893617021276595%;*width: 14.840425531914894%;}.row-fluid .span1 {width: 6.382978723404255%;*width: 6.329787234042553%;}.row-fluid .offset12 {margin-left: 104.25531914893617%;*margin-left: 104.14893617021275%;}.row-fluid .offset12:first-child {margin-left: 102.12765957446808%;*margin-left: 102.02127659574467%;}.row-fluid .offset11 {margin-left: 95.74468085106382%;*margin-left: 95.6382978723404%;}.row-fluid .offset11:first-child {margin-left: 93.61702127659574%;*margin-left: 93.51063829787232%;}.row-fluid .offset10 {margin-left: 87.23404255319149%;*margin-left: 87.12765957446807%;}.row-fluid .offset10:first-child {margin-left: 85.1063829787234%;*margin-left: 84.99999999999999%;}.row-fluid .offset9 {margin-left: 78.72340425531914%;*margin-left: 78.61702127659572%;}.row-fluid .offset9:first-child {margin-left: 76.59574468085106%;*margin-left: 76.48936170212764%;}.row-fluid .offset8 {margin-left: 70.2127659574468%;*margin-left: 70.10638297872339%;}.row-fluid .offset8:first-child {margin-left: 68.08510638297872%;*margin-left: 67.9787234042553%;}.row-fluid .offset7 {margin-left: 61.70212765957446%;*margin-left: 61.59574468085106%;}.row-fluid .offset7:first-child {margin-left: 59.574468085106375%;*margin-left: 59.46808510638297%;}.row-fluid .offset6 {margin-left: 53.191489361702125%;*margin-left: 53.085106382978715%;}.row-fluid .offset6:first-child {margin-left: 51.063829787234035%;*margin-left: 50.95744680851063%;}.row-fluid .offset5 {margin-left: 44.68085106382979%;*margin-left: 44.57446808510638%;}.row-fluid .offset5:first-child {margin-left: 42.5531914893617%;*margin-left: 42.4468085106383%;}.row-fluid .offset4 {margin-left: 36.170212765957444%;*margin-left: 36.06382978723405%;}.row-fluid .offset4:first-child {margin-left: 34.04255319148936%;*margin-left: 33.93617021276596%;}.row-fluid .offset3 {margin-left: 27.659574468085104%;*margin-left: 27.5531914893617%;}.row-fluid .offset3:first-child {margin-left: 25.53191489361702%;*margin-left: 25.425531914893618%;}.row-fluid .offset2 {margin-left: 19.148936170212764%;*margin-left: 19.04255319148936%;}

.marginleftonly { margin-left: 10px; }
.margintoponly { margin-top: 10px; }
.marginrightonly { margin-right: 10px; }
.marginbottomonly { margin-bottom: 10px; }

.supportemailfield {
    display: inline-block;
    min-width: 150px;
}

.prioritylow {
	color: #fff;
    background-color: #dfba49;
    padding: 3px;
    border-radius: 5px;
}
.prioritymedium {
	color: #fff;
    background-color: #889820;
    padding: 3px;
    border-radius: 5px;
}
.priorityhigh {
	color: #fff;
    background-color: #118811;
    padding: 3px;
    border-radius: 5px;
}

.row-fluid .offset2:first-child {margin-left: 17.02127659574468%;*margin-left: 16.914893617021278%;}.row-fluid .offset1 {margin-left: 10.638297872340425%;*margin-left: 10.53191489361702%;}.row-fluid .offset1:first-child {margin-left: 8.51063829787234%;*margin-left: 8.404255319148938%;}
[class*='span'].hide,.row-fluid [class*='span'].hide {display: none;}
[class*='span'].pull-right,.row-fluid [class*='span'].pull-right {float: right;}
.container {margin-right: auto;margin-left: auto;*zoom: 1;}.container:before,
.container:after {display: table;content: '';line-height: 0;}.container:after {clear: both;}.container-fluid {padding-right: 20px;padding-left: 20px;*zoom: 1;}
.container-fluid:before,.container-fluid:after {display: table;content: '';line-height: 0;}.container-fluid:after {clear: both;}p {margin: 0 0 10px;}.lead {margin-bottom: 20px;font-size: 21px;font-weight: 200;line-height: 30px;}small {font-size: 85%;}strong {font-weight: bold;}em {font-style: italic;}cite {font-style: normal;}.muted {color: #999999;}.text-warning {color: #c09853;}.text-error {color: #b94a48;}.text-info {color: #3a87ad;}.text-success {color: #468847;}h1,h2,h3,h4,h5,h6 {margin: 10px 0;font-family: inherit;font-weight: bold;line-height: 1;color: inherit;text-rendering: optimizelegibility;}h1 small,h2 small,h3 small,h4 small,h5 small,h6 small {font-weight: normal;line-height: 1;color: #999999;}h1 {font-size: 36px;line-height: 40px;}h2 {font-size: 30px;line-height: 40px;}h3 {font-size: 24px;line-height: 40px;}h4 {font-size: 18px;line-height: 20px;}h5 {font-size: 14px;line-height: 20px;}h6 {font-size: 12px;line-height: 20px;}h1 small {font-size: 24px;}h2 small {font-size: 18px;}h3 small {font-size: 14px;}h4 small {font-size: 14px;}.page-header {padding-bottom: 9px;margin: 20px 0 30px;border-bottom: 1px solid #eeeeee;}
ul,ol {padding: 0;margin: 0 0 10px 15px;}
ul ul,ul ol,ol ol,ol ul {margin-bottom: 0;}
li {line-height: 24px;}
ul.unstyled,ol.unstyled {margin-left: 0;list-style: none;}dl {margin-bottom: 20px;}dt,dd {line-height: 20px;}dt {font-weight: bold;}dd {margin-left: 10px;}.dl-horizontal {*zoom: 1;}
.dl-horizontal:before,.dl-horizontal:after {display: table;content: '';line-height: 0;}.dl-horizontal:after {clear: both;}.dl-horizontal dt {float: left;width: 160px;clear: left;text-align: right;overflow: hidden;text-overflow: ellipsis;white-space: nowrap;}.dl-horizontal dd {margin-left: 180px;}hr {margin: 20px 0;border: 0;border-top: 1px solid #eeeeee;border-bottom: 1px solid #ffffff;}abbr[title] {cursor: help;border-bottom: 1px dotted #999999;}abbr.initialism {font-size: 90%;text-transform: uppercase;}blockquote {padding: 0 0 0 15px;margin: 0 0 20px;border-left: 5px solid #eeeeee;}blockquote p {margin-bottom: 0;font-size: 16px;font-weight: 300;line-height: 25px;}blockquote small {display: block;line-height: 20px;color: #999999;}
blockquote.pull-right {float: right;padding-right: 15px;padding-left: 0;border-right: 5px solid #eeeeee;border-left: 0;}blockquote.pull-right p,blockquote.pull-right small {text-align: right;}
blockquote.pull-right small:before {content: '';}
q:before,q:after,blockquote:before,blockquote:after {content: '';}
address {display: block;margin-bottom: 20px;font-style: normal;line-height: 20px;}code,pre {padding: 0 3px 2px;font-family: Monaco, Menlo, Consolas, 'Courier New', monospace;font-size: 12px;color: #333333;-webkit-border-radius: 3px;-moz-border-radius: 3px;border-radius: 3px;}code {padding: 2px 4px;color: #d14;background-color: #f7f7f9;border: 1px solid #e1e1e8;}pre {display: block;padding: 9.5px;margin: 0 0 10px;font-size: 13px;line-height: 20px;word-break: break-all;word-wrap: break-word;white-space: pre;white-space: pre-wrap;background-color: #f5f5f5;border: 1px solid #ccc;border: 1px solid rgba(0, 0, 0, 0.15);-webkit-border-radius: 4px;-moz-border-radius: 4px;border-radius: 4px;}pre.prettyprint {margin-bottom: 20px;}pre code {padding: 0;color: inherit;background-color: transparent;border: 0;}.pre-scrollable {max-height: 340px;overflow-y: scroll;}
.label,.badge {font-size: 11.844px;font-weight: bold;line-height: 14px;color: #ffffff;vertical-align: baseline;white-space: nowrap;text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.25);background-color: #999999;}.label {padding: 1px 4px 2px;-webkit-border-radius: 3px;-moz-border-radius: 3px;border-radius: 3px;}.badge {padding: 1px 9px 2px;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;}a.label:hover,a.badge:hover {color: #ffffff;text-decoration: none;cursor: pointer;}.label-important,.badge-important {background-color: #b94a48;}.label-important[href],.badge-important[href] {background-color: #953b39;}.label-warning,.badge-warning {background-color: #f89406;}.label-warning[href],.badge-warning[href] {background-color: #c67605;}.label-success,.badge-success {background-color: #468847;}.label-success[href],.badge-success[href] {background-color: #356635;}.label-info,.badge-info {background-color: #3a87ad;}.label-info[href],.badge-info[href] {background-color: #2d6987;}.label-inverse,.badge-inverse {background-color: #333333;}.label-inverse[href],.badge-inverse[href] {background-color: #1a1a1a;}.btn .label,.btn .badge {position: relative;top: -1px;}.btn-mini .label,.btn-mini .badge {top: 0;}table {max-width: 100%;background-color: transparent;border-collapse: collapse;border-spacing: 0;}.table {width: 100%;margin-bottom: 20px;}.table th,.table td {padding: 8px;line-height: 20px;text-align: left;vertical-align: top;border-top: 1px solid #dddddd;}.table th {font-weight: bold;}.table thead th {vertical-align: bottom;}.table caption + thead tr:first-child th,.table caption + thead tr:first-child td,.table colgroup + thead tr:first-child th,.table colgroup + thead tr:first-child td,.table thead:first-child tr:first-child th,.table thead:first-child tr:first-child td {border-top: 0;}.table tbody + tbody {border-top: 2px solid #dddddd;}.table-condensed th,.table-condensed td {padding: 4px 5px;}.table-bordered {border: 1px solid #dddddd;border-collapse: separate;*border-collapse: collapse;border-left: 0;-webkit-border-radius: 4px;-moz-border-radius: 4px;border-radius: 4px;}.table-bordered th,.table-bordered td {border-left: 1px solid #dddddd;}.table-bordered caption + thead tr:first-child th,.table-bordered caption + tbody tr:first-child th,.table-bordered caption + tbody tr:first-child td,.table-bordered colgroup + thead tr:first-child th,.table-bordered colgroup + tbody tr:first-child th,.table-bordered colgroup + tbody tr:first-child td,.table-bordered thead:first-child tr:first-child th,.table-bordered tbody:first-child tr:first-child th,.table-bordered tbody:first-child tr:first-child td {border-top: 0;}.table-bordered thead:first-child tr:first-child th:first-child,.table-bordered tbody:first-child tr:first-child td:first-child {-webkit-border-top-left-radius: 4px;border-top-left-radius: 4px;-moz-border-radius-topleft: 4px;}.table-bordered thead:first-child tr:first-child th:last-child,.table-bordered tbody:first-child tr:first-child td:last-child {-webkit-border-top-right-radius: 4px;border-top-right-radius: 4px;-moz-border-radius-topright: 4px;}.table-bordered thead:last-child tr:last-child th:first-child,.table-bordered tbody:last-child tr:last-child td:first-child,.table-bordered tfoot:last-child tr:last-child td:first-child {-webkit-border-radius: 0 0 0 4px;-moz-border-radius: 0 0 0 4px;border-radius: 0 0 0 4px;-webkit-border-bottom-left-radius: 4px;border-bottom-left-radius: 4px;-moz-border-radius-bottomleft: 4px;}.table-bordered thead:last-child tr:last-child th:last-child,.table-bordered tbody:last-child tr:last-child td:last-child,.table-bordered tfoot:last-child tr:last-child td:last-child {-webkit-border-bottom-right-radius: 4px;border-bottom-right-radius: 4px;-moz-border-radius-bottomright: 4px;}.table-bordered caption + thead tr:first-child th:first-child,.table-bordered caption + tbody tr:first-child td:first-child,.table-bordered colgroup + thead tr:first-child th:first-child,.table-bordered colgroup + tbody tr:first-child td:first-child {-webkit-border-top-left-radius: 4px;border-top-left-radius: 4px;-moz-border-radius-topleft: 4px;}.table-bordered caption + thead tr:first-child th:last-child,.table-bordered caption + tbody tr:first-child td:last-child,.table-bordered colgroup + thead tr:first-child th:last-child,.table-bordered colgroup + tbody tr:first-child td:last-child {-webkit-border-top-right-radius: 4px;border-top-right-radius: 4px;-moz-border-radius-topleft: 4px;}.table-striped tbody tr:nth-child(odd) td,.table-striped tbody tr:nth-child(odd) th {background-color: #f9f9f9;}.table-hover tbody tr:hover td,.table-hover tbody tr:hover th {background-color: #f5f5f5;}

.table .span1 {float: none;width: 44px;margin-left: 0;}.table .span2 {float: none;width: 124px;margin-left: 0;}.table .span3 {float: none;width: 204px;margin-left: 0;}.table .span4 {float: none;width: 284px;margin-left: 0;}.table .span5 {float: none;width: 364px;margin-left: 0;}.table .span6 {float: none;width: 444px;margin-left: 0;}.table .span7 {float: none;width: 524px;margin-left: 0;}.table .span8 {float: none;width: 604px;margin-left: 0;}.table .span9 {float: none;width: 684px;margin-left: 0;}.table .span10 {float: none;width: 764px;margin-left: 0;}.table .span11 {float: none;width: 844px;margin-left: 0;}.table .span12 {float: none;width: 924px;margin-left: 0;}.table .span13 {float: none;width: 1004px;margin-left: 0;}.table .span14 {float: none;width: 1084px;margin-left: 0;}.table .span15 {float: none;width: 1164px;margin-left: 0;}.table .span16 {float: none;width: 1244px;margin-left: 0;}.table .span17 {float: none;width: 1324px;margin-left: 0;}.table .span18 {float: none;width: 1404px;margin-left: 0;}.table .span19 {float: none;width: 1484px;margin-left: 0;}.table .span20 {float: none;width: 1564px;margin-left: 0;}.table .span21 {float: none;width: 1644px;margin-left: 0;}.table .span22 {float: none;width: 1724px;margin-left: 0;}.table .span23 {float: none;width: 1804px;margin-left: 0;}.table .span24 {float: none;width: 1884px;margin-left: 0;}.table tbody tr.success td {background-color: #dff0d8;}.table tbody tr.error td {background-color: #f2dede;}.table tbody tr.warning td {background-color: #fcf8e3;}.table tbody tr.info td {background-color: #d9edf7;}.table-hover tbody tr.success:hover td {background-color: #d0e9c6;}.table-hover tbody tr.error:hover td {background-color: #ebcccc;}.table-hover tbody tr.warning:hover td {background-color: #faf2cc;}.table-hover tbody tr.info:hover td {background-color: #c4e3f3;}form {margin: 0 0 20px;}fieldset {padding: 0;margin: 0;border: 0;}legend {display: block;width: 100%;padding: 0;margin-bottom: 20px;font-size: 21px;line-height: 40px;color: #333333;border: 0;border-bottom: 1px solid #e5e5e5;}legend small {font-size: 15px;color: #999999;}

.alert {padding: 8px 35px 8px 14px;margin-bottom: 20px;text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);background-color: #fcf8e3;border: 1px solid #fbeed5;-webkit-border-radius: 4px;-moz-border-radius: 4px;border-radius: 4px;color: #c09853;}
.alert h4 {margin: 0;}.alert .close {position: relative;top: -2px;right: -21px;line-height: 20px;}.alert-success {background-color: #dff0d8;border-color: #d6e9c6;color: #468847;}.alert-danger,.alert-error {background-color: #f2dede;border-color: #eed3d7;color: #b94a48;}.alert-info {background-color: #d9edf7;border-color: #bce8f1;color: #3a87ad;}.alert-block {padding-top: 14px;padding-bottom: 14px;}.alert-block > p,.alert-block > ul {margin-bottom: 0;}.alert-block p + p {margin-top: 5px;}

#enterUserAccountDetails .control-group {
	padding-bottom: 12px;
}
.linked-flds input, .linked-flds select {
	vertical-align: bottom;
}

.width300 {
	width: 300px;
}

@media (min-width: 980px) {
	.nav-collapse.collapse {height: auto !important;overflow: visible !important;}
}
@media (max-width: 980px) {
	body {
		padding-top: 20px !important;
		padding-bottom: 10px;
	}
}

.div-table-responsive, .div-table-responsive-no-min {
overflow-x: auto;
min-height: 0.01%;
}
.liste_titre {
background-color: #ddd;
}
.tablecommission tr td { padding: 4px; }
.group:before,.group:after {content: '';display: table;}.group:after {clear: both;}.group {zoom: 1;}ol,ul {list-style: none;}
body {
padding-top: 60px; padding-bottom: 40px;
}
body.register {
background: #fff;
}
.container-fluid {margin: 0 20px;}table thead {background-color: #eee;}
div.block {
background-color: #fff;
}
div.block header {
background-color: #334;
color: #eee;padding: 10px;margin-bottom: 20px;
text-align: center;
}
div.block header h1 {
padding-top: 0;
margin-bottom: 2px;
}
div.block header h1 small {
color: #ddd;
font-size: 0.8em;
}
div.block header .pull-left {float: left;}div.block header .pull-right {float: right;}div.block header .action {margin-top: 5px;}div.block .content {padding: 20px;}div.block .content section {padding: 50px 0;}div.block .content section header {background: transparent;border-bottom: 1px solid #eee;padding-bottom: 17px;font-size: 30px;}
div.block header.inverse {
background-color: #FFF;
/* background-color: #f5f5f5; border: 1px solid #e5e5e5; */
color: #334;
}
.stats li {text-align: center;float: left;margin: 0 10px;}.stats .stats-value {font-weight: 300;font-size: 1.6em;}.stats .stats-label {font-size: 0.9em;}ul.urls {font-size: 1.3em;}ul.urls li {margin-bottom: 20px;}ul.urls li:last-child {margin-bottom: 0;}.btn-group.inpage {margin-bottom: 20px;display: table;width: 100%;}
.btn-group.inpage .btn {display: table-cell;float: none;color: #008ee8;}.alert ul {margin: 0;}.nav-list {margin-left: 20px;}.clickable {cursor: pointer;}td .btn-group {display: inline-block;}.subsection-actions {padding: 0 0 20px 20px;}.subsection-actions form {margin: 0;}.domain-availability alert {margin: 10px 0;}#checkDomainForm {margin: 20px 0;}p.icon-padding {padding: 4px 8px;}a {cursor: pointer;}.delayed-alerts {position: relative;}.delayed-alerts .alert {position: absolute;left: 50%;-webkit-box-shadow: 0 2px 4px rgba(0,0,0,0.2);-moz-box-shadow: 0 2px 4px rgba(0,0,0,0.2);box-shadow: 0 2px 4px rgba(0,0,0,0.2);}.delayed-alerts .alert .icon-remove {font-size: 0.9em;}.delayed-alerts .alert-success {border-color: #468847;}#appInstanceApp .modal {width: inherit;}#appInstanceApp .modal .modal-body {padding-right: 30px;}ul.invoices li {float: left;padding: 20px;margin: 20px;background: #fff;border: 1px solid #ccc;-webkit-border-radius: 2px;-moz-border-radius: 2px;border-radius: 2px;text-align: center;}ul.invoices li .invoice-total {font-size: 1.2em;}input.inline {display: inline-block;}table.results {margin: 40px 0px 0px 0px;font-size: 13px;width: 900px;}.filters {padding: 20px;}.filters input {text-align: center;}.filters label {display: block;}.settings .row-fluid {border-bottom: 1px solid #ccc;margin-bottom: 40px;padding-bottom: 3px;}.settings .span3 {text-align: right;}.settings .span9 {font-weight: bold;}.settings a {margin-right: 20px;}.row-fluid {overflow: auto;}th.currency,td.currency,input.currency {text-align: right;}.tabbable .tab-content {display: block;width: auto;}.underline {border-bottom: 1px solid #eee;padding-bottom: 1px;margin-bottom: 30px;}.attributes {margin-top: 30px;}.attributes li label {float: left;}.horizontal-fld label {display: block;}.navbar-fixed-top {min-width: 960px;}.attributes {margin-left: 0;}.attributes label {font-weight: bold;}button.paypal {background: transparent url('9cvNFViGtjRAK7hDW9FxFHOrEG7YJeQxTEFxnqRqUT4.gif') no-repeat left center;border: 0;color: #fff;cursor: pointer;text-decoration: none;font-size: 15px;line-height: 19px;padding: 5px 10px;display: block;-webkit-border-radius: 4px 4px 4px 4px;-moz-border-radius: 4px 4px 4px 4px;-webkit-border-radius: 4px 4px 4px 4px;-moz-border-radius: 4px 4px 4px 4px;border-radius: 4px 4px 4px 4px;width: 145px;height: 42px;}button.paypal span {display: block;width: 0;height: 0;overflow: hidden;}.horizontal-fld {float: left;margin-right: 20px;}.navbar {font-size: 1.1em;font-weight: bold;}.input-small.date {width: 90px;}form .input-append input {margin-bottom: 0;}form .content {padding: 10px;}form {margin: 0;}[data-ng-click],[ng-click] {cursor: pointer;}.post {margin-bottom: 15px;margin-top: 15px;}.post-avatar border 1px solid #ccc img {width: 60px;}.post-actions {border-top: 1px solid #ccc;padding-top: 5px;margin-top: 10px;}.triangle-isosceles {position: relative;padding: 15px;margin: 0 0 1em;color: #000;background: #f3961c;background: -webkit-gradient(linear, 0 0, 0 100%, from(#ccc), to(#ccc));background: -moz-linear-gradient(#ccc, #eee);background: -o-linear-gradient(#ccc, #eee);background: linear-gradient(#ccc, #eee);-webkit-border-radius: 10px;-moz-border-radius: 10px;-webkit-border-radius: 10px;-moz-border-radius: 10px;border-radius: 10px;}.triangle-isosceles.left {margin-left: 30px;background: #eee;}
.triangle-isosceles:after {content: '';position: absolute;bottom: -15px;left: 50px;border-width: 10px 10px 0;border-style: solid;border-color: #eee transparent;display: block;width: 0;}.triangle-isosceles.left:after {top: 15px;left: -20px;bottom: auto;border-width: 10px 20px 10px 0;border-color: transparent #eee;}.box-sizing {-webkit-box-sizing: border-box;-moz-box-sizing: border-box;box-sizing: border-box;}article.editing {width: 100%;}header h1 {font-size: 25px;line-height: 25px;}form label {font-weight: bold;}

.required label:after {
	/* color: #e32;
	content: '*';
	display: inline; */
}

.centered {position: fixed;top: 50%;left: 50%;margin-top: -50px;margin-left: -100px;}.notifications {position: fixed;top: 100px;left: 50%;width: 560px;margin: 0 0 0 -280px;text-align: center;font-weight: bold;border: 1px solid #c09853;z-index: 9999;opacity: 0.7;}

.app-instance.nav-list {font-size: 1.2em;margin-bottom: 20px;}.app-instance.nav-list li {padding: 5px;}.app-instance.nav-list li a {padding: 5px;}.last-login {margin-right: 30px;text-align: center;}.last-login .stats-value {font-size: 1.1em;}p.intro {margin-bottom: 20px;}h4 {color: #585858;}[ngcloak],[ng-cloak],.ng-cloak {display: none;}.label.app-resource {margin-right: 5px;}.form-content {padding: 10px 20px;}.form-actions {padding: 10px 20px;background: #f5f5f5;border-top: 1px solid #e5e5e5;margin: 0;}
.signUpAlert {width: 90%;margin: 0 auto;}
header.inverse  h1 { padding-top: 5px; }
.center { text-align: center; }
.taligncenter { text-align: center; }
.valignmiddle { vertical-align: middle; }
.valigntop { vertical-align: top; }

#logo {
    text-align: center;	max-width:200px; max-height: 60px;
}

.block.small {
width: 500px;
min-width: 500px;
margin-top: 20px;
}

.large {
	margin: 0 auto;
}
.block.medium {
}
.page-header-top {
    background: #f4f4f4;
	padding-top: 20px;
	padding-bottom: 25px;
}

.nowrap { white-space: nowrap; }

.signup { margin: 0 auto; max-width: 700px; padding-top: 20px;}

.signup .block.medium { padding-top: 10px; }

.signup2 { max-width: 700px; display: inline-block; text-align: initial; }

.customcompanylogo{
	display:none;
}

.customregisterheader{
	display:none;
}

.paddingtop20 {
    padding-top: 0px;
}

.paddingall {
    padding: 5px;
}
.paddingleft {
    padding-left: 5px;
}
.paddingright {
    padding-right: 5px;
}

.margintop {
    margin-top: 5px;
}
.marginbottom {
    margin-bottom: 5px;
}
.nomarginbottom {
	margin-bottom: 0px;
}

div#waitMask {
	text-align: center;
	z-index: 999;
	position: fixed;
	top: 0;
	right: 0;
	height: 100%;
	width: 100%;
	cursor: wait;
	padding-top: 250px;
	background-color: #000;
	opacity: 0;
	transition-duration: 0.5s;
	-webkit-transition-duration: 0.5s;
}


.center {
text-align: center;
margin: 0px auto;
}
.login_main_message {
text-align: center;
max-width: 570px;
margin-top: 20px;
margin-bottom: 10px;
}
.login_main_message .error {
border: 1px solid #caa;
padding: 10px;
}

/* Error message */
font.error, p.error {
font-weight: bold;
color: #dd4444;
}
div.error {
background: #EFCFCF;
}
div#card-errors {
color: #c80;
}

.note.note-warning {
background-color: #fcf8e3;
border-color: #f2cf87;
color: #8a6d3b;
}

.note {
margin: 0 0 30px 0;
padding: 15px 30px 15px 15px;
border-left: 5px solid #eee;
-webkit-border-radius: 0 4px 4px 0;
-moz-border-radius: 0 4px 4px 0;
-ms-border-radius: 0 4px 4px 0;
-o-border-radius: 0 4px 4px 0;
border-radius: 0 4px 4px 0;
}

.urlofinstancetodestroy {
    min-width: 300px;
}

.register_text {
    padding: 10px 20px;
    text-align: justify;
    opacity: 0.65;
	font-size: 0.95em;
}

.areaforresources {
	padding-left: 12px;
	padding-right: 12px;
}

.badge-myaccount-status {
	box-shadow: 0px 0px 10px #ccc;
}

.whitespacenowrap {
	white-space : normal !important;
}

.grid-wrapper-automigration {
	display : grid;
	grid-row : auto auto;
	grid-template-columns : auto auto;
	grid-row-gap : 20px;
	grid-column-gap : 20px;
}
.grid-boxes-automigration-left {
	display : flex;
	justify-content : right;
}



input.input-field {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 5px;
	margin-left: -28px;
    padding-left: 34px;
    background-color: transparent;
}

.span-icon-user, .span-icon-password {
 	font-family: 'Font Awesome 5 Free';
	padding-left: 10px;
}
.span-icon-user:before, .span-icon-password:before {
	opacity: 0.4;
}


.tagtable, .table-border { display: table; }
.tagtr, .table-border-row  { display: table-row; }
.tagtd, .table-border-col, .table-key-border-col, .table-val-border-col { display: table-cell; }



/* For smartphones */

@media (max-width: 760px) {
    #logo {
        text-align: center;	max-width:110px; max-height: 60px;
    }

	.areaforresources {
		padding-left: 0px;
		padding-right: 0px;
	}

	.urlofinstancetodestroy {
	    min-width: 250px;
	}

    .paddingtop20 {
        padding-top: 0;
    }

    input.sldAndSubdomain {
        max-width: 150px;
    }

    img#logo {
        padding-top: 10px;
    }

	.boxresource {
		width: 155px !important;
	}

	.register_text {
		font-size: 0.85em;
	}
}

";
