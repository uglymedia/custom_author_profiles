<?php
/**
 * @version umd_cap.class.php Jul 19, 2018 22:23:38 Nicholas Moller
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

class umd_cap {
	public static $field_prefix = '';
	private static $initiated = false;

	public static function init(){
		if(self::$initiated)
			return true;

		self::set_field_prefix();

		self::$initiated = true;
	}

	/**
	 * Wrapper that alters the meta key value to include the umd_cap prefix and then passes onto the WP get_the_author_meta function.
	 *
	 * @param $field_name
	 *
	 * @return string
	 */
	public static function get_author_meta($field_name){
		$field_name = self::$field_prefix . str_replace(self::$field_prefix, '', $field_name);

		return get_the_author_meta($field_name);
	}

	/**
	 * Wrapper that alters the meta key value to include the umd_cap prefix and then passes onto the WP get_user_meta function.
	 *
	 * @param $user_id
	 * @param $field_name
	 * @param bool $single
	 *
	 * @return mixed
	 */
	public static function get_user_meta($user_id, $field_name, $single = false){
		$field_name = self::$field_prefix . str_replace(self::$field_prefix, '', $field_name);

		return get_user_meta($user_id, $field_name, $single);
	}

	/**
	 * Gets the prefix for user meta data options.
	 *
	 * @return bool always true
	 */
	private static function set_field_prefix(){
		if(!empty(self::$field_prefix))
			return true;

		$custom_field_prefix = get_option('umd_cap_custom_field_prefix');

		if($custom_field_prefix){
			self::$field_prefix = $custom_field_prefix;
		} else {
			self::$field_prefix = "umd_cap_";
		}

		return true;
	}
}