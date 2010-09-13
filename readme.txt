=== Bug Tracker ===
Contributors: EBO
Donate link: http://www.zingiri.com/
Tags: forum, bulletin board, support, discussion, social engine, groups, subscribe
Requires at least: 2.1.7
Tested up to: 3.0.1
Stable tag: 0.2

Bug Tracker is a plugin that integrates the powerfull Mantis Bug Tracker software with Wordpress.
== Description ==

Bug Tracker is a plugin that integrates the powerfull myBB bulletin board software with Wordpress. It brings one of the most powerfull free bug tracking softwares in reach of Wordpress users.

[MantisBT](http://www.mantisbt.org/ "Mantis") is a free popular  web-based bugtracking system.

WordPress ... well you know.

Bug Tracker provides the glue to connect both providing a fully functional proven forum & bulletin board solution. 

== Installation ==

1. Upload the `bug-tracker` folder to the `/wp-content/plugins/` directory
2. Ensure the directory /bug-tracker/cache is writable (chmod 777)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to the Wordpress Settings page and find the link to the Admininistration Panel of Bug Tracker, login with the default user admin and password admin.

Please visit the [Zingiri](http://forums.zingiri.com/forumdisplay.php?fid=23 "Zingiri Support Forum") for more information and support.

== Frequently Asked Questions ==

Please visit the [Zingiri](http://forums.zingiri.com/forumdisplay.php?fid=23 "Zingiri Support Forum") for more information and support.

== Screenshots ==

Please visit the [Zingiri](http://zingiri.com/demo/forum "Zingiri") website for a Demo.

== Upgrade Notice ==

Simply go to the Wordpress Settings page for the plugins and click the Upgrade button.

== MyBB Hacks ==

This section provides a quick overview of MyBB files that had to be modified to integrate it seamlessly with Wordpress

* admin/styles/zingiri: custom styles
* inc/wp-settings.php: path set in config.php
* inc/settings.php
* inc/config.php: force $settings['bburl'] with $_GET['zing']
* jscripts/thread.js: ajax request, pass full http
* wp-attachment.php

== Changelog ==

= 0.2 =
* Alpha release

= 0.1 =
* Mantis BT 1.2.2