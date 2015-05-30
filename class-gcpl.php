<?php

if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('GoogleCloudPrintLibrary_GCPL')):
define('GOOGLECLOUDPRINTLIBRARY_VERSION', '0.4.0');
class GoogleCloudPrintLibrary_GCPL {

	public $version;

	public function __construct() {
		$this->version = GOOGLECLOUDPRINTLIBRARY_VERSION;
	}

	public function print_document($printer_id = false, $title, $document, $prepend = false, $copies = false, $options = array()) {

		// $options should be populated with 'token' (token), 'printer' (if $printer_id is false) and 'header' (if $prepend is false)
		if (empty($options)) $options = apply_filters('google_cloud_print_options', array());

		$copies = apply_filters('google_cloud_print_copies', $copies);

		if (0 == $copies) {
			$x = new stdClass;
			$x->success = false;
			$x->message = 'No copies to print';
			return $x;
		}

		$copies = max(intval($copies), 1);

		$token = empty($options['token']) ? '' : $options['token'];

		if ($printer_id == false || empty($token)) {
			$printer_id = $options['printer'];
			if (empty($printer_id)) {
				$x = new stdClass;
				$x->success = false;
				$x->message = "Error: no printer has been configured";
				return $x;
			}
		}

		# Get capabilities of printer
// 		$can_fit_to_page = false;
// // 		$u = "https://www.google.com/cloudprint/printer";#?printerid=".urlencode($printer_id)."&use_cdd=true&output=json";
// 		$p = array('use_cdd' => 'true', 'printerid' => $printer_id);
// 		$r = $this->process_request("https://www.google.com/cloudprint/printer", $p, $token);
// 		if (is_string($r)) {
// 			$r = json_decode($r);
// 			if (is_object($r) && isset($r->printers[0]->capabilities->printer->fit_to_page->option)) {
// 				$options = $r->printers[0]->capabilities->printer->fit_to_page->option;
// 				foreach ($options as $num => $opt) {
// 					if (is_object($opt) && isset($opt->type) && $opt->type == 'FIT_TO_PAGE') $can_fit_to_page = $num;
// 				}
// 			}
// 		}

		# http://code.google.com/p/dompdf/wiki/Usage#Usage

		if ($prepend === false) $prepend = (isset($options['header'])) ? $options['header'] : '';

		if (is_string($document)) {

			if (!class_exists('DOMPDF')) require_once(apply_filters('google_cloud_print_dompdf_loader', dirname(__FILE__)."/dompdf/dompdf_config.inc.php"));

			if (false === stripos($prepend, '<html>') && false === stripos($document, '<html>')) {
				$html = '<html><body>'.$prepend.$document.'</body></html>';
			} else {
				$html = $prepend.$document;
			}

			try {
				$dompdf = new DOMPDF();
				if (!defined('WP_DEBUG') || !WP_DEBUG) $dompdf->set_option('log_output_file', false);
				$dompdf->load_html($html);
				$dompdf->render();
				# Send to browser
				// $dompdf->stream("sample.pdf");
				# Save to file
				// file_put_contents('sample.pdf', $dompdf->output());
				$pdf_output = $dompdf->output();
			} catch (Exception $e) {
				$x = new stdClass;
				$x->success = false;
				$x->message = 'DOMPDF error ('.get_class($e).', '.$e->getCode().'): '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')';
				return $x;
			}
		} elseif (is_array($document) && !empty($document['pdf-raw'])) {
			$pdf_output = $document['pdf-raw'];
		} elseif (is_array($document) && !empty($document['pdf-file'])) {
			$pdf_output = file_get_contents($document['pdf-file']);
		}

		$url = "https://www.google.com/cloudprint/submit?printerid=".urlencode($printer_id)."&output=json";

		$post = array(
			"printerid" => $printer_id,
// 			"capabilities" => "",
			"contentType" => "dataUrl",
			"title" => $title,
			"content" => 'data:application/pdf;base64,'. base64_encode($pdf_output)
		);

// 		if (false !== $can_fit_to_page) {
// 			$post['ticket'] = json_encode(array(
// 				'version' => '1.0',
// 				'print' => array(
// 					'vendor_ticket_item' => array(),
// 					'fit_to_page' => array( 'type' => $can_fit_to_page),
// // 					'margins' => array(
// // 						'top_microns' => 30000
// // 					)
// 				),
// 			));
// 		}

		for ($i=1; $i<=$copies; $i++) {
			$ret = $this->process_request($url, $post, $options);
			if ($i == $copies && is_string($ret)) return json_decode($ret);
		}

		$x = new stdClass;
		$x->success = false;
		$x->message = $ret;
		return $x;

	}

	public function get_printers() {

		if (!defined('DOING_AJAX') || DOING_AJAX != true) {
			$printers = get_transient('google_cloud_print_library_printers');
			if (is_array($printers)) return $printers;
		}

		// Wanted key: access token
		$options = apply_filters('google_cloud_print_options', array());

		// This should only be set if authenticated
		if (isset($options['token'])) {

			$post = array();

			$printers = $this->process_request('https://www.google.com/cloudprint/interface/search', $post, $options);

			if (is_wp_error($printers)) return $printers;

			if (is_string($printers)) $printers = json_decode($printers);

			if (is_object($printers) && isset($printers->success) && $printers->success == true && isset($printers->printers) && is_array($printers->printers)) {

				set_transient('google_cloud_print_library_printers', $printers->printers, 86400);

				return $printers->printers;

			}

		}

		return array();

	}

