<?php

defined('ABSPATH') || die();

add_action('wp', ['GFDotpay', 'maybe_thankyou_page'], 5);

GFForms::include_payment_addon_framework();

class GFDotpay extends GFPaymentAddOn
{
    // Main class variables to configure the add-on
    protected $_min_gravityforms_version = '2.4';
    protected $_slug = 'gravityformsdotpay';
    protected $_path = 'gravityformsdotpay/dotpay.php';
    protected $_full_path = __FILE__;
    protected $_url = 'https://trui.pl';
    protected $_title = 'Gravity Forms Dotpay Add-On';
    protected $_short_title = 'Dotpay';
    protected $_requires_credit_card = false;
    protected $_supports_callbacks = true;

    // Dotpay variables
    private $production_url = 'https://ssl.dotpay.pl/t2/';
    private $sandbox_url = 'https://ssl.dotpay.pl/test_payment/';

    // Members plugin integration
    protected $_capabilities = ['gravityforms_dotpay', 'gravityforms_dotpay_uninstall'];

    // Permissions
    protected $_capabilities_settings_page = 'gravityforms_dotpay';
    protected $_capabilities_form_settings = 'gravityforms_dotpay';
    protected $_capabilities_uninstall = 'gravityforms_dotpay_uninstall';

    // Automatic upgrade disabled
    protected $_enable_rg_autoupgrade = false;

    /**
     * @var object|null $_instance If available, contains an instance of this class.
     */
    private static $_instance = null;

