<?php
/**
 * @package wp-coupon
 * @author qnub
 * @version 0.1
 */
/*
Plugin Name: wp-coupon
Plugin URI: https://github.com/qnub/wp-coupon
Description: wp-coupon allows you to offer downloadable, printable coupons from your Wordpress site. coupons can be available to anyone, or require a name and email address before they can be downloaded.
Author: qnub, based on VoucherPress plugin by Chris Taylor
Version: 0.1
Author URI: http://www.google.com/profiles/qnub.ru
*/

// set the current version
function wp_coupon_current_version() {
	return "1.2";
}

//define("wp-couponDEV", true);

// set activation hook
register_activation_hook( __FILE__, "wp_coupon_activate" );
register_deactivation_hook( __FILE__, "wp_coupon_deactivate" );

// initialise the plugin
wp_coupon_init();

// ==========================================================================================
// initialisation functions

function wp_coupon_init() {
	if ( function_exists( "add_action" ) ) {
		// add template redirect action
		add_action( "template_redirect", "wp_coupon_template" );
		// add the admin menu
		add_action( "admin_menu", "wp_coupon_add_admin" );
		// add the create coupon function
		add_action( "admin_menu", "wp_coupon_check_create_coupon" );
		// add the edit coupon function
		add_action( "admin_menu", "wp_coupon_check_edit_coupon" );
		// add the debug coupon function
		add_action( "admin_menu", "wp_coupon_check_debug_coupon" );
		// add the admin preview function
		add_action( "admin_menu", "wp_coupon_check_preview" );
		// add the admin email download function
		add_action( "admin_menu", "wp_coupon_check_download" );
		// add the admin head includes
		if ( substr( @$_GET["page"], 0, 7 ) == "coupons" ) {
			add_action( "admin_head", "wp_coupon_admin_css" );
			add_action( "admin_head", "wp_coupon_admin_js" );
		}
		// setup shortcodes
		if ( !is_admin() ) {
			//add_filter('widget_text', 'coupon_do_coupon_shortcode', 11);
			//add_filter('widget_text', 'coupon_do_coupon_form_shortcode', 11);
			//add_filter('widget_text', 'coupon_do_list_shortcode', 11);
		}
		// [coupon id="" preview=""]
		add_shortcode( 'coupon', 'coupon_do_coupon_shortcode' );
		// [couponform id=""]
		add_shortcode( 'couponform', 'coupon_do_coupon_form_shortcode' );
		// [couponlist]
		add_shortcode( 'couponlist', 'coupon_do_list_shortcode' );
	}
}

function wp_coupon_template() {
	// if requesting a coupon
	if ( isset( $_GET["coupon"] ) && $_GET["coupon"] != "" )
	{
		// get the details
		$coupon_guid = $_GET["coupon"];
		$download_guid = @$_GET["guid"];

		// check the template exists
		if ( wp_coupon_coupon_exists( $coupon_guid ) ) {
			// if the email addres supplied is valid
			if ( wp_coupon_download_guid_is_valid( $coupon_guid, $download_guid ) != "unregistered" ) {
				// download the coupon
				wp_coupon_download_coupon( $coupon_guid, $download_guid );
			} else {
				// show the form
				wp_coupon_register_form( $coupon_guid );
			}
			exit();
		}
		wp_coupon_404();
	}
}

// show a 404 page
function wp_coupon_404($found=true) {
	global $wp_query;
	$wp_query->set_404();
	//if ( file_exists( TEMPLATEPATH.'/404.php' ) ) {
	//	require TEMPLATEPATH.'/404.php';
	//} else {
		if ($found) {
			wp_die( __( "Sorry, that item is not available", "wp-coupon" ) );
		} else {
			wp_die( __( "Sorry, that item was not found", "wp-coupon" ) );
		}
	//}
	exit();
}

// show an expired coupon page
function wp_coupon_expired() {
	global $wp_query;
	$wp_query->set_404();
	//if ( file_exists( TEMPLATEPATH.'/404.php' ) ) {
	//	require TEMPLATEPATH.'/404.php';
	//} else {
		wp_die( __( "Sorry, that item has expired", "wp-coupon" ) );
	//}
	exit();
}

// show a run out coupon page
function wp_coupon_runout() {
	global $wp_query;
	$wp_query->set_404();
	//if ( file_exists( TEMPLATEPATH.'/404.php' ) ) {
	//	require TEMPLATEPATH.'/404.php';
	//} else {
		wp_die( __( "Sorry, that item has run out", "wp-coupon" ) );
	//}
	exit();
}

// show a downloaded coupon page
function wp_coupon_downloaded() {
	global $wp_query;
	$wp_query->set_404();
	//if ( file_exists( TEMPLATEPATH.'/404.php' ) ) {
	//	require TEMPLATEPATH.'/404.php';
	//} else {
		wp_die( __( "You have already downloaded this coupon", "wp-coupon" ) );
	//}
	exit();
}

// ==========================================================================================
// activation functions

// activate the plugin
function wp_coupon_activate() {
	// if PHP is less than version 5
	if ( version_compare( PHP_VERSION, '5.0.0', '<' ) )
	{
		echo '
		<div id="message" class="error">
			<p><strong>' . __( "Sorry, your PHP version must be 5 or above. Please contact your server administrator for help.", "wp-coupon" ) . '</strong></p>
		</div>
		';
	} else {
		//check install
		wp_coupon_check_install();
		// save options
		$data = array(
			"register_title" => "Enter your email address",
			"register_message" => "You must supply your name and email address to download this coupon. Please enter your details below, a link will be sent to your email address for you to download the coupon.",
			"email_label" => "Your email address",
			"name_label" => "Your name",
			"button_text" => "Request coupon",
			"bad_email_message" => "Sorry, your email address seems to be invalid. Please try again.",
			"thanks_message" => "Thank you, a link has been sent to your email address for you to download this coupon.",
			"coupon_not_found_message" => "Sorry, the coupon you are looking for cannot be found."
			);
		// add options
		add_option ( "wp_coupon_data", maybe_serialize( $data ) );
		add_option ( "wp_coupon_version", wp_coupon_current_version() );
	}
}

// deactivate the plugin
function wp_coupon_deactivate() {
	// delete options
	delete_option( "wp_coupon_data" );
	delete_option( "wp_coupon_version" );
}

// insert the default templates
function wp_coupon_insert_templates() {
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix )  ) { $prefix = $wpdb->base_prefix; }
	$templates = $wpdb->get_var( "select count(name) from " . $prefix . "wp_coupon_templates;" );
	if ($templates == 0) {
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Plain black border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Mint chocolate', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Red floral border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Single red rose (top left)', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Red flowers', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Pink flowers', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Abstract green bubbles', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('International post', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Gold ribbon', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Monochrome bubble border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Colourful swirls', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Red gift bag', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Blue ribbon', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Autumn floral border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Yellow gift boxes', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Wrought iron border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Abstract rainbow flowers', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Christmas holly border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Small gold ribbon', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Small red ribbon', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('White gift boxes', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Glass flowers border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Single red rose (bottom centre)', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Fern border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Blue floral watermark', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Monochrome ivy border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Ornate border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Winter flower corners', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Spring flower corners', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Pattern border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Orange flower with bar', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Small coat of arms', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Grunge border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Coffee beans', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Blue gift boxes', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Spring flowers border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Ornate magenta border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Mexico border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Chalk border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Thick border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Dark chalk border', 1, 0);");
		$wpdb->query("insert into " . $prefix . "wp_coupon_templates (name, live, blog_id) values ('Ink border', 1, 0);");
	}
}

// check wp-coupon is installed correctly
function wp_coupon_check_install() {
	// create tables
	wp_coupon_create_tables();
	// sleep for 1 second to allow the tables to be created
	sleep(1);
	// if there are no templates saved
	$templates = wp_coupon_get_templates();
	if ( !$templates || !is_array($templates) || count($templates) == 0 ) {
		// insert the default templates
		wp_coupon_insert_templates();
	}
	// check the templates directory is writeable
	if ( !@is_writable( ABSPATH . "/wp-content/plugins/wp-coupon/templates/" ) ) {
		echo '
		<div id="message" class="warning">
			<p><strong>' . __( "The system does not have write permissions on the folder (" . ABSPATH . "/wp-content/plugins/wp-coupon/templates/) where your custom templates are stored. You may not be able to upload your own templates. Please contact your system administrator for more information.", "wp-coupon" ) . '</strong></p>
		</div>
		';
	}
}

// get the currently installed version
function wp_coupon_get_version() {
	if ( function_exists( "get_site_option" ) ) {
		return get_site_option( "wp_coupon_version" );
	} else {
		return get_option( "wp_coupon_version" );
	}
}

// update the currently installed version
function wp_coupon_update_version() {
	$version = wp_coupon_current_version();
	if ( function_exists( "get_site_option" ) ) {
		update_site_option( "wp_coupon_version", $version );
	} else {
		return update_option( "wp_coupon_version", $version );
	}
}

// delete the currently installed version flag
function wp_coupon_delete_version() {
	if ( function_exists( "get_site_option" ) ) {
		delete_site_option( "wp_coupon_version" );
	} else {
		return delete_option( "wp_coupon_version");
	}
}

// create the tables
function wp_coupon_create_tables() {
	
	// check the current version
	if ( version_compare( wp_coupon_get_version(), wp_coupon_current_version() ) == -1 || defined( "wp-couponDEV" ) )
	{
	
		global $wpdb;
		$prefix = $wpdb->prefix;
		if ( isset( $wpdb->base_prefix )  ) { $prefix = $wpdb->base_prefix; }

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		// table to store the coupons
		$sql = "CREATE TABLE " . $prefix . "wp_coupon_coupons (
			  id mediumint(9) NOT NULL AUTO_INCREMENT,
			  blog_id mediumint NOT NULL,
			  time bigint(11) DEFAULT '0' NOT NULL,
			  name VARCHAR(50) NOT NULL,
			  `text` varchar(250) NOT NULL,
			  `description` TEXT NULL,
			  terms varchar(500) NOT NULL,
			  template varchar(55) NOT NULL,
			  font varchar(55) DEFAULT 'helvetica' NOT NULL,
			  require_email TINYINT DEFAULT 1 NOT NULL,
			  `limit` MEDIUMINT(9) NOT NULL DEFAULT 0,
			  guid varchar(36) NOT NULL,
			  live TINYINT DEFAULT '0',
			  expiry int DEFAULT '0',
			  codestype varchar(12) DEFAULT 'random',
			  codeprefix varchar(6) DEFAULT '',
			  codesuffix varchar(6) DEFAULT '',
			  codelength int DEFAULT 6,
			  codes MEDIUMTEXT NOT NULL DEFAULT '',
			  deleted tinyint DEFAULT '0',
			  PRIMARY KEY  id (id)
			);";
		dbDelta( $sql );
		
		// table to store downloads
		$sql = "CREATE TABLE " . $prefix . "wp_coupon_downloads (
			  id mediumint(9) NOT NULL AUTO_INCREMENT,
			  couponid mediumint(9) NOT NULL,
			  time bigint(11) DEFAULT '0' NOT NULL,
			  ip VARCHAR(15) NOT NULL,
			  name VARCHAR(55) NULL,
			  email varchar(255) NULL,
			  guid varchar(36) NOT NULL,
			  code varchar(255) NOT NULL,
			  downloaded TINYINT DEFAULT '0',
			  PRIMARY KEY  id (id)
			);";
		dbDelta( $sql );
		
		// table to store templates
		$sql = "CREATE TABLE " . $prefix . "wp_coupon_templates (
			  id mediumint(9) NOT NULL AUTO_INCREMENT,
			  blog_id mediumint NOT NULL,
			  time bigint(11) DEFAULT '0' NOT NULL,
			  name VARCHAR(55) NOT NULL,
			  live tinyint DEFAULT '1',
			  PRIMARY KEY  id (id)
			);";
		dbDelta( $sql );
		
		// if there were no coupon download guids found, move the codes
		$sql = "select count(id) from " . $prefix . "wp_coupon_downloads where code <> '';";
		$codes = (int)$wpdb->get_var( $sql );
		if ( $codes == 0 ) {
			$sql = "update " . $prefix . "wp_coupon_downloads set code = guid;";
			$wpdb->query( $sql );
			$sql = "update " . $prefix . "wp_coupon_downloads set guid = '';";
			$wpdb->query( $sql );
		}
		
		// update the version
		wp_coupon_update_version();
	
	}

}

