<?php

$wpusers=new wpusers();

class wpusers {
	var $prefix;
	var $base_prefix;
	var $wpAdmin=false;
	var $wpCustomer=false;
	var $dbname;

	function wpusers() {
		global $wpdb;
		if (isset($wpdb->base_prefix)) $this->base_prefix=$wpdb->base_prefix;
		else $this->base_prefix=$wpdb->prefix;
		if ($n=get_option('zing_forum_mantisbt_dbname')) {
			$this->prefix=get_option('zing_forum_mantisbt_dbprefix');
			$this->dbname=get_option('zing_forum_mantisbt_dbname');
		} else {
			$this->prefix=$wpdb->prefix."mantis_";
			$this->dbname=DB_NAME;
		}
		if (get_option('zing_forum_login') == "WP") {
			$this->wpAdmin=true;
			$this->wpCustomer=true;
		}
	}

	function getWpUsers() {
		global $wpdb,$blog_id;
		$users=array();
		$u=get_users_of_blog($blog_id);
		foreach ($u as $o) {
			$users[$o->user_login]=$o->user_id;
		}
		return $users;
	}

	function sync() {
		global $wpdb,$blog_id;
		global $zErrorLog;
		
		if (!$this->wpAdmin) return;

		$wpdb->show_errors();
		$users=$this->getWpUsers();
		//print_r($users);

		//sync Forum to Wordpress - Wordpress is master so we're not changing roles in Wordpress
		$bbUsers=$this->getForumUsers();
		foreach ($bbUsers as $row) {
			$zErrorLog->log(0,'Sync Forum to WP: '.$row['username']);
			if ($row['access_level']=='90') $role='editor';
			else $role='subscriber';
			$query2=sprintf("SELECT `ID` FROM `".$this->base_prefix."users` WHERE `user_login`='%s'",$row['username']);
			$sql2 = mysql_query($query2) or die(mysql_error());
			if (mysql_num_rows($sql2) == 0) { //WP user doesn't exist
				$data=array();
				$data['user_login']=$row['username'];
				$data['user_email']=$row['email'];
				$data['user_pass']='';
				$id=$this->createWpUser($data,$role);
				if (function_exists('add_user_to_blog')) {
					add_user_to_blog($blog_id,$id,$role);
				}
			}
		}
		//sync Wordpress to Forum - Wordpress is master so we're updating roles in Forum
		$users=$this->getWpUsers();
		foreach ($users as $id) {
			$user=new WP_User($id);
			$zErrorLog->log(0,'Sync WP to Forum: '.$id.'/'.$user->data->display_name);
			if (!isset($user->data->first_name)) $user->data->first_name=$user->data->display_name;
			if (!isset($user->data->last_name)) $user->data->last_name=$user->data->display_name;
			$group=$this->getForumGroup($user);
			if (!$this->existsForumUser($user->data->user_login)) { //create user
				$this->createForumUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
			} else { //update user
				$this->updateForumUser($user->data->user_login,$user->data->user_pass,$user->data->user_email,$group);
			}
		}
	}

	function getForumUsers() {
		global $wpdb;
		$rows=array();

		$wpdb->select($this->dbname);
		$query="select * from `##user_table`";
		$query=str_replace("##",$this->prefix,$query);
		$sql = mysql_query($query) or die(mysql_error());
		while ($row = mysql_fetch_array($sql)) {
			//$query_group=sprintf("SELECT * FROM `".$this->prefix."usergroups` WHERE `gid`='%s'",$row['usergroup']);
			//$sql_group = mysql_query($query_group) or die(mysql_error());
			//if ($row_group = mysql_fetch_array($sql_group)) {
				//$row['group']=$row_group;
			//}
			$rows[]=$row;
		}
		$wpdb->select(DB_NAME);
		return $rows;
	}

	function getForumGroup($user) {
		//echo 'ok';
		if ($user->has_cap('level_10')) {
			$group='90'; //admins
		} elseif ($user->has_cap('level_5')) {
			$group='55'; //moderators
		} else {
			$group='25'; //registered
		}
		return $group;
	}

	function currentForumUser() {
		global $current_user;
		global $wpdb;
		
		$wpdb->select($this->dbname);
		$query=sprintf("SELECT * FROM `".$this->prefix."user_table` WHERE `username`='".$current_user->data->user_login."'");
		$sql = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($sql);
		$wpdb->select(DB_NAME);
		return $row;
	}

