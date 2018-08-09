<?php
/**
 * @version umd_cap_admin.class.php Jul 19, 2018 14:15:58 Nicholas Moller
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
 * Class umd_cap_admin
 *
 * @todo better templating for settings pages.
 * @todo improve the field delete process.
 */
class umd_cap_admin {
	private $initiated = false;
	private $field_list = false;
	private $admin_notices = array();
	private $field_prefix;
	private $wpdb;
	private $db_prefix;

	public function __construct(){
		if($this->initiated)
			return true;

		global $wpdb;

		$this->wpdb = $wpdb;
		$this->db_prefix = $this->wpdb->prefix;
		$this->field_prefix = umd_cap::$field_prefix;

		// outside the admin menus scope for redirection purposes.
		add_action('init', array($this, 'delete_field'));

		add_action('admin_menu', array($this, 'add_menus'));
		add_filter('plugin_action_links_' . UMD_CAP_PLUGIN_NAME, array($this, 'add_plugin_action_links'));
		add_action('show_user_profile', array($this, 'add_fields_to_profile'));
		add_action('edit_user_profile', array($this, 'add_fields_to_profile'));
		add_action('personal_options_update', array($this,'update_user_profile_information'));
		add_action('edit_user_profile_update', array($this,'update_user_profile_information'));

		$this->initiated = true;
	}

	/**
	 * Adds a notice to the notices array for later display.
	 *
	 * @param $type
	 * @param $message
	 */
	private function add_notice($type, $message){
		switch($type){
			case 1:
				$notice_type = "notice-success";
				break;
			case 2:
				$notice_type = "notice-error";
				break;
			case 3:
				$notice_type = "notice-warning";
				break;
			case 4:
				$notice_type = "notice-info";
				break;
			default:
				$notice_type = "notice-error";
		}
		$this->admin_notices[] = '<div class="notice ' . $notice_type . ' is-dismissible"><p>' . __( $message ) . '</p></div>';
	}

	/**
	 * Determines what function to call when processing an administrative page request.
	 *
	 * @return bool
	 */
	public function admin_route(){
		if(!current_user_can("manage_options")){
			wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
		}

		if(isset($_GET['page'])){
			switch($_GET['page']){
				case 'umd_cap_settings':
					$this->umd_cap_settings();
					break;
				case 'umd_cap_fields':
					if(isset($_GET['func'])){
						if($_GET['func'] == "add_new_field"){
							$this->show_settings_header('');
							$this->show_add_field_form();
							$this->show_settings_footer();
						}

						if($_GET['func'] == "edit_field")
							$this->edit_field_form();

					} else {
						$this->umd_cap_fields();
					}
					break;
				default:
					// do nothing
					break;
			}

			return true;
		}

		return false;
	}


	/**
	 * Function to handle displaying the cap settings page and updating plugin settings.
	 *
	 */
	private function umd_cap_settings(){
		if($_SERVER['REQUEST_METHOD'] == "POST"){
			if(
				isset($_POST['umd_cap_settings_nonce']) &&
				wp_verify_nonce($_POST['umd_cap_settings_nonce'], 'umd_cap_settings')
			){
				if($_POST['umd_cap_custom_field_prefix'] != $this->field_prefix){
					$new_prefix = rtrim(preg_replace("/[^a-zA-Z0-9-_.]/", "", $_POST['umd_cap_custom_field_prefix']), "_") . "_";
					$test = $this->wpdb->get_results("SELECT * FROM `{$this->db_prefix}usermeta` WHERE meta_key LIKE '{$new_prefix}%' AND meta_key NOT LIKE '{$this->field_prefix}%' LIMIT 1");

					if(!empty($test)){
						$this->add_notice(2, __("Unable to save your new custom prefix, meta_keys exist with this prefix already."));
					} else {
						$this->wpdb->query("START TRANSACTION");

						$test = $this->wpdb->query("
							UPDATE `{$this->db_prefix}usermeta` um
							SET meta_key = REPLACE(meta_key, '{$this->field_prefix}', '{$new_prefix}')
							WHERE meta_key LIKE 'umd_cap_%'"
						);

						$test2 = $this->wpdb->query("
							UPDATE `{$this->db_prefix}umd_cap_fields` cf
							SET slug = REPLACE(slug, '{$this->field_prefix}', '{$new_prefix}')
							WHERE slug LIKE 'umd_cap_%'"
						);

						if($test !== false && $test2 !== false){
							$this->wpdb->query("COMMIT");
							update_option( 'umd_cap_custom_field_prefix', $new_prefix );
						} else {
							$this->wpdb->query("ROLLBACK");
							$this->add_notice(2, __("Unable to update database."));
						}
					}
				}

				update_option('umd_cap_data_to_delete', $_POST['umd_cap_data_to_delete']);

				$this->add_notice(1, __("Settings saved."));
			} else {
				$this->add_notice(2, __("Looks like you didn't mean to do that."));
			}
		}

		$this->show_settings_header("main_settings");
		$this->show_settings_page();
		$this->show_settings_footer();
	}

