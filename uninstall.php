<?php
define( 'UMD_CAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMD_CAP_INCLUDE_DIR', plugin_dir_path( __FILE__ ) . "includes" . DIRECTORY_SEPARATOR );
define( 'UMD_CAP_PLUGIN_NAME', plugin_basename( __FILE__ ));

require_once(UMD_CAP_INCLUDE_DIR . "umd_cap.class.php");
umd_cap::init();

if(!defined("WP_UNINSTALL_PLUGIN")){
	exit;
}

$data_to_delete = get_option("umd_cap_data_to_delete");

if($data_to_delete === false)
	return true;

$data_to_delete = (int)$data_to_delete;

if($data_to_delete === 0 || $data_to_delete === 1)
	return true;

global $wpdb;
$db_prefix = $wpdb->prefix;
$field_prefix = umd_cap::$field_prefix;

// Delete tables
if(in_array($data_to_delete, array(2,9))){
	$wpdb->query("DROP TABLE `{$db_prefix}umd_cap_fields`");
}

// Delete plugin options
if(in_array($data_to_delete, array(2,9))){
	$wpdb->query("DELETE FROM `{$db_prefix}options` WHERE option_name LIKE 'umd_cap_%'");
}

// Delete user meta data
if(in_array($data_to_delete, array(9))){
	$wpdb->query("DELETE FROM `{$db_prefix}usermeta` WHERE meta_key LIKE '{$field_prefix}%'");
}