	function existsForumUser($login) {
		global $wpdb;

		$wpdb->select($this->dbname);
		$query2=sprintf("SELECT `id` FROM `".$this->prefix."user_table` WHERE `username`='%s'",$login);
		$sql2 = mysql_query($query2) or die(mysql_error());
		if (mysql_num_rows($sql2) == 0) $exists=false;
		else $exists=true;
		$wpdb->select(DB_NAME);
		return $exists;
	}

	function getForumUser($login) {
		global $wpdb;
		
		$wpdb->select($this->dbname);
		$query=sprintf("SELECT * FROM `".$this->prefix."user_table` WHERE `username`='".$login."'");
		$sql = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($sql);
		$wpdb->select(DB_NAME);
		return $row;
	}
	
	function createForumUser($username,$password,$email,$group) {
		global $zErrorLog;
		
		zing_bt_login_admin();
		$admin=$this->getForumUser(get_option('zing_forum_admin_login'));
		
		$zErrorLog->log(0,'Create Forum user '.$username);
		$post['username']=$username;
		$post['realname']=$username;
		$post['email']=$email;
		$pos['access_level']=$group;
		//10 viewer
		//25 reporter
		//40 updater
		//55 developer
		//70 manager
		//90 administrator
		$post['enabled']=1;
		$post['password']=$post['password_verify']=substr($password,1,25);
		$http=zing_bt_http("mantisbt",'manage_user_create_page.php');
		$news = new HTTPRequest($http);
		$news->post=$post;
		if ($news->live()) {
			$output=$news->DownloadToString(true,false);
			$zErrorLog->log(0,'out='.$output.'=');
		}
	}

	function updateForumUser($user_login,$user_pass,$user_email,$group) {
		global $wpdb,$zErrorLog;
		
		$zErrorLog->log(0,'Update Forum user '.$username);
		$password=md5(substr($user_pass,1,25));

		$wpdb->select($this->dbname);
		//$query2=sprintf("UPDATE `".$this->prefix."user_table` SET `usergroup`='%s',`salt`='%s',`loginkey`='%s',`password`='%s' WHERE `username`='%s'",$group,$salt,$loginkey,$password,$user_login);
		$query2=sprintf("UPDATE `".$this->prefix."user_table` SET `password`='%s' WHERE `username`='%s'",$password,$user_login);
		$zErrorLog->log(0,$query2);
		$wpdb->query($query2);
		$wpdb->select(DB_NAME);
	}

	function createWpUser($user,$role) {
		global $wpdb,$zErrorLog;
		
		$zErrorLog->log(0,'Create WP user '.$user);
		require_once(ABSPATH.'wp-includes/registration.php');
		$user['role']=$role;
		$id=wp_insert_user($user);
		return $id;
	}

	function deleteForumUser($login) {
		global $zErrorLog;
		
		$user=$this->getForumUser($login);
		$admin=$this->getForumUser(get_option('zing_forum_admin_login'));
		$zErrorLog->log(0,'Delete Forum user '.$user);
		
		//$post['username']=$username;
		//$post['password']=$post['confirm_password']=substr($password,1,25);
		//$post['email']=$email;
		//$post['usergroup']=$group;
		//$post['displaygroup']=0;
		$post['submit']='Yes';
		$post['my_post_key']=md5($admin['loginkey'].$admin['salt'].$admin['regdate']);
		$_GET['module']='user/users';
		$_GET['action']='delete';
		$_GET['uid']=$user['uid'];
		$http=zing_bt_http("mantisbt",'admin/index.php');
		$zErrorLog->log(0,$http);
		$news = new HTTPRequest($http);
		$news->post=$post;
		if ($news->live()) {
			$output=$news->DownloadToString(true,false);
			$zErrorLog->log(0,'out='.$output.'=');
		}
		
	}
	
	/*
	function updateWpUser($user,$role) {
		require_once(ABSPATH.'wp-includes/registration.php');
		global $wpdb;
		$olduser=get_userdatabylogin($user['user_login']);
		$id=$user['ID']=$olduser->ID;
		$user['role']=$role;
		$user['user_pass']=wp_hash_password($user['user_pass']);
		wp_insert_user($user);
	}
	*/

	function loggedIn() {
		if ($this->wpAdmin && is_user_logged_in()) return true;
		else return false;
	}

	function isAdmin() {
		if ($this->wpAdmin && (current_user_can('edit_plugins')  || current_user_can('edit_pages'))) return true;
		else return false;
	}

	function loginWpUser($login,$pass) {
		wp_signon(array('user_login'=>$login,'user_password'=>$pass));
	}
}

?>