	/**
	 * Handles routing and display data for the fields settings page.
	 *
	 * @return bool
	 */
	private function umd_cap_fields(){
		if($_SERVER['REQUEST_METHOD'] == 'POST'){
			switch($_POST['umd_cap_action']){
				case 'umd_cap_add_field':
					if(!$this->umd_cap_add_edit_field()){
						$this->show_settings_header('');
						$this->show_add_field_form();
						$this->show_settings_footer();
						return false;
					}
					break;
				case 'umd_cap_edit_field':
					if(!$this->umd_cap_add_edit_field()){
						$this->show_settings_header('');
						$this->show_edit_field_form(false);
						$this->show_settings_footer();
						return false;
					}
					break;
				default:
					$this->add_notice(2, __("Sorry, this action is not supported."));
					break;
			}
		}

		$this->fetch_field_data();
		$this->show_settings_header("fields");
		$this->show_fields_page();
		$this->show_settings_footer();
	}

	/**
	 * Handles editing/adding existing/new fields.
	 *
	 * @return bool
	 */
	private function umd_cap_add_edit_field(){
		if($_POST['umd_cap_action'] == 'umd_cap_edit_field'){
			$field_id = $_POST['field_id'];
			$umd_cap_nonce_value = $_POST['umd_cap_edit_field_nonce'];
			$umd_cap_nonce_action = 'umd_cap_edit_field' . "_" . $_POST['field_id'];
			$edit = true;
		} else {
			$edit = false;
			$umd_cap_nonce_value = $_POST['umd_cap_add_field_nonce'];
			$umd_cap_nonce_action = 'umd_cap_add_field';
		}

		if(!wp_verify_nonce($umd_cap_nonce_value, $umd_cap_nonce_action)){
			$this->add_notice(2, __("Looks like you didn't mean to do that."));
			return false;
		}

		$this->fetch_field_data();
		$field_slug = preg_replace("/[^0-9A-Za-z-_.]+/", "", $_POST['umd_cap_field_slug']);
		$field_name = sanitize_text_field($_POST['umd_cap_field_name']);
		$field_description = sanitize_text_field($_POST['umd_cap_field_description']);

		if($field_slug == "" || $field_name == ""){
			$this->add_notice(2, __("Please fill out the both name and slug."));
			return false;
		}


		$field_slug = $this->field_prefix . sanitize_text_field($field_slug);

		if(!$edit){
			foreach($this->field_list as $field){
				if($field->slug == $field_slug){
					$this->add_notice(2, __("The field slug already exists."));
					return false;
				}
			}
		}

		if($edit){
			$query = "UPDATE `{$this->db_prefix}umd_cap_fields` SET `name` = '$field_name', `slug` = '$field_slug', `description` = '$field_description' WHERE id = $field_id;";
		} else {
			$query = "INSERT INTO `{$this->db_prefix}umd_cap_fields` (`name`,`slug`,`description`) VALUES ('$field_name', '$field_slug', '$field_description');";
		}

		$test = $this->wpdb->query($query);

		if($this->wpdb->last_error !== ''){
			$this->add_notice(2, __("Unable to update database"));
			return false;
		}

		$this->fetch_field_data(true);

		return true;
	}