// ==========================================================================================
// general admin function

// add the menu items
function wp_coupon_add_admin()
{
	add_menu_page( __( "coupons", "wp-coupon" ), __( "coupons", "wp-coupon" ), "publish_posts", "coupons", "coupons_admin" ); 
	add_submenu_page( "coupons", __( "Create a coupon", "wp-coupon" ), __( "Create", "wp-coupon" ), "publish_posts", "coupons-create", "wp_coupon_create_coupon_page" );
	// the reports page has not yet been developed
	//add_submenu_page( "coupons", __( "coupon reports", "wp-coupon" ), __( "Reports", "wp-coupon" ), "publish_posts", "coupons-reports", "wp_coupon_reports_page" ); 
	add_submenu_page( "coupons", __( "coupon templates", "wp-coupon" ), __( "Templates", "wp-coupon" ), "publish_posts", "coupons-templates", "wp_coupon_templates_page" ); 
	
	// for WPMU site admins
	if ( ( function_exists( 'is_super_admin' ) && is_super_admin() ) || ( function_exists( 'is_site_admin' ) && is_site_admin() ) ) {
		add_submenu_page('wpmu-admin.php', __('coupons'), __('coupons'), "edit_users", 'wp-coupon-admin', 'wp_coupon_site_admin');
	}
}

// show the general site admin page
function wp_coupon_site_admin()
{
	wp_coupon_report_header();
	
	echo '<h2>' . __( "coupons", "wp-coupon" ) . '</h2>';
	
	echo '<div class="wp_coupon_col1">
	<h3>' . __( "25 most recent coupons", "wp-coupon" ) . '</h3>
	';
	$coupons = wp_coupon_get_all_coupons( 25, 0 );
	if ( $coupons && is_array( $coupons ) && count( $coupons ) > 0 )
	{
		wp_coupon_table_header( array( "Blog", "Name", "Downloads" ) );
		foreach( $coupons as $coupon )
		{
			echo '
			<tr>
				<td><a href="http://' . $coupon->domain . $coupon->path . '">' . $coupon->domain . $coupon->path . '</a></td>
				<td><a href="http://' . $coupon->domain . $coupon->path . '?coupon=' . $coupon->guid . '">' . $coupon->name . '</a></td>
				<td>' . $coupon->downloads . '</td>
			</tr>
			';
		}
		wp_coupon_table_footer();
	} else {
		echo '
		<p>' . __( 'No coupons found. <a href="admin.php?page=coupons-create">Create your first coupon here.</a>', "wp-coupon" ) . '</p>
		';
	}
	echo '
	</div>';
	
	echo '<div class="wp_coupon_col2">
	<h3>' . __( "25 most popular coupons", "wp-coupon" ) . '</h3>
	';
	$coupons = wp_coupon_get_all_popular_coupons( 25, 0 );
	if ( $coupons && is_array( $coupons ) && count( $coupons ) > 0 )
	{
		wp_coupon_table_header( array( "Blog", "Name", "Downloads" ) );
		foreach( $coupons as $coupon )
		{
			echo '
			<tr>
				<td><a href="http://' . $coupon->domain . $coupon->path . '">' . $coupon->domain . $coupon->path . '</a></td>
				<td><a href="http://' . $coupon->domain . $coupon->path . '?coupon=' . $coupon->guid . '">' . $coupon->name . '</a></td>
				<td>' . $coupon->downloads . '</td>
			</tr>
			';
		}
		wp_coupon_table_footer();
	} else {
		echo '
		<p>' . __( 'No coupons found. <a href="admin.php?page=coupons-create">Create your first coupon here.</a>', "wp-coupon" ) . '</p>
		';
	}
	echo '
	</div>';
	
	wp_coupon_report_footer();
}

// show the general admin page
function coupons_admin()
{
	wp_coupon_report_header();
	
	// if a coupon has not been chosen
	if ( !isset( $_GET["id"] ) )
	{
	
		echo '<h2>' . __( "coupons", "wp-coupon" ) . '
		<span style="float:right;font-size:80%"><a href="admin.php?page=coupons-create" class="button">' . __( "Create a coupon", "wp-coupon" ) . '</a></span></h2>';
		
		if ( @$_GET["reset"] == "true" ) {
			wp_coupon_delete_version();
			echo '
			<div id="message" class="updated">
				<p><strong>' . __( "Your wp-coupon database will be reset next time you create or edit a coupon. You will not lose any data, the tables will just be checked for all the correct fields.", "wp-coupon" ) . '</strong></p>
			</div>
			';
		}
		
		echo '<div class="wp_coupon_col1">
		<h3>' . __( "Your coupons", "wp-coupon" ) . '</h3>
		';
		$coupons = wp_coupon_get_coupons( 10, true );
		if ( $coupons && is_array( $coupons ) && count( $coupons ) > 0 )
		{
			wp_coupon_table_header( array( "Name", "Downloads", "Email required" ) );
			foreach( $coupons as $coupon )
			{
				echo '
				<tr>
					<td><a href="admin.php?page=coupons&amp;id=' . $coupon->id . '">' . $coupon->name . '</a></td>
					<td>' . $coupon->downloads . '</td>
					<td>' . wp_coupon_yes_no( $coupon->require_email ) . '</td>
				</tr>
				';
			}
			wp_coupon_table_footer();
		} else {
			echo '
			<p>' . __( 'No coupons found. <a href="admin.php?page=coupons-create">Create your first coupon here.</a>', "wp-coupon" ) . '</p>
			';
		}
		echo '
		</div>';
		
		echo '<div class="wp_coupon_col2">
		<h3>' . __( "Popular coupons", "wp-coupon" ) . '</h3>
		';
		$coupons = wp_coupon_get_popular_coupons();
		if ( $coupons && is_array( $coupons ) && count( $coupons ) > 0 )
		{
			wp_coupon_table_header( array( "Name", "Downloads", "Email required" ) );
			foreach( $coupons as $coupon )
			{
				echo '
				<tr>
					<td><a href="admin.php?page=coupons&amp;id=' . $coupon->id . '">' . $coupon->name . '</a></td>
					<td>' . $coupon->downloads . '</td>
					<td>' . wp_coupon_yes_no( $coupon->require_email ) . '</td>
				</tr>
				';
			}
			wp_coupon_table_footer();
		} else {
			echo '
			<p>' . __( 'No coupons found. <a href="admin.php?page=coupons-create">Create your first coupon here.</a>', "wp-coupon" ) . '</p>
			';
		}
		echo '
		<p><a href="' . wp_nonce_url( "admin.php?page=coupons&amp;download=emails", "wp_coupon_download_csv" ) . '">' . __( "Download all registered email addresses", "wp-coupon" ) . '</a></p>
		</div>';
	
	// if a coupon has been chosen
	} else {
	
		wp_coupon_edit_coupon_page();
	
	}
	
	wp_coupon_report_footer();
}

// show the create coupon page
function wp_coupon_create_coupon_page()
{
	wp_coupon_report_header();
	
	echo '
	<h2>' . __( "Create a coupon", "wp-coupon" ) . '</h2>
	';
	
	if ( @$_GET["result"] != "" ) {
		if ( @$_GET["result"] == "1" ) {
			echo '
			<div id="message" class="error">
				<p><strong>' . __( "Sorry, your coupon could not be created. Please click back and try again.", "wp-coupon" ) . '</strong></p>
			</div>
			';
		}
	}
	
	echo '
	<form action="admin.php?page=coupons-create" method="post" id="couponform">
	
	<div id="couponpreview">
	
		<h2><textarea name="name" id="name" rows="2" cols="100">' . __( "coupon name (30 characters)", "wp-coupon" ) . '</textarea></h2>
		
		<p><textarea name="text" id="text" rows="3" cols="100">' . __( "Type the coupon text here (200 characters)", "wp-coupon" ) . '</textarea></p>
		
		<p>[' . __( "coupon code inserted here", "wp-coupon" ) . ']</p>
		
		<p id="couponterms"><textarea name="terms" id="terms" rows="4" cols="100">' . __( "Type the coupon terms and conditions here (300 characters)", "wp-coupon" ) . '</textarea></p>
	
	</div>
	
	<p>' . __("coupon description (optional): enter a longer description here which will go in the email sent to a user registering for this coupon.", "wp-coupon" ) . '</p>
	<p><textarea name="description" id="description" rows="3" cols="100"></textarea></p>
	
	';
	$fonts = wp_coupon_fonts();
	echo '
	<h3>' . __( "Font", "wp-coupon" ) . '</h3>
	<p><label for="font">' . __( "Font", "wp-coupon" ) . '</label>
	<select name="font" id="font">
	';
	foreach ( $fonts as $font )
	{
		echo '
		<option value="' . $font[0] . '">' . $font[1] . '</option>
		';
	}
	echo '
	</select> <span>' . __( "Set the font for this coupon", "wp-coupon" ) . '</span></p>
	';
	$templates = wp_coupon_get_templates();
	if ( $templates && is_array( $templates ) && count( $templates ) > 0 )
	{
		echo '
		<h3>' . __( "Template", "wp-coupon" ) . '</h3>
		<div id="couponthumbs">
		';
		foreach( $templates as $template )
		{
			echo '
			<span><img src="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $template->id . '_thumb.jpg" id="template_' . $template->id . '" alt="' . $template->name . '" /></span>
			';
		}
		echo '
		</div>
		';
	} else {
		echo '
		<p>' . __( "Sorry, no templates found", "wp-coupon" ) . '</p>
		';
	}
	
	echo '
	<h3>' . __( "Settings", "wp-coupon" ) . '</h3>
	
	<p><label for="requireemail">' . __( "Require email address", "wp-coupon" ) . '</label>
	<input type="checkbox" name="requireemail" id="requireemail" value="1" /> <span>' . __( "Tick this box to require a valid email address to be given before this coupon can be downloaded", "wp-coupon" ) . '</span></p>
	
	<p><label for="limit">' . __( "Number of coupons available", "wp-coupon" ) . '</label>
	<input type="text" name="limit" id="limit" class="num" value="" /> <span>' . __( "Set the number of times this coupon can be downloaded (leave blank or 0 for unlimited)", "wp-coupon" ) . '</span></p>
	
	<p><label for="expiryyear">' . __( "Date coupon expires", "wp-coupon" ) . '</label>
	' . __( "Year:", "wp-coupon" ) . ' <input type="text" name="expiryyear" id="expiryyear" class="num" value="" />
	' . __( "Month:", "wp-coupon" ) . ' <input type="text" name="expirymonth" id="expirymonth" class="num" value="" />
	' . __( "Day:", "wp-coupon" ) . ' <input type="text" name="expiryday" id="expiryday" class="num" value="" /> 
	<span>' . __( "Enter the date on which this coupon will expire (leave blank for never)", "wp-coupon" ) . '</span></p>
	
	<h3>' . __( "coupon codes", "wp-coupon" ) . '</h3>
	
	<p><label for="randomcodes">' . __( "Use random codes", "wp-coupon" ) . '</label>
	<input type="radio" name="codestype" id="randomcodes" value="random" checked="checked" /> <span>' . __( "Tick this box to use a random character code on each coupon", "wp-coupon" ) . '</span></p>
	
	<p class="hider" id="codelengthline"><label for="codelength">' . __( "Random code length", "wp-coupon" ) . '</label>
	<select name="codelength" id="codelength">
	<option value="6">6</option>
	<option value="7">7</option>
	<option value="8">8</option>
	<option value="9">9</option>
	<option value="10">10</option>
	</select> <span>' . __( "How long would you like the random code to be?", "wp-coupon" ) . '</span></p>
	
	<p><label for="sequentialcodes">' . __( "Use sequential codes", "wp-coupon" ) . '</label>
	<input type="radio" name="codestype" id="sequentialcodes" value="sequential" /> <span>' . __( "Tick this box to use sequential codes (1, 2, 3 etc) on each coupon", "wp-coupon" ) . '</span></p>
	
	<p class="hider" id="codeprefixline"><label for="codeprefix">' . __( "Sequential code prefix", "wp-coupon" ) . '</label>
	<input type="text" name="codeprefix" id="codeprefix" /> <span>' . __( "Text to show before the sequential code (eg <strong>ABC</strong>123XYZ)", "wp-coupon" ) . '</span></p>
	
	<p class="hider" id="codesuffixline"><label for="codesuffix">' . __( "Sequential code suffix", "wp-coupon" ) . '</label>
	<input type="text" name="codesuffix" id="codesuffix" /> <span>' . __( "Text to show after the sequential code (eg ABC123<strong>XYZ</strong>)", "wp-coupon" ) . '</span></p>
	
	<p><label for="customcodes">' . __( "Use custom codes", "wp-coupon" ) . '</label>
	<input type="radio" name="codestype" id="customcodes" value="custom" /> <span>' . __( "Tick this box to use your own codes on each download of this coupon. You must enter all the codes you want to use below:", "wp-coupon" ) . '</span></p>
	
	<p class="hider" id="customcodelistline"><label for="customcodelist">' . __( "Custom codes (one per line)", "wp-coupon" ) . '</label>
	<textarea name="customcodelist" id="customcodelist" rows="6" cols="100"></textarea></p>
	
	<p><label for="singlecode">' . __( "Use a single code", "wp-coupon" ) . '</label>
	<input type="radio" name="codestype" id="singlecode" value="single" /> <span>' . __( "Tick this box to use one code on all downloads of this coupon. Enter the code you want to use below:", "wp-coupon" ) . '</span></p>
	
	<p class="hider" id="singlecodetextline"><label for="singlecodetext">' . __( "Single code", "wp-coupon" ) . '</label>
	<input type="text" name="singlecodetext" id="singlecodetext" /></p>
	
	<p><input type="button" name="preview" id="previewbutton" class="button" value="' . __( "Preview", "wp-coupon" ) . '" />
	<input type="submit" name="save" id="savebutton" class="button-primary" value="' . __( "Save", "wp-coupon" ) . '" />
	<input type="hidden" name="template" id="template" value="1" />';
	wp_nonce_field( "wp_coupon_create" );
	echo '</p>
	
	</form>
	
	<script type="text/javascript">
	jQuery(document).ready(vp_show_random);
	</script>
	';
	
	wp_coupon_report_footer();
}

