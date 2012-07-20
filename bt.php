<?php
/*
 Plugin Name: Mantis Bug Tracker Bridge
 Plugin URI: http://www.zingiri.com
 Description: Mantis Bug Tracker Bridge is a plugin that integrates the powerfull Mantis bug tracker software with Wordpress. It brings one of the most powerfull bug tracking softwares in reach of Wordpress users.

 Author: Zingiri
 Version: 1.2.3
 Author URI: http://www.zingiri.com/
 */

define("ZING_BT_VERSION","1.2.3");
define("ZING_MANTIS","mantisbt");
define("ZING_MANTIS_VERSION","1.2.2");

// Pre-2.6 compatibility for wp-content folder location
if (!defined("WP_CONTENT_URL")) {
	define("WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
}
if (!defined("WP_CONTENT_DIR")) {
	define("WP_CONTENT_DIR", ABSPATH . "wp-content");
}

if (!defined("ZING_BT_PLUGIN")) {
	$zing_bt_plugin=str_replace(realpath(dirname(__FILE__).'/..'),"",dirname(__FILE__));
	$zing_bt_plugin=substr($zing_bt_plugin,1);
	define("ZING_BT_PLUGIN", $zing_bt_plugin);
}
define("ZING_BT_URL", WP_CONTENT_URL . "/plugins/".ZING_BT_PLUGIN."/");

define("ZING_MANTIS_SELF",get_option('zing_bt_subdir'));
define("ZING_MANTIS_URL",get_option('zing_bt_url').ZING_MANTIS_SELF);

$zing_bt_version=get_option("zing_bt_version");
if ($zing_bt_version) {
	add_action("init","zing_bt_init");
	if (get_option('zing_bt_footer')=='Site') add_filter('wp_footer','zing_footers');
	add_filter('the_content', 'zing_bt_content', 10, 3);
	add_action('wp_head','zing_bt_header');
	//add_action('wp_login','zing_bt_login');
	//add_action('wp_logout','zing_bt_logout');

	//add_filter('check_password','zing_bt_check_password',10,4);
	//add_action('profile_update','zing_bt_profile_update'); //post wp update
	//add_action('user_register','zing_bt_user_register'); //post wp update
	//add_action('delete_user','zing_bt_user_delete');
}
register_activation_hook(__FILE__,'zing_bt_activate');
register_deactivation_hook(__FILE__,'zing_bt_deactivate');

require_once(dirname(__FILE__) . '/includes/errorlog.class.php');
require_once(dirname(__FILE__) . '/includes/shared.inc.php');
require_once(dirname(__FILE__) . '/includes/http.class.php');
require_once(dirname(__FILE__) . '/includes/footer.inc.php');
require_once(dirname(__FILE__) . '/includes/integrator.inc.php');
require_once(dirname(__FILE__) . '/bt_controlpanel.php');
require_once(dirname(__FILE__) . '/includes/btusers.class.php');

$zErrorLog=new zErrorLog();

function zing_bt_check() {
	$errors=array();
	$warnings=array();
	$files=array();
	$dirs=array();

	if (phpversion() < '5')	$warnings[]="You are running PHP version ".phpversion().". We recommend you upgrade to PHP 5.3 or higher.";
	if (ini_get("zend.ze1_compatibility_mode")) $warnings[]="You are running PHP in PHP 4 compatibility mode. We recommend you turn this option off.";
	if (!function_exists('curl_init')) $errors[]="You need to have cURL installed. Contact your hosting provider to do so.";
	if (!session_id()) $errors[]='Sessions are not working on your installation, make sure they are turned on.';
	return array('errors'=> $errors, 'warnings' => $warnings);
}


/**
 * Activation: creation of database tables & set up of pages
 * @return unknown_type
 */
function zing_bt_activate() {
	//nothing much to do
}

function zing_bt_install() {
	global $wpdb,$zErrorLog,$current_user;

	$eaw=zing_bt_check();
	if (count($eaw['errors']) > 0) return false;

	ob_start();
	$zErrorLog->clear();
	set_error_handler(array($zErrorLog,'log'));
	error_reporting(E_ALL & ~E_NOTICE);

	if (get_option('zing_bt_mantisbt_dbname')) {
		$prefix = get_option('zing_bt_mantisbt_dbprefix');
	} else {
		$prefix = $wpdb->prefix."mantis_";
	}

	$zing_bt_version=get_option("zing_bt_version");

	//first installation of BT
	if (!$zing_bt_version) {
		$zErrorLog->msg('Install bug tracker');
		//zing_bt_mantisbt_install();
		update_option("zing_mantisbt_version",ZING_MANTIS_VERSION);
	}
	//upgrade BT if needed
	elseif (get_option("zing_mantisbt_version") != ZING_MANTIS_VERSION) {
		$zErrorLog->msg('Upgrade forum');
		//zing_bt_mantisbt_upgrade();
		update_option("zing_mantisbt_version",ZING_MANTIS_VERSION);
	}

	//create pages
	$zErrorLog->msg('Creating pages');
	if (!$zing_bt_version) {
		$pages=array();
		$pages[]=array("Bug tracker","bugtracker","*",0);

		$ids="";
		foreach ($pages as $i =>$p)
		{
			$my_post = array();
			$my_post['post_title'] = $p['0'];
			$my_post['post_content'] = '';
			$my_post['post_status'] = 'publish';
			$my_post['post_author'] = 1;
			$my_post['post_type'] = 'page';
			$my_post['menu_order'] = 100+$i;
			$my_post['comment_status'] = 'closed';
			$id=wp_insert_post( $my_post );
			if (empty($ids)) { $ids.=$id; } else { $ids.=",".$id; }
			if (!empty($p[1])) add_post_meta($id,'zing_bt_page',$p[1]);
		}
		if (get_option("zing_bt_pages"))
		{
			update_option("zing_bt_pages",$ids);
		}
		else {
			add_option("zing_bt_pages",$ids);
		}
	}

	//login configuration
	if (get_option('zing_bt_mantisbt_dbname')) $wpdb->select(get_option('zing_bt_mantisbt_dbname'));

	//set default admin to current CMS admin
	//$query2="UPDATE `".$prefix."user_table` SET `username`='".$current_user->data->user_login."' WHERE `username`='administrator'";
	//$zErrorLog->msg($query2);
	//$wpdb->query($query2);

	if (get_option('zing_bt_mantisbt_dbname')) $wpdb->select(DB_NAME);

	restore_error_handler();

	if (!$zing_bt_version) add_option("zing_bt_version",ZING_BT_VERSION);
	else update_option("zing_bt_version",ZING_BT_VERSION);

	return true;
}

/*
 function zing_bt_hash() {
 return filemtime(dirname(__FILE__).'/'.ZING_MANTIS.'/install/lock');
 }
 */

function zing_bt_mantisbt_install() {
	global $wpdb,$zErrorLog,$current_user;

	$post['install']='2';
	$post['dbtype']='mysql';
	$post['hostname']=DB_HOST;
	$post['db_username']=DB_USER;
	$post['db_password']=DB_PASSWORD;
	$post['database_name']=DB_NAME;
	$post['admin_username']=DB_USER;
	$post['admin_password']=DB_PASSWORD;

	$http=zing_bt_http("mantisbt",'admin/install.php');
	$zErrorLog->msg($http);
	$news = new btHTTPRequest($http);
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString(true,false);
		$zErrorLog->msg('out='.$output);
	}

	//allow anonymous access
	//$zErrorLog->msg('create anonymous user');
	//createBugTrackerUser('anonymous','','anonymous@localhost');
	//$current_user->data->user_email
}

