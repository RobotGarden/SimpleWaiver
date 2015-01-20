<?php
/*
Plugin Name: Simple Waiver
Description: Simple waiver for things
Version: 0.1
Author: Robot Garden
Author URI: http://www.robotgarden.org
*/

/* Database version for ensuring that updates happen when changing custom fields */

register_activation_hook(   __FILE__, array( 'SimpleWaiver', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'SimpleWaiver', 'on_deactivation' ) );
register_uninstall_hook(    __FILE__, array( 'SimpleWaiver', 'on_uninstall' ) );

add_action( 'plugins_loaded', array( 'SimpleWaiver', 'init' ) );

class SimpleWaiver{
	protected static $instance;
	
	public static function init(){
		is_null( self::$instance ) AND self::$instance = new self;
		return self::$instance;
	}

    public static function on_activation(){
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "activate-plugin_{$plugin}" );

        # Uncomment the following line to see the function in action
        # exit( var_dump( $_GET ) );
        
        $role = get_role('administrator');
		$role->add_cap('simpleWaiver_download_csv');
    }

    public static function on_deactivation(){
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "deactivate-plugin_{$plugin}" );

        # Uncomment the following line to see the function in action
        # exit( var_dump( $_GET ) );
    }

    public static function on_uninstall(){
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        check_admin_referer( 'bulk-plugins' );

        // Important: Check if the file is the one
        // that was registered during the uninstall hook.
        if ( __FILE__ != WP_UNINSTALL_PLUGIN )
            return;

        # Uncomment the following line to see the function in action
        # exit( var_dump( $_GET ) );
    }
    
	function __construct(){
		/* Adding shortcode */
		add_shortcode( 'simplewaiver', array(&$this, 'shortcode_generate_waiver') );
		add_shortcode( 'simplewaiver_data', array(&$this, 'shortcode_data_func') );
		add_shortcode( 'simplewaiver_search', array(&$this, 'shortcode_data_search') );

		/* Registering settings */
		add_action( 'admin_menu', array(&$this, 'add_admin_menu') );
		add_action( 'admin_init', array(&$this, 'settings_init') );

		/* Registering Ajax return functions */
		add_action( 'wp_ajax_waiver_submitted', array(&$this, 'waiver_submitted') );
		add_action( 'wp_ajax_nopriv_waiver_submitted', array(&$this, 'waiver_submitted') );

		add_action( 'wp_ajax_waiver_search', array(&$this, 'data_search_return') );
		add_action( 'wp_ajax_nopriv_waiver_search', array(&$this, 'data_search_return') );

		/* Hooks for CSV download */
		//TODO: this should be some other hook that happens earlier.
		add_action('admin_init',array(&$this,'check_for_export'));
	}

	function __vars(){
		$this->db_version = '1.0';
	}

	function db_version(){
//		$options = get_option( 'simple_waiver_settings_global' );
		return $this->db_version;//.'_'.hash('crc32', $options['custom_fields']);
	}

	function add_admin_menu(  ) { 
		$this->settings_page = add_options_page( 'Simple Waiver', 'Simple Waiver', 'manage_options', 'simple_waiver', array( &$this, 'options_page') );

		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts') );

		add_management_page( 'Export Waivers', 'Export Waivers', 'simpleWaiver_download_csv', 'export_waivers', array( &$this, 'export_page') );
	}

	function admin_enqueue_scripts( $hook_suffix ) {
		if($this->settings_page == $hook_suffix){
			wp_register_script( 'simple-waiver-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ));
			$translation_array = array(	'remove' => __( 'Remove' , 'simple_waiver'),
										'description' => __( 'Description' , 'simple_waiver'),
										'placeholder' => __( 'Placeholder' , 'simple_waiver')
									   );
			wp_localize_script( 'simple-waiver-admin', 'simple_waiver', $translation_array );

			wp_enqueue_script( 'simple-waiver-admin' );
		}
	}

	function settings_init(  ) { 
		// TODO: Add sanitation as third argument, return '' on error
		register_setting( 'simple_waiver_settings', 'simple_waiver_settings_global' );

		add_settings_section(
			'simple_waiver_settings_general', 
			__( 'Main settings', 'simple_waiver' ), 
			array(&$this, 'settings_section_callback'), 
			'simple_waiver_settings'
		);

		add_settings_field( 
			'require_signature', 
			__( 'Require signature', 'simple_waiver' ), 
			array(&$this, 'setting_render_checkbox'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_general' ,
			'require_signature'
		);
	
		add_settings_field( 
			'require_guardian', 
			__( 'Require guardians signature', 'simple_waiver' ), 
			array(&$this, 'setting_render_checkbox'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_general',
			'require_guardian'
		);
			
		add_settings_field( 
			'show_time', 
			__( 'Show time on data reports', 'simple_waiver' ), 
			array(&$this, 'setting_render_checkbox'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_general',
			'show_time'
		);

		add_settings_section(
			'simple_waiver_settings_custom_fields', 
			__( 'Custom Fields', 'simple_waiver' ), 
			array(&$this, 'settings_section_callback'), 
			'simple_waiver_settings'
		);

		add_settings_field( 
			'custom_fields', 
			__( 'Custom fields in waiver', 'simple_waiver' ), 
			array(&$this, 'custom_fields_render'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_custom_fields' 
		);
	
		add_settings_section(
			'simple_waiver_settings_mailchimp', 
			__( 'Mail Chimp integration', 'simple_waiver' ), 
			array(&$this, 'settings_section_callback'), 
			'simple_waiver_settings'
		);
		
		add_settings_field( 
			'mailchimp_api_key', 
			__( 'API key', 'simple_waiver' ), 
			array(&$this, 'setting_render_text'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_mailchimp',
			'mailchimp_api_key'
		);

		add_settings_field( 
			'mailchimp_default_checked', 
			__( 'Default to subscribe user', 'simple_waiver' ), 
			array(&$this, 'setting_render_checkbox'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_mailchimp',
			'mailchimp_default_checked'
		);
		
		add_settings_field( 
			'mailchimp_invitation_text', 
			__( 'Maillist invitation text', 'simple_waiver' ), 
			array(&$this, 'setting_render_text'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_mailchimp',
			'mailchimp_invitation_text'
		);

		add_settings_field( 
			'mailchimp_list_id', 
			__( 'List subscriptions', 'simple_waiver' ), 
			array(&$this, 'setting_mailchimp_lists'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_mailchimp'
		);

		add_settings_field( 
			'mailchimp_list_groups', 
			__( 'Allowed interest groups', 'simple_waiver' ), 
			array(&$this, 'setting_mailchimp_list_groups'), 
			'simple_waiver_settings', 
			'simple_waiver_settings_mailchimp'
		);
	}

	function custom_fields_render(){
		$options = get_option( 'simple_waiver_settings_global' );
		?>
		<input type="hidden" id="custom-fields" name="simple_waiver_settings_global[custom_fields]" value='<?=htmlspecialchars($options['custom_fields']); ?>'>
		<div class="sw-custom-fields"></div>
		<button type="button" class="sw-add-field button"><?=__('Add field','simple_waiver');?></button>
		<?php
	}

	function setting_render_checkbox($args){
		$options = get_option( 'simple_waiver_settings_global' );
		?>
		<input type='checkbox' name='simple_waiver_settings_global[<?=$args;?>]' <?php checked( $options[$args], 1 ); ?> value='1'>
		<?php
	}

	function setting_render_text($args){
		$options = get_option( 'simple_waiver_settings_global' );
		?>
		<input type='text' name='simple_waiver_settings_global[<?=$args;?>]' value='<?=$options[$args];?>'>
		<?php
	}

	function settings_section_callback($arg){
		$descriptions = Array();
		if($descriptions[$arg["id"]])
			echo __( 'This section description','simple_waiver');
	}
	
	function get_mailchimp(){
		if(!isset($this->mailchimp)){
			require_once(dirname(__FILE__ ).'/lib/Mailchimp.php');
			$options = get_option('simple_waiver_settings_global');
			$this->mailchimp = new Mailchimp($options["mailchimp_api_key"]);
		}
		
		return $this->mailchimp;
	}
	
	function setting_mailchimp_lists(){
		$options = get_option('simple_waiver_settings_global');
		if(!empty($options["mailchimp_api_key"])){
			try{
				$mailchimp = $this->get_mailchimp();
				$mailchimp_lists = new Mailchimp_Lists($mailchimp);

				$api_lists = $mailchimp_lists->getList();
				
				$lists = [];
				foreach($api_lists["data"] as $list){
					$lists[$list["id"]] = $list["name"];
				}

				echo $this->generate_radio_buttons("simple_waiver_settings_global[mailchimp_list_id]",$lists,$options["mailchimp_list_id"]);
			}catch(MailChimp_Error $e){
				echo "Invalid API key";
			}
		}
	}
	
	function setting_mailchimp_list_groups(){
		$options = get_option('simple_waiver_settings_global');
		if(!empty($options["mailchimp_list_id"])){
			try{
				$mailchimp = $this->get_mailchimp();
				$mailchimp_lists = new Mailchimp_Lists( $mailchimp );
			
				$groupings = $mailchimp_lists->interestGroupings($options["mailchimp_list_id"]);
				
				echo sprintf('<input type="hidden" name="simple_waiver_settings_global[mailchimp_list_groups]" value="%s">',htmlspecialchars($options["mailchimp_list_groups"]));
				
				$saved_selections = json_decode($options["mailchimp_list_groups"]);
				
				foreach($groupings as $group){
					echo sprintf('<div class="sw_group">%s</br>',$group["name"]);
					echo sprintf('<input type="hidden" name="name" value="%s">',$group["name"]);
					echo sprintf('<input type="hidden" name="id" value="%s">',$group["id"]);
					echo sprintf('<input type="hidden" name="form_field" value="%s">',$group["form_field"]);
					foreach($group["groups"] as $collection){
					// checked( $options[$args], 1 );
						echo sprintf("<input type='checkbox' name='%s' class='sw_list_group_selection' value='%s' %s>%s</br>",$collection["name"],$collection["id"],((isset($saved_selections->$group["name"]->groups->$collection["id"]))?"checked":""),$collection["name"]);
					}
					echo sprintf("</div>");
				}

			}catch(MailChimp_Error $e){
				
			}
		}
	}
	
	function generate_radio_buttons($name, $list, $checked="", $none=true){
		if($none)
			$returnValue = sprintf('<input type="radio" name="%s" value="" %s>None<br/>',$name,checked($checked,"",false));
		else
			$returnValue = "";
			
		foreach($list as $key => $value){
				$returnValue .= sprintf('<input type="radio" name="%s" value="%s" %s>%s<br/>',$name,$key,checked($checked,$key,false),$value);
		}
		
		return $returnValue;
	}

	function options_page() { 
		?>
		<form action='options.php' method='post'>
		
			<h2>Simple Waiver</h2>
		
			<?php
			settings_fields( 'simple_waiver_settings' );
			do_settings_sections( 'simple_waiver_settings' );
			submit_button();
			?>
		
		</form>
		<?php

	}

	/* Setting up the shortcode */
	function shortcode_generate_waiver($atts){
		$id = "";
		$pre = "";
		$options = Array();

		if($atts != ""){
			foreach($atts as $key => $value){
				switch($key){
					case "id":
						$id = sanitize_text_field($value);
					break;
					case "require_guardian":
					case "require_signature":
					case "custom_fields":
						$options[$pre.$key] = sanitize_text_field($value);
					break;
				}
			}
		}

		return $this->generate_waiver($id, $options);
	}

	/* Creating the form */
	function generate_waiver($id, $sc_options){
		$options = array_merge(get_option( 'simple_waiver_settings_global' ), $sc_options);
		$this->submit_javascript($options);
		$this->submit_stylesheets();

		$return = '
		<div id="simpleWaiverWrapper">
		<form id="simpleWaiverForm">
			<input type="hidden" name="swId" value="'.$id.'">
			<input type="hidden" name="swNonce" value="'.$this->generate_waiver_nonce($id).'">
			<div id="simpleWaiverRequired">
				<table class="simpleWaiverTable">
					<tr>
						<td>
							<label for="swName">Name</label>
						</td>
						<td>
							<input type="text" name="swName" placeholder="Enter your name" class="simpleWaiverInput" required>
						</td>
					</tr>
					<tr>
						<td>
							<label for="swEmail">E-Mail</label>
						</td>
						<td>
							 <input type="email" name="swEmail" placeholder="Enter your e-mail" class="simpleWaiverInput" required>
						</td>
					</tr>
';

		if($options['custom_fields'] != "[]"){
			$custom_fields = json_decode($options['custom_fields']);
			if($custom_fields){
				$return .= '
					<input type="hidden" name="swCustomFields" value="">';
				foreach($custom_fields as $custom_field_id => $custom_field_object){
					$return .= sprintf('
					<tr>
						<td>
							<label for="%s">%s</label>
						</td>
						<td>
							 <input type="%s" name="%s" placeholder="%s" class="simpleWaiverInput" %s maxlength=255>
						</td>
					</tr>',$custom_field_id,$custom_field_object->name,$custom_field_object->type,$custom_field_id,$custom_field_object->placeholder,(($custom_field_object->required==1)?"required":""));
				}
			}
		}

		if($options["require_guardian"]==1){
			$return .= '
					<tr>
						<td>
							<label>Date of birth</label>
						</td>
						<td>
							 <input type="date" onChange="sw_check_date(this)" required>
						</td>
					</tr>';
		}

		if($options["require_signature"]==1){
			$return .= '
					<tr>
						<td>
							<label for="swSignature">Digital signature</label>
						</td>
						<td>
							<input type="checkbox" name="swSignature" required> I have read, understood and agree to the waiver and release.
						</td>
					</tr>';
		}

		if($options["require_guardian"]==1){
			$return .= '
					<tr class="simpleWaiverGuardian" style="display:none;">
						<td colspan="2">
							<b>Please have your guardian provide the following information:</b>
						</td>
					</tr>
					<tr class="simpleWaiverGuardian" style="display:none;">
						<td>
							<label for="swGuardianName">Name</label>
						</td>
						<td>
							<input type="text" name="swGuardianName" placeholder="Enter your guardians name" style="width: 100%" >
						</td>
					</tr>
					<tr class="simpleWaiverGuardian" style="display:none;">
						<td>
							<label for="swGuardianEmail">E-Mail</label>
						</td>
						<td>
							<input type="email" name="swGuardianEmail" placeholder="Enter your guardians e-mail" style="width: 100%" >
						</td>
					</tr>';
			
			if($options["require_signature"]==1){
			$return .= '
					<tr class="simpleWaiverGuardian" style="display:none;">
						<td>
							<label for="swGuardianSignature">Digital signature</label>
						</td>
						<td>
							<input type="checkbox" name="swGuardianSignature" required> I have read, understood and agree to the waiver and release.
						</td>
					</tr>';
			}
		}
		
		if(!empty($options["mailchimp_list_groups"])){
			$return .= sprintf('
					<input type="hidden" name="swMailChimpSelectedGroup" value="">
					<tr class="simpleWaiverMailChimp">
						<td colspan="2">
							<input type="checkbox" name="swMailChimpSignup" %s><label for="swGuardianSignature">%s</label>
						</td>
					</tr>',checked($options["mailchimp_default_checked"],true,false),$options["mailchimp_invitation_text"]);
			$list_groups = json_decode($options["mailchimp_list_groups"]);
			foreach($list_groups as $name => $group){
				$return .= sprintf('
					<tr class="simpleWaiverMailChimpGroup" %s>
						<td>
							<label>%s</label>
						</td>
						<td>
							<div>
								<input type="hidden" name="id" value="%s">
								%s
							</div>
						</td>
					</tr>',($options["mailchimp_default_checked"]=="1")?"":"style=\"display:none;\"",$name,$group->id,$this->generate_mailchimp_group_selections($group->groups,$group->form_field));
			}
		}
		
				$return .= '
					    </table>
				<input type="submit" value="Submit waiver" class="simpleWaiverButton"/> 
			</div>
		</form>
		<div class="simpleWrapperResponse simpleWrapperSubmitting">
			<h4>
				<span>Form <span class="coloredtext">Submitted </span></span>
			</h4>
		</div>
		<div class="simpleWrapperResponse simpleWrapperSubmitted">
			<h4>
				<span>Form <span class="coloredtext">Recieved </span></span>
			</h4>
			<span>
				Thank you. The form will reset in a few seconds. If not, then <a class="reset_form">click here</a>.
			</span>
		</div>
		<div class="simpleWrapperResponse simpleWrapperError">
			<h4>
				<span>Form <span class="coloredtext">Error </span></span>
			</h4>
			<span>
				Something went wrong. Try to reload the page and if that does not work then contact the site owner.
			</span>
		</div>
		</div>';
		
		return $return;
	}
	
	function generate_mailchimp_group_selections($groups,$form_type){
		switch($form_type){
			case "checkboxes":
				$return = "";
				foreach($groups as $id => $name){
					$return .= sprintf('<input type="checkbox" class="simpleWaiverMailChimpGroupSelection" name="%s">%s<br/>',htmlspecialchars($name),$name);
				}
			break;
			default:
				$return = sprintf('Unkown type %s',$form_type);
			break;
		}
		
		return $return;
	}
	
	function submit_stylesheets(){
		wp_register_style( 'simple_waiver_style', plugin_dir_url( __FILE__ ).'css/style.css', 'all');
		wp_enqueue_style('simple_waiver_style');
	}

	/* Ajax post for waiver */
	//add_action( 'admin_footer', 'my_action_javascript' ); // Write our JS below here

	function submit_javascript($options) {
		wp_enqueue_script( 'jquery-form' );

		// embed the javascript file that makes the AJAX request
		wp_enqueue_script( 'waiver-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ));

		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		wp_localize_script( 'waiver-ajax-request', 'WaiverAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

		// passing the guardian option to the javscript
		wp_localize_script( 'waiver-ajax-request', 'SimpleWaiverOptions', array( 'require_guardian' => ($options["require_guardian"]==1?"true":"false"), 'mailchimp_default_checked' => ($options["mailchimp_default_checked"]==1?"true":"false"), 'custom_fields' => $options["custom_fields"] ) );
	}

	/* Ajax response to the submission */
	function waiver_submitted() {
		global $wpdb;
		
		if(isset($_REQUEST["swNonce"])){
			$whitelist = array("swId", "swName", "swEmail", "swGuardianName", "swGuardianEmail", "swCustomFields");
		
			$dbData = [];
			$response = [];
			
			foreach($_POST as $key => $value){
				if(!in_array($key, $whitelist))
					continue;
			
				$dbData[$key] = sanitize_text_field($_POST[$key]);				
			}
			
			if($_REQUEST["swNonce"] == $this->generate_waiver_nonce($dbData["swId"])){
				$this->update_db();
				$table_name = $wpdb->prefix . "simple_waiver";
				$wpdb->insert($table_name, $dbData);			
				$response["nonce"] = $this->generate_waiver_nonce($dbData["swId"]);
			}else{
				exit();
			}
		}else{
			exit();
		}
		
		if(isset($_REQUEST["swMailChimpSignup"])){
			$options = get_option('simple_waiver_settings_global');
			$list_id = $options["mailchimp_list_id"];
			$email = ["email" => $_POST["swEmail"]];
		
			$merge_vars = [];
			$merge_vars["groupings"] = array();
		
			$groups = json_decode(stripslashes($_POST["swMailChimpSelectedGroup"]));
			
			foreach($groups as $id => $selectedgroups){
				$new_grouping = [];
				$new_grouping["id"] = $id;
				$new_grouping["groups"] = array();
	
				foreach($selectedgroups->groups as $id){
					array_push($new_grouping["groups"],$id);
				}
				
				array_push($merge_vars["groupings"], $new_grouping);
			}
						
			$mailchimp = $this->get_mailchimp();
			$mailchimp_lists = new Mailchimp_Lists($mailchimp);

			try{
				$response["mailchimp"] = $mailchimp_lists->subscribe($list_id, $email, $merge_vars);
			}catch(MailChimp_Error $e){
				$response["error"] = true;
				$response["mailchimp error"] = $e->getMessage();
			}
		}

		echo json_encode($response);

		exit();
	}

	/* updating the database */
	function update_db(){
		if($this->db_version() != get_option("simple_waiver_db_version")){
			global $wpdb;

			$table_name = $wpdb->prefix . 'simple_waiver';

			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				swId varchar(255) NOT NULL,
				swName varchar(255) NOT NULL,
				swEmail varchar(255) NOT NULL,
				swGuardianName varchar(255),
				swGuardianEmail varchar(255),
				swCustomFields mediumtext,
				UNIQUE KEY id (id)
			) $charset_collate;";
		
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		
			update_option("simple_waiver_db_version", $this->db_version());
		}
	}

	/* handler for formatting the databse to a table */
	// TODO: Add time as a parameter, default to on
	function format_data_html($signatures,$sc_options){
		$options = array_merge(get_option( 'simple_waiver_settings_global' ), $sc_options);

		$custom_fields_display = array();
	
		if($options['custom_fields'] != "[]"){
			$custom_fields = json_decode($options['custom_fields']);
			if($custom_fields){
				foreach($custom_fields as $custom_field_id => $custom_field_object){
					$custom_fields_display[$custom_field_id] = $custom_field_object->name;
				}
			}
		}

		$returnData = "<table>\n\t<tr>";
		if($options['show_time'] == 1){
			$returnData .= "<th>Time</th>";
		}
		$returnData .= "<th>Name</th><th>E-mail</th>";
		if($options['require_guardian'] == 1){
			$returnData .= "<th>Guardian</th><th>E-mail</th>";
		}
		
		foreach($custom_fields_display as $custom_field_id => $custom_field_name){
			$returnData .= "<th>$custom_field_name</th>";
		}
		$returnData .= "</tr>\n";
	
		foreach ($signatures as $signature){
			$returnData .= "\t<tr>";
			if($options['show_time'] == 1){
				$returnData .= "<td>${signature["time"]}</td>";
			}
			$returnData .= "<td>${signature["swName"]}</td><td>${signature["swEmail"]}</td>";
			if($options['require_guardian'] == 1){
				$returnData .= "<td>${signature["swGuardianName"]}</td><td>${signature["swGuardianEmail"]}</td>";
			}
			
			$custom_fields_data = json_decode(stripslashes($signature["swCustomFields"]));
			
			if($custom_fields_data){
				foreach($custom_fields_display as $custom_field_id => $custom_field_name){
					$returnData .= sprintf("<td>%s</td>",$custom_fields_data->$custom_field_id);
				}
			}else{
				foreach($custom_fields_display as $custom_field_id => $custom_field_name){
					$returnData .= "<td></td>";
				}
			}
					
			$returnData .= "</tr>\n";
		}
		$returnData .= "</table>\n";
	
		return $returnData;
	}

	/* shortcode for printing databse */
	function shortcode_data_func( $atts ){
		global $wpdb;

		$table_name = $wpdb->prefix . 'simple_waiver';
		
		$limit = "";
		$sqlrestrictions = "";
		
		$options = array();
		
		if($atts != ""){
			foreach($atts as $key => $value){
				switch($key){
					case "id":
						$sqlrestrictions = sprintf(" WHERE `swId`='%s'",sanitize_text_field($value));
					case "last":
						if(ctype_digit($value)){
							$limit = sprintf(" LIMIT %s",$value);
						}
					break;
					case "show_time":
					case "require_guardian":
						$options[$pre.$key] = intval($value);
					case "custom_fields":
						$options[$pre.$key] = sanitize_text_field($value);
					break;
				}
			}
		}
		
		$query = "SELECT * FROM ${table_name}${sqlrestrictions} ORDER BY time DESC${limit};";	
	
		$returnData = "";
		
		$signatures = $wpdb->get_results($query, ARRAY_A);
	
		if($signatures){
			$returnData .= $this->format_data_html($signatures,$options);
		}
	
		return $returnData;
	}

	/* shortcode for searching database for past waivers */
	function shortcode_data_search( $atts ){
		$this->submit_search_javascript();
		
		$returnData = sprintf('
			<form id="simpleWaiverSearch">
				<input type="text" name="searchfield">
				<input type="submit">
			</form>
			<table id="simpleWaiverSearchResult">
				<tbody>
				</tbody>
			</table>
		');
		
		return $returnData;
	}
	
	/* javascript for search */
	function submit_search_javascript() {
		wp_enqueue_script( 'jquery-form' );

		// embed the javascript file that makes the AJAX request
		wp_enqueue_script( 'waiver-ajax-request', plugin_dir_url( __FILE__ ) . 'js/search.js', array( 'jquery' ));

		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		wp_localize_script( 'waiver-ajax-request', 'WaiverAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

		// passing the guardian option to the javscript
//		wp_localize_script( 'waiver-ajax-request', 'SimpleWaiverOptions', array( 'require_guardian' => ($options["require_guardian"]==1?"true":"false"), 'custom_fields' => $options["custom_fields"] ) );
	}

	
	/* json backend for returning searched data */
	function data_search_return(){
		global $wpdb;
		$table_name = $wpdb->prefix . 'simple_waiver';

		$options = get_option('simple_waiver_settings_global');
		
		$name = like_escape($_REQUEST["searchfield"]);		
		$query = "SELECT * FROM ${table_name} WHERE `swName` LIKE '%${name}%' ORDER BY `time` DESC LIMIT 10;";	
	
		$returnData = "";
		
		$signatures = $wpdb->get_results($query, ARRAY_A);
		
				
		$returnData = [];
		
		if($options['custom_fields'] != "[]"){
			$custom_fields = json_decode($options['custom_fields']);
		}

		foreach($signatures as $signature){			
			$entry = array(	"Time"=>$signature["time"],
							"Name"=>$signature["swName"],
							"E-mail"=>$signature["swEmail"],
							"Guardian Name"=>$signature["swGuardianName"],
							"Guardian E-Mail"=>$signature["swGuardianEmail"]);
			
			$cf = json_decode(stripslashes($signature["swCustomFields"]));
			if(!empty($custom_fields)){
				foreach($custom_fields as $custom_field_id => $custom_field_object){
					if(!empty($cf->$custom_field_id))
						$entry[$custom_field_object->name] = $cf->$custom_field_id;
					else
						$entry[$custom_field_object->name] = "";
				}
			}
			
			array_push($returnData,$entry);
		}
	
		echo json_encode($returnData);
		exit;
	}
		
	/* Nonce for AJAX signup */
	
	function generate_waiver_nonce($id){
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'simple_waiver';
		$lastpostid = $wpdb->get_var(sprintf("SELECT id FROM %s WHERE `swId`='%s' ORDER BY `swId` DESC LIMIT 1;",$table_name,$id));
		
		return wp_create_nonce(sprintf('submit-waiver_%s_%s',$id,$lastpostid));
	}
	
	/* CSV download hooks for the Tools menu */
	
	function check_for_export() {
		global $pagenow;
		
		if ($pagenow=='tools.php' && 
		current_user_can('simpleWaiver_download_csv') && 
		(isset($_POST['page']) && $_POST['page'] == "export_waivers")  && 
		(isset($_POST['submit']) && $_POST['submit'] == "Download")) {
			header("Content-type: application/x-msdownload");
			header("Content-Disposition: attachment; filename=waivers.csv");
			header("Pragma: no-cache");
			header("Expires: 0");
			
			global $wpdb;

			$conditional = " WHERE";
			
			switch($_POST["time"]){
				case "d":
					$sqlrestriction = "$conditional time >= DATE_ADD(CURDATE(), INTERVAL -1 DAY) ";
					$conditional = "AND";
				break;
				case "w":
					$sqlrestriction = "$conditional time >= DATE_ADD(CURDATE(), INTERVAL -1 WEEK) ";
					$conditional = "AND";
				break;
				case "m":
					$sqlrestriction = "$conditional time >= DATE_ADD(CURDATE(), INTERVAL -1 MONTH) ";
					$conditional = "AND";
				break;
				case "y":
					$sqlrestriction = "$conditional time >= DATE_ADD(CURDATE(), INTERVAL -1 YEAR) ";
					$conditional = "AND";
				break;
				case "a":
					$sqlrestriction = "";
				break;
			}
			
			if(!empty($_POST["id"])){
				$sqlrestriction .= sprintf("%s WHERE swId=`%s` ",$conditional,sanitize_text_field($_POST["id"]));
			}
			
			$table_name = $wpdb->prefix . 'simple_waiver';
			$query = "SELECT * FROM $table_name${sqlrestriction}ORDER BY time DESC;";

			$signatures = $wpdb->get_results($query, ARRAY_A);

			$out = fopen('php://output', 'w');
			
			$options = get_option('simple_waiver_settings_global');

			$custom_fields_display = array();
	
			if($options['custom_fields'] != "[]"){
				$custom_fields = json_decode($options['custom_fields']);
				if($custom_fields){
					foreach($custom_fields as $custom_field_id => $custom_field_object){
						$custom_fields_display[$custom_field_id] = $custom_field_object->name;
					}
				}
			}
			
			$csvheaders = array('Date','Time','Name','E-mail','Guardian name','Guardian E-mail');

			foreach($custom_fields_display as $custom_field_id => $custom_field_name){
				array_push($csvheaders,$custom_field_name);
			}
			
			fputcsv($out, $csvheaders);
	
			foreach ($signatures as $signature){
				list($date,$time) = explode(" ",$signature["time"]);
				$csvline = array($date,$time,$signature["swName"],$signature["swEmail"],$signature["swGuardianName"],$signature["swGuardianEmail"]);
								
				$custom_fields_data = json_decode(stripslashes($signature["swCustomFields"]));
			
				if($custom_fields_data){
					foreach($custom_fields_display as $custom_field_id => $custom_field_name){
						array_push($csvline,$custom_fields_data->$custom_field_id);
					}
				}else{
					foreach($custom_fields_display as $custom_field_id => $custom_field_name){
						array_push($csvline,"");
					}
				}

				fputcsv($out, $csvline);
			}			
			exit();
		}
    }
    
    function export_page(){
    	global $wpdb;
?>
	<h2>Simple Waiver</h2>
	<p>Choose which versions of waivers you want to download.</p>
	<form method="post">
		<input type="hidden" name="page" value="export_waivers">
		<h3>Timeframe</h3>
			<select name="time">
				<option value="d">Last Day</option>
				<option value="w">Last Week</option>
				<option value="m" selected>Last Month</option>
				<option value="y">Last Year</option>
				<option value="a">Forever</option>
			</select>
		<h3>Waiver ID</h3>
			<select name="id">
				<option value="">All</option>
<?php
		$table_name = $wpdb->prefix . 'simple_waiver';
		$query = "SELECT DISTINCT swId FROM $table_name;";
		
		$ids = $wpdb->get_results($query, ARRAY_A);
	
		if($ids){
			foreach ($ids as $id){
				echo sprintf("				<option value=\"%s\">%s</option>\n",$id["swId"],$id["swId"]);
			}
		}
?>
			</select>
<?php
		submit_button( 'Download');
?>
	</form>
<?php
		
    }
}
?>