// show the edit coupon page
function wp_coupon_edit_coupon_page()
{
	//check install
	wp_coupon_check_install();

	$coupon = wp_coupon_get_coupon( @$_GET["id"], 0 );
	if ( $coupon && is_object( $coupon ) )
	{
		echo '
		<h2>' . __( "Edit coupon:", "wp-coupon" ) . ' ' . htmlspecialchars( stripslashes( $coupon->name ) ) . ' <span class="r">';
		
		
		
		if ( $coupon->downloads > 0 ) {
			echo __( "Downloads:", "wp-coupon" ) . " " . $coupon->downloads;
			echo ' | <a href="' . wp_nonce_url( "admin.php?page=coupons&amp;download=emails&amp;coupon=" . $coupon->id, "wp_coupon_download_csv" ) . '">' . __( "CSV", "wp-coupon" ) . '</a>';
			echo ' | ';
		}
		
		echo '<a href="#" id="showshortcodes">Shortcodes</a></span></h2>
		
		<div class="hider" id="shortcodes">
		
		<h3>' . __( "Shortcode for this coupon:", "wp-coupon" ) . ' <input type="text" value="[coupon id=&quot;' . $coupon->id . '&quot;]" /> = <a href="' . wp_coupon_link( $coupon->guid ) . '">' . htmlspecialchars( stripslashes( $coupon->name ) ) . '</a></h3>
		
		';
		
		if ( $coupon->require_email == "1" )
		{
		echo '
		<h3>' . __( "Shortcode for this coupon registration form:", "wp-coupon" ) . ' <input type="text" value="[couponform id=&quot;' . $coupon->id . '&quot;]" /></h3>
		';
		}
		
		echo '
		
		<h3>' . __( "Shortcode for this coupon:", "wp-coupon" ) . ' <input type="text" value="[coupon id=&quot;' . $coupon->id . '&quot; preview=&quot;true&quot;]" /> = <a href="' . wp_coupon_link( $coupon->guid ) . '"><img src="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $coupon->template . '_thumb.jpg" alt="' . htmlspecialchars( stripslashes( $coupon->name ) ) . '" /></a></h3>
		
		<h3>' . __( "Link for this coupon:", "wp-coupon" ) . ' <input type="text" value="' . wp_coupon_link( $coupon->guid ) . '" /></h3>
		
		</div>
		';
		
		if ( @$_GET["result"] != "" ) {
			if ( @$_GET["result"] == "1" ) {
				echo '
				<div id="message" class="updated fade">
					<p><strong>' . __( "Your coupon has been created.", "wp-coupon" ) . '</strong></p>
				</div>
				';
			}
			if ( @$_GET["result"] == "2" ) {
				echo '
				<div id="message" class="error">
					<p><strong>' . __( "Sorry, your coupon could not be edited.", "wp-coupon" ) . '</strong></p>
				</div>
				';
			}
			if ( @$_GET["result"] == "3" ) {
				echo '
				<div id="message" class="updated fade">
					<p><strong>' . __( "Your coupon has been edited.", "wp-coupon" ) . '</strong></p>
				</div>
				';
			}
			if ( @$_GET["result"] == "4" ) {
				echo '
				<div id="message" class="updated fade">
					<p><strong>' . __( "The coupon has been deleted.", "wp-coupon" ) . '</strong></p>
				</div>
				';
			}
			if ( @$_GET["result"] == "5" ) {
				echo '
				<div id="message" class="error">
					<p><strong>' . __( "The coupon could not be deleted.", "wp-coupon" ) . '</strong></p>
				</div>
				';
			}
		}
		
		// if this coupon has an expiry date which has passed
		if ( $coupon->expiry != "" && (int)$coupon->expiry != 0 && (int)$coupon->expiry <= time() ) {
			echo '
			<div id="message" class="updated fade">
				<p><strong>' . sprintf( __( "This coupon expired on %s. Change the expiry date below to allow this coupon to be downloaded.", "wp-coupon" ), date( "Y/m/d", $coupon->expiry ) ) . '</strong></p>
			</div>
			';
		}
		
		echo '
		<form action="admin.php?page=coupons&amp;id=' . $_GET["id"] . '" method="post" id="couponform">
		
		<div id="couponpreview" style="background-image:url(' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $coupon->template . '_preview.jpg)">
		
			<h2><textarea name="name" id="name" rows="2" cols="100">' . stripslashes( $coupon->name ) . '</textarea></h2>
			
			<p><textarea name="text" id="text" rows="3" cols="100">' . stripslashes( $coupon->text ) . '</textarea></p>
			
			<p>[' . __( "The coupon code will be inserted automatically here", "wp-coupon" ) . ']</p>
			
			<p id="couponterms"><textarea name="terms" id="terms" rows="4" cols="100">' . stripslashes( $coupon->terms ) . '</textarea></p>
		
		</div>
		
		<p>' . __("coupon description (optional): enter a longer description here which will go in the email sent to a user registering for this coupon.", "wp-coupon" ) . '</p>
	<p><textarea name="description" id="description" rows="3" cols="100">' . $coupon->description . '</textarea></p>
		
		';
		$fonts = wp_coupon_fonts();
		echo '
		<h3>' . __( "Font", "wp-coupon" ) . '</h3>
		<p><label for="font">' . __( "Font", "wp-coupon" ) . '</label>
		<select name="font" id="font">
		';
		foreach ( $fonts as $font )
		{
			if ( $coupon->font == $font[0] ) {
				$selected = ' selected="selected"';
			}
			echo '
			<option value="' . $font[0] . '"' . $selected . '>' . $font[1] . '</option>
			';
			$selected  = "";
		}
		echo '
		</select> <span>' . __( "Set the font for this coupon", "wp-coupon" ) . '</span></p>
		';
		$templates = wp_coupon_get_templates();
		if ( $templates && is_array( $templates ) && count( $templates ) > 0 )
		{
			echo '
			<h3>' . __( "Template", "wp-coupon" ) . '</h3>
			<div id="couponthumbs">
			';
			foreach( $templates as $template )
			{
				echo '
				<span><img src="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $template->id . '_thumb.jpg" id="template_' . $template->id . '" alt="' . $template->name . '" /></span>
				';
			}
			echo '
			</div>
			';
		} else {
			echo '
			<p>' . __( "Sorry, no templates found", "wp-coupon" ) . '</p>
			';
		}
		
		echo '
		<h3>' . __( "Settings", "wp-coupon" ) . '</h3>
		
		<p><label for="requireemail">' . __( "Require email address", "wp-coupon" ) . '</label>
		<input type="checkbox" name="requireemail" id="requireemail" value="1"';
		if ( $coupon->require_email == "1" ) {
			echo ' checked="checked"';
		}
		echo '/> <span>' . __( "Tick this box to require a valid email address to be given before this coupon can be downloaded", "wp-coupon" ) . '</span></p>
		';
		if ( $coupon->limit == "0" ) {
			$coupon->limit = "0";
		}
		echo '
		<p><label for="limit">' . __( "Number of coupons available", "wp-coupon" ) . '</label>
		<input type="text" name="limit" id="limit" class="num" value="' . $coupon->limit . '" /> <span>' . __( "Set the number of times this coupon can be downloaded (leave blank for unlimited)", "wp-coupon" ) . '</span></p>
		
		<p><label for="expiry">' . __( "Date coupon expires", "wp-coupon" ) . '</label>
		' . __( "Year:", "wp-coupon" ) . ' <input type="text" name="expiryyear" id="expiryyear" class="num" value="';
		if ( $coupon->expiry != "" && $coupon->expiry > 0 ) {
			echo date( "Y", $coupon->expiry  );
		}
		echo '" />
		' . __( "Month:", "wp-coupon" ) . ' <input type="text" name="expirymonth" id="expirymonth" class="num" value="';
		if ( $coupon->expiry != "" && $coupon->expiry > 0 ) {
			echo date( "n", $coupon->expiry  );
		}
		echo '" />
		' . __( "Day:", "wp-coupon" ) . ' <input type="text" name="expiryday" id="expiryday" class="num" value="';
		if ( $coupon->expiry != "" && $coupon->expiry > 0 ) {
			echo date( "j", $coupon->expiry  );
		}
		echo '" /> 
		<span>' . __( "Enter the date on which this coupon will expire (leave blank for never)", "wp-coupon" ) . '</span></p>
		
		<p><strong>' . __( "This box MUST be ticked for this coupon to be available.", "wp-coupon" ) . '</strong></p>
		<p><label for="live">' . __( "coupon available", "wp-coupon" ) . '</label>
		<input type="checkbox" name="live" id="live" value="1"';
		if ( $coupon->live == "1" ) {
			echo ' checked="checked"';
		}
		echo '/> <span>' . __( "Tick this box to allow this coupon to be downloaded", "wp-coupon" ) . '</span></p>
		
		<h3>' . __( "coupon codes", "wp-coupon" ) . '</h3>
	
		<p><label for="randomcodes">' . __( "Use random codes", "wp-coupon" ) . '</label>
		<input type="radio" name="codestype" id="randomcodes" value="random"';
		if ( $coupon->codestype == "random" || $coupon->codestype == "" ) {
			echo ' checked="checked"';
		}
		echo ' /> <span>' . __( "Tick this box to use a random 6-character code on each coupon", "wp-coupon" ) . '</span></p>
		
		<p class="hider" id="codelengthline"><label for="codelength">' . __( "Random code length", "wp-coupon" ) . '</label>
		<select name="codelength" id="codelength">
		<option value="6"';
		if ( $coupon->codelength == "6" ) {
			echo ' selected="selected"';
		}
		echo '>6</option>
		<option value="7"';
		if ( $coupon->codelength == "7" ) {
			echo ' selected="selected"';
		}
		echo '>7</option>
		<option value="8"';
		if ( $coupon->codelength == "8" ) {
			echo ' selected="selected"';
		}
		echo '>8</option>
		<option value="9"';
		if ( $coupon->codelength == "9" ) {
			echo ' selected="selected"';
		}
		echo '>9</option>
		<option value="10"';
		if ( $coupon->codelength == "10" ) {
			echo ' selected="selected"';
		}
		echo '>10</option>
		</select> <span>' . __( "How long would you like the random code to be?", "wp-coupon" ) . '</span></p>
		
		<p><label for="sequentialcodes">' . __( "Use sequential codes", "wp-coupon" ) . '</label>
		<input type="radio" name="codestype" id="sequentialcodes" value="sequential"';
		if ( $coupon->codestype == "sequential" ) {
			echo ' checked="checked"';
		}
		echo ' /> <span>' . __( "Tick this box to use sequential codes (1, 2, 3 etc) on each coupon", "wp-coupon" ) . '</span></p>
		
		<p class="hider" id="codeprefixline"><label for="codeprefix">' . __( "Sequential code prefix", "wp-coupon" ) . '</label>
	<input type="text" name="codeprefix" id="codeprefix" value="' . $coupon->codeprefix . '" /> <span>' . __( "Text to show before the sequential code (eg <strong>ABC</strong>123XYZ)", "wp-coupon" ) . '</span></p>
	
		<p class="hider" id="codesuffixline"><label for="codesuffix">' . __( "Sequential code suffix", "wp-coupon" ) . '</label>
	<input type="text" name="codesuffix" id="codesuffix" value="' . $coupon->codesuffix . '" /> <span>' . __( "Text to show after the sequential code (eg ABC123<strong>XYZ</strong>)", "wp-coupon" ) . '</span></p>
		
		<p><label for="customcodes">' . __( "Use custom codes", "wp-coupon" ) . '</label>
		<input type="radio" name="codestype" id="customcodes" value="custom"';
		if ( $coupon->codestype == "custom" ) {
			echo ' checked="checked"';
		}
		echo ' /> <span>' . __( "Tick this box to use your own codes on each coupon. You must enter all the codes you want to use below:", "wp-coupon" ) . '</span></p>
		
		<p class="hider" id="customcodelistline"><label for="customcodelist">' . __( "Custom codes (one per line)", "wp-coupon" ) . '</label>
		<textarea name="customcodelist" id="customcodelist" rows="6" cols="100">';
		if ( $coupon->codestype == "custom" ) {
			echo $coupon->codes;
		}
		echo '</textarea></p>
		
		<p><label for="singlecode">' . __( "Use a single code", "wp-coupon" ) . '</label>
		<input type="radio" name="codestype" id="singlecode" value="single"';
		if ( $coupon->codestype == "single" ) {
			echo ' checked="checked"';
		}
		echo ' /> <span>' . __( "Tick this box to use one code on all downloads of this coupon. Enter the code you want to use below:", "wp-coupon" ) . '</span></p>
		
		<p class="hider" id="singlecodetextline"><label for="singlecodetext">' . __( "Single code", "wp-coupon" ) . '</label>
		<input type="text" name="singlecodetext" id="singlecodetext" value="';
		if ( $coupon->codestype == "single" ) {
			echo $coupon->codes;
		}
		echo '" /></p>
		
		<h3>' . __( "Delete coupon", "wp-coupon" ) . '</h3>
		
		<p><label for="delete">' . __( "Delete coupon", "wp-coupon" ) . '</label>
		<input type="checkbox" name="delete" id="delete" value="1" /> <span>' . __( "Tick this box to delete this coupon", "wp-coupon" ) . '</span></p>
		
		<p><input type="button" name="preview" id="previewbutton" class="button" value="' . __( "Preview", "wp-coupon" ) . '" />
		<input type="submit" name="save" id="savebutton" class="button-primary" value="' . __( "Save", "wp-coupon" ) . '" />
		<input type="hidden" name="template" id="template" value="' . $coupon->template . '" />';
		wp_nonce_field( "wp_coupon_edit" );
		echo '</p>
		
		</form>
		
		<script type="text/javascript">
		jQuery(document).ready(vp_show_' . $coupon->codestype . ');
		</script>
		';
		
	} else {
	
		if ( @$_GET["result"] == "4" ) {
			echo '
			<h2>' . __( "coupon deleted", "wp-coupon" ) . '</h2>
			<div id="message" class="updated fade">
				<p><strong>' . __( "The coupon has been deleted.", "wp-coupon" ) . '</strong></p>
			</div>
			';
		} else {
			echo '
			<h2>' . __( "coupon not found", "wp-coupon" ) . '</h2>
			<p>' . __( "Sorry, that coupon was not found.", "wp-coupon" ) . '</p>
			';
		}
	}
}