	/**
	 * Aids in displaying the edit form field.
	 *
	 */
	private function edit_field_form(){
		$the_field = false;
		if($_GET['func'] == "edit_field"){
			$this->fetch_field_data();
			if(!isset($_GET['field_id'])){
				_e("Sorry, no field ID specified.");
				return;
			}

			foreach($this->field_list as $field){
				if($field->id == $_GET['field_id'])
					$the_field = $field;
			}

			if(!$the_field){
				_e("Sorry, that field doesn't exist.");
				return;
			}
		}

		$this->show_settings_header('');
		$this->show_edit_field_form($the_field);
		$this->show_settings_footer();
	}

	/**
	 * Deletes a specific field.
	 *
	 * @return bool
	 */
	public function delete_field(){
		// Return if this is not supposed to be called.
		if(
			$_GET['page'] != "umd_cap_fields" ||
			$_GET['func'] != 'delete_field' ||
			str_replace(str_replace(home_url(), "", admin_url()), "", explode("?", $_SERVER['REQUEST_URI'])[0]) != "admin.php"
		){
			return true;
		}

		if(!current_user_can("manage_options")){
			wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
		}

		if(!isset($_GET['field_id']) || !isset($_GET['umd_cap_delete_field_nonce'])){
			wp_die( __( 'Malformed URL, please go back and try again.' ), 403 );
		}

		$field_id = (int)$_GET['field_id'];

		if($field_id === 0){
			echo "<p>" . __("Field doesn't exist, please go back and try again") . "</p>";
			return false;
		}

		if(!wp_verify_nonce($_GET['umd_cap_delete_field_nonce'], 'umd_cap_delete_field_' . $field_id)){
			echo "<p>" . __("Looks like you didn't mean to do that.") . "</p>";
			return false;
		}

		$this->wpdb->delete($this->db_prefix . "umd_cap_fields", array('id'=>$field_id));

		wp_redirect(admin_url("admin.php?page=umd_cap_fields"), 302);
	}


