<?php

if (!defined('ABSPATH')) die('No direct access allowed');

if (!class_exists('GoogleCloudPrintLibrary_GCPL')):
define('GOOGLECLOUDPRINTLIBRARY_VERSION', '0.3.0');
class GoogleCloudPrintLibrary_GCPL {

	public $version;

	public function __construct() {
		$this->version = GOOGLECLOUDPRINTLIBRARY_VERSION;
	}

	public function print_document($printer_id = false, $title, $document, $prepend = false, $copies = false, $options = array()) {

		// $options should be populated with 'password' (token), 'printer' (if $printer_id is false) and 'header' (if $prepend is false)
		if (empty($options)) $options = apply_filters('google_cloud_print_options', array());

		$token = $options['password'];

		$copies = apply_filters('google_cloud_print_copies', $copies);

		if (0 == $copies) {
			$x = new stdClass;
			$x->success = false;
			$x->message = 'No copies to print';
			return $x;
		}

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

		/*
		# Get capabilities of printer
		$u = "https://www.google.com/cloudprint/printer?printerid=".urlencode($printer_id)."&output=json";
		$a= array('printerid' => $printer_id);
		$r = $this->process_request($u, $p, $token);
		error_log(serialize($r));
		*/

		# http://code.google.com/p/dompdf/wiki/Usage#Usage

		if ($prepend === false) $prepend = $options['header'];

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
			"capabilities" => "",
			"contentType" => "dataUrl",
			"title" => $title,
			"content" => 'data:application/pdf;base64,'. base64_encode($pdf_output)
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

	public function get_printers() {

		if (!defined('DOING_AJAX') || DOING_AJAX != true) {
			$printers = get_transient('google_cloud_print_library_printers');
			if (is_array($printers)) return $printers;
		}

		// Wanted key: password
		$options = apply_filters('google_cloud_print_options', array());

		// This should only be set if authenticated
		if (isset($options['password'])) {

			$post = array();

			$printers = $this->process_request('https://www.google.com/cloudprint/interface/search', $post, $options['password']);
			if (is_string($printers)) $printers = json_decode($printers);

			if (is_object($printers) && isset($printers->success) && $printers->success == true && isset($printers->printers) && is_array($printers->printers)) {

				set_transient('google_cloud_print_library_printers', $printers->printers, 86400);

				return $printers->printers;

			}

		}

		return array();

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

	public function process_request($url, $post_fields, $token = null, $referer = '' ) {  

		$ret = "";

		$post = wp_remote_post($url,
			array(
				'user-agent' => "Google Cloud Print Library For WordPress/".$this->version,
				'headers' => array(
					'Authorization' => "GoogleLogin auth=$token",
					'X-CloudPrint-Proxy' => "google-cloud-print-library-for-wordpress",
					'Referer' => $referer
				),
				'sslverify' => true,
				'redirection' => 5,
				'body' => $post_fields,
				'timeout' => 15
			)
		);

		if (is_wp_error($post)) {
 			error_log('POST error: '.$post->get_error_code().': '.$post->get_error_message());
			return $post;
		}

		if (!is_array($post['response']) || !isset($post['response']['code'])) {
 			error_log('POST error: Unexpected response: '.serialize($post));
			return false;
		}

		if ($post['response']['code'] >=400 && $post['response']['code']<500) {
 			error_log('POST error: Unexpected response (code '.$post['response']['code'].'): '.serialize($post));
			return new WP_Error('http_badauth', "Authentication failed (".$post['response']['code']."): ".$post['body']);
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
