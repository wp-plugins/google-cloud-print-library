<?php
/*
Plugin Name: Google Cloud Print Library
Plugin URI: http://wordpress.org/extend/plugins/google-cloud-print-library
Description: Some routines used for sending simple text files to Google Cloud Print
Author: DavidAnderson
Version: 0.1.6
License: MIT
Author URI: http://david.dw-perspective.org.uk
Donate: http://david.dw-perspective.org.uk/donate
*/

if (!defined ('ABSPATH')) die ('No direct access allowed');

define('GOOGLECLOUDPRINTLIBRARY_VERSION', '0.1.6');

define('GOOGLECLOUDPRINTLIBRARY_SLUG', 'google-cloud-print-library');
define('GOOGLECLOUDPRINTLIBRARY_DIR', dirname(__FILE__));
define('GOOGLECLOUDPRINTLIBRARY_URL', plugins_url('', __FILE__));

set_include_path(get_include_path().'/'.GOOGLECLOUDPRINTLIBRARY_DIR.'/library');

if (!isset($googlecloudprintlibrary_gcpl) || !is_a($googlecloudprintlibrary_gcpl, 'GoogleCloudPrintLibrary_GCPL')) $googlecloudprintlibrary_gcpl = new GoogleCloudPrintLibrary_GCPL();

class GoogleCloudPrintLibrary_GCPL {

	var $version;

	function __construct() {
		$this->version = GOOGLECLOUDPRINTLIBRARY_VERSION;
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_init', array($this, 'admin_init'));
		add_filter('plugin_action_links', array($this, 'action_links'), 10, 2 );
		add_action('wp_ajax_gcpl_test_print', array($this, 'test_print'));
		add_action('wp_ajax_gcpl_refresh_printers', array($this, 'google_cloud_print_library_options_printer'));
	}