    /**
     * Returns an instance of this class, and stores it in the $_instance property.
     *
     * @return object $_instance An instance of this class.
     */
    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFDotpay();
        }

        return self::$_instance;
    }

    private function __clone()
    {
        /* do nothing */
    }

    /**
     * Configures the settings which should be rendered on the Form Settings -> Add-On tab.
     *
     * @return array
     */
    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        $fields = [
            [
                'name' => 'dotpayID',
                'label' => __('Shop ID', 'gravityformsdotpay'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . __('Shop\'s ID Number', 'gravityformsdotpay') . '</h6>' . __('The ID number can be found after logging into the Dotpay panel in the Settings tab. It is a 6-digit number.', 'gravityformsdotpay')
            ],
            [
                'name' => 'dotpayPIN',
                'label' => __('Shop\'s PIN', 'gravityformsdotpay'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . __('Store\'s PIN Number', 'gravityformsdotpay') . '</h6>' . __('This is the character string that the vendor must generate for the store (id) in the Dotpay panel.', 'gravityformsdotpay')
            ],
            [
                'name' => 'mode',
                'label' => __('Mode', 'gravityformsdotpay'),
                'type' => 'radio',
                'choices' => [
                    ['id' => 'gf_dotpay_mode_production', 'label' => __('Production', 'gravityformsdotpay'), 'value' => 'production'],
                    ['id' => 'gf_dotpay_mode_test', 'label' => __('Testing', 'gravityformsdotpay'), 'value' => 'test'],
                ],
                'horizontal' => true,
                'default_value' => 'production',
                'tooltip' => '<h6>'. __('Operation Mode', 'gravityformsdotpay') .'</h6>' . __('Select Production to receive live payments. Select Test for testing purposes when using the Dotpay development sandbox.', 'gravityformsdotpay')
            ],
        ];

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);

        // remove "subscription" from transaction type dropdown
        $transaction_type = parent::get_field('transactionType', $default_settings);
        unset($transaction_type['choices'][2]);
        $default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);

        return apply_filters('gform_dotpay_feed_settings_fields', $default_settings);
    }

    /**
     * Generate hashed "chk" key for Dotpay
     *
     * @param array $parameters
     *
     * @return string
     */
    private function generate_chk($parameters)
    {
        $chk = $parameters['pin'] .
            (isset($parameters['api_version']) ? $parameters['api_version'] : null) .
            (isset($parameters['lang']) ? $parameters['lang'] : null) .
            (isset($parameters['id']) ? $parameters['id'] : null) .
            (isset($parameters['pid']) ? $parameters['pid'] : null) .
            (isset($parameters['amount']) ? $parameters['amount'] : null) .
            (isset($parameters['currency']) ? $parameters['currency'] : null) .
            (isset($parameters['description']) ? $parameters['description'] : null) .
            (isset($parameters['control']) ? $parameters['control'] : null) .
            (isset($parameters['channel']) ? $parameters['channel'] : null) .
            (isset($parameters['credit_card_brand']) ? $parameters['credit_card_brand'] : null) .
            (isset($parameters['ch_lock']) ? $parameters['ch_lock'] : null) .
            (isset($parameters['channel_groups']) ? $parameters['channel_groups'] : null) .
            (isset($parameters['onlinetransfer']) ? $parameters['onlinetransfer'] : null) .
            (isset($parameters['url']) ? $parameters['url'] : null) .
            (isset($parameters['type']) ? $parameters['type'] : null) .
            (isset($parameters['buttontext']) ? $parameters['buttontext'] : null) .
            (isset($parameters['urlc']) ? $parameters['urlc'] : null) .
            (isset($parameters['firstname']) ? $parameters['firstname'] : null) .
            (isset($parameters['lastname']) ? $parameters['lastname'] : null) .
            (isset($parameters['email']) ? $parameters['email'] : null) .
            (isset($parameters['street']) ? $parameters['street'] : null) .
            (isset($parameters['street_n1']) ? $parameters['street_n1'] : null) .
            (isset($parameters['street_n2']) ? $parameters['street_n2'] : null) .
            (isset($parameters['state']) ? $parameters['state'] : null) .
            (isset($parameters['addr3']) ? $parameters['addr3'] : null) .
            (isset($parameters['city']) ? $parameters['city'] : null) .
            (isset($parameters['postcode']) ? $parameters['postcode'] : null) .
            (isset($parameters['phone']) ? $parameters['phone'] : null) .
            (isset($parameters['country']) ? $parameters['country'] : null) .
            (isset($parameters['code']) ? $parameters['code'] : null) .
            (isset($parameters['p_info']) ? $parameters['p_info'] : null) .
            (isset($parameters['p_email']) ? $parameters['p_email'] : null) .
            (isset($parameters['n_email']) ? $parameters['n_email'] : null) .
            (isset($parameters['expiration_date']) ? $parameters['expiration_date'] : null) .
            (isset($parameters['deladdr']) ? $parameters['deladdr'] : null) .
            (isset($parameters['recipient_account_number']) ? $parameters['recipient_account_number'] : null) .
            (isset($parameters['recipient_company']) ? $parameters['recipient_company'] : null) .
            (isset($parameters['recipient_first_name']) ? $parameters['recipient_first_name'] : null) .
            (isset($parameters['recipient_last_name']) ? $parameters['recipient_last_name'] : null) .
            (isset($parameters['recipient_address_street']) ? $parameters['recipient_address_street'] : null) .
            (isset($parameters['recipient_address_building']) ? $parameters['recipient_address_building'] : null) .
            (isset($parameters['recipient_address_apartment']) ? $parameters['recipient_address_apartment'] : null) .
            (isset($parameters['recipient_address_postcode']) ? $parameters['recipient_address_postcode'] : null) .
            (isset($parameters['recipient_address_city']) ? $parameters['recipient_address_city'] : null) .
            (isset($parameters['application']) ? $parameters['application'] : null) .
            (isset($parameters['application_version']) ? $parameters['application_version'] : null) .
            (isset($parameters['warranty']) ? $parameters['warranty'] : null) .
            (isset($parameters['bylaw']) ? $parameters['bylaw'] : null) .
            (isset($parameters['personal_data']) ? $parameters['personal_data'] : null) .
            (isset($parameters['credit_card_number']) ? $parameters['credit_card_number'] : null) .
            (isset($parameters['credit_card_expiration_date_year']) ? $parameters['credit_card_expiration_date_year'] : null) .
            (isset($parameters['credit_card_expiration_date_month']) ? $parameters['credit_card_expiration_date_month'] : null) .
            (isset($parameters['credit_card_security_code']) ? $parameters['credit_card_security_code'] : null) .
            (isset($parameters['credit_card_store']) ? $parameters['credit_card_store'] : null) .
            (isset($parameters['credit_card_store_security_code']) ? $parameters['credit_card_store_security_code'] : null) .
            (isset($parameters['credit_card_customer_id']) ? $parameters['credit_card_customer_id'] : null) .
            (isset($parameters['credit_card_id']) ? $parameters['credit_card_id'] : null) .
            (isset($parameters['blik_code']) ? $parameters['blik_code'] : null) .
            (isset($parameters['credit_card_registration']) ? $parameters['credit_card_registration'] : null) .
            (isset($parameters['surcharge_amount']) ? $parameters['surcharge_amount'] : null) .
            (isset($parameters['surcharge']) ? $parameters['surcharge'] : null) .
            (isset($parameters['surcharge']) ? $parameters['surcharge'] : null) .
            (isset($parameters['ignore_last_payment_channel']) ? $parameters['ignore_last_payment_channel'] : null) .
            (isset($parameters['vco_call_id']) ? $parameters['vco_call_id'] : null) .
            (isset($parameters['vco_update_order_info']) ? $parameters['vco_update_order_info'] : null) .
            (isset($parameters['vco_subtotal']) ? $parameters['vco_subtotal'] : null) .
            (isset($parameters['vco_shipping_handling']) ? $parameters['vco_shipping_handling'] : null) .
            (isset($parameters['vco_tax']) ? $parameters['vco_tax'] : null) .
            (isset($parameters['vco_discount']) ? $parameters['vco_discount'] : null) .
            (isset($parameters['vco_gift_wrap']) ? $parameters['vco_gift_wrap'] : null) .
            (isset($parameters['vco_misc']) ? $parameters['vco_misc'] : null) .
            (isset($parameters['vco_promo_code']) ? $parameters['vco_promo_code'] : null) .
            (isset($parameters['credit_card_security_code_required']) ? $parameters['credit_card_security_code_required'] : null) .
            (isset($parameters['credit_card_operation_type']) ? $parameters['credit_card_operation_type'] : null) .
            (isset($parameters['credit_card_avs']) ? $parameters['credit_card_avs'] : null) .
            (isset($parameters['credit_card_threeds']) ? $parameters['credit_card_threeds'] : null) .
            (isset($parameters['customer']) ? $parameters['customer'] : null) .
            (isset($parameters['gp_token']) ? $parameters['gp_token'] : null) .
            (isset($parameters['blik_refusenopayid']) ? $parameters['blik_refusenopayid'] : null) .
            (isset($parameters['auto_reject_date']) ? $parameters['auto_reject_date'] : null) .
            (isset($parameters['ap_token']) ? $parameters['ap_token'] : null);

        return hash('sha256', $chk);
    }

    /**
     * Generate URL & send data to Dotpay
     *
     * @param array $feed
     * @param array $submission_data
     * @param array $form
     * @param array $entry
     *
     * @return string
     */
    public function redirect_url($feed, $submission_data, $form, $entry)
    {
        // Getting URL (Production or Sandbox)
        $url = $feed['meta']['mode'] == 'production' ? $this->production_url : $this->sandbox_url;
        $url .= '?';

	    // updating lead's payment_status to "Processing"
	    GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');

        $ids_query = self::encode_ids_query($form, $entry);

        // Parameters for URL
        $parameters = [
            'id' => $feed['meta']['dotpayID'],
            'pin' => $feed['meta']['dotpayPIN'],
            'amount' => $submission_data['payment_amount'],
            'currency' => 'PLN',
            'description' => $feed['meta']['feedName'],
            'url' => get_bloginfo('url') . '/?gf_dotpay_return=' . $ids_query,
            'urlc' => get_bloginfo('url') . '/?gf_dotpay_callback=' . $ids_query,
            'type' => 0,
	        'email' => rgar($entry, $feed['meta']['billingInformation_email']),
        ];

        // Generate Dotpay URL
        $chk = $this->generate_chk($parameters);
        unset($parameters['pin']);
        $parameters['chk'] = $chk;
        $url .= http_build_query($parameters);

        return $url;
    }

    /**
     * Display (or redirect to) "Thank You Page" after payment
     */
    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();

        if (!$instance->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_dotpay_return')) {
            if ($data = self::decode_ids_query($str)) {
                $form = GFAPI::get_form($data['form']);
                $lead = GFAPI::get_entry($data['entry']);

                if (!class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }

                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }

                GFFormDisplay::$submission[$data['form']] = ['is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead];
            }
        }
    }

    //------- PROCESSING DOTPAY IPN (Callback) -----------//

    /**
     * Checks if the URL contains the appropriate variable
     *
     * @return bool
     */
    public function is_callback_valid()
    {
        if (rgget('gf_dotpay_callback')) {
            return true;
        }

        return false;
    }


    /**
     * Perform the appropriate operations after receiving POST from Dotpay
     *
     * @return array|bool|void|WP_Error
     */
    public function callback()
    {
        if (!$this->is_gravityforms_supported()) {
            return false;
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
            return false;
        }

        $this->log_debug(__METHOD__ . '(): IPN request received. Starting to process => ' . print_r($_POST, true));

        // getting entry related to this IPN
        $data = self::decode_ids_query(rgget('gf_dotpay_callback'));
        $this->log_debug(__METHOD__ . '(): Data: ' . print_r($data, true));
        $entry = $this->get_entry($data['entry']);

        // ignore orphan IPN messages (ones without an entry)
        if (!$entry) {
            $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');

            return false;
        }

        $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));

        // ignore SPAM
        if ($entry['status'] == 'spam') {
            $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');

            return false;
        }

        // getting feed related to this IPN
        $feed = $this->get_payment_feed($entry);

        // send request to Dotpay and verify it has not been spoofed
        $is_verified = $this->verify_dotpay_ipn($feed['meta']['dotpayPIN']);
        if (is_wp_error($is_verified)) {
            $this->log_error(__METHOD__ . '(): IPN verification failed with an error. Aborting with a 500 error so that IPN is resent.');

            return new WP_Error('IPNVerificationError', 'There was an error when verifying the IPN message with Dotpay', array('status_header' => 500));
        } elseif (!$is_verified) {
            $this->log_error(__METHOD__ . '(): IPN request could not be verified by Dotpay. Aborting.');

            return false;
        }

        $this->log_debug(__METHOD__ . '(): IPN message successfully verified by Dotpay');

        // ignore IPN messages from forms that are no longer configured with the Dotpay add-on
        if (!$feed || !rgar($feed, 'is_active')) {
            $this->log_error(__METHOD__ . "(): Form no longer is configured with Dotpay Addon. Form ID: {$entry['form_id']}. Aborting.");

            return false;
        }
        $this->log_debug(__METHOD__ . "(): Form {$entry['form_id']} is properly configured.");

        // making sure this IPN can be processed
        if (!$this->can_process_ipn($feed, $entry)) {
            $this->log_debug(__METHOD__ . '(): IPN cannot be processed.');

            return false;
        }

        // processing IPN
        $this->log_debug(__METHOD__ . '(): Processing IPN...');
        $action = $this->process_ipn($feed, $entry, rgpost('operation_status'), rgpost('operation_type'), rgpost('operation_number'), rgpost('operation_amount'));
        $this->log_debug(__METHOD__ . '(): IPN processing complete.');

        if (rgempty('entry_id', $action)) {
            return false;
        }

        return $action;
    }

    /**
     * Returns the appropriate feed
     *
     * @param array $entry
     * @param bool $form
     * @return array|bool
     */
    public function get_payment_feed($entry, $form = false)
    {
        $feed = parent::get_payment_feed($entry, $form);

        if (empty($feed) && !empty($entry['id'])) {
            //looking for feed created by legacy versions
            $feed = $this->get_dotpay_feed_by_entry($entry['id']);
        }

        $feed = apply_filters('gform_dotpay_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form($entry['form_id']));

        return $feed;
    }


    /**
     * Selects the appropriate feed according to entry ID
     *
     * @param $entry_id
     * @return bool
     */
    private function get_dotpay_feed_by_entry($entry_id)
    {
        $feed_id = gform_get_meta($entry_id, 'dotpay_feed_id');
        $feed = $this->get_feed($feed_id);

        return !empty($feed) ? $feed : false;
    }

    /**
     * Handle post processing of the callback
     *
     * @param $callback_action
     * @param $callback_result
     * @return bool|void
     */
    public function post_callback($callback_action, $callback_result)
    {
        if (is_wp_error($callback_action) || !$callback_action) {
            return false;
        }

        //run the necessary hooks
        $entry = GFAPI::get_entry($callback_action['entry_id']);
        $feed = $this->get_payment_feed($entry);
        $transaction_id = rgar($callback_action, 'transaction_id');
        $amount = rgar($callback_action, 'amount');
        $status = rgpost('operation_status');
        $txn_type = rgpost('operation_type');

        //run gform_dotpay_fulfillment only in certain conditions
        if (rgar($callback_action, 'ready_to_fulfill') && !rgar($callback_action, 'abort_callback')) {
            $this->fulfill_order($entry, $transaction_id, $amount, $feed);
        } else {
            if (rgar($callback_action, 'abort_callback')) {
                $this->log_debug(__METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.');
            } else {
                $this->log_debug(__METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_dotpay_fulfillment hook.');
            }
        }

        do_action('gform_post_dotpay_status', $feed, $entry, $status, $transaction_id, $amount);
        if (has_filter('gform_post_payment_status')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_post_payment_status.');
        }

        do_action('gform_dotpay_ipn_' . $txn_type, $entry, $feed, $status, $txn_type, $transaction_id, $amount);
        if (has_filter('gform_dotpay_ipn_' . $txn_type)) {
            $this->log_debug(__METHOD__ . "(): Executing functions hooked to gform_dotpay_ipn_{$txn_type}.");
        }

        do_action('gform_dotpay_post_ipn', $_POST, $entry, $feed, false);
        if (has_filter('gform_dotpay_post_ipn')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_dotpay_post_ipn.');
        }
    }

    /**
     * Checks if the signature returned by Dotpay is correct
     *
     * @param $pin
     * @return bool
     */
    private function verify_dotpay_ipn($pin)
    {
        $this->log_debug(__METHOD__ . "(): Checking IPN request with Dotpay validation.");

        $sign =
            $pin .
            $_POST['id'] .
            $_POST['operation_number'] .
            $_POST['operation_type'] .
            $_POST['operation_status'] .
            $_POST['operation_amount'] .
            $_POST['operation_currency'] .
            $_POST['operation_withdrawal_amount'] .
            $_POST['operation_commission_amount'] .
            $_POST['is_completed'] .
            $_POST['operation_original_amount'] .
            $_POST['operation_original_currency'] .
            $_POST['operation_datetime'] .
            $_POST['operation_related_number'] .
            $_POST['control'] .
            $_POST['description'] .
            $_POST['email'] .
            $_POST['p_info'] .
            $_POST['p_email'] .
            $_POST['credit_card_issuer_identification_number'] .
            $_POST['credit_card_masked_number'] .
            $_POST['credit_card_expiration_year'] .
            $_POST['credit_card_expiration_month'] .
            $_POST['credit_card_brand_codename'] .
            $_POST['credit_card_brand_code'] .
            $_POST['credit_card_unique_identifier'] .
            $_POST['credit_card_id'] .
            $_POST['channel'] .
            $_POST['channel_country'] .
            $_POST['geoip_country'];

        $signature = hash('sha256', $sign);

        return $signature == $_POST['signature'];
    }

    /**
     * Checks the status of payments and returns the appropriate data
     *
     * @param $feed
     * @param $entry
     * @param $status
     * @param $transaction_type
     * @param $transaction_id
     * @param $amount
     * @return array
     */
    private function process_ipn($feed, $entry, $status, $transaction_type, $transaction_id, $amount)
    {
        $this->log_debug(__METHOD__ . "(): Payment status: {$status} - Transaction Type: {$transaction_type} - Transaction ID: {$transaction_id} - Amount: {$amount}");

        $action = [];
        switch (strtolower($status)) {
            case 'completed':
                $action['id'] = $transaction_id . '_' . $status;
                $action['type'] = 'complete_payment';
                $action['transaction_id'] = $transaction_id;
                $action['amount'] = $amount;
                $action['entry_id'] = $entry['id'];
                $action['payment_date'] = gmdate('y-m-d H:i:s');
                $action['payment_method'] = 'Dotpay';
                $action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;
                break;

            case 'processing':
            case 'processing_realization_waiting':
            case 'processing_realization':
                $action['id'] = $transaction_id . '_' . $status;
                $action['type'] = 'pending_payment';
                $action['transaction_id'] = $transaction_id;
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                $action['entry_id'] = $entry['id'];
                $amount_formatted = GFCommon::to_money($action['amount'], $entry['currency']);
                $action['note'] = sprintf(__('Payment is pending. Amount: %s. Transaction ID: %s.', 'gravityformsdotpay'), $amount_formatted, $action['transaction_id']);
                break;

            case 'rejected':
                $action['id'] = $transaction_id . '_' . $status;
                $action['type'] = 'fail_payment';
                $action['transaction_id'] = $transaction_id;
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                break;
        }

        return $action;
    }

    /**
     * Gets the entry with the given ID
     *
     * @param $entry_id
     * @return array|bool|WP_Error
     */
    public function get_entry($entry_id)
    {
        $this->log_debug(__METHOD__ . "(): IPN message has a valid Entry ID: {$entry_id}");

        $entry = GFAPI::get_entry($entry_id);

        if (is_wp_error($entry)) {
            $this->log_error(__METHOD__ . '(): ' . $entry->get_error_message());

            return false;
        }

        return $entry;
    }

    /**
     * Pre IPN processing filter. Allows users to cancel IPN processing
     *
     * @param $feed
     * @param $entry
     * @return bool
     */
    public function can_process_ipn($feed, $entry)
    {
        $cancel = apply_filters('gform_dotpay_pre_ipn', false, $_POST, $entry, $feed);

        if ($cancel) {
            $this->log_debug(__METHOD__ . '(): IPN processing cancelled by the gform_dotpay_pre_ipn filter. Aborting.');
            do_action('gform_dotpay_post_ipn', $_POST, $entry, $feed, true);

            if (has_filter('gform_dotpay_post_ipn')) {
                $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_dotpay_post_ipn.');
            }

            return false;
        }

        return true;
    }

    /**
     * Encodes URL variables
     *
     * @param $form
     * @param $entry
     * @return string
     */
    private static function encode_ids_query($form, $entry)
    {
        $query = "ids={$form['id']}|{$entry['id']}";
        $query .= '&hash=' . wp_hash($query);
        return base64_encode($query);
    }

    /**
     * Decodes URL variables
     *
     * @param $string
     * @return array|bool
     */
    private static function decode_ids_query($string)
    {
        $string = base64_decode($string);

        parse_str($string, $query);
        if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
            list($result['form'], $result['entry']) = explode('|', $query['ids']);
            return $result;
        }

        return false;
    }

    //------- ADMIN FUNCTIONS & HOOKS -----------//

    /**
     * Add actions to allow the payment status to be modified
     */
    public function init_admin()
    {
        parent::init_admin();

        add_action('gform_payment_status', array($this, 'admin_edit_payment_status'), 3, 3);
        add_action('gform_payment_date', array($this, 'admin_edit_payment_date'), 3, 3);
        add_action('gform_payment_transaction_id', array($this, 'admin_edit_payment_transaction_id'), 3, 3);
        add_action('gform_payment_amount', array($this, 'admin_edit_payment_amount'), 3, 3);
        add_action('gform_after_update_entry', array($this, 'admin_update_payment'), 4, 2);
    }

    /**
     * Generates a dropdown to change the payment status
     *
     * @param $payment_status
     * @param $form
     * @param $entry
     * @return string
     */
    public function admin_edit_payment_status($payment_status, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_status;
        }

        // create drop down for payment status
        $payment_string = gform_tooltip('dotpay_edit_payment_status', '', true);
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">' . __('Paid', 'gravityformsdotpay') . '</option>';
        $payment_string .= '</select>';

        return $payment_string;
    }

    /**
     * Generates a field for changing the payment date
     *
     * @param $payment_date
     * @param $form
     * @param $entry
     * @return string
     */
    public function admin_edit_payment_date($payment_date, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_date;
        }

        $payment_date = $entry['payment_date'];
        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    /**
     * Generates a field for changing the payment ID
     *
     * @param $transaction_id
     * @param $form
     * @param $entry
     * @return string
     */
    public function admin_edit_payment_transaction_id($transaction_id, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $transaction_id;
        }

        $input = '<input type="text" id="dotpay_transaction_id" name="dotpay_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    /**
     * Generates field to change the amount of payment
     *
     * @param $payment_amount
     * @param $form
     * @param $entry
     * @return string
     */
    public function admin_edit_payment_amount($payment_amount, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_amount;
        }

        if (empty($payment_amount)) {
            $payment_amount = GFCommon::get_order_total($form, $entry);
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }

    /**
     * Update payment information in admin,
     *
     * @param $form
     * @param $entry_id
     */
    public function admin_update_payment($form, $entry_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        // need to use this function so the lead data is updated before displayed in the sidebar info section
        $entry = GFFormsModel::get_lead($entry_id);

        if ($this->payment_details_editing_disabled($entry, 'update')) {
            return;
        }

        // get payment fields to update
        $payment_status = rgpost('payment_status');
        // when updating, payment status may not be editable, if no value in post, set to lead payment status
        if (empty($payment_status)) {
            $payment_status = $entry['payment_status'];
        }

        $payment_amount = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('dotpay_transaction_id');
        $payment_date = rgpost('payment_date');

        $status_unchanged = $entry['payment_status'] == $payment_status;
        $amount_unchanged = $entry['payment_amount'] == $payment_amount;
        $id_unchanged = $entry['transaction_id'] == $payment_transaction;
        $date_unchanged = $entry['payment_date'] == $payment_date;

        if ($status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged) {
            return;
        }

        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        } else {
            // format date entered by user
            $payment_date = date('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id = 0;
        $user_name = 'System';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry['payment_status'] = $payment_status;
        $entry['payment_amount'] = $payment_amount;
        $entry['payment_date'] = $payment_date;
        $entry['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if (($payment_status == 'Approved' || $payment_status == 'Paid') && !$entry['is_fulfilled']) {
            $action['id'] = $payment_transaction;
            $action['type'] = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount'] = $payment_amount;
            $action['entry_id'] = $entry['id'];

            $this->complete_payment($entry, $action);
            $this->fulfill_order($entry, $payment_transaction, $payment_amount);
        }
        // update lead, add a note
        GFAPI::update_entry($entry);
        GFFormsModel::add_note($entry['id'], $user_id, $user_name, sprintf(esc_html__('Payment information was manually updated. Status: %s. Amount: %s. Transaction ID: %s. Date: %s', 'gravityformsdotpay'), $entry['payment_status'], GFCommon::to_money($entry['payment_amount'], $entry['currency']), $payment_transaction, $entry['payment_date']));
    }

    /**
     * Add supported notification events.
     *
     * @param array $form The form currently being processed.
     *
     * @return array
     */
    public function supported_notification_events($form)
    {
        if (!$this->has_feed($form['id'])) {
            return false;
        }

        return [
            'complete_payment' => esc_html__('Payment Completed', 'gravityformsdotpay'),
            'fail_payment' => esc_html__('Payment Failed', 'gravityformsdotpay'),
            'pending_payment' => esc_html__('Payment Pending', 'gravityformsdotpay'),
        ];
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {
        if (!$feed) {
            $feed = $this->get_payment_feed($entry);
        }

        $form = GFFormsModel::get_form_meta($entry['form_id']);
        if (rgars($feed, 'meta/delayPost')) {
            $this->log_debug(__METHOD__ . '(): Creating post.');
            $entry['post_id'] = GFFormsModel::create_post($form, $entry);
            $this->log_debug(__METHOD__ . '(): Post created.');
        }

        if (rgars($feed, 'meta/delayNotification')) {
            // sending delayed notifications
            $notifications = $this->get_notifications_to_send($form, $feed);
            GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
        }

        do_action('gform_dotpay_fulfillment', $entry, $feed, $transaction_id, $amount);
        if (has_filter('gform_dotpay_fulfillment')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_dotpay_fulfillment.');
        }
    }

    public function dotpay_fulfillment($entry, $dotpay_config, $transaction_id, $amount)
    {
        //no need to do anything for Dotpay when it runs this function, ignore
        return false;
    }

    /**
     * Retrieve the IDs of the notifications to be sent.
     *
     * @param array $form The form which created the entry being processed.
     * @param array $feed The feed which processed the entry.
     *
     * @return array
     */
    public function get_notifications_to_send($form, $feed)
    {
        $notifications_to_send = array();
        $selected_notifications = rgars($feed, 'meta/selectedNotifications');

        if (is_array($selected_notifications)) {
            // make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
            foreach ($form['notifications'] as $notification) {
                if (rgar($notification, 'event') != 'form_submission' || !in_array($notification['id'], $selected_notifications)) {
                    continue;
                }

                $notifications_to_send[] = $notification['id'];
            }
        }

        return $notifications_to_send;
    }

    /**
     * Editing of the payment details should only be possible if the entry was processed by Dotpay,
     * if the payment status is Pending or Processing, and the transaction was not a subscription.
     *
     * @param array $entry The current entry
     * @param string $action The entry detail page action, edit or update.
     *
     * @return bool
     */
    public function payment_details_editing_disabled($entry, $action = 'edit')
    {
        if (!$this->is_payment_gateway($entry['id'])) {
            // entry was not processed by this add-on, don't allow editing.
            return true;
        }

        $payment_status = rgar($entry, 'payment_status');
        if ($payment_status == 'Approved' || $payment_status == 'Paid' || rgar($entry, 'transaction_type') == 2) {
            // editing not allowed for this entries transaction type or payment status.
            return true;
        }

        if ($action == 'edit' && rgpost('screen_mode') == 'edit') {
            // editing is allowed for this entry.
            return false;
        }

        if ($action == 'update' && rgpost('screen_mode') == 'view' && rgpost('action') == 'update') {
            // updating the payment details for this entry is allowed.
            return false;
        }

        // in all other cases editing is not allowed.
        return true;
    }
}