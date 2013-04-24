<?php
$html = '';
//$html .= heading(_('Welcome!'), 3) . '<hr class="backup-hr"/>';
if ($errors) {
	$html .= '<span class="obe_error">';
	$html .= _('Please correct the following errors:');
	$html .= ul($errors);
	$html .= '</span>';
}
$html .= '<div id="login_form">';
$html .= form_open($_SERVER['REQUEST_URI'], 'id="loginform"');
$html .= _('To get started, please enter your credentials:');
$html .= br(2);
$data = array(
			'name' => 'username',
			'placeholder' => _('username')
		);
$html .= form_input($data);
$html .= br(2);
$data = array(
			'name' => 'password',
			'type' => 'password',
			'placeholder' => _('password')
		);
$html .= form_input($data);
$html .= br(2);
//$html .= form_submit('submit', _('Login'));
//$html .= br(2);
$html .= form_close();
$html .= '</div>';
$html .= '<div id="login_icon_holder">';
$html .= '<a href="#" class="login_item" id="login_admin" style="background-image: url(assets/images/sys-admin.png);"/>&nbsp</a>';
$html .= '<a href="/recordings" '
		. 'class="login_item" id="login_ari" style="background-image: url(assets/images/user-control.png);"/>&nbsp</a>';
if ($panel) {
    $html .= '<a href="' . $panel . '" '
		    . 'class="login_item" id="login_fop" style="background-image: url(assets/images/operator-panel.png);"/>&nbsp</a>';
}
$html .= '<a href="http://www.schmoozecom.com/oss.php" '
		. 'class="login_item" id="login_support" style="background-image: url(assets/images/support.png);"/>&nbsp</a>';
$html .= '<div></div>';
$html .= '<div class="login_item_title">' . _('FreePBX Administration') . '</div>';
$html .= '<div class="login_item_title">' . _('User Control Panel') . '</div>';
if ($panel) {
    $html .= '<div class="login_item_title">' . _('Operator Panel') . '</div>';
}
$html .= '<div class="login_item_title">' . _('Get Support') . '</div>';
$html .= '</div>';
$html .= br(5) . '<div id="key" style="color: white;font-size:small">'
	  . session_id()
	  . '</div>';

/*$html .= '<script type="text/javascript">';
$html .= '$(document).ready(function(){
		$("#key").click(function(){
			dest = "ssh://" + window.location.hostname + " \"/usr/sbin/amportal a u ' . session_id() . '\"";
			console.log(dest)
			window.open(dest).close(); setTimeout(\'window.location.reload()\', 3000);
		});
})';
$html .= '</script>';*/

$html .= '<script type="text/javascript" src="assets/js/views/login.js"></script>';

echo $html;

?>