// show the coupon reports page
function wp_coupon_reports_page()
{
	wp_coupon_report_header();
	
	echo '
	<h2>' . __( "coupon reports", "wp-coupon" ) . '</h2>

	';
	
	wp_coupon_report_footer();
}

// show the templates page
function wp_coupon_templates_page()
{
	wp_coupon_report_header();
	
	echo '
	<h2>' . __( "coupon templates", "wp-coupon" ) . '</h2>
	';
	
	// get templates
	$templates = wp_coupon_get_templates();
	
	// if submitting a form
	if ( $_POST && is_array( $_POST ) && count( $_POST) > 0 )
	{
		// if updating templates
		if ( wp_verify_nonce(@$_POST["_wpnonce"], 'wp_coupon_edit_template') && @$_POST["action"] == "update" )
		{
			// loop templates
			foreach( $templates as $template )
			{
				$live = 1;
				if ( @$_POST["delete" . $template->id] == "1" ) {
					$live = 0;
				}
				// edit this template
				wp_coupon_edit_template( $template->id, @$_POST["name" . $template->id], $live );
			}
			
			// get the new templates
			$templates = wp_coupon_get_templates();
			
			echo '
			<div id="message" class="updated fade">
				<p><strong>' . __( "Templates updated", "wp-coupon" ) . '</strong></p>
			</div>
			';
		}
		// if adding a template
		if ( @$_POST["action"] == "add" )
		{

			if ( wp_verify_nonce(@$_POST["_wpnonce"], 'wp_coupon_add_template') && @$_FILES && is_array( $_FILES ) && count( $_FILES ) > 0 && $_FILES["file"]["name"] != "" && (int)$_FILES["file"]["size"] > 0 )
			{
				// check the GD functions exist
				if ( function_exists( "imagecreatetruecolor" ) && function_exists( "getimagesize" ) && function_exists( "imagejpeg" ) ) 
				{
				
					$name = $_POST["name"];
					if ( $name == "" ) { $name = "New template " . date( "F j, Y, g:i a" ); }
				
					// try to save the template name
					$id = wp_coupon_add_template( $name );
					
					// if the id can be fetched
					if ( $id )
					{
				
						$uploaded = wp_coupon_upload_template( $id, $_FILES["file"] );
						
						if ( $uploaded )
						{
						
							echo '
							<div id="message" class="updated fade">
								<p><strong>' . __( "Your template has been uploaded.", "wp-coupon" ) . '</strong></p>
							</div>
							';
							
							// get templates
							$templates = wp_coupon_get_templates();
						
						} else {
						
							echo '
							<div id="message" class="error">
								<p><strong>' . __( "Sorry, the template file you uploaded was not in the correct format (JPEG), or was not the correct size (1181 x 532 pixels). Please upload a correct template file.", "wp-coupon" ) . '</strong></p>
							</div>
							';
						
						}
					
					} else {
					
						echo '
						<div id="message" class="error">
							<p><strong>' . __( "Sorry, your template could not be saved. Please try again.", "wp-coupon" ) . '</strong></p>
						</div>
						';
					
					}
					
				} else {
					echo '
					<div id="message" class="error">
						<p><strong>' . __( "Sorry, your host does not support GD image functions, so you cannot add your own templates.", "wp-coupon" ) . '</strong></p>
					</div>
					';
				}
			} else {
				echo '
				<div id="message" class="error">
					<p><strong>' . __( "Please attach a template file", "wp-coupon" ) . '</strong></p>
				</div>
				';
			}
		}
	}
	
	if ( function_exists( "imagecreatetruecolor" ) && function_exists( "getimagesize" ) && function_exists( "imagejpeg" ) ) {
	echo '
	<h3>' . __( "Add a template", "wp-coupon" ) . '</h3>
	
	<form action="admin.php?page=coupons-templates" method="post" enctype="multipart/form-data" id="templateform">
	
	<p>' . __( sprintf( 'To create your own templates use <a href="%s">this empty template</a>.', get_option( "siteurl" ) . "/wp-content/plugins/wp-coupon/templates/1.jpg" ), 'wp-coupon' ) . '</p>
	
	<p><label for="file">' . __( "Template file", "wp-coupon" ) . '</label>
	<input type="file" name="file" id="file" /></p>
	
	<p><label for="name">' . __( "Template name", "wp-coupon" ) . '</label>
	<input type="text" name="name" id="name" /></p>
	
	<p><input type="submit" class="button-primary" value="' . __( "Add template", "wp-coupon" ) . '" />
	<input type="hidden" name="action" value="add" />';
	wp_nonce_field( "wp_coupon_add_template" );
	echo '</p>
	
	</form>
	';
	} else {
		echo '
		<p>' . __( "Sorry, your host does not support GD image functions, so you cannot add your own templates.", "wp-coupon" )  . '</p>
		';
	}
	
	if ( $templates && is_array( $templates ) && count( $templates ) > 0 )
	{
		echo '
		<form id="templatestable" method="post" action="">
		';
		wp_coupon_table_header( array( "Preview", "Name", "Delete" ) );
		foreach( $templates as $template )
		{
			echo '
			<tr>
				<td><a href="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $template->id . '_preview.jpg" class="templatepreview"><img src="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $template->id . '_thumb.jpg" alt="' . $template->name . '" /></a></td>
				';
				// if this is not a multisite-wide template
				if ( $template->blog_id != "0" || ( !defined( 'VHOST' ) && ( !defined( 'MULTISITE' ) || MULTISITE == "" || MULTISITE == false ) ) )
				{
				echo '
				<td><input type="text" name="name' . $template->id . '" value="' . $template->name . '" /></td>
				<td><input class="checkbox" type="checkbox" value="1" name="delete' . $template->id . '" /></td>
				';
				} else {
				echo '
				<td colspan="2">' . __( "This template cannot be edited", "wp-coupon" ) . '</td>
				';
				}
			echo '
			</tr>
			';
		}
		wp_coupon_table_footer();
		echo '
		<p><input type="submit" class="button-primary" value="' . __( "Save templates", "wp-coupon" ) . '" />
		<input type="hidden" name="action" value="update" />';
		wp_nonce_field( "wp_coupon_edit_template" );
		echo '</p>
		</form>
		';
	} else {
		echo '
		<p>' . __( "Sorry, no templates found", "wp-coupon" ) . '</p>
		';
	}
	
	wp_coupon_report_footer();
}

// include the wp-coupon CSS file
function wp_coupon_admin_css()
{
	echo '
	<link rel="stylesheet" href="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/wp-coupon.css" type="text/css" media="all" />
	';
}

// include the wp-coupon JS file
function wp_coupon_admin_js()
{
	echo '
	<script type="text/javascript">
		var vp_siteurl = "' . get_option("siteurl") . '";
	</script>
	<script type="text/javascript" src="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/wp-coupon.js"></script>
	';
}

// to display above every report
function wp_coupon_report_header() {
	echo '
	<div id="wp-coupon" class="wrap">
	';
	wp_coupon_wp_plugin_standard_header( "GBP", "wp-coupon", "Chris Taylor", "chris@stillbreathing.co.uk", "http://wordpress.org/extend/plugins/wp-coupon/" );
}

// to display below every report
function wp_coupon_report_footer() {
	wp_coupon_wp_plugin_standard_footer( "GBP", "wp-coupon", "Chris Taylor", "chris@stillbreathing.co.uk", "http://wordpress.org/extend/plugins/wp-coupon/" );
	echo '
	<p><a href="admin.php?page=coupons&amp;reset=true">Reset wp-coupon database</a></p>
	</div>
	';
}

// display the header of a data table
function wp_coupon_table_header( $headings ) {
	echo '
	<table class="widefat post fixed">
	<thead>
	<tr>
	';
	foreach( $headings as $heading ) {
		echo '<th>' . __( $heading, "wp-coupon" ) . '</th>
		';
	}
	echo '
	</tr>
	</thead>
	<tbody>
	';
}

// display the footer of a data table
function wp_coupon_table_footer() {
	echo '
	</tbody>
	</table>
	';
}

// ==========================================================================================
// general functions

