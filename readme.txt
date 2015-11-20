=== Google Cloud Print Library ===
Contributors: DavidAnderson
Tags: google cloud print, woocommerce print orders, print orders
Requires at least: 3.2
Tested up to: 4.4
Stable tag: 0.5.0
Donate link: http://david.dw-perspective.org.uk/donate
License: MIT/GPLv2+

== Description ==

This plugin is mainly for programmers to use. It contains an options page to set up a connection to a Google account, and allows you to choose a Google Cloud Print printer from your account and to test printing to it.

The main use of this plugin is for developers of other plugins to deploy, and integrate with their plugins. For example, it has been integrated into <a title="WooCommerce - automatically print orders, invoices and delivery notes" href="https://www.simbahosting.co.uk/s3/product/woocommerce-automatic-order-printing/">this plugin that can send orders (invoices, delivery and packing notes) from a WooCommerce web shop automatically to Google Cloud Print</a>.

To set up WordPress to have access to Google Cloud Print, you will need to follow this reasonably-simple tutorial: https://www.simbahosting.co.uk/s3/support/configuring-google-drive-api-access-for-cloud-print/

If you find it useful in your project, then please do consider a donation: http://david.dw-perspective.org.uk/donate

= How can a developer use it? =

Here's some example code:

`// Ensure that we can get a Google Cloud Print Library object

if (class_exists('GoogleCloudPrintLibrary_GCPL_v2')) {

	// The first parameter to print_document() is the printer ID. Use false to send to the default. You can use the get_printers() method to get a list of those available.

	$gcpl = new GoogleCloudPrintLibrary_GCPL_v2();

	$printed = $gcpl->print_document(false, get_bloginfo('name').' - test print', '<b>My HTML to print</b>');

	// Parse the results
	if (!isset($printed->success)) {
		trigger_error('Unknown response received from GoogleCloudPrintLibrary_GCPL->print_document()', E_USER_NOTICE);
	} elseif ($printed->success !== true) {
		trigger_error('GoogleCloudPrintLibrary_GCPL->print_document(): printing failed: '.$printed->message, E_USER_NOTICE);
	}

}`


== Screenshots ==

1. Options page

== Installation ==

Standard WordPress plugin installation:

1. Search for "Google Cloud Print Library" in the WordPress plugin installer
2. Click 'Install'

== Frequently Asked Questions ==

= What does this plugin do? =

It is a developers' plugin. It provides code to get you connected quickly and easily to Google Cloud Print. A developer can harness it rapidly from within his own plugin to despatch print jobs to a Google Cloud Print-connected printer. For example, its underlying engine is deployed in this plugin for printing automatically WooCommerce orders (great for restaurants, take-aways or anyone who always wants to print a packing slip and wants to save time): https://www.simbahosting.co.uk/s3/product/woocommerce-automatic-order-printing/

= How do I, as a developer, use it? =

Please see the plugin description.

= Please will you add a new feature? =

Only upon commission. This plugin does what I need - I am sharing it with the community, but not intending to develop it further unless I personally need to. I am happy to accept patches for improvements from the community.

= What support is provided for this plugin? =

None from me. I will accept useful patches that make this plugin more useful for others - please email them to david at wordshell dot net. This plugin is by a developer, for developers. To understand more of how it works beyond the description here, please read the (short, simple) code.

= Got any other interesting tools? =

Please check out the very popular UpdraftPlus backup plugin (https://updraftplus.com), my profile page (http://profiles.wordpress.org/DavidAnderson), and for command-line users, WordShell (http://wordshell.net).

== Changelog ==

= 0.5.0 - 28/Sep/2015 =
* TWEAK: Support the passing of more options, including paper size

= 0.4.3 - 20/Aug/2015 =
* TWEAK: Remove a CSS class from the admin notice that caused some themes to not show it.

= 0.4.2 - 01/Aug/2015 =
* Tested/compatible with WordPress 4.3
* Bumped internal version number that should have been bumped last time

= 0.4.1 - 24/Jun/2015 =
* FEATURE: Now allows sending to multiple printers (the UI now provides check-boxes, instead of a drop-down)

= 0.4.0 - 30/May/2015 =
* TWEAK: Now uses OAuth2 for authentication with Google (since Google have removed ClientLogin). As a result, the settings page now has entries for a client ID + secret (and a link to instructions), and the previous settings for your Google username are now gone.

= 0.3.2 - 30/Jan/2015 =
* TWEAK: Add a message with instructions for users who fall foul of Google's recent changes to the ClientLogin method

= 0.3.1 - 05/Nov/2014 =
* FIX: Fix bug that meant you had to enter your password twice (first time always failed)
* TWEAK: Stop the page jumping when you click on the 'refresh' link for the list of printers

= 0.3.0 - 04/Nov/2014 =
* TWEAK: Update to DomPDF 0.6
* TWEAK: Minor changes to code internals

= 0.2.2 - 01/Nov/2014 =
* TWEAK: Minor changes to code internals

= 0.2.1 - 22/Oct/2014 =
* TWEAK: Code re-factored and brought up to date with best practices. Also now uses wp_remote_post() instead of Curl directly.
* FEATURE: Internationalised (i.e. ready for translation)

= 0.1.6 - 07/Sep/2013 =
* FEATURE: Allow printing of multiple copies (compatible with all printers - job is sent multiple times)

= 0.1.5 - 29/July/2013 =
* FIX: display saved printer preference on options page

= 0.1.4 - 10/May/2013 =
* First version

== License ==

Copyright 2013- David Anderson

MIT License:

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

The authors of the DomPDF library (http://code.google.com/p/dompdf/) are gratefully acknowledged. The DomPDF library is used under the Lesser GNU Public Licence (LGPL, version 2.1).

== Upgrade Notice ==
0.5.0 : Support the passing of more options internally