function zing_bt_mantisbt_upgrade() {
}
/**
 * Deactivation: nothing to do
 * @return void
 */
function zing_bt_deactivate() {
	wp_clear_scheduled_hook('zing_bt_cron_hook');
}

/**
 * Uninstallation: removal of database tables
 * @return void
 */
function zing_bt_uninstall() {
	global $wpdb;
	/*
	 $prefix=$wpdb->prefix."mantis";
	 $rows=$wpdb->get_results("show tables like '".$prefix."%'",ARRAY_N);
	 foreach ($rows as $id => $row) {
		$query="drop table ".$row[0];
		$wpdb->query($query);
		}
		*/
	$ids=get_option("zing_bt_pages");
	$ida=explode(",",$ids);
	foreach ($ida as $id) {
		wp_delete_post($id);
	}
	delete_option("zing_bt_version");
	delete_option("zing_bt_pages");
	delete_option("zing_mantisbt_version");
	//$fh = fopen(dirname(__FILE__).'/'.ZING_MANTIS.'/inc/settings.php', 'w');
	//fclose($fh);
}

/**
 * Main function handling content, footer and sidebars
 * @param $process
 * @param $content
 * @return unknown_type
 */
function zing_bt_main($process,$content="") {
	global $zing_bt_content;
	if ($zing_bt_content) {
		if ($zing_bt_content=="redirect") {
			echo 'Location:'.get_option('home').'/?page_id='.zing_bt_mainpage();
			//header('Location:'.get_option('home').'/?page_id='.zing_bt_mainpage());
			die('redirect1');
		}
		else {
			if (isset($_GET['action']) && $_GET['action']=='logout') {
				unset($_SESSION['tmpfile']);
			}
			$content=$zing_bt_content;
			if (get_option('zing_bt_footer')=='Page') $content.=zing_footers(true);
		}
	}
	return $content;
}

