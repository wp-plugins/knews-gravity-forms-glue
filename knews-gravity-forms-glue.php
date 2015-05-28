<?php
/*
Plugin Name: Knews + Gravity Forms Glue
Plugin URI: http://www.knewsplugin.com/knews-contact-form-7-glue/
Description: Add a Knews subscription field to your Gravity Forms forms
Author: Carles Reverter
Author URI: http://www.knewsplugin.com/
Version: 0.9.0
License: GPLv2 or later
Domain Path: /languages
Text Domain: knews_gf
*/

/* Inspired on WP SMITH: http://wpsmith.net/2011/plugins/how-to-create-a-custom-form-field-in-gravity-forms-with-a-terms-of-service-form-field-example/ 
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class knews_gf {

   function __construct() {
		add_action('plugins_loaded', array ($this, 'myplugin_load_textdomain') );
		add_filter('gform_add_field_buttons', array($this, 'add_field_button'));
		add_filter('gform_field_type_title', array($this, 'field_title'), 20, 2);
		add_action('gform_field_input', array($this, 'field_input'), 10, 5 );
		add_action('gform_editor_js', array($this, 'editor_js') );
		add_action('gform_field_standard_settings', array($this, 'standard_settings') , 10, 3 );
		add_action('gform_after_submission', array($this, 'after_submission'), 10, 2);
		add_action('admin_notices', array($this, 'show_admin_messages') );
		add_filter('gform_tooltips', array($this, 'tooltips'));
	}

	function add_user($email, $id_list_news, $lang='en', $lang_locale='en_US', $custom_fields=array(), $bypass_confirmation=false) {

		//Hi, guys: if you're looking for add subscription to Knews method, here the official way:
		apply_filters('knews_add_user_db', 0, $email, $id_list_news, $lang, $lang_locale, $custom_fields, $bypass_confirmation);
	}

	function myplugin_load_textdomain() {
		load_plugin_textdomain( 'knews_gf', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	}		

	function add_field_button($field_groups){
		foreach($field_groups as &$group){
			if($group['name'] == 'standard_fields'){
				$group['fields'][] = array(
										'class' => 'button',
										'data-type' => 'knews',
										'value' => 'Knews',
										'onclick' => "StartAddField('knews');",
				);
				break;
			}
		}
		return $field_groups;
	}

	function field_title( $title, $field_type ) {
		if ( $field_type == 'knews' ) $title = 'Knews Subscription';
		return $title;
	}	
	
	function field_input ( $input, $field, $value, $lead_id, $form_id ){
		if ( $field['type'] == 'knews' ) {
			
			$input_name = $form_id .'_' . $field["id"];
			$tabindex = GFCommon::get_tabindex();
			$css = isset( $field['cssClass'] ) ? $field['cssClass'] : '';
			$checked = ''; if (isset($field['knews_gf_checked_id']) && $field['knews_gf_checked_id']==1) $checked = ' checked="checked" ';
			
			if (isset($field['knews_gf_label'])) {
				$knews_gf_label = $field['knews_gf_label'];
			} else {
				$knews_gf_label = __('Knews Subscription Addon','knews_gf');
			}

			if (IS_ADMIN) {
				$input = "<div class='ginput_container'><input type='checkbox' name='input_%s' id='%s' class='knews_cf_checkbox_preview gform_knews %s' $tabindex value='%s' style='width:auto;' disabled='disabled' $checked /> <label id='knews_gf_label_preview'>$knews_gf_label</label></div>";
			} else {
								
				$input = "<div class='ginput_container'><input type='checkbox' name='input_%s' id='%s' class='textarea gform_knews %s' $tabindex value='%s' style='width:auto;' $checked /> <label>$knews_gf_label</label></div>";

				global $Knews_plugin;
				if (!$Knews_plugin->initialized) $Knews_plugin->init();

				$lang = $Knews_plugin->pageLang();
				if (is_array($lang) && isset($lang['language_code'])) {
					$input .= '<input type="hidden" name="knews_gf_lang" value="' . $lang['language_code'] . '" />';
					$input .= '<input type="hidden" name="knews_gf_locale" value="' . $lang['localized_code'] . '" />';
				}
			}
			$input =  sprintf($input, $field["id"], 'knews-'.$field['id'] , $field['type'] . ' ' . esc_attr( $css ) . ' ' . $field['size'] , 1);

		}
		return $input;
	}

	function editor_js(){
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {

			fieldSettings['knews'] = ".label_setting, .description_setting, .admin_label_setting, .error_message_setting, .css_class_setting, .visibility_setting, .knews_setting"; 
			
			//binding to the load field settings event to initialize the checkbox
			$(document).bind("gform_load_field_settings", function(event, field, form){
				
				jQuery('input.knews_save_inputs').each(function() {
					id=jQuery(this).attr('id');
					jQuery('#'+id).val(field[id]);
				});
				jQuery('input.knews_save_checks').each(function() {
					id=jQuery(this).attr('id');
					jQuery('#'+id).prop('checked', (field[id]==true));
				});
				jQuery('#knews_gf_label').keyup(function() {
					jQuery('#knews_gf_label_preview').html(jQuery(this).val());
				});
				jQuery('#knews_gf_checked_id').change(function() {
					jQuery('input.knews_cf_checkbox_preview').prop('checked', jQuery(this).is(':checked') );
				});
			});
		});
		
		gform.addFilter("gform_pre_form_editor_save", "knews_gf_save_form");
		function knews_gf_save_form(form) {

			start = 'Knews Gravity Forms Glue:' + "\n\n";
			knews_repeated=false;
			if (jQuery.isArray(form.fields)) {

				email_field_id=0;
				for (fchk=0; fchk<form.fields.length; fchk++) {
					if (form.fields[fchk].type=='email') email_field_id = form.fields[fchk].id;
				}

				for (f=0; f<form.fields.length; f++) {
					if (form.fields[f].type=='knews') {
						
						if (email_field_id==0) {
							alert(start+'The form should have an email input field');
							return form;
						}

						if (knews_repeated) {
							alert(start+'Only one instance of Knews subscription should be added');
							return form;
						}
						knews_repeated=true;

						if (undefined === form.fields[f].knews_gf_field_email_id || form.fields[f].knews_gf_field_email_id == '') {
							alert(start+'Insert the Email Field ID (' + email_field_id + ') in "Get email from field ID" field');
							return form;
						}
						
						email_field=false;
						for (fchk=0; fchk<form.fields.length; fchk++) {
							if (form.fields[fchk].type=='email' && form.fields[fchk].id==form.fields[f].knews_gf_field_email_id) email_field=true;
						}
						
						if (!email_field) {
							alert(start+'Wrong Email Field ID. The "Get email from field ID" should be: ' + email_field_id);
							return form;
						}

					}
				}
			}
			return form;
		}
		</script>
		<?php
	}

	function standard_settings( $position, $form_id ){

		// Create settings on position 50 (right after Field Label)
		if( $position == 50 ){
			echo '<li class="knews_setting field_setting">';
			//echo '<div class="knews_fields" style="display:none">';
			$this->tag_pane();
			echo '</li>';
		}
	}

	function after_submission($entry, $form) {
		// make sure the user has Knews installed & active
		if ( !defined('KNEWS_VERSION') ) return;

		$field = false;
		foreach($form['fields'] as $field){
			if($field['type'] == 'knews') break;
		}
		if (!$field) return;
		if (!isset($_REQUEST['input_' . $field['id']]) || $_REQUEST['input_' . $field['id']] != 1) return;
		
		//Get the email. This field is required to subscribe
		$user_email = '';
		if (!isset($field['knews_gf_field_email_id']) || (int) $field['knews_gf_field_email_id'] == 0) return;
		$field_email = 'input_' . (int) $field['knews_gf_field_email_id'];
		if (!isset($_REQUEST[$field_email]) || trim($_REQUEST[$field_email]) == '') return;
		$user_email = trim($_REQUEST[$field_email]);

		global $Knews_plugin;

		$user_cf = array();	
		$knews_extra_fields = $Knews_plugin->get_extra_fields();
		//Get the extra fields
		foreach ($knews_extra_fields as $ef) {
			$field_number = isset($field['knews_gf_field_' . $ef->id . '_id']) ? $field['knews_gf_field_' . $ef->id . '_id'] : 0;
			if ($field_number != 0) {
				$user_cf[$ef->id] = isset($_REQUEST['input_' . $field_number]) ? trim($_REQUEST['input_' . $field_number]) : '';
			}
		}
	
		//Get the user lang
		$lang = isset($_REQUEST['knews_gf_lang']) ? $_REQUEST['knews_gf_lang'] : 'en';
		$locale = isset($_REQUEST['knews_gf_locale']) ? $_REQUEST['knews_gf_locale'] : 'en_US';
		
		//Get the lists
		$all_lists = $this->knews_lists();
		$knews_lists = array();
		foreach ($all_lists as $list) {
			$configname = 'knews_gf_list_' . (KNEWS_MULTILANGUAGE ? $lang . '_' : '') . $list->id . '_id';
			if (isset($field[$configname]) && $field[$configname]==1) $knews_lists[] = $list->id;
		}
		if (count($knews_lists) == 0) return;
		
		//Optin
		$bypass_confirmation = false;		
		if (isset($field['knews_gf_optin_off_id']) && $field['knews_gf_optin_off_id']==1) $bypass_confirmation=true;
		
		//No support for multiple mailing lists subscription on old Knews releases
		if ((version_compare(KNEWS_VERSION, '1.7.1') <= 0 && version_compare(KNEWS_VERSION, '2.0.0') < 0) || version_compare(KNEWS_VERSION, '2.3.3') <= 0) {
			$knews_lists = $knews_lists[0];
		}
		
		if (!$Knews_plugin->initialized) $Knews_plugin->init();
		
		//Subscribe
		$this->add_user($user_email, $knews_lists, $lang, $locale, $user_cf, $bypass_confirmation);

	}
	
	function knews_lists() {
		global $wpdb, $Knews_plugin;
		if (!$Knews_plugin->initialized) $Knews_plugin->init();

		$query = "SELECT * FROM " . KNEWS_LISTS;

		if ((version_compare(KNEWS_VERSION, '1.6.4') >= 0 && version_compare(KNEWS_VERSION, '2.0.0') < 0) || version_compare(KNEWS_VERSION, '2.2.6') >= 0) $query .= ' WHERE auxiliary=0';
		
		if (version_compare(KNEWS_VERSION, '1.2.3') >= 0) $query .= " ORDER BY orderlist";
		
		return $wpdb->get_results( $query );
	}

	function show_admin_messages() {
		$html_error = '';
		if (function_exists('is_plugin_active') && !is_plugin_active('gravityforms/gravityforms.php') ) $html_error = __('Gravity Forms is not ready. ', 'knews_gf' );
		if (!defined('KNEWS_VERSION')) $html_error .= __('Knews is not ready (you can use with Knews or Knews Pro).', 'knews_gf' );
		
		if ($html_error != '') 
			echo '<div class="error"><p><strong>' . __( 'Knews + Gravity Forms Glue:', 'knews_gf' ) . '</strong> '
			. $html_error . '</p></div>';
	}
	 
	function tooltips($tooltips){
		$tooltips["knews_gf_mailing_lists"] = "<h6>Mailing lists</h6>You must select at least one mailing list. The new subscriber will be subscribed to the selected mailing lists (one or more can be choosen)";
		$tooltips["knews_gf_field_email_id"] = "<h6>Email field</h6>You must insert the email field ID here. The email field will be used to subscribe the user to Knews.";
		$tooltips["knews_gf_field_text"] = "<h6>Other fields</h6>You can collect any form field into Knews. Put here the field ID you want to collect.<br><br>If you are getting info from the Advanced Field NAME, add a sufix to the field ID:<br>_2 : get the <em>prefix</em> subfield<br>_3 : get the <em>first</em> subfield<br>_4 : get the <em>middle</em> subfield<br>_6 : get the <em>last</em> subfield<br>_8 : get the <em>suffix</em> subfield";
		$tooltips["knews_gf_required"] = "<h6>Subscription required</h6>The user can't send the form while not accepting the subscription checkbox";
		$tooltips["knews_gf_optin"] = "<h6>Direct activation</h6>The subscriber will be actived, without email confirmation (optin).<br><strong>It will breach European Law</strong>";

		return $tooltips;
	}

	function tag_pane() {

		global $Knews_plugin;

		$lists = $this->knews_lists();

		if (count($lists) != 0) {

			if (KNEWS_MULTILANGUAGE) {

				$languages = $Knews_plugin->getLangs();
				foreach ($languages as $l) {
					echo '<h4 style="margin-bottom:0.25em;">';
					printf( __( 'Select mailing list for %s users:', 'knews_gf'), $l['native_name']); 
					echo ' ';
					gform_tooltip("knews_gf_mailing_lists");
					echo '</h4>';
					foreach ($lists as $list) {

						echo '<input type="checkbox" id="knews_gf_list_' . $l['language_code'] . '_' . $list->id . '_id" onclick="SetFieldProperty(\'knews_gf_list_' . $l['language_code'] . '_' . $list->id . '_id\', jQuery(this).is(\':checked\'));" class="knews_save_checks" value="1" />' . $list->name . '</br>';
					}
				}

			} else {
				echo '<h4 style="margin-bottom:0.25em;">';
				echo __( 'Select mailing list:', 'knews_gf');
				echo ' ';
				gform_tooltip("knews_gf_mailing_lists");
				echo '</h4>';
				foreach ($lists as $list) {
					echo '<input type="checkbox" id="knews_gf_list_' . $list->id . '_id" onclick="SetFieldProperty(\'knews_gf_list_' . $list->id . '_id\', jQuery(this).is(\':checked\'));" class="knews_save_checks" value="1" />' . $list->name . '</br>';
				}

			}
		} else {
			echo '<p><span style="color:red">' . __('Create some mailing list first', 'knews_gf' ) . '</span></p>';
		}

		echo '<h4 style="margin-bottom:0.25em;">';
		 _e('Configuration','knews_gf');
		echo '</h4>';

		_e('Checkbox label:','knews_gf'); echo '<br>';
		echo ' <input type="text" id="knews_gf_label" onkeyup="SetFieldProperty(\'knews_gf_label\', this.value);" class="knews_save_inputs fieldwidth-3" placeholder="' . __('Knews Subscription Addon','knews_gf') . '" /><br />';
		/*
		<input type="checkbox" id="knews_gf_required_field_id" onclick="SetFieldProperty('knews_gf_required_field_id', jQuery(this).is(':checked'));" class="knews_save_checks" />&nbsp;<?php echo esc_html( __( 'Required field? (will force subscription)', 'knews_gf' ) ); ?><br>
		*/
		?>
		<input type="checkbox" id="field_required" onclick="SetFieldRequired(this.checked);">&nbsp;<?php echo esc_html( __( 'Required field? (will force subscription)', 'knews_gf' ) ); 
		echo ' ';
		gform_tooltip("knews_gf_required");
		?>
		<br>
		<input type="checkbox" id="knews_gf_checked_id" onclick="SetFieldProperty('knews_gf_checked_id', jQuery(this).is(':checked'));" class="knews_save_checks" />&nbsp;<?php echo esc_html( __( "Make subscription option checked by default", 'knews_gf' ) ); ?><br />

		<input type="checkbox" id="knews_gf_optin_off_id" onclick="SetFieldProperty('knews_gf_optin_off_id', jQuery(this).is(':checked'));" class="knews_save_checks" />&nbsp;<?php echo esc_html( __( "Do not send optin/confirmation", 'knews_gf' ) ); 
		echo ' ';
		gform_tooltip("knews_gf_optin");

		echo '<h4 style="margin-bottom:0.25em;">';
		_e('Data collection', 'knews_gf');
		echo '</h4>';
		
		printf (__('Get %s from field ID','knews_gf'), '<strong>' . __('email','knews_gf') . '</strong>');
		echo ' <input type="text" style="width:50px" id="knews_gf_field_email_id" onkeyup="SetFieldProperty(\'knews_gf_field_email_id\', this.value);" class="knews_save_inputs" /> ';
		gform_tooltip("knews_gf_field_email_id");
		echo '<br />';
		$extra_fields = $Knews_plugin->get_extra_fields();
		foreach ($extra_fields as $f) {
			printf (__('Get %s from field ID','knews_gf'), '<strong>' . $f->name . '</strong>');
			echo ' <input type="text" style="width:50px" id="knews_gf_field_' . $f->id . '_id" onkeyup="SetFieldProperty(\'knews_gf_field_' . $f->id . '_id\', this.value);" class="knews_save_inputs" /> ';
			gform_tooltip("knews_gf_field_text");
			echo '<br />';
		}
		_e('(blank fields will not be collected)', 'knews_gf');
		echo '<br>';
		
	}
	
}

if (!isset($knews_gf)) $knews_gf = new knews_gf();

