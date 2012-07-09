<?php
function zing_bt_options() {
	global $zing_bt_name,$zing_bt_shortname,$current_user;
	$zing_bt_name = "Mantis BT Bridge";
	$zing_bt_shortname = "zing_bt";

	$zing_bt_options[] = array(  "name" => "Integration Settings",
            "type" => "heading",
			"desc" => "This section customizes the way Mantis Bug Tracker Bridge interacts with Wordpress.");
	$zing_bt_options[] = array(	"name" => "Root URL",
			"desc" => "Specify the Mantis BT domain, e.g. http://www.example.org.",
			"id" => $zing_bt_shortname."_url",
			"type" => "text");
	$zing_bt_options[] = array(	"name" => "Subdirectory",
			"desc" => "If you installed Mantis BT in a subdirectory of the URL specified here above, enter the domain, e.g. subdir.",
			"id" => $zing_bt_shortname."_subdir",
			"type" => "text");
	$zing_bt_options[] = array(	"name" => "Footer",
			"desc" => "If you like the plugin and want to support our developers, please activate the footer here.",
			"id" => $zing_bt_shortname."_footer",
			"std" => 'None',
			"type" => "select",
			"options" => array('Site','Page','None'));

	return $zing_bt_options;
}

function zing_bt_add_admin() {

	global $zing_bt_name, $zing_bt_shortname;

	$zing_bt_options=zing_bt_options();

	if ( isset($_GET['page']) && $_GET['page'] == "bug-tracker-cp" ) {

		if ( isset($_REQUEST['action']) && 'install' == $_REQUEST['action'] ) {
			foreach ($zing_bt_options as $value) {
				update_option( $value['id'], $_REQUEST[ $value['id'] ] );
			}

			foreach ($zing_bt_options as $value) {
				if( isset( $_REQUEST[ $value['id'] ] ) ) {
					update_option( $value['id'], $_REQUEST[ $value['id'] ]  );
				} else { delete_option( $value['id'] );
				}
			}
			if (zing_bt_install()) {
				//				$btusers=new btusers();
				//				$btusers->sync();
				if (substr(get_option('zing_bt_url'),-1)!='/') update_option('zing_bt_url',get_option('zing_bt_url').'/');
				if (substr(get_option('zing_bt_subdir'),-1)!='/') update_option('zing_bt_subdir',get_option('zing_bt_subdir').'/');
			}
			header("Location: options-general.php?page=bug-tracker-cp&installed=true");
			die;
		}

		if( isset($_REQUEST['action']) && 'uninstall' == $_REQUEST['action'] ) {
			zing_bt_uninstall();
			foreach ($zing_bt_options as $value) {
				delete_option( $value['id'] );
				update_option( $value['id'], $value['std'] );
			}
			header("Location: options-general.php?page=bug-tracker-cp&uninstalled=true");
			die;
		}
	}

	add_options_page($zing_bt_name, $zing_bt_name, 'manage_options', 'bug-tracker-cp', 'zing_bt_admin');

}

function zing_mantisbt_admin() {
	global $zing_bt_mode;
	global $zing_bt_content;
	//global $zing_bt_menu;

	$zing_bt_mode="admin";
	if (!$_GET['zbtadmin']) $_GET['zbtadmin']='index';
	echo '<div style="width:80%;">';
	zing_bt_login_admin();
	zing_bt_header();
	//if ($zing_bt_content=='redirect') {
	//	header('Location:'.get_option('home').'/?page_id='.zing_bt_mainpage());
	//	die();
	//} else {
	echo $zing_bt_content;
	//}
	echo '</div>';

}
function zing_bt_admin() {

	global $zing_bt_name, $zing_bt_shortname;

	$zing_bt_options=zing_bt_options();

	if ( isset($_REQUEST['installed']) && $_REQUEST['installed'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_bt_name.' installed.</strong></p></div>';
	if ( isset($_REQUEST['uninstalled']) && $_REQUEST['uninstalled'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_bt_name.' uninstalled.</strong></p></div>';

	?>
<div class="wrap">
	<h2>
		<b><?php echo $zing_bt_name; ?> </b>
	</h2>

	<?php
	$zing_ew=zing_bt_check();
	$zing_errors=$zing_ew['errors'];
	$zing_warnings=$zing_ew['warnings'];
	if ($zing_errors) {
		echo '<div style="background-color:pink" id="message" class="updated fade"><p>';
		echo '<strong>Errors - you need to resolve these errors before continuing:</strong><br /><br />';
		foreach ($zing_errors as $zing_error) echo $zing_error.'<br />';
		echo '</p></div>';
	}
	if ($zing_warnings) {
		echo '<div style="background-color:peachpuff" id="message" class="updated fade"><p>';
		echo '<strong>Warnings - you might want to have a look at these issues to avoid surprises or unexpected behaviour:</strong><br /><br />';
		foreach ($zing_warnings as $zing_warning) echo $zing_warning.'<br />';
		echo '</p></div>';
	}
	$zing_bt_version=get_option("zing_bt_version");
	if (empty($zing_bt_version)) {
		echo 'Please proceed with a clean install or deactivate your plugin';
		$submit='Install';
	} elseif ($zing_bt_version != ZING_BT_VERSION) {
		echo 'You downloaded version '.ZING_BT_VERSION.' and need to upgrade your settings (currently at version '.$zing_bt_version.') by clicking Upgrade below.';
		$submit='Upgrade';
	} elseif ($zing_bt_version == ZING_BT_VERSION) {
		$submit='Update';
	}

	//if (count($zing_errors)==0) {
	$controlpanelOptions=$zing_bt_options;
	?>
	<form method="post">

	<?php require(dirname(__FILE__).'/includes/cpedit.inc.php');?>

		<p class="submit">
			<input name="install" type="submit" value="<?php echo $submit;?>" /> <input type="hidden"
				name="action" value="install"
			/>
		</p>
	</form>
	<?php if ($zing_bt_version) { ?>
	<hr />
	<form method="post">
		<p class="submit">
			<input name="uninstall" type="submit" value="Uninstall" /> <input type="hidden" name="action"
				value="uninstall"
			/>
		</p>
	</form>
	<?php } ?>
	<hr />
	<p>
		For any support queries, contact us via our <a
			href="http://forums.zingiri.com/forumdisplay.php?fid=73"
		>support forums</a>.
	</p>
	<img src="<?php echo ZING_BT_URL?>images/logo.png" />
	<?php
}
add_action('admin_menu', 'zing_bt_add_admin'); ?>