<?php

$btusers=new btusers();

class btusers {
	var $prefix;
	var $base_prefix;
	var $wpAdmin=false;
	var $wpCustomer=false;
	var $dbname;

	function btusers() {
		global $wpdb;
		if (isset($wpdb->base_prefix)) $this->base_prefix=$wpdb->base_prefix;
		else $this->base_prefix=$wpdb->prefix;
			$this->prefix=$wpdb->prefix."mantis_";
			$this->dbname=DB_NAME;
		$this->wpAdmin=true;
		$this->wpCustomer=true;
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

		//sync Bug tracker to Wordpress - Wordpress is master so we're not changing roles in Wordpress
		$bbUsers=$this->getBugTrackerUsers();
		foreach ($bbUsers as $row) {
			$zErrorLog->log(0,'Sync Bug tracker to WP: '.$row['username']);
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
		//sync Wordpress to Bug tracker - Wordpress is master so we're updating roles in Bug tracker
		$users=$this->getWpUsers();
		foreach ($users as $id) {
			$user=new WP_User($id);
			$zErrorLog->log(0,'Sync WP to Bug tracker: '.$id.'/'.$user->data->display_name);
			if (!isset($user->data->first_name)) $user->data->first_name=$user->data->display_name;
			if (!isset($user->data->last_name)) $user->data->last_name=$user->data->display_name;
			$group=$this->getBugTrackerGroup($user);
			if (!$this->existsBugTrackerUser($user->data->user_login)) { //create user
				$this->createBugTrackerUser($user->data->user_login,btPassword($user->data->user_pass),$user->data->user_email,$group);
			} else { //update user
				$this->updateBugTrackerUser($user->data->user_login,btPassword($user->data->user_pass),$user->data->user_email,$group);
			}
		}
	}

	function getBugTrackerUsers() {
		global $wpdb;
		$rows=array();

		$wpdb->select($this->dbname);
		$query="select * from `##user_table` where `username`<>'anonymous'";
		$query=str_replace("##",$this->prefix,$query);
		$sql = mysql_query($query) or die(mysql_error());
		while ($row = mysql_fetch_array($sql)) {
			$rows[]=$row;
		}
		$wpdb->select(DB_NAME);
		return $rows;
	}

	function getBugTrackerGroup($user) {
		if ($user->has_cap('level_10')) {
			$group='90'; //admins
		} elseif ($user->has_cap('level_5')) {
			$group='55'; //moderators
		} else {
			$group='25'; //registered
		}
		return $group;
	}

	function currentBugTrackerUser() {
		global $current_user;
		global $wpdb;

		$wpdb->select($this->dbname);
		$query=sprintf("SELECT * FROM `".$this->prefix."user_table` WHERE `username`='".$current_user->data->user_login."'");
		$sql = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($sql);
		$wpdb->select(DB_NAME);
		return $row;
	}

	function existsBugTrackerUser($login) {
		global $wpdb;

		$wpdb->select($this->dbname);
		$query2=sprintf("SELECT `id` FROM `".$this->prefix."user_table` WHERE `username`='%s'",$login);
		$sql2 = mysql_query($query2) or die(mysql_error());
		if (mysql_num_rows($sql2) == 0) $exists=false;
		else $exists=true;
		$wpdb->select(DB_NAME);
		return $exists;
	}

	function getBugTrackerUser($login) {
		global $wpdb;

		$wpdb->select($this->dbname);
		$query=sprintf("SELECT * FROM `".$this->prefix."user_table` WHERE `username`='".$login."'");
		$sql = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($sql);
		$wpdb->select(DB_NAME);
		return $row;
	}

	function createBugTrackerUser($username,$password,$email,$group) {
		global $zErrorLog,$wpdb;

		$prefix = $wpdb->prefix."mantis_";
		$realname=$username;
			
		$zErrorLog->log(0,'Create Bug tracker user '.$username);
			
		$t_val = mt_rand( 0, mt_getrandmax() ) + mt_rand( 0, mt_getrandmax() );
		$t_val = md5( $t_val ) . md5( time() );

		$query= sprintf("INSERT INTO `".$prefix."user_table` (`username`, `realname`, `email`, `password`, `enabled`, `protected`, `access_level`, `date_created`, `cookie_string`)
	VALUES('%s', '%s', '%s', '%s', 1, 1, '%s', %s, '%s')",
		$username,$realname,$email,md5($password),$group,time(),$t_val);
		$wpdb->query($query);
	}

	function updateBugTrackerUser($user_login,$password,$user_email,$group) {
		global $wpdb,$zErrorLog;

		$wpdb->select($this->dbname);
		$query2=sprintf("UPDATE `".$this->prefix."user_table` SET `password`='%s' WHERE `username`='%s'",md5($password),$user_login);
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

	function deleteBugTrackerUser($login) {
		global $zErrorLog,$wpdb;

		$query=sprintf("DELETE FROM `".$this->prefix."user_table` WHERE `username`='%s'",$login);
		$wpdb->query($query);
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

function createBugTrackerUser($username,$password,$email,$realname='') {
	global $wpdb;

	$btusers=new btusers();
	$btusers->createBugTrackerUser($username,$password,$email,'10');
}

function btPassword($password) {
	return substr($password,1,25);
}
?>