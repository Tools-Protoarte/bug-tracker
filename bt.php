<?php
/*  bt.php
 Copyright 2008,2009,2010 Erik Bogaerts
 Support site: http://www.zingiri.com

 This file is part of Bug Tracker.

 Bug Tracker is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Bug Tracker is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Bug Tracker; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
?>
<?php
/*
 Plugin Name: Bug Tracker
 Plugin URI: http://www.zingiri.com
 Description: Bug Tracker is a plugin that integrates the powerfull Mantis bug tracker software with Wordpress. It brings one of the most bug tracking softwares in reach of Wordpress users.

 Author: EBO
 Version: 0.2
 Author URI: http://www.zingiri.com/
 */

//error_reporting(E_ALL & ~E_NOTICE);
//ini_set('display_errors', '1');

define("ZING_BT_VERSION","0.2");
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

define("ZING_MANTIS_URL",ZING_BT_URL.ZING_MANTIS);

$zing_bt_version=get_option("zing_bt_version");
if ($zing_bt_version) {
	add_action("init","zing_bt_init");
	if (get_option('zing_bt_footer')=='Site') add_filter('wp_footer','zing_footers');
	add_filter('the_content', 'zing_bt_content', 10, 3);
	add_action('wp_head','zing_bt_header');
	add_action('wp_login','zing_bt_login');
	add_action('wp_logout','zing_bt_logout');

	add_filter('check_password','zing_bt_check_password',10,4);
	add_action('profile_update','zing_bt_profile_update'); //post wp update
	add_action('user_register','zing_bt_user_register'); //post wp update
	add_action('delete_user','zing_bt_user_delete');
}
register_activation_hook(__FILE__,'zing_bt_activate');
register_deactivation_hook(__FILE__,'zing_bt_deactivate');

require_once(dirname(__FILE__) . '/includes/errorlog.class.php');
require_once(dirname(__FILE__) . '/includes/shared.inc.php');
require_once(dirname(__FILE__) . '/includes/http.class.php');
require_once(dirname(__FILE__) . '/includes/footer.inc.php');
require_once(dirname(__FILE__) . '/includes/integrator.inc.php');
require_once(dirname(__FILE__) . '/bt_controlpanel.php');

$zErrorLog=new zErrorLog();