// return a list of safe fonts
function wp_coupon_fonts()
{
	return array( 
		array( "dejavusans", "Deja Vu Sans"),
		array( "dejavusansb", "Deja Vu Sans (bold)"),
		array( "dejavusansi", "Deja Vu Sans (italic)"),
		array( "dejavusansbi", "Deja Vu Sans (bold, italic)"),
		array( "dejavusanscondensed", "Deja Vu Sans Condensed"),
		array( "dejavusanscondensedb", "Deja Vu Sans Condensed (bold)"),
		array( "dejavusanscondensedi", "Deja Vu Sans Condensed (italic)"),
		array( "dejavusanscondensedbi", "Deja Vu Sans Condensed (bold, italic)"),
		array( "dejavusansmono", "Deja Vu Sans Monospace"),
		array( "dejavusansmonob", "Deja Vu Sans Monospace (bold)"),
		array( "dejavusansmonoi", "Deja Vu Sans Monospace (italic)"),
		array( "dejavusansmonobi", "Deja Vu Sans Monospace (bold, italic)"),
		array( "dejavuserif", "Deja Vu Serif"),
		array( "dejavuserifb", "Deja Vu Serif (bold)"),
		array( "dejavuserifi", "Deja Vu Serif (italic)"),
		array( "dejavuserifbi", "Deja Vu Serif (bold, italic)"),
		array( "dejavuserifcondensed", "Deja Vu Serif Condensed"),
		array( "dejavuserifcondensedb", "Deja Vu Serif Condensed (bold)"),
		array( "dejavuserifcondensedi", "Deja Vu Serif Condensed (italic)"),
		array( "dejavuserifcondensedbi", "Deja Vu Serif Condensed (bold, italic)")
	);
}

// check if the site is using pretty URLs
function wp_coupon_pretty_urls() {
	$structure = get_option( "permalink_structure" );
	if ( $structure != "" || strpos( $structure, "?" ) === false ) {
		return true;
	}
	return false;
}

// create a URL to a wp-coupon page
function wp_coupon_link( $coupon_guid, $download_guid = "", $encode = true ) {
	if ( wp_coupon_pretty_urls() ) {
		if ( $download_guid != "" ) {
			if ( $encode )
			{
				$download_guid = "&amp;guid=" . urlencode( $download_guid );
			} else {
				$download_guid = "&guid=" . urlencode( $download_guid );
			}
		}
		return get_option( "siteurl" ) . "/?coupon=" . $coupon_guid . $download_guid;
	}
	if ( $download_guid != "" ) {
		if ( $encode )
		{
			$download_guid = "&amp;guid=" . urlencode( $download_guid );
		} else {
			$download_guid = "&guid=" . urlencode( $download_guid );
		}
	}
	return get_option( "siteurl" ) . "/?coupon=" . $coupon_guid . $download_guid;
}

// create an md5 hash of a guid
// from http://php.net/manual/en/function.com-create-guid.php
function wp_coupon_guid( $length = 6 ){
    if (function_exists('com_create_guid')){
        return substr( md5( str_replace( "{", "", str_replace( "}", "", com_create_guid() ) ) ), 0, $length );
    }else{
        mt_srand( ( double )microtime()*10000 );
        $charid = strtoupper( md5( uniqid( rand(), true ) ) );
        $hyphen = chr(45);
        $uuid = 
                substr( $charid, 0, 8 ).$hyphen
                .substr( $charid, 8, 4 ).$hyphen
                .substr( $charid,12, 4 ).$hyphen
                .substr( $charid,16, 4 ).$hyphen
                .substr( $charid,20,12 );
        return substr( md5( str_replace( "{", "", str_replace( "}", "", $uuid ) ) ), 0, $length );
    }
}

// get the users IP address
// from http://roshanbh.com.np/2007/12/getting-real-ip-address-in-php.html
function wp_coupon_ip() {
	if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
      $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
      $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
      $ip=$_SERVER['REMOTE_ADDR'];
    }
    return $ip;

}

// get the current blog ID (for WP Multisite) or '1' for standard WP
function wp_coupon_blog_id(){
	global $current_blog;
	if ( is_object( $current_blog ) && $current_blog->blog_id != "" ) {
		return $current_blog->blog_id;
	} else {
		return 1;
	}
}

// return yes or no
function wp_coupon_yes_no( $val )
{
	if ( !$val || $val == "" || $val == "0" )
	{
		return __( "No", "wp-coupon" );
	} else {
		return __( "Yes", "wp-coupon" );
	}
}

// create slug
// Bramus! pwnge! : simple method to create a post slug (http://www.bram.us/)
function wp_coupon_slug( $string ) {
	$slug = preg_replace( "/[^a-zA-Z0-9 -]/", "", $string );
	$slug = str_replace( " ", "-", $slug );
	$slug = strtolower( $slug );
	return $slug;
}

// process a shortcode for a coupon
function coupon_do_coupon_shortcode( $atts ) {
	extract( shortcode_atts( array( 
		'id' => '',
		'preview' => ''
	), $atts ) );
	if ( $id != "" ) {
		$coupon = wp_coupon_get_coupon( $id );
		if ( $coupon && ( $coupon->expiry == "" || (int)$coupon->expiry == 0 || (int)$coupon->expiry > time() ) ) {
			if ( $preview == 'true' ) {
				return '<a href="' . wp_coupon_link( $coupon->guid ) . '"><img src="' . get_option( "siteurl" ) . '/wp-content/plugins/wp-coupon/templates/' . $coupon->template . '_thumb.jpg" alt="' . htmlspecialchars( $coupon->name ) . '" /></a>';
			} else {
				return '<a href="' . wp_coupon_link( $coupon->guid ) . '">' . htmlspecialchars( $coupon->name ) . '</a>';
			}
		} else {
			$r = "<!-- The shortcode for coupon " . $id . " is displaying nothing because the coupon was not found, or the expiry date has passed";
			if ( $coupon ) {
				$r .= ". coupon found, expiry: '" . $coupon->expiry . "'";
			}
			$r .= " -->";
			return $r;
		}
	}
}

// process a shortcode for a coupon form
function coupon_do_coupon_form_shortcode( $atts ) {
	extract( shortcode_atts( array( 
		'id' => ''
	), $atts ) );
	if ( $id != "" ) {
		$coupon = wp_coupon_get_coupon( $id );
		if ( $coupon && ( $coupon->expiry == "" || (int)$coupon->expiry == 0 || (int)$coupon->expiry > time() ) ) {
			return wp_coupon_register_form( $coupon->guid, true );
		} else {
			$r = "<!-- The form shortcode for coupon " . $id . " is displaying nothing because the coupon was not found, or the expiry date has passed";
			if ( $coupon ) {
				$r .= ". coupon found, expiry: '" . $coupon->expiry . "'";
			}
			$r .= " -->";
		}
	}
}

// process a shortcode for a list of coupons
function coupon_do_list_shortcode() {
	
	$coupons = wp_coupon_get_coupons();
	if ( $coupons && is_array( $coupons ) && count( $coupons ) > 0 ) {
		
		$r = "<ul class=\"couponlist\">\n";
		
		foreach( $coupons as $coupon ) {
		
			$r .= '<li><a href="' . wp_coupon_link( $coupon->guid ) . '">' . htmlspecialchars( $coupon->name ) . '</a></li>';
		
		}
		
		$r .= '</ul>';
		
		return $r;
	}
	
}

// ==========================================================================================
// coupon administration functions

// listen for downloads of email addresses
function wp_coupon_check_download() {
	// download all unique email addresses
	if ( wp_verify_nonce(@$_GET["_wpnonce"], 'wp_coupon_download_csv') && ( @$_GET["page"] == "coupons" ) && @$_GET["download"] == "emails" && @$_GET["coupon"] == "" ) {
		if ( !wp_coupon_download_emails() ) {
			wp_die( __("Sorry, the list could not be downloaded. Please click back and try again.", "wp-coupon" ) );
		}
	}
	// download unique email addresses for a coupon
	if ( wp_verify_nonce(@$_GET["_wpnonce"], 'wp_coupon_download_csv') && ( @$_GET["page"] == "coupons" ) && @$_GET["download"] == "emails" && @$_GET["coupon"] != "" ) {
		if ( !wp_coupon_download_emails($_GET["coupon"]) ) {
			wp_die( __("Sorry, the list could not be downloaded. Please click back and try again.", "wp-coupon" ) );
		}
	}
}

// listen for previews of a coupon
function wp_coupon_check_preview() {
	if ( ( @$_GET["page"] == "coupons" || @$_GET["page"] == "coupons-create" ) && @$_GET["preview"] == "coupon" ) {
		wp_coupon_preview_coupon( $_POST["template"], $_POST["font"], $_POST["name"], $_POST["text"], $_POST["terms"] );
	}
}

// listen for creation of a coupon
function wp_coupon_check_create_coupon() {
	if ( wp_verify_nonce(@$_POST["_wpnonce"], 'wp_coupon_create') && @$_GET["page"] == "coupons-create" && @$_GET["preview"] == "" && @$_POST && is_array( $_POST ) && count( $_POST ) > 0 ) {
		$require_email = 0;
		if ( isset( $_POST["requireemail"] ) && $_POST["requireemail"] == "1" ) { $require_email = 1; }
		$limit = 0;
		if ( $_POST["limit"] != "" && $_POST["limit"] != "0" ) { $limit = (int)$_POST["limit"]; }
		$expiry = 0;
		if ( $_POST["expiryyear"] != "" && $_POST["expiryyear"] != "0" && $_POST["expirymonth"] != "" && $_POST["expirymonth"] != "0" && $_POST["expiryday"] != "" && $_POST["expiryday"] != "0" ) { $expiry = strtotime( $_POST["expiryyear"] . "/" . $_POST["expirymonth"] . "/" . $_POST["expiryday"]); }
		if ( $_POST["codestype"] == "random" || $_POST["codestype"]== "sequential" || $_POST["codestype"]== "custom" || $_POST["codestype"]== "single" ) {
			$codestype = $_POST["codestype"];
		} else {
			$codestype = "random";
		}
		if ( $_POST["codelength"] != "" ) {
			$codelength = (int)$_POST["codelength"];
		}
		if ( $codelength == "" || $codelength == 0 ) {
			$codelength = 6;
		}
		$codeprefix = trim( $_POST["codeprefix"] );
		if ( strlen( $codeprefix ) > 6 ) { $codeprefix = substr( $codeprefix, 6 ); }
		$codesuffix = trim( $_POST["codesuffix"] );
		if ( strlen( $codesuffix ) > 6 ) { $codesuffix = substr( $codesuffix, 6 ); }
		$codes = "";
		if ( $_POST["codestype"]== "custom" ) {
			$codes = trim( $_POST["customcodelist"] );
		}
		if ( $_POST["codestype"]== "single" ) {
			$codes = trim( $_POST["singlecodetext"] );
		}
		$array = wp_coupon_create_coupon( $_POST["name"], $require_email, $limit, $_POST["text"], $_POST["description"], $_POST["template"], $_POST["font"], $_POST["terms"], $expiry, $codestype, $codelength, $codeprefix, $codesuffix, $codes );
		if ( $array && is_array( $array ) && $array[0] == true && $array[1] > 0 ) {
			// eventually the plugin will create thumbnails for a coupon
			//wp_coupon_create_coupon_thumb( $array[1], $_POST["template"], $_POST["font"], $_POST["name"], $_POST["text"], $_POST["terms"] );
			header( "Location: admin.php?page=coupons&id=" . $array[1] . "&result=1" );
			exit();
		} else {
			header( "Location: admin.php?page=coupons-create&result=1" );
			exit();
		}
	}
}

