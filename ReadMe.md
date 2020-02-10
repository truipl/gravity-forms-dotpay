# Gravity Forms Dotpay Add-On

This plugin integrates Gravity Forms with polish payment gateway - [Dotpay](https://www.dotpay.pl/). It allows end-users to purchase goods and services via Gravity Forms.

**NOTE:** this plugin requires [Gravity Forms](https://www.gravityforms.com/); so you need to have installed and activated Gravity Forms.

## Requirements

  * The Gravity Forms plugin
  * An account with Dotpay

## Installation

  1. Install the Gravity Forms plugin.
  1. Activate the Gravity forms plugin, and set settings as desired.
  1. Upload the Gravity Forms Dotpay Add-On to your `/wp-content/plugins/` directory.
  1. Activate Gravity Forms Dotpay Add-On via Plugins menu in WordPress.

## Using

  1. Select 'Plugins' from the WordPress Admin menu.
  1. Click the 'Settings' link for the Dotpay plugin.
  1. Enter your Dotpay Shop ID.
  1. Enter your Dotpay Shop PIN.
  1. Select desired mode ('Production' or 'Testing').
  1. Save the settings by clicking the 'Save Settings' button.

**NOTE:** It may be required to disable the "Block external urlc" and "HTTPS verify" options in the Dotpay panel.

## Frequently Asked Questions

### How to move the currency symbol from the right to the left?

Copy and paste the code below into your theme’s `functions.php` file.
```php
function change_gravity_currency($currencies) {
	$currencies['PLN'] = [
		'name'               => 'Polish Złoty',
		'symbol_left'        => '',
		'symbol_right'       => 'zł',
		'symbol_padding'     => ' ',
		'thousand_separator' => ',',
		'decimal_separator'  => ',',
		'decimals'           => 2
	];

	return $currencies;
}
add_filter('gform_currencies', 'change_gravity_currency');
```

### How to display the relevant confirmation depending on the payment?

Currently, the only option is to redirect the confirmation to the WordPress page, to which URL parameters should be passed.

  1. Create new theme template:
  ```php
  <?php
  /**
  * Template Name: Thank You Page
  */
  get_header();
  ?>

    <main class="main">
      <?php
        if (isset($_GET['entry']) && is_numeric($_GET['entry'])) {
          $entry = GFAPI::get_entry($_GET['entry']);

          if (!is_wp_error($entry)) {
            switch ($entry['payment_status']) {
              case 'Paid':
                echo '<h1>The payment has been successfully confirmed!</h1>';
                break;
              case 'Processing':
                echo '<h1>Your payment is being processed</h1>';
                break;
              default:
                echo '<h1>Payment failed...</h1>';
            }
          }
        }
      ?>
    </main>

  <?php get_footer(); ?>
  ```
  1. Create new WordPress page and select created template.
  1. Open your form's settings.
  1. Go to 'Confirmations'.
  1. Select your confirmation.
  1. Select 'Page' in 'Confirmation Type'.
  1. Choose created page.
  1. Check 'Pass Field Data Via Query String' and paste `entry={entry_id}`.
  1. Save the settings by clicking the 'Save Confirmation' button.

### How to send notifications after successful payment?

  1. Open your form's settings.
  1. Go to 'Notifications'.
  1. Select your notification.
  1. Change 'Event' to 'Payment Completed'.