function zing_bt_check() {
	global $wpdb;
	$errors=array();
	$warnings=array();
	$files=array();
	$dirs=array();

	$files[]=dirname(__FILE__).'/log.txt';
	foreach ($files as $file) {
		if (!is_writable($file)) $warnings[]='File '.$file.' is not writable, please chmod to 666';
	}

	$dirs[]=dirname(__FILE__).'/cache';
	$dirs[]=dirname(__FILE__).'/mantisbt';
	foreach ($dirs as $file) {
		if (!is_writable($file)) $errors[]='Directory '.$file.' is not writable, please chmod to 777';
	}

	if (phpversion() < '5')	$warnings[]="You are running PHP version ".phpversion().". We recommend you upgrade to PHP 5.3 or higher.";
	if (ini_get("zend.ze1_compatibility_mode")) $warnings[]="You are running PHP in PHP 4 compatibility mode. We recommend you turn this option off.";
	if (!function_exists('curl_init')) $errors[]="You need to have cURL installed. Contact your hosting provider to do so.";
	else {
		ob_start();
		$c=new HTTPRequest(ZING_MANTIS_URL.'/zingiri.php');
		if (!$c->checkConnection()) $errors[]='Can\'t connect to MyBB sub folders, please check your .htaccess file.';
	}
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

	//first installation of MyBB
	if (!$zing_bt_version) {
		if (get_option('zing_bt_mantisbt_dbname')=='') {
			$zErrorLog->msg('Install bug tracker');
			zing_bt_mantisbt_install();
		} else {
			/*
			 $zErrorLog->msg('Connect forum');
			 $wpdb->select(get_option('zing_bt_mantisbt_dbname'));
			 $query="update ".get_option('zing_bt_mantisbt_dbprefix')."settings set value='".ZING_MANTIS_URL."' where name='bburl'";
			 $wpdb->query($query);
			 $query="update ".get_option('zing_bt_mantisbt_dbprefix')."settings set value='' where name='cookiedomain'";
			 $wpdb->query($query);
			 $user_login=get_option('zing_bt_admin_login');//$current_user->data->user_login;
			 $user_pass=zing_bt_admin_password();
			 $salt=create_sessionid(8);
			 $loginkey=create_sessionid(50);
			 $password=md5(md5($salt).md5($user_pass));
			 $query2=sprintf("UPDATE `".get_option('zing_bt_mantisbt_dbprefix')."users` SET `salt`='%s',`loginkey`='%s',`password`='%s' WHERE `username`='%s'",$salt,$loginkey,$password,$user_login);
			 $zErrorLog->msg($query2);
			 $wpdb->query($query2);
			 $wpdb->select(DB_NAME);
			 */
		}
		update_option("zing_mantisbt_version",ZING_MANTIS_VERSION);
	}
	//upgrade MyBB if needed
	elseif (get_option("zing_mantisbt_version") != ZING_MANTIS_VERSION) {
		$zErrorLog->msg('Upgrade forum');
		zing_bt_mantisbt_upgrade();
		update_option("zing_mantisbt_version",ZING_MANTIS_VERSION);
	}

	//upload Zingiri theme and stylesheets
	/*
	 if ($handle = opendir(dirname(__FILE__).'/db')) {
		$files=array();
		while (false !== ($file = readdir($handle))) {
		if (strstr($file,".sql")) {
		$f=explode("-",$file);

		$v=str_replace(".sql","",$f[1]);
		if ($zing_bt_version < $v) {
		$files[]=dirname(__FILE__).'/db/'.$file;
		}
		}
		}
		closedir($handle);
		asort($files);
		if (count($files) > 0) {
		if (get_option('zing_bt_mantisbt_dbname')) $wpdb->select(get_option('zing_bt_mantisbt_dbname'));
		foreach ($files as $file) {
		$file_content = file($file);
		$query = "";
		foreach($file_content as $sql_line) {
		$tsl = trim($sql_line);
		if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) {
		$sql_line = str_replace("CREATE TABLE `", "CREATE TABLE `".$prefix, $sql_line);
		$sql_line = str_replace("CREATE TABLE IF NOT EXISTS `", "CREATE TABLE `".$prefix, $sql_line);
		$sql_line = str_replace("INSERT INTO `", "INSERT INTO `".$prefix, $sql_line);
		$sql_line = str_replace("ALTER TABLE `", "ALTER TABLE `".$prefix, $sql_line);
		$sql_line = str_replace("UPDATE `", "UPDATE `".$prefix, $sql_line);
		$sql_line = str_replace("TRUNCATE TABLE `", "TRUNCATE TABLE `".$prefix, $sql_line);
		$query .= $sql_line;

		if(preg_match("/;\s*$/", $sql_line)) {
		$zErrorLog->msg($query);
		$wpdb->query($query);
		$query = "";
		}
		}
		}
		}
		if (get_option('zing_bt_mantisbt_dbname')) $wpdb->select(DB_NAME);
		}
		}

		*/
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
	$query2="UPDATE `".$prefix."user_table` SET `username`='".$current_user->data->user_login."' WHERE `username`='administrator'";
	$zErrorLog->msg($query2);
	$wpdb->query($query2);
	//$query2="UPDATE `".$prefix."settings` SET `value`='0' WHERE `name`='failedlogincount'";
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
	$news = new HTTPRequest($http);
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString(true,false);
		$zErrorLog->msg('out='.$output);
	}
}

function zing_bt_mantisbt_upgrade() {
}
/**
 * Deactivation: nothing to do
 * @return void
 */
function zing_bt_deactivate() {
}

/**
 * Uninstallation: removal of database tables
 * @return void
 */
function zing_bt_uninstall() {
	global $wpdb;
	$prefix=$wpdb->prefix."mantis";
	$rows=$wpdb->get_results("show tables like '".$prefix."%'",ARRAY_N);
	foreach ($rows as $id => $row) {
		$query="drop table ".$row[0];
		$wpdb->query($query);
	}
	$ids=get_option("zing_bt_pages");
	$ida=explode(",",$ids);
	foreach ($ida as $id) {
		wp_delete_post($id);
	}
	delete_option("zing_bt_version");
	delete_option("zing_bt_pages");
	delete_option("zing_mantisbt_version");
	$fh = fopen(dirname(__FILE__).'/'.ZING_MANTIS.'/inc/settings.php', 'w');
	fclose($fh);
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
			//echo 'Location:'.get_option('home').'/?page_id='.zing_bt_mainpage();
			header('Location:'.get_option('home').'/?page_id='.zing_bt_mainpage());
			die();
		}
		else {
			if ($_GET['action']=='logout') {
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
				return $content;
			}
			if (isset($cf['cat'])) {
				$_GET['cat']=$cf['cat'][0];
			}
			break;
	}
	if (strstr($zing_bt_to_include,'archive/index.php/')) $http=zing_bt_http("mantisbt",$zing_bt_to_include,$page);
	else $http=zing_bt_http("mantisbt",$zing_bt_to_include.'.php',$page);
	//echo '<br />'.$http.'<br />';
	$news = new HTTPRequest($http);

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
		if ($news->redirect) {
			//echo $output;
			//die('stop here');
			header($output);
		}
		if (empty($output)) {
			return 'redirect';
		}
		else return '<!--forum:start--><div><br /></div>'.$output.'<!--forum:end-->';
	}
}

