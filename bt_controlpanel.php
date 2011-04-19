<?php
function zing_bt_options() {
	global $zing_bt_name,$zing_bt_shortname,$current_user;
	$zing_bt_name = "ccTracker";
	$zing_bt_shortname = "zing_bt";

	$zing_bt_options[] = array(  "name" => "Integration Settings",
            "type" => "heading",
			"desc" => "This section customizes the way ccTracker interacts with Wordpress.");
	$zing_bt_options[] = array(	"name" => "Footer",
			"desc" => "Specify where you want our footer to appear. If you disable the footer here,<br />we count on you to link back to our site some other way.",
			"id" => $zing_bt_shortname."_footer",
			"std" => 'Page',
			"type" => "select",
			"options" => array('Site','Page','None'));

	return $zing_bt_options;
}

function zing_bt_add_admin() {

	global $zing_bt_name, $zing_bt_shortname;

	$zing_bt_options=zing_bt_options();

	if ( $_GET['page'] == "bug-tracker-cp" ) {

		if ( 'install' == $_REQUEST['action'] ) {
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
				$btusers=new btusers();
				$btusers->sync();
			}
			header("Location: options-general.php?page=bug-tracker-cp&installed=true");
			die;
		}

		if( 'uninstall' == $_REQUEST['action'] ) {
			zing_bt_uninstall();
			foreach ($zing_bt_options as $value) {
				delete_option( $value['id'] );
				update_option( $value['id'], $value['std'] );
			}
			header("Location: options-general.php?page=bug-tracker-cp&uninstalled=true");
			die;
		}
	}

	add_menu_page($zing_bt_name, $zing_bt_name, 'administrator', 'bug-tracker-cp','zing_bt_admin');
	add_submenu_page('bug-tracker-cp', $zing_bt_name.'- Integration', 'Integration', 'administrator', 'bug-tracker-cp', 'zing_bt_admin');
	//if (get_option("zing_bt_version")) add_submenu_page('bug-tracker-cp', $zing_bt_name.'- Administration', 'Administration', 'administrator', 'bug-tracker-admin', 'zing_mantisbt_admin');

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

	if ( $_REQUEST['installed'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_bt_name.' installed.</strong></p></div>';
	if ( $_REQUEST['uninstalled'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_bt_name.' uninstalled.</strong></p></div>';

	?>
<div class="wrap">
<h2><b><?php echo $zing_bt_name; ?></b></h2>

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
		echo 'You downloaded version '.ZING_BT_VERSION.' and need to upgrade your database (currently at version '.$zing_bt_version.') by clicking Upgrade below.';
		$submit='Upgrade';
	} elseif ($zing_bt_version == ZING_BT_VERSION) {
		echo 'Your version is up to date!';
		$submit='Update';
	}

	//if (count($zing_errors)==0) {
	?>
<form method="post">

<table class="optiontable">

<?php if ($zing_bt_options) foreach ($zing_bt_options as $value) {

	if ($value['type'] == "text") { ?>

	<tr align="left">
		<th scope="row"><?php echo $value['name']; ?>:</th>
		<td><input name="<?php echo $value['id']; ?>" id="<?php echo $value['id']; ?>"
			type="<?php echo $value['type']; ?>"
			value="<?php if ( get_settings( $value['id'] ) != "") { echo get_settings( $value['id'] ); } else { echo $value['std']; } ?>"
			size="40"
		/></td>

	</tr>
	<tr>
		<td colspan=2><small><?php echo $value['desc']; ?> </small>
		<hr />
		</td>
	</tr>

	<?php } elseif ($value['type'] == "textarea") { ?>
	<tr align="left">
		<th scope="row"><?php echo $value['name']; ?>:</th>
		<td><textarea name="<?php echo $value['id']; ?>" id="<?php echo $value['id']; ?>" cols="50"
			rows="8"
		/>
		<?php if ( get_settings( $value['id'] ) != "") { echo stripslashes (get_settings( $value['id'] )); }
		else { echo $value['std'];
		} ?>
</textarea></td>

	</tr>
	<tr>
		<td colspan=2><small><?php echo $value['desc']; ?> </small>
		<hr />
		</td>
	</tr>

	<?php } elseif ($value['type'] == "select") { ?>

	<tr align="left">
		<th scope="top"><?php echo $value['name']; ?>:</th>
		<td><select name="<?php echo $value['id']; ?>" id="<?php echo $value['id']; ?>">
		<?php foreach ($value['options'] as $option) { ?>
			<option <?php if ( get_settings( $value['id'] ) == $option) { echo ' selected="selected"'; }?>><?php echo $option; ?></option>
			<?php } ?>
		</select></td>

	</tr>
	<tr>
		<td colspan=2><small><?php echo $value['desc']; ?> </small>
		<hr />
		</td>
	</tr>

	<?php } elseif ($value['type'] == "heading") { ?>

	<tr valign="top">
		<td colspan="2" style="text-align: left;">
		<h2 style="color: green;"><?php echo $value['name']; ?></h2>
		</td>
	</tr>
	<tr>
		<td colspan=2><small>
		<p style="color: red; margin: 0 0;"><?php echo $value['desc']; ?></P>
		</small>
		<hr />
		</td>
	</tr>

	<?php } ?>
	<?php
}
?>
</table>

<p class="submit"><input name="install" type="submit" value="<?php echo $submit;?>" /> <input
	type="hidden" name="action" value="install"
/></p>
</form>
<?php //}?> <?php if ($zing_bt_version) { ?>
</form>
<hr />
<form method="post">
<p class="submit"><input name="uninstall" type="submit" value="Uninstall" /> <input type="hidden"
	name="action" value="uninstall"
/></p>
</form>
<?php } ?>
<hr />
<img src="<?php echo ZING_BT_URL?>/choppedcode.png" height="50px" />
<p>For more info and support, contact us at <a href="http://www.choppedcode.com">ChoppedCode</a> or check
out our <a href="http://forums.choppedcode.com">support forums</a>.</p>
<?php
}
add_action('admin_menu', 'zing_bt_add_admin'); ?>