<?php
/**
* @version functions.php Jul 19, 2018 23:19:47 Nicholas Moller
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

/**
 * Global scope wrapper for umd_cap::get_author_meta
 *
 * @param string $field_name The name of the meta field.
 *
 * @return string
 */
function umd_cap_get_author_meta($field_name){
	if(!class_exists("umd_cap"))
		return '';

	return umd_cap::get_author_meta($field_name);
}

/**
 * Global scope wrapper for umd_cap::get_user_meta
 *
 * @param integer $user_id The user id of the particular user you want meta information for.
 * @param string $field_name The name of the meta field.
 * @param boolean $single Whether to return a single value.
 *
 * @return mixed|string
 */
function umd_cap_get_user_meta($user_id, $field_name, $single = false){
	if(!class_exists("umd_cap"))
		return '';

	return umd_cap::get_user_meta($user_id, $field_name, $single);
}