function zing_bt_http($module,$to_include="index",$page="",$key="") {
	global $wpdb;

	$vars="";
	if (!$to_include) $to_include="index";
	$http=ZING_MANTIS_URL.'/';
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
	if (!strstr($to_include,'archive/index.php'))
	{
		$vars.=$and.'zing='.zing_urlencode(ZING_MANTIS_URL);
		if (get_option('zing_bt_mantisbt_dbname')) {
			//$vars.='&zing_dbhost='.get_option('zing_bt_mantisbt_dbhost');
			$vars.='&zing_dbname='.get_option('zing_bt_mantisbt_dbname');
			//$vars.='&zing_dbuser='.get_option('zing_bt_mantisbt_dbuser');
			//$vars.='&zing_dbpassword='.get_option('zing_bt_mantisbt_dbpassword');
			$vars.='&zing_dbprefix='.get_option('zing_bt_mantisbt_dbprefix');
		} else {
			if (isset($wpdb->base_prefix)) {
				$vars.='&prefix='.zing_urlencode($wpdb->prefix."mantis");
			}
		}
	}
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
	//zing_integrator_cut($output,'<div id="welcome">','</div>'); //remove admin login header
	zing_integrator_cut($output,'<span class="forgot_password">','</span>');

	$zing_bt_content=$output;

	echo '<script type="text/javascript" language="javascript">';
	echo "var zing_bt_url='".ZING_BT_URL."ajax/';";
	echo "var zing_bt_index='".get_option('home')."/index.php?';";
	//echo "function zing_url_enhance(s) { s=s.replace('.php?','&'); return zing_bt_index+'zbt='+s; }";
	echo "function zing_bt_url_ajax(s) { return zing_bt_url+s; }";
	echo '</script>';

	echo '<link rel="stylesheet" type="text/css" href="' . ZING_BT_URL . 'zing.css" media="screen" />';
}

