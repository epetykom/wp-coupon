<?php
/*
Include this file at the end of your plugin, then create a new instance of the Plugin_Register class. Here's some sample code:

// include the Plugin_Register class
require_once( "plugin-register.class.php" );

// create a new instance of the Plugin_Register class
$register = new Plugin_Register(); // leave this as it is
$register->file = __FILE__; // leave this as it is
$register->slug = "pluginregister"; // create a unique slug for your plugin (normally the plugin name in lowercase, with no spaces or special characters works fine)
$register->name = "Plugin Register"; // the full name of your plugin (this will be displayed in your statistics)
$register->version = "1.0"; // the version of your plugin (this will be displayed in your statistics)
$register->developer = "Chris Taylor"; // your name
$register->homepage = "http://www.stillbreathing.co.uk"; // your Wordpress website where Plugin Register is installed (no trailing slash)

// the next two lines are optional
// 'register_plugin' is the message you want to be displayed when someone has activated this plugin. The %1 is replaced by the correct URL to register the plugin (the %1 MUST be the HREF attribute of an <a> element)
$register->register_message = 'Hey! Thanks! <a href="%1">Register the plugin here</a>.';
// 'thanks_message' is the message you want to display after someone has registered your plugin
$register->thanks_message = "That's great, thanks a million.";

$register->Plugin_Register(); // leave this as it is
*/
if ( !class_exists( "Plugin_Register" ) ) {
	class Plugin_Register {
		var $slug = "";
		var $developer = "the developer";
		var $version = "";
		var $homepage = "#";
		var $name = "";
		var $file = "";
		var $register_message = "";
		var $thanks_message = "";
		function Plugin_Register() {
			@session_start();
			register_activation_hook( $this->file, array( $this, "Activated" ) );
		}
		function Activated() {
			if ( $this->slug != "" && $this->name != "" && $this->version != "" ) {
				$_SESSION["activated_plugin"] = $this->slug;
			}
		}
	}
}