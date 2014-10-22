<?php
/*
Plugin Name: Google Cloud Print Library
Plugin URI: http://wordpress.org/extend/plugins/google-cloud-print-library
Description: Some routines used for sending simple text files to Google Cloud Print
Author: DavidAnderson
Version: 0.2.0
License: MIT
Author URI: http://david.dw-perspective.org.uk
Donate: http://david.dw-perspective.org.uk/donate
Text Domain: google-cloud-print-library
Domain Path: /languages
*/

// TODO: (Is this true?) Find out why we always get BadAuth the first time

if (!defined('ABSPATH')) die ('No direct access allowed');

define('GOOGLECLOUDPRINTLIBRARY_PLUGINVERSION', '0.2.0');

define('GOOGLECLOUDPRINTLIBRARY_SLUG', 'google-cloud-print-library');
define('GOOGLECLOUDPRINTLIBRARY_DIR', dirname(realpath(__FILE__)));

if (!class_exists('GoogleCloudPrintLibrary_GCPL_v2')) require_once(GOOGLECLOUDPRINTLIBRARY_DIR.'/class-gcpl.php');

# Setting this global variable is legacy - there's no reason why there needs to be a global. But, it is used by existing versions of the WooCommerce Print Orders plugin. GoogleCloudPrintLibrary_GCPL_v2 and GoogleCloudPrintLibrary_GCPL are compatible - but we invoke GoogleCloudPrintLibrary_GCPL_v2 specifically here, to prefer our version if it is available (as it will be newer).
if (!isset($googlecloudprintlibrary_gcpl) || !is_a($googlecloudprintlibrary_gcpl, 'GoogleCloudPrintLibrary_GCPL')) $googlecloudprintlibrary_gcpl = new GoogleCloudPrintLibrary_GCPL_v2();

$googlecloudprintlibrary_plugin = new GoogleCloudPrintLibrary_Plugin($googlecloudprintlibrary_gcpl);

class GoogleCloudPrintLibrary_Plugin {

	public $version;
	private $gcpl;
	private $printers_found = 0;

	public function __construct($gcpl) {
		$this->version = GOOGLECLOUDPRINTLIBRARY_PLUGINVERSION;
		$this->gcpl = $gcpl;

		// Stuff specific to the setup of this plugin
		add_action('plugins_loaded', array($this, 'load_translations'));
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_init', array($this, 'admin_init'));
		add_filter('plugin_action_links', array($this, 'action_links'), 10, 2 );

		// AJAX actions for our settings page
		add_action('wp_ajax_gcpl_test_print', array($this, 'test_print'));
		add_action('wp_ajax_gcpl_refresh_printers', array($this, 'google_cloud_print_library_options_printer'));

		// Provide default values from this plugin's settings
		add_filter('google_cloud_print_copies', array($this, 'google_cloud_print_copies'));
		add_filter('google_cloud_print_options', array($this, 'google_cloud_print_options'));
	}

	public function load_translations() {
		load_plugin_textdomain('google-cloud-print-library', false, GOOGLECLOUDPRINTLIBRARY_DIR.'/languages/');
	}

	public function google_cloud_print_options($options) {
		if (!empty($options)) return $options;
		return get_option('google_cloud_print_library_options', array());
	}

	public function google_cloud_print_copies($copies) {
		if (false !== $copies) return $copies;
		$options = get_option('google_cloud_print_library_options');
		return (int)$options['copies'];
	}