function zing_bt_output($process) {
	global $post;
	global $wpdb;

	global $zing_bt_loaded,$zing_bt_to_include,$zing_bt_mode;

	switch ($process)
	{
		case "content":
			$cf=get_post_custom($post->ID);
			if (isset($_GET['zbt']))
			{
				$zing_bt_to_include=$_GET['zbt'];
				$zing_bt_mode="client";
			}
			elseif (isset($_GET['zbtadmin']))
			{
				$zing_bt_to_include="admin/".$_GET['zbtadmin'];
				$zing_bt_mode="admin";
			}
			elseif (isset($_GET['module']))
			{
				$zing_bt_to_include="admin/index";
				$zing_bt_mode="admin";
			}
			elseif (isset($cf['zing_bt_page']))
			{
				if ($cf['zing_bt_page'][0]=='bugtracker') {
					$zing_bt_to_include="my_view_page";
					$zing_bt_mode="client";
				}
			}
			else
			{
				return;
			}
			if (isset($cf['cat'])) {
				$_GET['cat']=$cf['cat'][0];
			}
			break;
	}
	if (strstr($zing_bt_to_include,'archive/index.php/')) $http=zing_bt_http("mantisbt",$zing_bt_to_include);
	else $http=zing_bt_http("mantisbt",$zing_bt_to_include.'.php');
	//echo '<br />'.$http.'<br />';
	$news = new btHTTPRequest($http);

	$news->post=$_POST;
	if (isset($_POST['bt_name'])) {
		$news->post['name']=$_POST['bt_name'];
		unset($_POST['bt_name']);
	}

	if (!$news->curlInstalled()) return "cURL not installed";
	elseif (!$news->live()) return "A HTTP Error occured";
	else {
		$output=$news->DownloadToString(true,false);
		$output=zing_bt_ob($output);
		if (empty($output)) {
			return 'An error occured: Empty output';
		}
		else {
			return '<!--mbt:start-->'.$output.'<!--mbt:end-->';
		}
	}
}

function zing_bt_http($module,$to_include="index",$page="",$key="") {
	global $wpdb;

	$vars="";
	if (!$to_include) $to_include="index";
	$http=ZING_MANTIS_URL;
	$http.= $to_include;
	$and="";
	if (count($_GET) > 0) {
		foreach ($_GET as $n => $v) {
			if ($n!="zbt" && $n!="page_id" && $n!="zbtadmin")
			{
				$vars.= $and.$n.'='.zing_urlencode($v);
				$and="&";
			}
		}
	}
	$vars.=$and.'zing='.zing_urlencode(ZING_MANTIS_URL);
	$vars.='&prefix='.zing_urlencode($wpdb->prefix."mantis");
	if ($vars) $http.='?'.$vars;
	//echo $http;
	return $http;
}

/**
 * Page content filter
 * @param $content
 * @return unknown_type
 */
function zing_bt_content($content) {
	return zing_bt_main("content",$content);
}


/**
 * Header hook: loads FWS addons and css files
 * @return unknown_type
 */
function zing_bt_header()
{
	global $zing_bt_content;
	global $zing_bt_menu;
	$output=zing_bt_output("content");

	zing_integrator_cut($output,'<div id="footer">','</div>'); //remove footer
	zing_integrator_cut($output,'<span class="forgot_password">','</span>');

	$zing_bt_content=$output;

	echo '<script type="text/javascript" language="javascript">';
	echo "var zing_bt_url='".ZING_BT_URL."ajax/';";
	echo "var zing_bt_index='".get_option('home')."/index.php?';";
	echo "function zing_bt_url_ajax(s) { return zing_bt_url+s; }";
	echo '</script>';

	echo '<link rel="stylesheet" type="text/css" href="' . ZING_BT_URL . 'bt.css" media="screen" />';
}