// listen for editing of a coupon
function wp_coupon_check_edit_coupon() {
	if ( wp_verify_nonce(@$_POST["_wpnonce"], 'wp_coupon_edit') && @$_GET["page"] == "coupons" && @$_GET["preview"] == "" && @$_POST && is_array( $_POST ) && count( $_POST ) > 0 ) {
		if ( isset( $_POST["delete"] ) ) {
			$done = wp_coupon_delete_coupon( $_GET["id"] );
			if ( $done ) {
				header( "Location: admin.php?page=coupons&id=" . $_GET["id"] . "&result=4" );
				exit();
			} else {
				header( "Location: admin.php?page=coupons&id=" . $_GET["id"] . "&result=5" );
				exit();
			}
		}
		$require_email = 0;
		if ( isset( $_POST["requireemail"] ) && $_POST["requireemail"] == "1" ) { $require_email = 1; }
		$live = 0;
		if ( isset( $_POST["live"] ) && $_POST["live"] == "1" ) { $live = 1; }
		$limit = 0;
		if ( $_POST["limit"] != "" && $_POST["limit"] != "0" ) { $limit = (int)$_POST["limit"]; }
		$expiry = 0;
		if ( $_POST["expiryyear"] != "" && $_POST["expiryyear"] != "0" && $_POST["expirymonth"] != "" && $_POST["expirymonth"] != "0" && $_POST["expiryday"] != "" && $_POST["expiryday"] != "0" ) { $expiry = strtotime( $_POST["expiryyear"] . "/" . $_POST["expirymonth"] . "/" . $_POST["expiryday"]); }
		if ( $_POST["codestype"] == "random" || $_POST["codestype"]== "sequential" || $_POST["codestype"]== "custom" || $_POST["codestype"]== "single" ) {
			$codestype = $_POST["codestype"];
		} else {
			$codestype = "random";
		}
		if ( $_POST["codelength"] != "" ) {
			$codelength = (int)$_POST["codelength"];
		}
		if ( $codelength == "" || $codelength == 0 ) {
			$codelength = 6;
		}
		$codeprefix = trim( $_POST["codeprefix"] );
		if ( strlen( $codeprefix ) > 6 ) { $codeprefix = substr( $codeprefix, 6 ); }
		$codesuffix = trim( $_POST["codesuffix"] );
		if ( strlen( $codesuffix ) > 6 ) { $codesuffix = substr( $codesuffix, 6 ); }
		$codes = "";
		if ( $_POST["codestype"]== "custom" ) {
			$codes = trim( $_POST["customcodelist"] );
		}
		if ( $_POST["codestype"]== "single" ) {
			$codes = trim( $_POST["singlecodetext"] );
		}
		$done = wp_coupon_edit_coupon( $_GET["id"], $_POST["name"], $require_email, $limit, $_POST["text"], $_POST["description"], $_POST["template"], $_POST["font"], $_POST["terms"], $live, $expiry, $codestype, $codelength, $codeprefix, $codesuffix, $codes );
		if ( $done ) {
			header( "Location: admin.php?page=coupons&id=" . $_GET["id"] . "&result=3" );
			exit();
		} else {
			header( "Location: admin.php?page=coupons&id=" . $_GET["id"] . "&result=2" );
			exit();
		}
	}
}

// listen for debugging of a coupon
function wp_coupon_check_debug_coupon() {
	if ( @$_GET["page"] == "coupons" && @$_GET["debug"] == "true" && @$_GET["id"] != "" ) {
		$coupon = wp_coupon_get_coupon( $_GET["id"], 0 );
		if ( $coupon ) {
			header( 'Content-type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="coupon-debug.csv"' );
			echo "ID,Name,Text,Terms,Font,Template,Require Email,Limit,Expiry,GUID,Live\n";
			echo $coupon->id . ',"' . $coupon->name . '","' . $coupon->text . '","' . $coupon->terms . '","' . $coupon->font . '",' . $coupon->template . ',' . $coupon->require_email . ',' . $coupon->limit . ',"' . $coupon->expiry . '","' .  $coupon->guid . '",' . $coupon->live . "\n\n";
			$downloads = wp_coupon_coupon_downloads( $_GET["id"] );
			if ( $downloads ) {
				echo "Datestamp,Email,Name,Code,GUID,Downloaded\n";
				foreach( $downloads as $download ) {
					echo '"' . date("r", $download->time) . '","' . $download->email . '","' . $download->name . '","' . $download->code . '",' . $download->guid . ',' . $download->downloaded . "\n";
				}
			}
			exit();
		} else {
			wp_coupon_404();
		}
	}
}

function wp_coupon_coupon_downloads( $couponid = 0 ) {
	global $wpdb, $current_blog;
	$blog_id = 1;
	if ( is_object( $current_blog ) ) { $blog_id = $current_blog->blog_id; }
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "select v.name as coupon, d.time, d.downloaded, d.email, d.name, d.code, d.guid from " . $prefix . "wp_coupon_downloads d inner join " . $prefix . "wp_coupon_coupons v on v.id = d.couponid
	where (%d = 0 or couponid = %d)
	and deleted = 0
	and v.blog_id = %d;",
	$couponid, $couponid, $blog_id );
	$emails = $wpdb->get_results( $sql );
	return $emails;
}

// download a list of email addresses
function wp_coupon_download_emails( $couponid = 0 ) {
	$emails = wp_coupon_coupon_downloads( $couponid );
	if ( $emails && is_array( $emails ) && count( $emails ) > 0 ) {
		header( 'Content-type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="coupon-emails.csv"' );
		echo "coupon,Datestamp,Name,Email,Code\n";
		foreach( $emails as $email ) {
			echo htmlspecialchars( $email->coupon ) . "," . str_replace( ",", "", date( "r", $email->time ) ) . "," . htmlspecialchars( $email->name ) . "," . htmlspecialchars( $email->email ) . "," . htmlspecialchars( $email->code ) . "\n";
		}
		exit();
	} else {
		return false;
	}
}

// preview a coupon
function wp_coupon_preview_coupon( $template, $font, $name, $text, $terms ) {

	global $current_user;
	
	$coupon->template = $template;
	$coupon->font = $font;
	$coupon->name = $name;
	$coupon->text = $text;
	$coupon->terms = $terms;
	
	wp_coupon_render_coupon( $coupon, "[" . __( "coupon code inserted here", "wp-coupon" ) . "]" );
	
}

// create a new coupon
function wp_coupon_create_coupon( $name, $require_email, $limit, $text, $description, $template, $font, $terms, $expiry, $codestype, $codelength, $codeprefix, $codesuffix, $codes ) {

	// check wp-coupon is installed correctly
	wp_coupon_check_install();

	$blog_id = wp_coupon_blog_id();
	$guid = wp_coupon_guid( 36 );
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "insert into " . $prefix . "wp_coupon_coupons 
	(blog_id, name, `text`, `description`, terms, template, font, require_email, `limit`, guid, time, live, expiry, codestype, codelength, codeprefix, codesuffix, codes, deleted) 
	values 
	(%d, %s, %s, %s, %s, %d, %s, %d, %d, %s, %d, %d, %d, %s, %d, %s, %s, %s, 0);", 
	$blog_id, $name, $text, $description, $terms, $template, $font, $require_email, $limit, $guid, time(), 1, $expiry, $codestype, $codelength, $codeprefix, $codesuffix, $codes );
	$done = $wpdb->query( $sql );
	$id = 0;
	if ( $done ) {
		$id = $wpdb->insert_id;
		do_action( "wp_coupon_create", $id, $name, $text, $description, $template, $require_email, $limit, $expiry );
	}
	return array( $done, $id );
}

// create a new coupon thumbnail
function wp_coupon_create_coupon_thumb( $id, $name, $require_email, $limit, $text, $template, $font, $terms, $expiry ) {
	// do nothing
}

// delete a coupon
function wp_coupon_delete_coupon( $id ) {

	// check wp-coupon is installed correctly
	wp_coupon_check_install();

	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	
	$sql = $wpdb->prepare( "update " . $prefix . "wp_coupon_coupons 
	set deleted = 1 
	where id = %d and blog_id = %d;",
	$id, $blog_id);
	return $wpdb->query( $sql );
}

// edit a coupon
function wp_coupon_edit_coupon( $id, $name, $require_email, $limit, $text, $description, $template, $font, $terms, $live, $expiry, $codestype, $codelength, $codeprefix, $codesuffix, $codes ) {

	// check wp-coupon is installed correctly
	wp_coupon_check_install();

	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "update " . $prefix . "wp_coupon_coupons set 
	time = %d,
	name = %s, 
	`text` = %s, 
	`description` = %s,
	terms = %s,
	template = %d, 
	font = %s, 
	require_email = %d,
	`limit` = %d,
	live = %d,
	expiry = %d,
	codestype = %s,
	codelength = %d,
	codeprefix = %s,
	codesuffix = %s,
	codes = %s
	where id = %d 
	and blog_id = %d;", 
	time(), $name, $text, $description, $terms, $template, $font, $require_email, $limit, $live, $expiry, $codestype, $codelength, $codeprefix, $codesuffix, $codes, $id, $blog_id );
	$done = $wpdb->query( $sql );
	if ( $done ) {
		do_action( "wp_coupon_edit", $id, $name, $text, $description, $template, $require_email, $limit, $expiry );
	}
	return $done;
}

// ==========================================================================================
// template functions

function wp_coupon_upload_template( $id, $file ) {

	$file = $file["tmp_name"];

	// get the image size
	$imagesize = getimagesize( $file );
	$width = $imagesize[0];
	$height = $imagesize[1];
	$imagetype = $imagesize[2];
	
	// if the imagesize could be fetched and is JPG, PNG or GIF
	if ( $imagetype == 2 && $width == 1181 && $height == 532 )
	{

		// check wp-coupon is installed correctly
		wp_coupon_check_install();
	
		$path = ABSPATH . "/wp-content/plugins/wp-coupon/templates/";
		
		// move the temporary file to the full-size image (1181 x 532 px @ 150dpi)
		$fullpath = $path . $id . ".jpg";
		move_uploaded_file( $file, $fullpath );
		
		// get the image
		$image = imagecreatefromjpeg( $fullpath );
		
		// create the preview image (800 x 360 px @ 72dpi)
		$preview = imagecreatetruecolor( 800, 360 );
		imagecopyresampled( $preview, $image, 0, 0, 0, 0, 800, 360, $width, $height );
		$previewpath = $path . $id . "_preview.jpg";
		imagejpeg( $preview, $previewpath, 80 );
		
		// create the thumbnail image (200 x 90 px @ 72dpi)
		$thumb = imagecreatetruecolor( 200, 90 );
		imagecopyresampled( $thumb, $image, 0, 0, 0, 0, 200, 90, $width, $height );
		$thumbpath = $path . $id . "_thumb.jpg";
		imagejpeg( $thumb, $thumbpath, 70 );
		
		return true;

		
	} else {
	
		return false;
	
	}
}

// add a new template
function wp_coupon_add_template( $name ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "insert into " . $prefix . "wp_coupon_templates 
	(blog_id, name, time) 
	values 
	(%d, %s, %d);", 
	$blog_id, $name, time() );
	if ( $wpdb->query( $sql ) )
	{
		return $wpdb->insert_id;
	} else {
		return false;
	}
}

// edit a template
function wp_coupon_edit_template( $id, $name, $live ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "update " . $prefix . "wp_coupon_templates set 
	name = %s, 
	live = %d 
	where id = %d and blog_id = %d;", 
	$name, $live, $id, $blog_id );
	return $wpdb->query( $sql );
}

// get a list of templates
function wp_coupon_get_templates() {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "select id, blog_id, name from " . $prefix . "wp_coupon_templates where live = 1 and (blog_id = 0 or blog_id = %d);", $blog_id );
	return $wpdb->get_results( $sql );
}

// ==========================================================================================
// coupons functions

// get a list of all blog coupons
function wp_coupon_get_all_coupons( $num = 25, $start = 0 ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$showall = "0";
	if ($all) { $showall = "1"; }
	$limit = "limit " . (int)$start . ", " . (int)$num;
	if ($num == 0) { $limit = ""; }
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = "select b.domain, b.path, v.id, v.name, v.`text`, v.terms, v.require_email, v.`limit`, v.live, v.expiry, v.guid, 
(select count(d.id) from " . $prefix . "wp_coupon_downloads d where d.couponid = v.id) as downloads
from " . $prefix . "wp_coupon_coupons v
inner join " . $wpdb->base_prefix . "blogs b on b.blog_id = v.blog_id
where v.live = 1
and v.deleted = 0
order by v.time desc 
" . $limit . ";";
	return $wpdb->get_results( $sql );
}

// get a list of coupons
function wp_coupon_get_coupons( $num = 25, $all=false ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$showall = "0";
	if ($all) { $showall = "1"; }
	$limit = "limit " . (int)$num;
	if ($num == 0) { $limit = ""; }
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "select v.id, v.name, v.`text`, v.`description`, v.terms, v.require_email, v.`limit`, v.live, v.expiry, v.guid, 
(select count(d.id) from " . $prefix . "wp_coupon_downloads d where d.couponid = v.id) as downloads
from " . $prefix . "wp_coupon_coupons v
where (%s = '1' or v.live = 1)
and v.blog_id = %d
and v.deleted = 0
order by v.time desc 
" . $limit . ";", $showall, $blog_id );
	return $wpdb->get_results( $sql );
}

// get a list of all popular coupons by download
function wp_coupon_get_all_popular_coupons( $num = 25, $start = 0 ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$limit = "limit " . (int)$start . ", " . (int)$num;
	if ($num == 0) { $limit = ""; }
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = "select b.domain, b.path, v.id, v.name, v.`text`, v.`description`, v.terms, v.require_email, v.`limit`, v.live, v.expiry, v.guid, 
count(d.id) as downloads
from " . $prefix . "wp_coupon_downloads d 
inner join " . $prefix . "wp_coupon_coupons v on v.id = d.couponid
inner join " . $wpdb->base_prefix . "blogs b on b.blog_id = v.blog_id
group by b.domain, b.path, v.id, v.name, v.`text`, v.terms, v.require_email, v.`limit`, v.live, v.expiry, v.guid
where v.deleted = 0
order by count(d.id) desc
" . $limit . ";";
	return $wpdb->get_results( $sql );
}