	public function admin_init() {
		register_setting( 'google_cloud_print_library_options', 'google_cloud_print_library_options' , array($this, 'options_validate') );

		add_settings_section ( 'google_cloud_print_library_options', 'Google Cloud Print', array($this, 'options_header') , 'google_cloud_print_library');

		add_settings_field ( 'google_cloud_print_library_options_username', __('Google Username', 'google-cloud-print-library'), array($this, 'google_cloud_print_library_options_username'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_password', __('Password (if altering)', 'google-cloud-print-library'), array($this, 'google_cloud_print_library_options_password'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_printer', __('Printer', 'google-cloud-print-library'), array($this, 'google_cloud_print_library_options_printer'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_copies', __('Copies', 'google-cloud-print-library'), array($this, 'google_cloud_print_library_options_copies'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_header', __('Print job header', 'google-cloud-print-library'), array($this, 'google_cloud_print_library_options_header'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
	}

	public function test_print() {

		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gcpl-nonce') || empty($_POST['printer']) || empty($_POST['printtext'])) die;

		$printed = $this->gcpl->print_document($_POST['printer'], __('Google Cloud Print Test', 'google-cloud-print-library'), '<p>'.nl2br($_POST['printtext']).'</p>', $_POST['prependtext'], (int)$_POST['copies']);

		if (isset($printed->success) && $printed->success == true) {
			echo json_encode(array('result' => 'ok'));
			die;
		}

		if (isset($printed->message)) {
			echo json_encode(array('result' => $printed->message));
			die;
		}

		echo json_encode(array('result' => __("Non-understood response:", 'google-cloud-print-library')." ".serialize($printed)));
		die;

	}

	public function show_admin_warning($message, $class = "updated") {
		echo '<div class="'.$class.' fade">'."<p>$message</p></div>";
	}

	public function google_cloud_print_library_options_username() {
		$options = get_option('google_cloud_print_library_options');
		echo '<input id="google_cloud_print_library_options_username" name="google_cloud_print_library_options[username]" size="40" type="text" value="'.$options["username"].'" /><br><em>'.__('If your Google account has two-factor authentication, then you will need to <a href="http://support.google.com/accounts/bin/answer.py?hl=en&answer=185833">obtain an application-specific password</a>.', 'google-cloud-print-library').'</em>';
	}

	public function google_cloud_print_library_options_password() {
		$options = get_option('google_cloud_print_library_options');
		echo '<input id="google_cloud_print_library_options_password" name="google_cloud_print_library_options[password]" size="40" type="password" value="" /><br><em>'.__('N.B. Your password is not stored - it is used once to gain an authentication token, which is stored instead.', 'google-cloud-print-library').'</em>';
	}

	public function google_cloud_print_library_options_header() {
		$options = get_option('google_cloud_print_library_options');
		echo '<textarea id="google_cloud_print_library_options_header" name="google_cloud_print_library_options[header]" rows="10" cols="60" />'.htmlspecialchars($options['header']).'</textarea><br>';
		echo '<em>'.__('Anything you enter here will be pre-pended to the print job. Use any valid HTML (including &lt;style&gt; tags)', 'google-cloud-print-library').'</em>';
	}

	public function google_cloud_print_library_options_copies() {
		$options = get_option('google_cloud_print_library_options');
		$copies = max(intval($options['copies']), 1);
		echo '<input id="google_cloud_print_library_options_copies" name="google_cloud_print_library_options[copies]" size="2" type="text" value="'.$copies.'" maxlength="3" /><br>';
	}

	// This function is both an options field printer, and called via AJAX
	public function google_cloud_print_library_options_printer() {

		if (defined('DOING_AJAX') && DOING_AJAX == true && (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gcpl-nonce'))) die;

		$options = get_option('google_cloud_print_library_options');

		$printers = $this->gcpl->get_printers();

		$this->printers_found = count($printers);

		if (count($printers) == 0) {

			echo '<input type="hidden" name="google_cloud_print_library_options[printer]" value=""><em>('.__('Account either not connected, or no printers available)', 'google-cloud-print-library').'</em>';

		} else {

			echo '<select onchange="google_cloud_print_confirm_unload = true;" id="google_cloud_print_library_options_printer" name="google_cloud_print_library_options[printer]">';

			foreach ($printers as $printer) {
				echo '<option '.((isset($options['printer']) && $options['printer'] == $printer->id) ? 'selected="selected"' : '').'value="'.htmlspecialchars($printer->id).'">'.htmlspecialchars($printer->displayName).'</option>';
			}

			echo '</select>';
			if (defined('DOING_AJAX') && DOING_AJAX == true) die;

			echo ' <a href="#" id="gcpl_refreshprinters">('.__('refresh', 'google-cloud-print-library').')</a>';

		}

	}

	public function options_validate($input) {

		if (current_user_can('manage_options') && !empty($input['username']) && !empty($input['password'])) {

			// Reset
			delete_transient('google_cloud_print_library_printers');

			$input['copies'] = max(intval($input['copies']), 1);


			// Authenticate
			$authed = $this->gcpl->authorize(
				$input['username'],
				$input['password']
			);

			$existing_options = get_option('google_cloud_print_library_options');

			// We don't actually store the password - that's not needed
			$input['password'] = (isset($existing_options['password'])) ? $existing_options['password'] : '';

			if ($authed === false || is_wp_error($authed)) {
				if ($authed === false) {
					$msg = __('We did not understand the response from Google.', 'google-cloud-print-library');
					add_settings_error("google_cloud_print_library_options_username", 'google_cloud_print_library_options_username', $msg);
				} else {
					foreach ($authed->get_error_messages() as $msg) {
						add_settings_error("google_cloud_print_library_options_username", 'google_cloud_print_library_options_username', $msg);
					}
				}
			} else {
				$input['password'] = $authed;
			}
			
		} else {
			$existing_options = get_option('google_cloud_print_library_options');

			// We don't actually store the password - that's not needed
			$input['password'] = (isset($existing_options['password'])) ? $existing_options['password'] : '';
		}

		return $input;
	}

	public function options_header() {
	}

	public function admin_menu() {
		# http://codex.wordpress.org/Function_Reference/add_options_page
		add_options_page('Google Cloud Print', 'Google Cloud Print', 'manage_options', 'google_cloud_print_library', array($this, 'options_printpage'));
	}

	public function action_links($links, $file) {
		if ( $file == GOOGLECLOUDPRINTLIBRARY_SLUG."/".GOOGLECLOUDPRINTLIBRARY_SLUG.".php" ){
			array_unshift( $links, 
				'<a href="options-general.php?page=google_cloud_print_library">'.__('Settings').'</a>',
				'<a href="http://updraftplus.com">'.__('UpdraftPlus WordPress backups', 'google-cloud-print-library').'</a>'
			);
		}
		return $links;
	}

	# This is the function outputing the HTML for our options page
	public function options_printpage() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		wp_enqueue_script('jquery-ui-spinner', false, array('jquery'));

		$pver = GOOGLECLOUDPRINTLIBRARY_PLUGINVERSION;

		echo <<<ENDHERE
	<div style="clear: left;width:950px; float: left; margin-right:20px;">

		<h1>Google Cloud Print Library (version $pver)</h1>
ENDHERE;

		echo '<p>Authored by <strong>David Anderson</strong> (<a href="http://david.dw-perspective.org.uk">Homepage</a> | <a href="http://updraftplus.com">UpdraftPlus - Best WordPress Backup</a> | <a href="http://wordpress.org/plugins/google-cloud-print-library">'.__('Instructions', 'google-cloud-print-library').'</a>)</p>';

		echo "<div>\n";

		echo '<p><em><strong>'.__('Instructions', 'google-cloud-print-library').':</strong> '.__('Enter your username and password, and then save the changes. After that, a list of printers will appear. Choose one, and then save for the second time.', 'google-cloud-print-library').'</em></p>';

		echo '<form action="options.php" method="post" onsubmit="google_cloud_print_confirm_unload=null; return true;">';
		settings_fields('google_cloud_print_library_options');
		do_settings_sections('google_cloud_print_library');

		echo '<table class="form-table"><tbody>';
		echo '<td><input class="button-primary" name="Submit" type="submit" value="'.esc_attr(__('Save Changes', 'google-cloud-print-library')).'" /></td>';
		echo '</table></form>';

		echo '<h3>'.__('Test Printing', 'google-cloud-print-library').'</h3>';

		echo '<table class="form-table"><tbody>';

		echo '<tr valign="top">
				<th scope="row">'.__('Enter some text to print:', 'google-cloud-print-library').'</th>
				<td><textarea id="google_cloud_print_library_testprinttext" cols="60" rows="15"></textarea></td>
			</tr>
			<tr>
			<th>&nbsp;</th>';

		echo '<td><button id="gcpl-testprint" class="button-primary" name="Print" type="submit">'.__('Print', 'google-cloud-print-library').'</button></td>';

		$nonce = wp_create_nonce("gcpl-nonce");

		$youneed = esc_js(__('You need to enter some text to print.', 'google-cloud-print-library'));
		$printing = esc_js(__('Printing...', 'google-cloud-print-library'));
		$success = esc_js(__('The print job was sent successfully.', 'google-cloud-print-library'));
		$response = esc_js(__('Response:', 'google-cloud-print-library'));
		$notchosen = esc_js(__('No printer is yet chosen/available', 'google-cloud-print-library'));
		$refreshing = esc_js(__('refreshing...', 'google-cloud-print-library'));
		$refresh = esc_js(__('refresh', 'google-cloud-print-library'));
		$print = esc_js(__('Print', 'google-cloud-print-library'));
		echo <<<ENDHERE
			</tr>

		</tbody>
		</table>
		</div>

	</div>

	<script>

		var google_cloud_print_confirm_unload = null;

		window.onbeforeunload = function() { return google_cloud_print_confirm_unload; }

		jQuery(document).ready(function() {

			jQuery('#google_cloud_print_library_options_copies').spinner({ numberFormat: "n" });

			jQuery('#gcpl_refreshprinters').click(function() {
				jQuery('#google_cloud_print_library_options_printer').css('opacity','0.3');
				jQuery('#gcpl_refreshprinters').html('($refreshing)');
				jQuery.post(ajaxurl, {
					action: 'gcpl_refresh_printers',
					_wpnonce: '$nonce'
				}, function(response) {
					jQuery('#google_cloud_print_library_options_printer').replaceWith(response);
					jQuery('#google_cloud_print_library_options_printer').css('opacity','1');
					jQuery('#gcpl_refreshprinters').html('($refresh)');
				});
			});

			jQuery('#gcpl-testprint').click(function() {
				var whichprint = jQuery('#google_cloud_print_library_options_printer').val();
				var whatprint = jQuery('#google_cloud_print_library_testprinttext').val();
				if (whatprint == '') {
					alert('$youneed');
					return;
				}
				if (whichprint) {
					jQuery('#gcpl-testprint').html('$printing');
					jQuery.post(ajaxurl, {
						action: 'gcpl_test_print',
						printtext: whatprint,
						printer: whichprint,
						copies: jQuery('#google_cloud_print_library_options_copies').val(),
						prependtext: jQuery('#google_cloud_print_library_options_header').val(),
						_wpnonce: '$nonce'
					}, function(response) {
						try {
							resp = jQuery.parseJSON(response);
							if (resp.result == 'ok') {
								alert('$success');
							} else {
								alert('$response '+resp.result);
							}
						} catch(err) {
							alert('$response '+response);
							console.log(response);
							console.log(err);
						}
						jQuery('#gcpl-testprint').html('$print');
					});

				} else {
					alert("$notchosen");
				}
			});
		});
	</script>

ENDHERE;

	}

}