function zing_bt_mainpage() {
	$ids=get_option("zing_bt_pages");
	$ida=explode(",",$ids);
	return $ida[0];

}
function zing_bt_ob($buffer) {
	global $zing_bt_mode,$wpdb;
	$mantisbtself=str_replace('//','/','/'.ZING_MANTIS_SELF);
	$mantisbtfull=ZING_MANTIS_URL;
	$home=get_option("home")."/";
	$admin=get_option('siteurl').'/wp-admin/';
	$ids=get_option("zing_bt_pages");
	$ida=explode(",",$ids);
	$pid=zing_bt_mainpage();

	//css
	$buffer=str_replace($mantisbtfull.'css/default.css',ZING_BT_URL.'css/default.css',$buffer);

	//page header & footer
	$tagslist='head';
	$tags=explode(',',$tagslist);
	foreach ($tags as $tag)
	{
		$buffer=str_replace('<'.$tag,'<div id="mbt'.$tag.'"',$buffer);
		$buffer=str_replace($tag.'>','div>',$buffer);
	}
	$buffer=str_replace('<body','<div class="mbtbody"',$buffer);
	$buffer=str_replace('body>','div>',$buffer);

	$buffer=preg_replace('/<html.*>/','',$buffer);
	$buffer=preg_replace('/<.html>/','',$buffer);
	$buffer=preg_replace('/<meta.*>/','',$buffer);
	$buffer=preg_replace('/<title>.*<.title>/','',$buffer);
	$buffer=preg_replace('/<.DOCTYPE.*>/','',$buffer);

	if ($zing_bt_mode=="client") {
		$f[]='/src\="'.preg_quote('images/','/').'(.*?)"/';
		$r[]='src="'.ZING_MANTIS_URL.'/images/$1"';

		$f[]='/href\="'.preg_quote($mantisbtself,'/').'(.*?).php\?(.*?)"'.'/';
		$r[]='href="'.$home.'index.php?page_id='.$pid.'&zbt=$1&$2"';
		$f[]='/href\="'.preg_quote($mantisbtself,'/').'(.*?).php"'.'/';
		$r[]='href="'.$home.'index.php?page_id='.$pid.'&zbt=$1"';

		$f[]='/href\="'.preg_quote($mantisbtfull,'/').'(.*?).php"'.'/';
		$r[]='href="'.$home.'index.php?page_id='.$pid.'&zbt=$1"';
		$f[]='/href\="'.preg_quote($mantisbtfull,'/').'(.*?).php\?(.*?)"'.'/';
		$r[]='href="'.$home.'index.php?page_id='.$pid.'&zbt=$1&$2"';

		$f[]='/"([a-zA-Z\_]*?).php\?/';
		$r[]='"'.$home.'index.php?page_id='.$pid.'&zbt=$1&';

		$f[]='/"([a-zA-Z\_]*?).php\"/';
		$r[]='"'.$home.'index.php?page_id='.$pid.'&zbt=$1"';
			
		$f[]='/action\="'.preg_quote($mantisbtself,'/').'(.*?).php"'.'/';
		$r[]='action="'.$home.'index.php?page_id='.$pid.'&zbt=$1"';
		$f[]='/action\="'.preg_quote($mantisbtself,'/').'(.*?).php\?(.*?)"'.'/';
		$r[]='action="'.$home.'index.php?page_id='.$pid.'&zbt=$1&$2"';

		//replacement issue with src="/wordpress/wp-content/plugins/bug-tracker/mantisbt/images/rss.png" alt="RSS"
		$buffer=preg_replace($f,$r,$buffer,-1,$count);

		$buffer=str_replace('name="name"','name="bt_name"',$buffer);
	} else {
		//admin pages
	}

	return $buffer;
}
/**
 * Initialization of page, action & page_id arrays
 * @return unknown_type
 */
