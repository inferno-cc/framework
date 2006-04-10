<?php /* $Id$ */
//Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.
// start session
session_start();
?>

<?php
$title=_("freePBX administration");
$message=_("Administration");

require_once('functions.inc.php');

// get settings
$amp_conf = parse_amportal_conf("/etc/amportal.conf");

// connect to database
require_once('common/db_connect.php'); //PEAR must be installed

$quietmode = false;
include 'header.php'; 

if (isset($_REQUEST['display'])) {
	$display=$_REQUEST['display'];
} else { 
	$display='';
}
?>

<div class="nav">
    <li><a id="<?php  echo ($display=='' ? 'current':'') ?>" href="index.php">Welcome</a></li>
</div>


<div class="content">

<?php
if (isset($_REQUEST['display'])) {
	$display=$_REQUEST['display'];
} else { 
	$display='';
}
switch($display) {
    default:
?>

    <p align="right">
	<?php if (extension_loaded('gettext')) {
		if (!isset($_COOKIE['lang'])) {
			$_COOKIE['lang'] = "en_US";
		} ?>
&nbsp;&nbsp;&nbsp;<?php echo _("Language:") ?>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<select onchange="javascript:changeLang(this.value)">
<option value="en_US" <? echo ($_COOKIE['lang']=="en_US" ? "selected" : "") ?> >English</option>
<option value="fr_FR" <? echo ($_COOKIE['lang']=="fr_FR" ? "selected" : "") ?> >Fran&ccedil;ais</option>
<option value="de_DE" <? echo ($_COOKIE['lang']=="de_DE" ? "selected" : "") ?> >Deutsch</option>
<option value="it_IT" <? echo ($_COOKIE['lang']=="it_IT" ? "selected" : "") ?> >Italiano</option>
<option value="es_ES" <? echo ($_COOKIE['lang']=="es_ES" ? "selected" : "") ?> >Espa&ntilde;ol</option>
<option value="ru_RU" <? echo ($_COOKIE['lang']=="ru_RU" ? "selected" : "") ?> >Russki</option>
</select>
	<?php } ?>
    </p>

    <h2>freePBX</h2>
    <p>
        <?php echo _("Welcome to the FreePBX Administration") ?> <?php $ver=getversion(); echo $ver[0][0];?>
        <br><br><br><br><br><br>
    </p>
    
<?php
    break;
    case '1':
?>


    
<?php
    break;
}
?>

</div>




</div>

<script language="javascript">
<!--

function changeLang(lang) {
	document.cookie='lang='+lang;
	window.location.reload();
}

//-->
</script>