	function admin_init() {
		register_setting( 'google_cloud_print_library_options', 'google_cloud_print_library_options' , array($this, 'options_validate') );
		add_settings_section ( 'google_cloud_print_library_options', 'Google Cloud Print', array($this, 'options_header') , 'google_cloud_print_library');
		add_settings_field ( 'google_cloud_print_library_options_username', 'Google Username', array($this, 'google_cloud_print_library_options_username'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_password', 'Password (if altering)', array($this, 'google_cloud_print_library_options_password'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_printer', 'Printer', array($this, 'google_cloud_print_library_options_printer'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_copies', 'Copies', array($this, 'google_cloud_print_library_options_copies'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );
		add_settings_field ( 'google_cloud_print_library_options_header', 'Print job header', array($this, 'google_cloud_print_library_options_header'), 'google_cloud_print_library' , 'google_cloud_print_library_options' );

	}

	function test_print() {

		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gcpl-nonce') || empty($_POST['printer']) || empty($_POST['printtext'])) die;

		$printed = $this->print_document($_POST['printer'], 'Google Cloud Print Test', '<p>'.nl2br($_POST['printtext']).'</p>', $_POST['prependtext'], (int)$_POST['copies']);

		if (isset($printed->success) && $printed->success == true) {
			echo 'ok';
			die;
		}

		if (isset($printed->message)) {
			echo $printed->message;
			die;
		}

		echo "Non-understood response: ".json_encode($printed);

		die;

	}

	public function print_document($printer_id = false, $title, $text, $prepend = false, $copies = 1) {

		$options = get_option('google_cloud_print_library_options');
		$token = $options['password'];

		$copies = max(intval($copies), 1);

		if ($printer_id == false || empty($token)) {
			$printer_id = $options['printer'];
			if (empty($printer_id)) {
				$x = new stdClass;
				$x->success = false;
				$x->message = "Error: no printer has been configured";
				return $x;
			}
		}

		# http://code.google.com/p/dompdf/wiki/Usage#Usage

		require_once(GOOGLECLOUDPRINTLIBRARY_DIR."/dompdf/dompdf_config.inc.php");

		if ($prepend === false) $prepend = $options['header'];

		$html =
		'<html><body>'.$prepend.$text.'</body></html>';

		$dompdf = new DOMPDF();
		$dompdf->load_html($html);
		$dompdf->render();
		# Send to browser
		// $dompdf->stream("sample.pdf");
		# Save to file
		// file_put_contents('sample.pdf', $dompdf->output());

		/*
		# Get capabilities of printer
		$u = "https://www.google.com/cloudprint/printer?printerid=".urlencode($printer_id)."&output=json";
		$a= array('printerid' => $printer_id);
		$r = $this->process_request($u, $p, $token);
		error_log(serialize($r));
		*/

		$url = "https://www.google.com/cloudprint/submit?printerid=".urlencode($printer_id)."&output=json";

		$post = array(
			"printerid" => $printer_id,
			"capabilities" => "",
			"contentType" => "dataUrl",
			"title" => $title,
			"content" => 'data:application/pdf;base64,'. base64_encode($dompdf->output())
		);

		for ($i=1; $i<=$copies; $i++) {
			$ret = $this->process_request($url, $post, $token);
			if ($i == $copies && is_string($ret)) return json_decode($ret);
		}

		$x = new stdClass;
		$x->success = false;
		$x->message = $ret;
		return $x;

	}

	function show_admin_warning($message, $class = "updated") {
		echo '<div class="'.$class.' fade">'."<p>$message</p></div>";
	}

	function google_cloud_print_library_options_username() {
		$options = get_option('google_cloud_print_library_options');
		echo '<input id="google_cloud_print_library_options_username" name="google_cloud_print_library_options[username]" size="40" type="text" value="'.$options["username"].'" /><br><em>If your Google account has two-factor authentication, then you will need to <a href="http://support.google.com/accounts/bin/answer.py?hl=en&answer=185833">obtain an application-specific password</a>.</em>';
	}

	function google_cloud_print_library_options_password() {
		$options = get_option('google_cloud_print_library_options');
		echo '<input id="google_cloud_print_library_options_password" name="google_cloud_print_library_options[password]" size="40" type="password" value="" /><br><em>N.B. Your password is not stored - it is used once to gain an authentication token, which is stored instead.</em>';
	}

	function google_cloud_print_library_options_header() {
		$options = get_option('google_cloud_print_library_options');
		echo '<textarea id="google_cloud_print_library_options_header" name="google_cloud_print_library_options[header]" rows="10" cols="60" />'.htmlspecialchars($options['header']).'</textarea><br>';
		echo '<em>Anything you enter here will be pre-pended to the print job. Use any valid HTML (including &lt;style&gt; tags)</em>';
	}

	function google_cloud_print_library_options_copies() {
		$options = get_option('google_cloud_print_library_options');
		$copies = max(intval($options['copies']), 1);
		echo '<input id="google_cloud_print_library_options_copies" name="google_cloud_print_library_options[copies]" size="2" type="text" value="'.$copies.'" maxlength="3" /><br>';
	}

	// This function is both an options field printer, and called via AJAX
	function google_cloud_print_library_options_printer() {

		if (defined('DOING_AJAX') && DOING_AJAX == true && (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gcpl-nonce'))) die;

		$options = get_option('google_cloud_print_library_options');

		$printers = $this->get_printers();

		if (count($printers) == 0) {

			echo '<input type="hidden" name="google_cloud_print_library_options[printer]" value=""><em>(Account either not connected, or no printers available)</em>';

		} else {

			echo '<select id="google_cloud_print_library_options_printer" name="google_cloud_print_library_options[printer]">';

			foreach ($printers as $printer) {
				echo '<option '.((isset($options['printer']) && $options['printer'] == $printer->id) ? 'selected="selected"' : '').'value="'.htmlspecialchars($printer->id).'">'.htmlspecialchars($printer->displayName).'</option>';
			}

			echo '</select>';
			if (defined('DOING_AJAX') && DOING_AJAX == true) die;

			echo ' <a href="#" id="gcpl_refreshprinters">(refresh)</a>';

		}

	}

	function get_printers() {

		if (!defined('DOING_AJAX') || DOING_AJAX != true) {
			$printers = get_transient('google_cloud_print_library_printers');
			if (is_array($printers)) return $printers;
		}

		$options = get_option('google_cloud_print_library_options');

		// This should only be set if authenticated
		if (isset($options['password'])) {

			$post = array();

			$printers = $this->process_request('https://www.google.com/cloudprint/interface/search', $post, $options['password']);
			if (is_string($printers)) $printers = json_decode($printers);

			if (isset($printers->success) && $printers->success == true && isset($printers->printers) && is_array($printers->printers)) {

				set_transient('google_cloud_print_library_printers', $printers->printers, 86400);

				return $printers->printers;

			}

		}

		return array();

	}

	function options_validate($input) {

		if (current_user_can('manage_options') && !empty($input['username']) && !empty($input['password'])) {

			// Reset
			delete_transient('google_cloud_print_library_printers');

			$input['copies'] = max(intval($input['copies']), 1);


			// Authenticate
			$authed = $this->authorize(
				$input['username'],
				$input['password']
			);

			$existing_options = get_option('google_cloud_print_library_options');

			// We don't actually store the password - that's not needed
			$input['password'] = (isset($existing_options['password'])) ? $existing_options['password'] : '';

			if ($authed === false || is_wp_error($authed)) {
				if ($authed === false) {
					$msg = 'We did not understand the response from Google.';
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

	function options_header() {
	}

	function admin_menu() {
		# http://codex.wordpress.org/Function_Reference/add_options_page
		add_options_page('Google Cloud Print', 'Google Cloud Print', 'manage_options', 'google_cloud_print_library', array($this, 'options_printpage'));
	}

	function action_links($links, $file) {
		if ( $file == GOOGLECLOUDPRINTLIBRARY_SLUG."/".GOOGLECLOUDPRINTLIBRARY_SLUG.".php" ){
			array_unshift( $links, 
				'<a href="options-general.php?page=google_cloud_print_library">Settings</a>',
				'<a href="http://updraftplus.com">UpdraftPlus WordPress backups</a>'
			);
		}
		return $links;
	}

	public function authorize($username, $password) {

		$url = "https://www.google.com/accounts/ClientLogin";

		$post = array(
			"accountType" => "HOSTED_OR_GOOGLE",
			"Email" => $username,
			"Passwd" => $password,
			"service" => "cloudprint",
			"source" => "google-cloud-print-library-for-wordpress"
		);

		$resp = $this->process_request($url, $post);

		if (is_wp_error($resp)) return $resp;

		if (preg_match("/Error=([a-z0-9_\-]+)/i", $resp, $ematches)) return new WP_Error('bad_auth','Authentication failed: Google replied with: '.$ematches[1]);

		preg_match("/Auth=([a-z0-9_\-]+)/i", $resp, $matches);

		if (isset($matches[1])) {
			return $matches[1];
		} else {
			return false;
		}

	}

	function process_request($url, $post_fields, $token = null, $referer = '' ) {  

		if (!function_exists('curl_init')) {
			return new WP_Error('no_curl', 'You need to have curl installed in your webserver\'s PHP installation');
		}

		$ret = "";
		$ch = curl_init(); 

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, "Google Cloud Print Library For WordPress/".$this->version);
		if(!is_null($post_fields)) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		}

		if(is_string($token)) {
			$headers = array(
			"Authorization: GoogleLogin auth=$token",
			//"GData-Version: 3.0",
			"X-CloudPrint-Proxy", "google-cloud-print-library-for-wordpress"
			);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    

		$ret = curl_exec ($ch);

		curl_close ($ch); 

		return $ret;
	}

	# This is the function outputing the HTML for our options page
	function options_printpage() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		wp_enqueue_script('jquery-ui-spinner', false, array('jquery'));

		$pver = GOOGLECLOUDPRINTLIBRARY_VERSION;

		echo <<<ENDHERE
	<div style="clear: left;width:950px; float: left; margin-right:20px;">

		<h1>Google Cloud Print Library (version $pver)</h1>
ENDHERE;

		if (!function_exists('curl_init')) {
			echo '<div class="updated fade">'."<p>The Google Cloud Print Library plugin requires the PHP curl module to be installed on your web server, which it is not. You will need to discuss this with your web hosting provider.</p></div>";
		}

		echo <<<ENDHERE
		<p>Authored by <strong>David Anderson</strong> (<a href="http://david.dw-perspective.org.uk">Homepage</a> | <a href="http://updraftplus.com">UpdraftPlus - Best WordPress Backup</a> | <a href="http://wordpress.org/extend/plugins/google-cloud-print-library">Instructions</a>)
		</p>

		<div>
ENDHERE;
		echo '<p><em><strong>Instructions:</strong> Enter your username and password, and then save the changes. After that, a list of printers will appear. Choose one, and then save for the second time.</em></p>';

		echo '<form action="options.php" method="post">';
		settings_fields('google_cloud_print_library_options');
		do_settings_sections('google_cloud_print_library');

		echo '<table class="form-table"><tbody>';
		echo '<td><input class="button-primary" name="Submit" type="submit" value="'.esc_attr('Save Changes').'" /></td>';
		echo '</table></form>';

		echo '<h3>Test Printing</h3>';

		echo '<table class="form-table"><tbody>';

		echo <<<ENDHERE
			<tr valign="top">
				<th scope="row">Enter some text to print:</th>
				<td><textarea id="google_cloud_print_library_testprinttext" cols="60" rows="15"></textarea></td>
			</tr>
			<tr>
			<th>&nbsp;</th>
ENDHERE;
		echo '<td><button id="gcpl-testprint" class="button-primary" name="Print" type="submit">Print</button></td>';

		$nonce = wp_create_nonce("gcpl-nonce");

		echo <<<ENDHERE
			</tr>

		</tbody>
		</table>
		</div>

	</div>

	<script>
		jQuery(document).ready(function() {

			jQuery('#google_cloud_print_library_options_copies').spinner({ numberFormat: "n" });

			jQuery('#gcpl_refreshprinters').click(function() {
				jQuery('#google_cloud_print_library_options_printer').css('opacity','0.3');
				jQuery('#gcpl_refreshprinters').html('(refreshing...)');
				jQuery.post(ajaxurl, {
					action: 'gcpl_refresh_printers',
					_wpnonce: '$nonce'
				}, function(response) {
					jQuery('#google_cloud_print_library_options_printer').replaceWith(response);
					jQuery('#google_cloud_print_library_options_printer').css('opacity','1');
					jQuery('#gcpl_refreshprinters').html('(refresh)');
				});
			});

			jQuery('#gcpl-testprint').click(function() {
				var whichprint = jQuery('#google_cloud_print_library_options_printer').val();
				var whatprint = jQuery('#google_cloud_print_library_testprinttext').val();
				if (whatprint == '') {
					alert('You need to enter some text to print.');
					return;
				}
				if (whichprint) {
					jQuery('#gcpl-testprint').html('Printing...');
					jQuery.post(ajaxurl, {
						action: 'gcpl_test_print',
						printtext: whatprint,
						printer: whichprint,
						copies: jQuery('#google_cloud_print_library_options_copies').val(),
						prependtext: jQuery('#google_cloud_print_library_options_header').val(),
						_wpnonce: '$nonce'
					}, function(response) {
						if (response == 'ok') {
							alert('The print job was sent successfully.');
						} else {
							alert('Response: '+response);
						}
						jQuery('#gcpl-testprint').html('Print');
					});

				} else {
					alert("No printer is yet chosen/available");
				}
			});
		});
	</script>

ENDHERE;

	}

}