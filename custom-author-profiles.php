<?php
/**
 * Plugin Name: Simple Custom Author Profiles
 * Plugin URI: https://www.usbmemorydirect.com/open-source/custom_author_profiles/
 * Description: A simple plugin to add extended author information to a user profile.
 * Version: 1.0.0
 * Author: USB Memory Direct
 * Author URI: https://www.usbmemorydirect.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: custom-author-profiles
 * Domain Path: languages
 *
 * Custom Author Profiles allows you to add additional fields to an author/user profile
 * Copyright (C) 2018  USB Memory Direct
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Prevent direct access
if(!function_exists("add_action")){
	header( 'HTTP/1.1 403 Forbidden' );
	echo "Sorry, but we don't allow direct access here.";
	exit();
}

define( 'UMD_CAP_VERSION', '1.0.0');
define( 'UMD_CAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMD_CAP_INCLUDE_DIR', plugin_dir_path( __FILE__ ) . "includes" . DIRECTORY_SEPARATOR );
define( 'UMD_CAP_PLUGIN_NAME', plugin_basename( __FILE__ ));

require_once(UMD_CAP_INCLUDE_DIR . "umd_cap.class.php");
umd_cap::init();

require_once(UMD_CAP_INCLUDE_DIR . "functions.php");

if(is_admin()){
	require_once(UMD_CAP_INCLUDE_DIR . "umd_cap_admin.class.php");
	$umd_cap_admin = new umd_cap_admin();

	register_activation_hook( __FILE__, array( $umd_cap_admin, 'plugin_activation' ) );
	register_deactivation_hook( __FILE__, array( $umd_cap_admin, 'plugin_deactivation' ) );
}