function zing_bt_init()
{
	global $zing_bt_mode;

	ob_start();
	if (!session_id()) @session_start();
	zing_bt_login();
	if (isset($_GET['zbt']))
	{
		$zing_bt_mode="client";
	}
	elseif (isset($_GET['zbtadmin']) || isset($_GET['module']))
	{
		$zing_bt_mode="admin";
	}
}

function zing_bt_login() {
	global $current_user;
	//if (is_user_logged_in()) {
	//	zing_bt_login_user($current_user->data->user_login,$current_user->data->user_pass);
	//} else {
	zing_bt_login_anonymous();
	//}
}

function zing_bt_login_anonymous() {
	$post['username']='anonymous';
	//$post['password']='anonymous';
	$post['secure_session']="1";
	$http=zing_bt_http("mantisbt",'login_page.php');
	$news = new btHTTPRequest($http);
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString(true,false);
		//echo $output;
		//die();
	}
	return true;
}

function zing_bt_login_user($login,$password) {
	$post['username']=$login;
	if ($password) $post['password']=btPassword($password);
	$post['secure_session']="1";
	$http=zing_bt_http("mantisbt",'login.php');
	$news = new btHTTPRequest($http);
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString(true,false);
		//echo $output;
		//die();
	}
	return true;
}

function zing_bt_login_admin() {
	global $current_user;

	$post['username']=get_option('zing_bt_admin_login');//$current_user->data->user_login;
	$post['password']=zing_bt_admin_password();
	$post['secure_session']="1";
	$http=zing_bt_http("mantisbt",'login.php');
	$news = new btHTTPRequest($http);
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString(true,false);
	}
}

function zing_bt_logout() {
	if (isset($_SESSION['tmpfile'])) {
		$ckfile=dirname(__FILE__).'/cache/'.$_SESSION['tmpfile'].md5($_SESSION['tmpfile']).'.tmp';
		unlink($ckfile);
		unset($_SESSION['tmpfile']);
	}
}

function zing_bt_check_password($check,$password,$hash,$user_id) {
	global $wpdb;

	$prefix=$wpdb->prefix."mantis_";

	if (!$check) { //the user could be using his old password, pre synchronisation
		$user =  new WP_User($user_id);
		$query = sprintf("SELECT * FROM `".$prefix."user_table` WHERE `username`='%s'", $user->data->user_login);
		$sql = mysql_query($query) or die(mysql_error());
		if ($row = mysql_fetch_array($sql)) {
			if ($row['password']==md5($password)) return true;
		}
		else return false;
	} else return $check;
}

function zing_bt_profile_update($user_id) {
	//if (class_exists('btusers')) return;
	$user=new WP_User($user_id);
	$btusers=new btusers();
	$group=$btusers->getBugTrackerGroup($user);
	$btusers->updateBugTrackerUser($user->data->user_login,btPassword($user->data->user_pass),$user->data->user_email,$group);
}

function zing_bt_user_register($user_id) {
	//error_reporting(E_ALL & ~E_NOTICE);
	//ini_set('display_errors', '1');
	//	if (class_exists('btusers')) return;
	$user=new WP_User($user_id);
	$btusers=new btusers();
	$group=$btusers->getBugTrackerGroup($user);
	$btusers->createBugTrackerUser($user->data->user_login,btPassword($user->data->user_pass),$user->data->user_email,$group);
}

function zing_bt_user_delete($user_id) {
	$user=new WP_User($user_id);
	$btusers=new btusers();
	$btusers->deleteBugTrackerUser($user->data->user_login);
}

function zing_bt_admin_password() {
	$login=get_option('zing_bt_admin_login');
	$user=new WP_User($login);
	$user_pass=btPassword($user->data->user_pass);
	return $user_pass;
}

//cron
function zing_bt_cron() {
	$dir = dirname(__FILE__).'/cache/';
	if ($handle = opendir($dir)) {
		while (false !== ($filename = readdir($handle))){
			if (substr($filename,0,1) != '.' && $filename != 'index.php' && filemtime($dir.$filename) < strtotime ("-2 days") ){
				unlink ($dir.$filename) ;
			}
		}
		closedir($handle);
	}
}
if (get_option("zing_bt_version")) {
	if (!wp_next_scheduled('zing_bt_cron_hook')) {
		wp_schedule_event( time(), 'hourly', 'zing_bt_cron_hook' );
	}
	add_action('zing_bt_cron_hook','zing_bt_cron');
}

?>