// get a list of popular coupons by download
function wp_coupon_get_popular_coupons( $num = 25 ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$limit = "limit " . (int)$num;
	if ($num == 0) { $limit = ""; }
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "select v.id, v.name, v.`text`, v.`description`, v.terms, v.require_email, v.`limit`, v.live, v.expiry, v.guid, 
count(d.id) as downloads
from " . $prefix . "wp_coupon_downloads d 
inner join " . $prefix . "wp_coupon_coupons v on v.id = d.couponid
where v.blog_id = %d
and v.deleted = 0
group by v.id, v.name, v.`text`, v.terms, v.require_email, v.`limit`, v.live, v.expiry, v.guid
order by count(d.id) desc
" . $limit . ";", $blog_id );
	return $wpdb->get_results( $sql );
}

// ==========================================================================================
// individual coupon functions

// get a coupon by id or guid
function wp_coupon_get_coupon( $coupon, $live = 1, $code = "", $unexpired = 0 ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	// get by id
	if ( is_numeric( $coupon ) ) {
		$sql = $wpdb->prepare( "select v.id, v.name, v.`text`, v.`description`, v.terms, v.font, v.template, v.require_email, v.`limit`, v.expiry, v.guid, v.live, '' as registered_email, '' as registered_name,
		v.codestype, v.codeprefix, v.codesuffix, v.codelength, v.codes,
		(select count(d.id) from " . $prefix . "wp_coupon_downloads d where d.couponid = v.id) as downloads
		from " . $prefix . "wp_coupon_coupons v
		where 
		(%d = 0 or v.live = 1)
		and v.id = %d
		and v.deleted = 0
		and v.blog_id = %d", 
		$live, $coupon, $blog_id );
	// get by guid
	} else {
		// if a download code has been specified
		if ( $code != "")
		{
			$sql = $wpdb->prepare( "select v.id, v.name, v.`text`, v.`description`, v.terms, v.font, v.template, v.require_email, v.`limit`, v.expiry, v.guid, v.live, r.email as registered_email, r.name as registered_name,
			v.codestype, v.codeprefix, v.codesuffix, v.codelength, v.codes,
			(select count(d.id) from " . $prefix . "wp_coupon_downloads d where d.couponid = v.id) as downloads
			from " . $prefix . "wp_coupon_coupons v
			left outer join " . $prefix . "wp_coupon_downloads r on r.couponid = v.id and r.guid = %s
			where 
			v.live = 1
			and v.deleted = 0
			and v.guid = %s
			and v.blog_id = %d", 
			$code, $coupon, $blog_id );
		} else {
			$sql = $wpdb->prepare( "select v.id, v.name, v.`text`, v.`description`, v.terms, v.font, v.template, v.require_email, v.`limit`, v.expiry, v.guid, v.live, '' as registered_email, '' as registered_name,
			v.codestype, v.codeprefix, v.codesuffix, v.codelength, v.codes,
			(select count(d.id) from " . $prefix . "wp_coupon_downloads d where d.couponid = v.id) as downloads
			from " . $prefix . "wp_coupon_coupons v
			where 
			(%d = 0 or v.live = 1)
			and v.deleted = 0
			and v.guid = %s
			and v.blog_id = %d", 
			$live, $coupon, $blog_id);
		}
	}
	$row = $wpdb->get_row( $sql );
	if ( is_object( $row ) && $row->id != "" ) {
		return $row;
	} else {
		return false;
	}
}

// check a coupon exists and can be downloaded
function wp_coupon_coupon_exists( $guid ) {
	$blog_id = wp_coupon_blog_id();
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	$sql = $wpdb->prepare( "select v.id, v.`limit`,
	(select count(d.id) from " . $prefix . "wp_coupon_downloads d where d.couponid = v.id) as downloads
	from " . $prefix . "wp_coupon_coupons v
	where 
	v.guid = %s
	and v.deleted = 0
	and v.blog_id = %d", 
	$guid, $blog_id );
	$row = $wpdb->get_row( $sql );
	if ( $row ) {
		return true;
	}
	return false;
}

// download a coupon
function wp_coupon_download_coupon( $coupon_guid, $download_guid = "" ) {
	$coupon = wp_coupon_get_coupon( $coupon_guid, 1, $download_guid );
	if (
	is_object( $coupon )
	&& $coupon->live == 1 
	&& $coupon->id != "" 
	&& $coupon->name != "" 
	&& $coupon->text != "" 
	&& $coupon->terms != "" 
	&& $coupon->template != "" 
	&& wp_coupon_template_exists( $coupon->template ) 
	)
	{
		// see if this coupon can be downloaded
		$valid = wp_coupon_download_guid_is_valid( $coupon_guid, $download_guid );
		if ( $valid === "valid" ) {
		
			// set this download as completed
			$code = wp_coupon_create_download_code( $coupon->id, $download_guid );

			do_action( "wp_coupon_download", $coupon->id, $coupon->name, $code );
			
			// render the coupon
			wp_coupon_render_coupon( $coupon, $code );
			
		} else if ( $valid === "unavailable" )  {
		
			// this coupon is not available
			print "<!-- The coupon is not available for download -->";
			wp_coupon_404();
			
		} else if ( $valid === "runout" )  {
		
			// this coupon has run out
			print "<!-- The coupon has run out -->";
			wp_coupon_runout();
			
		} else if ( $valid === "downloaded" )  {
		
			// this coupon has been downloaded already
			print "<!-- The coupon has already been downloaded by this person -->";
			wp_coupon_downloaded();
			
		} else if ( $valid === "expired" )  {
		
			// this coupon has expired
			print "<!-- The coupon has expired -->";
			wp_coupon_expired();
			
		}
	} else {

		// this coupon is not available
		print "<!-- The coupon could not be found -->";
		wp_coupon_404(false);
		
	}
}

// render a coupon
function wp_coupon_render_coupon( $coupon, $code ) {

	global $current_user;
	// get the coupon template image
	if( wp_coupon_template_exists( $coupon->template ) )
	{
		// get the current memory limit
		$memory = ini_get( 'memory_limit' );
		
		// try to set the memory limit
		//@ini_set( 'memory_limit', '64mb' );
	
		$slug = wp_coupon_slug( $coupon->name );
	
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header( 'Content-type: image/jpeg' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '.jpg"' );
		
		// set the properties
		//$pdf->coupon_image = ABSPATH . 'wp-content/plugins/wp-coupon/templates/' . $coupon->template . '.jpg';
		//$pdf->coupon_image_w = 200;
		//$pdf->coupon_image_h = 90;
		//$pdf->coupon_image_dpi = 150;
		
		// set header and footer fonts
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		
		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
		
		//set margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->SetHeaderMargin(0);
		$pdf->SetFooterMargin(0);
		
		// remove default footer
		$pdf->setPrintFooter(false);
		
		//set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		
		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO); 
		
		//set some language-dependent strings
		$pdf->setLanguageArray($l); 
		
		// set top margin
		$pdf->SetTopMargin(15);
		
		// add a page
		$pdf->AddPage('L', array(200,90));
		
		// set title font
		$pdf->SetFont($coupon->font, '', 32);
		// print title
		$pdf->writeHTML( stripslashes( $coupon->name ), $ln=true, $fill=false, $reseth=false, $cell=false, $align='C');
		
		// set text font
		$pdf->SetFont($coupon->font, '', 18);
		// print text
		$pdf->Write( 5,  stripslashes( $coupon->text ), $link = '', $fill = 0, $align = 'C', $ln = true);

		$registered_name = "";
		if ( $coupon->registered_name != "" ) {
			$registered_name =  __( "Registered to:", "wp-coupon" ) . " "  . stripslashes( $coupon->registered_name ) . ": ";
		}
		
		// set code font
		$pdf->SetFont($coupon->font, '', 14);
		// print code
		$pdf->Write( 10, $registered_name . $code, $link = '', $fill = 0, $align = 'C', $ln = true);

		// get the expiry, if it exists
		$expiry = ""; 
		if ( $coupon->expiry != "" && (int)$coupon->expiry > 0 ) {
			$expiry = " " . __( "Expiry:", "wp-coupon" ) . " " . date( "Y/m/d", $coupon->expiry );
		}
		
		// set terms font
		$pdf->SetFont($coupon->font, '', 10);
		// print terms
		$pdf->Write( 5,  stripslashes( $coupon->terms ) . $expiry, $link = '', $fill = 0, $align = 'C', $ln = true);

		// close and output PDF document
		$pdf->Output( $slug . '.pdf', 'D' ); 
		
		// try to set the memory limit back
		//@ini_set( 'memory_limit', @memory );
		
		exit();
		
	} else {
	
		return false;
		
	}
}

// render a coupon
function wp_coupon_render_coupon_thumb( $coupon, $code ) {
	// do nothing
}

// check a template exists
function wp_coupon_template_exists( $template ) {
	$file = ABSPATH . "wp-content/plugins/wp-coupon/templates/" . $template . ".jpg";
	if ( file_exists( $file ) ) {
		return true;
	}
	return false;
}

// ==========================================================================================
// person functions

// show the registration form
function wp_coupon_register_form( $coupon_guid, $plain = false ) {

	$out = "";
	$showform = true;
	
	if ( !$plain ) {
	get_header();
	echo '
	<div id="content" class="narrowcolumn" role="main">
	<div class="post category-uncategorized" id="coupon-' . $coupon_guid . '">
	';
	}
	
	// if registering
	if ( @$_POST["coupon_email"] != "" && @$_POST["coupon_name"] != "" )
	{
	
		// if the email address is valid
		if ( is_email( trim($_POST["coupon_email"]) ) )
		{
	
			// register the email address
			$download_guid = wp_coupon_register_person( $coupon_guid, trim($_POST["coupon_email"]), trim($_POST["coupon_name"]) );
			
			// if the guid has been generated
			if ( $download_guid ) {
			
				$coupon = wp_coupon_get_coupon( $coupon_guid );

				$message = "";
				if ( $coupon->description != "" ) {
					$message .= $coupon->description . "\n\n";
				}
				$message .= __( "You have successfully registered to download this coupon, please download the coupon from here:", "wp-coupon" ) . "\n\n" . wp_coupon_link( $coupon_guid, $download_guid, false );
			
				// send the email
				wp_mail( trim($_POST["coupon_email"]), $coupon->name . " for " . trim($_POST["coupon_name"]), $message );
			
				do_action( "wp_coupon_register", $coupon->id, $coupon->name, $_POST["coupon_email"], $_POST["coupon_name"] );
			
				$out .= '
				<p>' .  __( "Thank you for registering. You will shortly receive an email sent to '" . trim($_POST["coupon_email"]) . "' with a link to your personalised coupon.", "wp-coupon" ) . '</p>
				';
				if ( !$plain ) {
					echo $out;
					$out = "";
				}
				$showform = false;
			
			} else {
			
				$out .= '
				<p>' .  __( "Sorry, your email address and name could not be registered. Have you already registered for this coupon? Please try again.", "wp-coupon" ) . '</p>
				';
				if ( !$plain ) {
					echo $out;
					$out = "";
				}
			
			}
			
		} else {
		
			$out .= '
			<p>' .  __( "Sorry, your email address was not valid. Please try again.", "wp-coupon" ) . '</p>
			';
			if ( !$plain ) {
				echo $out;
				$out = "";
			}
		
		}
	}
	
	if ( $showform )
	{
		if ( !$plain ) {
		$out .= '
		<h2>' . __( "Please provide some details", "wp-coupon" ) . '</h2>
		<p>' .  __( "To download this coupon you must provide your name and email address. You will then receive a link by email to download your personalised coupon.", "wp-coupon" ) . '</p>
		<form action="' . wp_coupon_link( $coupon_guid ) . '" method="post" class="wp_coupon_form">
		';
		} else {
		$out .= '
		<form action="' . wp_coupon_page_url() . '" method="post" class="wp_coupon_form">
		';
		}
		
		$out .= '
		<p><label for="coupon_email">' .  __( "Your email address", "wp-coupon" ) . '</label>
		<input type="text" name="coupon_email" id="coupon_email" value="' . trim(@$_POST["coupon_email"]) . '" /></p>
		<p><label for="coupon_name">' .  __( "Your name", "wp-coupon" ) . '</label>
		<input type="text" name="coupon_name" id="coupon_name" value="' . trim(@$_POST["coupon_name"]) . '" /></p>
		<p><input type="submit" name="coupon_submit" id="coupon_submit" value="' .  __( "Register for this coupon", "wp-coupon" ) . '" /></p>
		</form>
	';
	
		if ( !$plain ) {
			echo $out;
			$out = "";
		}
	
	}
	
	if ( !$plain ) {
	echo '
	</div>
	</div>
	';
	get_footer();
	}
	return $out;
}