	// Old deprecated + removed ClientLogin method - https://developers.google.com/identity/protocols/AuthForInstalledApps
// 	public function authorize($clientid, $password) {
// 
// 		$post = array(
// 			"accountType" => "HOSTED_OR_GOOGLE",
// 			"Email" => $username,
// 			"Passwd" => $password,
// 			"service" => "cloudprint",
// 			"source" => "google-cloud-print-library-for-wordpress"
// 		);
// 
// 		$resp = $this->process_request("https://www.google.com/accounts/ClientLogin", $post);
// 
// 		if (is_wp_error($resp)) return $resp;
// 
// 		if (preg_match("/Error=([a-z0-9_\-]+)/i", $resp, $ematches)) return new WP_Error('bad_auth','Authentication failed: Google replied with: '.$ematches[1]);
// 
// 		preg_match("/Auth=([a-z0-9_\-]+)/i", $resp, $matches);
// 
// 		if (isset($matches[1])) {
// 			return $matches[1];
// 		} else {
// 			return false;
// 		}
// 
// 	}

	// Get a Google account access token using the refresh token
	// Expected keys in options: clientid, clientsecret, (refresh)token
	private function access_token($options) {

		$refresh_token = $options['token'];

		$query_body = array(
			'refresh_token' => $refresh_token,
			'client_id' => $options['clientid'],
			'client_secret' => $options['clientsecret'],
			'grant_type' => 'refresh_token'
		);

		$result = wp_remote_post('https://accounts.google.com/o/oauth2/token',
			array(
				'timeout' => '15',
				'method' => 'POST',
				'body' => $query_body
			)
		);

		if (is_wp_error($result)) {
			return $result;
		} else {
			$json_values = json_decode( $result['body'], true );
			if ( isset( $json_values['access_token'] ) ) {
// 				error_log("Google Drive: successfully obtained access token");
				return $json_values['access_token'];
			} else {
// 				error_log("Google Drive error when requesting access token: response does not contain access_token");
				return false;
			}
		}
	}

	public function process_request($url, $post_fields, $options = array(), $referer = '' ) {  

		$ret = "";

		$wp_post_opts = array(
			'user-agent' => "Google Cloud Print Library For WordPress/".$this->version,
			'headers' => array(
				'X-CloudPrint-Proxy' => "google-cloud-print-library-for-wordpress",
				'Referer' => $referer
			),
			'sslverify' => true,
			'redirection' => 5,
			'body' => $post_fields,
			'timeout' => 15
		);

		if (!empty($options['username']) && empty($options['clientid'])) {
			// Legacy/deprecated - tokens from the now-removed ClientLogin API
			$wp_post_opts['headers']['Authorization'] = "GoogleLogin auth=$token";
		} else {
			$access_token = $this->access_token($options);
			if (is_wp_error($access_token) || empty($access_token)) return $access_token;
			$wp_post_opts['headers']['Authorization'] = "Bearer $access_token";
		}

		$post = wp_remote_post($url, $wp_post_opts);

		if (is_wp_error($post)) {
 			error_log('POST error: '.$post->get_error_code().': '.$post->get_error_message());
			return $post;
		}

		if (!is_array($post['response']) || !isset($post['response']['code'])) {
 			error_log('POST error: Unexpected response: '.serialize($post));
			return false;
		}

		if ($post['response']['code'] >=400 && $post['response']['code']<500) {

			$extra = '';

		// This pertains only to the old ClientLogin authentication mechanism during its deprecation stage (it is now entirely gone)
// 			if (403 == $post['response']['code'] && !empty($post['body']) && false !== strpos($post['body'], 'Info=WebLoginRequired') && preg_match('/Url=(\S+)/', $post['body'], $umatch)) {
// 				$extra = 'Due to recent Google API changes, you will need to <a href="https://www.google.com/settings/security/lesssecureapps">go to your Google account, and enable &quot;less secure&quot; apps</a>. (N.B. This app does not store your password after using it once, so is not actually insecure). Or, <a href="https://www.google.com/landing/2step/">enable two-factor authentication on your Google account</a> and then <a href="http://support.google.com/accounts/bin/answer.py?hl=en&answer=185833">obtain an application-specific password</a>.';
// 			}

  			error_log('POST error: Unexpected response (code '.$post['response']['code'].'): '.serialize($post));
			return new WP_Error('http_badauth', $extra."Authentication failed (".$post['response']['code']."): ".$post['body']);
		}

		if ($post['response']['code'] >=400) {
 			error_log('POST error: Unexpected response (code '.$post['response']['code'].'): '.serialize($post));
			return new WP_Error('http_error', 'POST error: Unexpected response (code '.$post['response']['code'].'): '.serialize($post));
		}

		return $post['body'];

	}

}
endif;

if (!class_exists('GoogleCloudPrintLibrary_GCPL_v2')):
class GoogleCloudPrintLibrary_GCPL_v2 extends GoogleCloudPrintLibrary_GCPL {
}
endif;