	/**
	 * Retrieves field data from the database.
	 *
	 * @return bool
	 */
	private function fetch_field_data($force = false){
		if(!$force && $this->field_list !== false)
			return true;

		$results = $this->wpdb->get_results("
			SELECT
				cf.id,
				cf.name as field_name,
				cf.slug,
				cf.description,
				cf.date_created
			FROM `{$this->db_prefix}umd_cap_fields` cf");

		if(empty($results)){
			$this->field_list = array();
			return true;
		}

		$this->field_list = $results;
		return true;
	}

	/////////////////////////////////
 	/// Callback functions
	/////////////////////////////////

	/**
	 * Callback function to add all menus to the admin interface panel.
	 *
	 * @return bool
	 */
	public function add_menus(){
		if(current_action() != "admin_menu")
			return false;

		add_menu_page(
			'Custom Author Profile Settings',
			'Author Profile',
			'manage_options',
			'umd_cap_settings',
			array($this, "admin_route"),
			'',
			999
		);

		add_submenu_page(
			'umd_cap_settings',
			'Testing',
			'Fields',
			'manage_options',
			'umd_cap_fields',
			array($this, "admin_route")
		);

		return true;
	}

	/**
	 * Add any links to the plugin on the plugins page.
	 *
	 * @param $links array The array of links passed by WP Core.
	 * @return array The modified array of links.
	 */
	public function add_plugin_action_links($links){
		$settings_link = '<a href="admin.php?page=umd_cap_settings">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Callback function to add fields to the edit user/display profile page.
	 * @param $user
	 *
	 * @return bool
	 */
	public function add_fields_to_profile($user){
		$this->fetch_field_data();

		if(empty($this->field_list)){
			return true;
		}

		?>
			<h3><?php _e("Custom Author Profile Fields"); ?></h3>
			<table class="form-table">
		<?php

		foreach($this->field_list as $field){
			?>
				<tr>
					<th><label for="<?php echo $field->slug; ?>"><?php _e($field->field_name); ?></label></th>
					<td>
						<input type="text" name="<?php echo $field->slug; ?>" id="<?php echo $field->slug; ?>" value="<?php echo esc_attr( get_the_author_meta( $field->slug, $user->ID ) ); ?>" class="regular-text" />
						<p class="description"><?php echo $field->description; ?></p>
					</td>
				</tr>
				<?php
		}
		?></table><?php
	}

	/**
	 * Callback function to update user meta data when profile is updated.
	 *
	 * @param $user_id
	 *
	 * @return bool
	 */
	public function update_user_profile_information($user_id){
		if ( !current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$this->fetch_field_data();

		foreach($this->field_list as $field){
			if(isset($_POST[$field->slug])){
				update_user_meta($user_id, $field->slug, $_POST[$field->slug]);
			}
		}

		return true;
	}

	//////////////////////////////////
	/// Template display functions
	//////////////////////////////////
	/**
	 * Displays the CAP settings header.
	 *
	 * @param $tab_part
	 */
	private function show_settings_header($tab_part){
		$main_settings_tab_active = $fields_tab_active = '';

		${$tab_part . "_tab_active"} = 'nav-tab-active';
		?>
		<div class="wrap">
		<script type="text/javascript">
		   jQuery(document).ready(function () {
			   jQuery(".delconfirm").click(function (e) {
				   var answer = prompt(jQuery(this).attr("data-delete-message"));
				   if (answer == "DELETE") {
					   jQuery(this).attr("href", jQuery(this).attr("href") + "&DELETE=true");
				   } else {
					   e.preventDefault();
				   }
			   });
		   });
		</script>
		<h1><?php _e("Custom Author Profiles"); ?></h1>
	<?php
	foreach($this->admin_notices as $notice){
		echo $notice;
	}
	?>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab <?php echo $main_settings_tab_active; ?>" id="umd_cap_fields_tab" href="admin.php?page=umd_cap_settings"><?php _e("Main Settings"); ?></a><a class="nav-tab <?php echo $fields_tab_active; ?>" id="umd_cap_fields_tab" href="admin.php?page=umd_cap_fields"><?php _e("Fields"); ?></a>
		</h2>
		<?php
	}

	/**
	 * Displays the CAP settings footer.
	 *
	 */
	private function show_settings_footer(){
		?>
		</div>
		<?php
	}

	/**
	 * Displays the content for the main settings page.
	 *
	 */
	private function show_settings_page(){
		?>
		<form method="post" action="admin.php?page=umd_cap_settings" novalidate="novalidate">
			<input type="hidden" name="umd_cap_settings_nonce" value="<?php echo wp_create_nonce('umd_cap_settings'); ?>" />
			<table class="form-table">
				<tr>
					<th>
						<label for="umd_cap_data_to_delete"><?php _e("Data to Delete on Uninstall"); ?></label>
					</th>
					<td>
						<select id="umd_cap_data_to_delete" name="umd_cap_data_to_delete">
							<?php
							$options = array(
								1 => __("None"),
								2 => __("All data excluding user meta data"),
								9 => __("All data (includes user metadata)")
							);

							$current_option_val = get_option("umd_cap_data_to_delete");

							foreach($options as $key => $option){
							?>
							<option value="<?php echo $key; ?>"<?php if($key == $current_option_val){ echo ' selected="selected"';} ?>><?php echo $option; ?></option>
										<?php
							}
							?>
							</select>
							<p class="description"><?php _e('What data to delete when you uninstall Custom Author Profiles.'); ?></p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="umd_cap_custom_field_prefix"><?php _e('Author Metadata Prefix'); ?></label>
					</th>
					<td>
						<input type="text" value="<?php echo get_option("umd_cap_custom_field_prefix"); ?>" name="umd_cap_custom_field_prefix" id="umd_cap_custom_field_prefix" />
						<p class="description"><?php _e('All custom fields have a prefix, this prevents duplicate meta_keys. Try to keep this more, rather than less, unique.'); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit"><input name="submit" id="submit" class="button button-primary" value="<?php _e("Save Changes"); ?>" type="submit"></p>
		</form>
		<?php
	}


	/**
	 * Display the fields settings page.
	 */
	private function show_fields_page(){
		if(empty($this->field_list)){
			echo "<p style='text-align: center;'>No custom fields yet</p>";
		} else {
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<th style="width:10%;"><?php _e("ID" ); ?></th>
					<th><?php _e("Field Name" ); ?></th>
					<th><?php _e("Field Slug" ); ?></th>
					<th><?php _e("Date Created" ); ?></th>
					<th style="width:10%"></th>
				</tr>
				</thead>
				<tbody><?php
				foreach($this->field_list as $field){

					?>
					<tr>
						<td><?php echo $field->id; ?></td>
						<td><a href="admin.php?page=umd_cap_fields&func=edit_field&field_id=<?php echo $field->id; ?>"><?php echo $field->field_name; ?></a></td>
						<td><?php echo str_replace($this->field_prefix, '', $field->slug); ?></td>
						<td><?php echo $field->date_created; ?></td>
						<td style="text-align: right;">
							<a href="admin.php?page=umd_cap_fields&func=edit_field&field_id=<?php echo $field->id; ?>"><?php _e("Edit"); ?></a> -
							<a class="delconfirm" data-delete-message="Type 'DELETE' to confirm you wish to delete the field '<?php echo $field->field_name; ?>'." href="admin.php?page=umd_cap_fields&func=delete_field&field_id=<?php echo $field->id; echo '&umd_cap_delete_field_nonce=' . wp_create_nonce("umd_cap_delete_field_" . $field->id); ?>"><?php _e("Delete"); ?></a>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>

			<?php
		}
		?>
		<p class="submit"><a href="admin.php?page=umd_cap_fields&func=add_new_field" class="button button-primary"><?php _e("Add New Field"); ?></a></p>
		<?php
	}

	/**
	 * Displays the add/edit form for a field.
	 */
	private function show_add_field_form(){
		?>
		<h3><?php echo __("Add a new field"); ?></h3>
		<form method="post" novalidate="novalidate" action="admin.php?page=umd_cap_fields">
			<input type="hidden" name="umd_cap_action" value="umd_cap_add_field" />
			<input type="hidden" name="umd_cap_add_field_nonce" value="<?php echo wp_create_nonce('umd_cap_add_field'); ?>" />
			<table class="form-table">
				<tr>
					<th>
						<label for="umd_cap_field_name"><?php _e("Field Name"); ?></label>
					</th>
					<td>
						<input class="regular-text" id="umd_cap_field_name" type="text" value="<?php echo $_POST['umd_cap_field_name']; ?>" name="umd_cap_field_name" />
						<p class="description"><?php _e('The stylized name for your field.'); ?></p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="umd_cap_field_slug"><?php _e("Field Slug"); ?></label>
					</th>
					<td>
						<input class="regular-text" id="umd_cap_field_slug" type="text" value="<?php echo $_POST['umd_cap_field_slug']; ?>" name="umd_cap_field_slug" />
						<p class="description"><?php _e('The slug used for your field (e.g.: twitter-username). This will always be prepended by the custom field prefix. Available characters: a-z A-Z 0-9 _ - .'); ?></p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="umd_cap_field_description"><?php _e("Field Description"); ?></label>
					</th>
					<td>
						<textarea rows="5" style="max-width:500px;width:100%;" name="umd_cap_field_description" id="umd_cap_field_description"><?php echo $_POST['umd_cap_field_description']; ?></textarea>
						<p class="description"><?php _e('Explain what this field is for.'); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit"><input name="submit" id="submit" class="button button-primary" value="<?php _e("Save Changes"); ?>"" type="submit"></p>
		</form>

		<?php
	}
	
	private function show_edit_field_form($field_data){
		$field_name = $field_data ? $field_data->field_name:$_POST['umd_cap_field_name'];
		$field_id = $field_data ? $field_data->id : $_POST['field_id'];
		$form_action = "umd_cap_edit_field";
		$form_nonce_action = $form_action . "_" . $field_id;
		$field_slug = $field_data ? str_replace($this->field_prefix, '', $field_data->slug) : $_POST['umd_cap_field_slug'];
		$field_description = $field_data ? $field_data->description : $_POST['umd_cap_field_description'];
		
		?>
		<h3><?php echo __("Edit the field") . " '$field_name'"; ?></h3>
		<form method="post" novalidate="novalidate" action="admin.php?page=umd_cap_fields">
			<input type="hidden" value="<?php echo $field_id; ?>" name="field_id" />
			<input type="hidden" name="umd_cap_action" value="<?php echo $form_action; ?>" />
			<input type="hidden" name="<?php echo $form_action; ?>_nonce" value="<?php echo wp_create_nonce($form_nonce_action); ?>" />
			<table class="form-table">
				<tr>
					<th>
						<label for="umd_cap_field_name"><?php _e("Field Name"); ?></label>
					</th>
					<td>
						<input class="regular-text" id="umd_cap_field_name" type="text" value="<?php echo $field_name; ?>" name="umd_cap_field_name" />
						<p class="description"><?php _e('The stylized name for your field.'); ?></p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="umd_cap_field_slug"><?php _e("Field Slug"); ?></label>
					</th>
					<td>
						<input class="regular-text" id="umd_cap_field_slug" type="text" value="<?php echo $field_slug; ?>" name="umd_cap_field_slug" />
						<p class="description"><?php _e('The slug used for your field (e.g.: twitter-username). This will always be prepended by the custom field prefix. Available characters: a-z A-Z 0-9 _ - .'); ?></p>
					</td>
				</tr>
				<tr>
					<th>
						<label for="umd_cap_field_description"><?php _e("Field Description"); ?></label>
					</th>
					<td>
						<textarea rows="5" style="max-width:500px;width:100%;" name="umd_cap_field_description" id="umd_cap_field_description"><?php echo $field_description; ?></textarea>
						<p class="description"><?php _e('Explain what this field is for.'); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit"><input name="submit" id="submit" class="button button-primary" value="<?php _e("Save Changes"); ?>" type="submit"></p>
		</form>
		
		<?php
	}

	///////////////////////////////////////////////////
	/// Activation, Deactivation, Uninstall Functions
	///////////////////////////////////////////////////
	public function plugin_activation(){
		if(!empty($this->wpdb->get_results("SHOW TABLES LIKE '{$this->db_prefix}umd_cap_fields'")))
			return true;

		if(get_option("umd_cap_custom_field_prefix") === false)
			add_option("umd_cap_custom_field_prefix", "umd_cap_");

		if(get_option("umd_cap_data_to_delete") === false)
			add_option("umd_cap_data_to_delete", "1");

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta("CREATE TABLE `{$this->db_prefix}umd_cap_fields` (
				`id` INT(11) NOT NULL AUTO_INCREMENT ,
				`name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL ,
				`slug` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL ,
				`description` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL ,
				`date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ,
				PRIMARY KEY  (`id`)
			)
			ENGINE = InnoDB
			CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;");

		return true;
	}

	public function plugin_deactivation(){
		//nothing to do
	}
}