function wp_coupon_page_url() {
	$pageURL = 'http';
	if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ) { $pageURL .= "s"; }
	$pageURL .= "://";
	if ( $_SERVER["SERVER_PORT"] != "80" ) {
		$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

// register a persons name and email address
function wp_coupon_register_person( $coupon_guid, $email, $name ) {
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	
	// get the coupon id
	$sql = $wpdb->prepare( "select id from " . $prefix . "wp_coupon_coupons where guid = %s and deleted = 0;", $coupon_guid );
	$couponid = $wpdb->get_var( $sql ); 
	
	// if the id has been found
	if ( $couponid != "" ) {
	
		// if the email address has already been registered
		$sql = $wpdb->prepare( "select guid from " . $prefix . "wp_coupon_downloads where couponid = %d and email = %s;", $couponid, $email );		
		$guid = $wpdb->get_var( $sql );

		if ( $guid == "" )
		{

			// get the IP address
			$ip = wp_coupon_ip();
			
			// create the code
			$code = wp_coupon_create_code( $couponid );
			
			// create the guid
			$guid = wp_coupon_guid( 36 );
			
			// insert the new download
			$sql = $wpdb->prepare( "insert into " . $prefix . "wp_coupon_downloads 
			(couponid, time, email, name, ip, code, guid, downloaded)
			values
			(%d, %d, %s, %s, %s, %s, %s, 0)", 
			$couponid, time(), $email, $name, $ip, $code, $guid );
			$wpdb->query( $sql );
			
		}
			
		return $guid;
	}
	return false;
}

// check a code address is valid for a coupon
function wp_coupon_download_guid_is_valid( $coupon_guid, $download_guid ) {
	if ( $coupon_guid == "" && $download_guid == "" ) {
		return false;
	} else {
		global $wpdb;
		$prefix = $wpdb->prefix;
		if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
		$blog_id = wp_coupon_blog_id();
		global $wpdb;
		$sql = $wpdb->prepare( "select v.id, v.require_email, ifnull( d.email, '' ) as email, ifnull( d.downloaded, 0 ) as downloaded, v.`limit`, v.expiry from
				" . $prefix . "wp_coupon_coupons v
				left outer join " . $prefix . "wp_coupon_downloads d on d.couponid = v.id and d.guid = %s
				where v.guid = %s
				and v.blog_id = %d
				and v.deleted = 0;", 
				$download_guid, $coupon_guid, $blog_id );
		$row = $wpdb->get_row( $sql );
		// if the coupon has been found
		if ( $row )
		{

			// a limit has been set
			if ( (int)$row->limit != 0 ) {
				$sql = $wpdb->prepare( "select count(id) from " . $prefix . "wp_coupon_downloads where couponid = %d", $row->id );
				$downloads = $wpdb->get_var( $sql );
				// if the limit has been reached
				if ( (int)$downloads >= (int)$row->limit )	{
					return "runout";
				}
			}
			
			// if there is an expiry and the expiry is in the past
			if ( (int)$row->expiry != 0 && (int)$row->expiry <= time() ) {
				return "expired";
			}
			
			// if emails are not required
			if ( $row->require_email != "1" ) {
				return "valid";
			} else {
				// if the coupon has been downloaded
				if ( $download_guid != "" && $row->email != "" && $row->downloaded != "0" ) {
					return "downloaded";
				}
				// if the coupon has not been downloaded
				if ( $download_guid != "" && $row->email != "" && $row->downloaded == "0" ) {
					return "valid";
				}
				return "unregistered";
			}
		}
		return "unavailable";
	}
}

// get the next custom code in a list
function wp_coupon_get_custom_code( $codes ) {
	if ( trim( $codes ) != "" ) {
		$codelist = explode( "\n", $codes );
		if ( is_array( $codelist ) && count( $codelist ) > 0 ) {
			return trim( $codelist[0] );
		}
	}
	return wp_coupon_guid();
}

// create a download code for a coupon
function wp_coupon_create_download_code( $couponid, $download_guid = "" ) {
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	if ( $download_guid != "" )
	{

		// set this coupon as being downloaded
		$sql = $wpdb->prepare( "update " . $prefix . "wp_coupon_downloads set downloaded = 1 where couponid = %d and guid = %s;", $couponid, $download_guid );
		$wpdb->query( $sql );
		
		// get the code
		$sql = $wpdb->prepare( "select code from " . $prefix . "wp_coupon_downloads where couponid = %d and guid = %s;", $couponid, $download_guid );
		$code = $wpdb->get_var( $sql );
		
	} else {
	
		// get the IP address
		$ip = wp_coupon_ip();
		
		$code = wp_coupon_create_code( $couponid );
		
		// insert the download
		$sql = $wpdb->prepare( "insert into " . $prefix . "wp_coupon_downloads 
		(couponid, time, ip, guid, downloaded)
		values
		(%d, %d, %s, %s, 1)", 
		$couponid, time(), $ip, $code );
		$wpdb->query( $sql );
	}
	
	// return this code
	return $code;
}

// create a code for a coupon
function wp_coupon_create_code( $couponid ) {
	global $wpdb;
	$prefix = $wpdb->prefix;
	if ( isset( $wpdb->base_prefix ) ) { $prefix = $wpdb->base_prefix; }
	// get the codes type for this coupon
	$sql = $wpdb->prepare( "select v.codestype, v.codeprefix, v.codesuffix, v.codelength, v.codes, count(d.id) as downloads from " . $prefix . "wp_coupon_coupons v left outer join " . $prefix . "wp_coupon_downloads d on d.couponid = v.id where v.id = %d and v.deleted = 0 group by v.codestype, v.codeprefix, v.codesuffix, v.codelength, v.codes;", $couponid );
	$coupon_codestype = $wpdb->get_row( $sql );
	// using custom codes
	if ( $coupon_codestype->codestype == "custom" ) {
	
		// use the next one of the custom codes
		$code = wp_coupon_get_custom_code( $coupon_codestype->codes );
		
		// set the remaining codes by removing this code
		$remaining_codes = trim( str_replace( $code, "", $coupon_codestype->codes ) );
		
		// update the codes to set this one as being used
		$sql = $wpdb->prepare( "update " . $prefix . "wp_coupon_coupons set codes = %s where id = %d;", $remaining_codes, $couponid );
		$wpdb->query( $sql );
		
	// using sequential codes
	} else if ( $coupon_codestype->codestype == "sequential" ) {
	
		// add one to the number of coupons already downloaded
		$code = $coupon_codestype->codeprefix . ((int)$coupon_codestype->downloads + 1) . $coupon_codestype->codesuffix;
		
	// using a single code
	} else if ( $coupon_codestype->codestype == "single" ) {
	
		// get the code
		$code = $coupon_codestype->codes;
		
	// using random codes
	} else {
	
		// create the random code
		$code = wp_coupon_guid($coupon_codestype->codelength);
		
	}
	return $code;
}

// a standard header for your plugins, offers a PayPal donate button and link to a support page
function wp_coupon_wp_plugin_standard_header( $currency = "", $plugin_name = "", $author_name = "", $paypal_address = "", $bugs_page ) {
	$r = "";
	$option = get_option( $plugin_name . " header" );
	if ( ( isset( $_GET[ "header" ] ) && $_GET[ "header" ] != "" ) || ( isset( $_GET["thankyou"] ) && $_GET["thankyou"] == "true" ) ) {
		update_option( $plugin_name . " header", "hide" );
		$option = "hide";
	}
	if ( isset( $_GET["thankyou"] ) && $_GET["thankyou"] == "true" ) {
		$r .= '<div class="updated"><p>' . __( "Thank you for donating" ) . '</p></div>';
	}
	if ( $currency != "" && $plugin_name != "" && ( !isset( $_GET["header"] ) || $_GET[ "header" ] != "hide" ) && $option != "hide" )
	{
		$r .= '<div class="updated">';
		$pageURL = 'http';
		if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ) { $pageURL .= "s"; }
		$pageURL .= "://";
		if ( $_SERVER["SERVER_PORT"] != "80" ) {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}
		if ( strpos( $pageURL, "?") === false ) {
			$pageURL .= "?";
		} else {
			$pageURL .= "&";
		}
		$pageURL = htmlspecialchars( $pageURL );
		if ( $bugs_page != "" ) {
			$r .= '<p>' . sprintf ( __( 'To report bugs please visit <a href="%s">%s</a>.' ), $bugs_page, $bugs_page ) . '</p>';
		}
		if ( $paypal_address != "" && is_email( $paypal_address ) ) {
			$r .= '
			<form id="wp_plugin_standard_header_donate_form" action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_donations" />
			<input type="hidden" name="item_name" value="Donation: ' . $plugin_name . '" />
			<input type="hidden" name="business" value="' . $paypal_address . '" />
			<input type="hidden" name="no_note" value="1" />
			<input type="hidden" name="no_shipping" value="1" />
			<input type="hidden" name="rm" value="1" />
			<input type="hidden" name="currency_code" value="' . $currency . '">
			<input type="hidden" name="return" value="' . $pageURL . 'thankyou=true" />
			<input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHosted" />
			<p>';
			if ( $author_name != "" ) {
				$r .= sprintf( __( 'If you found %1$s useful please consider donating to help %2$s to continue writing free Wordpress plugins.' ), $plugin_name, $author_name );
			} else {
				$r .= sprintf( __( 'If you found %s useful please consider donating.' ), $plugin_name );
			}
			$r .= '
			<p><input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="" /></p>
			</form>
			';
		}
		$r .= '<p><a href="' . $pageURL . 'header=hide" class="button">' . __( "Hide this") . '</a></p>';
		$r .= '</div>';
	}
	print $r;
}
function wp_coupon_wp_plugin_standard_footer( $currency = "", $plugin_name = "", $author_name = "", $paypal_address = "", $bugs_page ) {
	$r = "";
	if ( $currency != "" && $plugin_name != "" )
	{
		$r .= '<form id="wp_plugin_standard_footer_donate_form" action="https://www.paypal.com/cgi-bin/webscr" method="post" style="clear:both;padding-top:50px;"><p>';
		$pageURL = 'http';
		if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ) { $pageURL .= "s"; }
		$pageURL .= "://";
		if ( $_SERVER["SERVER_PORT"] != "80" ) {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}
		if ( strpos( $pageURL, "?") === false ) {
			$pageURL .= "?";
		} else {
			$pageURL .= "&";
		}
		$pageURL = htmlspecialchars( $pageURL );
		if ( $bugs_page != "" ) {
			$r .= sprintf ( __( '<a href="%s">Bugs</a>' ), $bugs_page );
		}
		if ( $paypal_address != "" && is_email( $paypal_address ) ) {
			$r .= '
			<input type="hidden" name="cmd" value="_donations" />
			<input type="hidden" name="item_name" value="Donation: ' . $plugin_name . '" />
			<input type="hidden" name="business" value="' . $paypal_address . '" />
			<input type="hidden" name="no_note" value="1" />
			<input type="hidden" name="no_shipping" value="1" />
			<input type="hidden" name="rm" value="1" />
			<input type="hidden" name="currency_code" value="' . $currency . '" />
			<input type="hidden" name="return" value="' . $pageURL . 'thankyou=true" />
			<input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHosted" />
			<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" name="submit" alt="' . __( "Donate" ) . ' ' . $plugin_name . '" />
			';
		}
		$r .= '</p></form>';
	}
	print $r;
}

require_once( "plugin-register.class.php" );
$register = new Plugin_Register();
$register->file = __FILE__;
$register->slug = "wp-coupon";
$register->name = "wp-coupon";
$register->version = wp_coupon_current_version();
$register->developer = "Chris Taylor";
$register->homepage = "http://www.stillbreathing.co.uk";
$register->Plugin_Register();