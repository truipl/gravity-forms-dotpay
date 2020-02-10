<?php
/*
Plugin Name: Gravity Forms Dotpay Add-On
Plugin URI: https://trui.pl
Description: Integrates Gravity Forms with Dotpay Payments, enabling end users to purchase goods and services through Gravity Forms.
Version: 0.1
Author: Trui.pl
Author URI: https://trui.pl
License: GPL-2.0+
*/

defined('ABSPATH') || die();

define('GF_DOTPAY_VERSION', '0.1');

add_action('gform_loaded', ['GFDotpayBootstrap', 'load'], 5);

class GFDotpayBootstrap
{
	public static function load()
	{
		if (!method_exists('GFForms', 'include_payment_addon_framework')) {
			return;
		}

		require_once('class-gf-dotpay.php');
		GFAddOn::register('GFDotpay');
	}
}

function gf_dotpay() {
	return GFDotpay::get_instance();
}