function zing_bt_mainpage() {
	$ids=get_option("zing_bt_pages");
	$ida=explode(",",$ids);
	return $ida[0];

}
function zing_bt_ob($buffer) {
	global $zing_bt_mode,$wpdb;
	$self=str_replace('index.php','',$_SERVER['PHP_SELF']);
	$loc='http://'.$_SERVER['SERVER_NAME'];
	$mantisbtself=str_replace($loc,'',ZING_MANTIS_URL);
	$home=get_option("home")."/";
	$admin=get_option('siteurl').'/wp-admin/';
	$ids=get_option("zing_bt_pages");
	$ida=explode(",",$ids);
	$pid=zing_bt_mainpage();

	if ($zing_bt_mode=="forum") {
		//	$buffer=str_replace(ZING_MANTIS_URL.'/member.php?action=logout',wp_logout_url(),$buffer);
		//	$buffer=str_replace(ZING_MANTIS_URL.'/member.php?action=login',wp_login_url(get_permalink()),$buffer);
		//	$buffer=str_replace(ZING_MANTIS_URL.'/member.php?action=register',get_option('siteurl').'/wp-login.php?action=register',$buffer);
	} else {
		//	$buffer=str_replace('href="index.php?action=logout"','href="'.wp_logout_url().'"',$buffer);
	}

	//$buffer=str_replace(ZING_MANTIS_URL."/admin/index.php",$home."index.php?page_id=".$pid."&zbtadmin=index",$buffer);
	//$buffer=str_replace('href="'.ZING_MANTIS_URL,'href="'.get_option('home'),$buffer);

	//css
	//if (isset($wpdb->base_prefix)) $infix=str_replace($wpdb->base_prefix,"",$wpdb->prefix); else $infix="";
	//$buffer=str_replace(get_option('home').'/css.php?',ZING_MANTIS_URL.'/css.php?zing_prefix='.$infix.'&',$buffer);
	//$buffer=str_replace(get_option('home').'/css.php?',ZING_MANTIS_URL.'/css.php?zing_prefix='.$infix.'&',$buffer);
	$buffer=str_replace(get_option('home').'css/default.css',ZING_BT_URL.'css/default.css',$buffer);

	//$buffer=str_replace('forumdisplay.php?',"index.php?zbt=forumdisplay&",$buffer);
	//$buffer=preg_replace('/([A-Za-z0-9_]*).php.(.*)/','index.php?zbt=$1&$2',$buffer);

	// replace by zing_integrator_tags($buffer,$bodyclass)
	//page header & footer
	$tagslist='head';
	$tags=explode(',',$tagslist);
	foreach ($tags as $tag)
	{
		$buffer=str_replace('<'.$tag,'<div id="zing'.$tag.'"',$buffer);
		$buffer=str_replace($tag.'>','div>',$buffer);
	}
	$buffer=str_replace('<body','<div class="zingbody"',$buffer);
	$buffer=str_replace('body>','div>',$buffer);

	$buffer=preg_replace('/<html.*>/','',$buffer);
	$buffer=preg_replace('/<.html>/','',$buffer);
	$buffer=preg_replace('/<meta.*>/','',$buffer);
	$buffer=preg_replace('/<title>.*<.title>/','',$buffer);
	$buffer=preg_replace('/<.DOCTYPE.*>/','',$buffer);
	//images
	//$buffer=str_replace('src="images/','src="'.ZING_MANTIS_URL.'/images/',$buffer);
	//$buffer=str_replace('src="../images/','src="'.ZING_MANTIS_URL.'/images/',$buffer);

	//hide logo
	//$buffer=str_replace('class="logo"','class="logo" style="display:none"',$buffer);
	//$buffer=str_replace('id="logo"','id="logo" style="display:none"',$buffer);
	if ($zing_bt_mode=="client") {
		$f[]='/\"'.preg_quote($mantisbtself,'/').'\/(.*?).php\?'.'/';
		$r[]='"'.$home.'index.php?page_id='.$pid.'&zbt=$1&';

		$f[]='/href=\"'.preg_quote($mantisbtself,'/').'\/(.*?).php'.'\"/';
		$r[]='href="'.$home.'index.php?page_id='.$pid.'&zbt=$1"';

		$f[]='/'.preg_quote(ZING_MANTIS_URL,'/').'\/(.*?).php\?'.'/';
		$r[]='"'.$home.'index.php?page_id='.$pid.'&zbt=$1&';

		$f[]='/action=\"(.*?).php/';
		$r[]='action="'.$home.'index.php?page_id='.$pid.'&zbt=$1"';

		$f[]='/href=\"([a-zA-Z\_]*?).php/';
		$r[]='href="'.$home.'index.php?page_id='.$pid.'&zbt=$1"';

		$f[]='/\"'.preg_quote($mantisbtself,'/').'\/([a-z]*?)"'.'/';
		$r[]='"'.$home.'index.php?page_id='.$pid.'&zbt=$1"';

		$buffer=preg_replace($f,$r,$buffer,-1,$count);

		$buffer=str_replace('name="name"','name="bt_name"',$buffer);
		//$buffer=preg_replace($s,'action="hello',$buffer);
		//$buffer=str_replace($mantisbtself.'/',$home.'?zbt=',$buffer);
		//popup
		//$buffer=str_replace("MyBB.popupWindow('".ZING_MANTIS_URL,"MyBB.popupWindow('".ZING_BT_URL."ajax/",$buffer);

		//$buffer=str_replace($home.'cache/themes/',ZING_MANTIS_URL.'/cache/themes/',$buffer); //global.css etc
		//$buffer=str_replace("attachment.php",ZING_BT_URL.'attachment.php',$buffer);
		//special cases - quickLogin
		//$buffer=str_replace(ZING_MANTIS_URL."/member.php?action=login",wp_login_url($home."?page_id".$pid),$buffer);
		//$buffer=str_replace('onclick="MyBB.quickLogin(); return false;"','',$buffer);

		//pages
		/*
		 $pageslist='index,misc,announcements,calendar,editpost,forumdisplay,global,managegroup,member,memberlist,modcp,moderation,newreply,newthread,online,polls,portal,printthread,private,ratethread,report,reputation,rss,search,sendthread,showteam,showthread,stats,syndication,usercp,usercp2,warnings,xmlhttp';
		 $pages=explode(",",$pageslist);

		 $buffer=str_replace('"'.$home.'index.php"','"'.$home.'index.php?page_id='.$pid.'"',$buffer);
		 foreach ($pages as $page) {
			$buffer=str_replace(ZING_MANTIS_URL."/".$page.".php?",$home."index.php?page_id=".$pid."&zbt=".$page."&",$buffer);
			$buffer=str_replace(ZING_MANTIS_URL."/".$page.".php",$home."index.php?page_id=".$pid."&zbt=".$page,$buffer);
			}
			unset($pages[0]);
			foreach ($pages as $page) {
			$buffer=str_replace("./".$page.".php?",$home."index.php?page_id=".$pid."&zbt=".$page."&",$buffer);
			$buffer=str_replace($home.$page.".php?",$home."index.php?page_id=".$pid."&zbt=".$page."&",$buffer);
			$buffer=str_replace($home.$page.".php",$home."index.php?page_id=".$pid."&zbt=".$page,$buffer);
			$buffer=str_replace($page.".php?",$home."index.php?page_id=".$pid."&zbt=".$page."&",$buffer);
			$buffer=str_replace($page.".php",$home."index.php?page_id=".$pid."&zbt=".$page,$buffer);
			}
			*/
		//menu
		//$buffer=str_replace('<div id="header">','<div>',$buffer);

		//javascripts
		//$buffer=str_replace('../jscripts/',ZING_MANTIS_URL.'/jscripts/',$buffer);
		//$buffer=str_replace('./jscripts/',ZING_MANTIS_URL.'/jscripts/',$buffer);
		//$buffer=str_replace('src="jscripts/','src="'.ZING_MANTIS_URL.'/jscripts/',$buffer);

		//captcha
		//$buffer=str_replace('src="captcha.php?','src="'.ZING_MANTIS_URL.'/captcha.php?',$buffer);

		//redirect form
		//$buffer=str_replace('"index.php"','"'.$home.'index.php?page_id='.$pid.'"&zbt=index',$buffer);

		//$buffer=preg_replace('/archive(.*).html/','index.php?page_id='.$pid.'&zbt=archive$1',$buffer);
		//$buffer=str_replace($home.'archive/index.php',$home.'index.php?page_id='.$pid.'&zbt='.urlencode('archive/index'),$buffer);

		//help
		//$buffer=str_replace($home.'misc.php?action=help',$home."index.php?page_id=".$pid."&zbt=misc&action=help",$buffer);
	} else {
		//admin pages
		/*
		 $pageslist='index,member';
		 $mantisbt=ZING_MANTIS_URL."/admin/";
		 $pages=explode(",",$pageslist);
		 foreach ($pages as $page) {
			$buffer=str_replace($mantisbt.$page.".php?",$admin."admin.php?page=bug-tracker-admin&zbtadmin=".$page."&",$buffer);
			$buffer=str_replace($mantisbt.$page.".php",$admin."admin.php?page=bug-tracker-admin&zbtadmin=".$page,$buffer);
			$buffer=str_replace($self.'wp-content/plugins/bug-tracker/'.ZING_MANTIS.'/admin/'.$page.".php?",$admin."admin.php?page=bug-tracker-admin&zbtadmin=".$page.'&',$buffer);
			$buffer=str_replace('../'.$page.'.php?',$admin."admin.php?page=bug-tracker-admin&zbtadmin=".$page."&",$buffer);
			}

			$buffer=str_replace('index.php?module=',$admin.'admin.php?page=bug-tracker-admin&module=',$buffer);
			//admin style sheets
			$styles=array('default','sharepoint');
			foreach ($styles as $style) {
			$buffer=str_replace('./styles/'.$style.'/',ZING_MANTIS_URL.'/admin/styles/zingiri/',$buffer);
			$buffer=str_replace('styles/'.$style.'/',ZING_MANTIS_URL.'/admin/styles/zingiri/',$buffer);
			}
			//special cases
			$buffer=str_replace('"index.php"',$admin.'admin.php?page=bug-tracker-admin&zbtadmin=index',$buffer);
			//		$buffer=str_replace(ZING_MANTIS_URL,$home.'index.php?page_id='.$pid.'',$buffer);
			$buffer=str_replace('"'.get_option("home").'"','"'.$home.'index.php?page_id='.$pid.'"',$buffer);

			//iframe
			$buffer=str_replace('<iframe src="index.php?page_id='.$pid.'&module=tools/php_info','<iframe src="'.ZING_MANTIS_URL.'/admin/index.php?module=tools/php_info',$buffer);
			$buffer=str_replace('<iframe src="'.$admin.'admin.php?page=bug-tracker-admin','<iframe src="'.ZING_BT_URL.'ajax/admin/index.php?',$buffer);

			//javascripts
			$buffer=str_replace('../jscripts/',ZING_MANTIS_URL.'/jscripts/',$buffer);
			$buffer=str_replace('./jscripts/',ZING_MANTIS_URL.'/jscripts/',$buffer);
			$buffer=str_replace('/jscripts/tabs.js','/admin/jscripts/tabs.js',$buffer);
			$buffer=str_replace('<div id="menu">','<div id="zingmenu">',$buffer);

			//logout
			$buffer=str_replace('"index.php?action=logout"','"'.$admin.'admin.php?page=bug-tracker-admin&zbtadmin=index&action=logout"',$buffer);
			$buffer=str_replace('&amp;','&',$buffer);

			//direct login
			$buffer=str_replace('action="'.$mantisbtself.'/admin/index.php','action="'.$admin.'admin.php',$buffer);

			//redirect form
			$buffer=str_replace('"index.php"','"'.$admin.'admin.php?page=bug-tracker-cp"&zbt=index',$buffer);

			//task
			$buffer=str_replace(ZING_MANTIS_URL.'task.php',ZING_BT_URL.'ajax/task.php',$buffer);
			*/
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
	session_start();

	zing_bt_login();
	if (isset($_GET['zbt']))
	{
		$zing_bt_mode="forum";
	}
	elseif (isset($_GET['zbtadmin']) || isset($_GET['module']))
	{
		$zing_bt_mode="admin";
	}
}

function zing_bt_login() {
	global $current_user;
	if (is_user_logged_in()) {
		zing_bt_login_user($current_user->data->user_login,$current_user->data->user_pass);
	}
}

function zing_bt_login_user($login,$password) {
	$post['username']=$login;
	$post['password']=substr($password,1,25);
	$post['secure_session']="1";
	$http=zing_bt_http("mantisbt",'login.php');
	$news = new HTTPRequest($http);
	$news->post=$post;
	if ($news->live()) {
		$output=$news->DownloadToString(true,false);
	}
	return true;
}

function zing_bt_login_admin() {
	global $current_user;

	$post['username']=get_option('zing_bt_admin_login');//$current_user->data->user_login;
	$post['password']=zing_bt_admin_password();//substr($current_user->data->user_pass,1,25);
	$post['secure_session']="1";
	$http=zing_bt_http("mantisbt",'login.php');
	$news = new HTTPRequest($http);
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

	$prefix=$wpdb->prefix."mantis";

	if (!$check) { //the user could be using his old password, pre Web Shop to Wordpress migration
		$user =  new WP_User($user_id);
		$query = sprintf("SELECT * FROM `".$prefix."users` WHERE `username`='%s'", $user->data->user_login);
		$sql = mysql_query($query) or die(mysql_error());
		if ($row = mysql_fetch_array($sql)) {
			if ($row['password']==md5(md5($row['salt']).md5($password))) return true;
		}
		else return false;
	} else return $check;
}

function zing_bt_profile_update($user_id) {
	if (class_exists('wpusers')) return;
	require(dirname(__FILE__).'/includes/wpusers.class.php');
	$user=new WP_User($user_id);
	$wpusers=new wpusers();
	$group=$wpusers->getBugTrackerGroup($user);
	$wpusers->updateBugTrackerUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
}

function zing_bt_user_register($user_id) {
	//error_reporting(E_ALL & ~E_NOTICE);
	//ini_set('display_errors', '1');
	if (class_exists('wpusers')) return;
	require(dirname(__FILE__).'/includes/wpusers.class.php');
	$user=new WP_User($user_id);
	$wpusers=new wpusers();
	$group=$wpusers->getBugTrackerGroup($user);
	$wpusers->createBugTrackerUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
}

function zing_bt_user_delete($user_id) {
	require(dirname(__FILE__).'/includes/wpusers.class.php');
	$user=new WP_User($user_id);
	$wpusers=new wpusers();
	$wpusers->deleteBugTrackerUser($user->data->user_login);
}

function zing_bt_admin_password() {
	$login=get_option('zing_bt_admin_login');
	$user=new WP_User($login);
	$user_pass=substr($user->data->user_pass,1,25);
	return $user_pass;
}
?>