<?php

/*
  Plugin Name: VivaPayments WooCommerce Payment Gateway
  Plugin URI: http://emspace.gr
  Description: Viva Wallet ( Vivapayments.gr ) Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard, AMex cards, Diners  and Visa cards On your Woocommerce Powered Site.
  Version: 1.0.5
  Author: emspace.gr
  Author URI: http://emspace.gr
  License:           GPL-3.0+
  License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

if (!defined('ABSPATH'))
    exit;

add_action('plugins_loaded', 'woocommerce_vivapay_init', 0);

function woocommerce_vivapay_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

    load_plugin_textdomain('viva-woocommerce-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    /**
     * Gateway class
     */
    class WC_em_Vivapayments_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            global $woocommerce;

            $this->id = 'em_vivapayments_gateway';
            $this->icon = apply_filters('woocommerce_vivaway_icon', plugins_url('assets/pay-via-vivapay.png', __FILE__));
            $this->has_fields = false;
            $this->liveurl = 'https://www.vivapayments.com/api/';
            $this->testurl = 'http://demo.vivapayments.com/api/';
            $this->notify_url = WC()->api_request_url('WC_em_Vivapayments_Gateway');
            $this->method_title = 'VivaPayments  Gateway';
            $this->method_description = __('VivaPay Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard, AMex cards, Diners  and Visa cards On your Woocommerce Powered Site.', 'viva-woocommerce-payment-gateway');

            $this->redirect_page_id = $this->get_option('redirect_page_id');
            // Load the form fields.
            $this->init_form_fields();

            //dhmioyrgia vashs

            global $wpdb;

            if ($wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "viva_payment_transactions'") === $wpdb->prefix . 'viva_payment_transactions') {
                // The database table exist
            } else {
                // Table does not exist
                $query = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'viva_payment_transactions (id int(11) unsigned NOT NULL AUTO_INCREMENT, ref varchar(100) not null, trans_code varchar(255) not null,  orderid varchar(100) not null , timestamp datetime default null, PRIMARY KEY (id))';
                $wpdb->query($query);
            }

            // Load the settings.
            $this->init_settings();


            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->vivaPayMerchantId = $this->get_option('vivaPayMerchantId');
            $this->vivaPayAPIKey = $this->get_option('vivaPayAPIKey');
            $this->vivaPayCodeId = $this->get_option('vivaPayCodeId');
			$this->customerMessage= $this->get_option('customerMessage');
            $this->mode = $this->get_option('mode');			
            $this->allowedInstallments= $this->get_option('installments');
            //Actions
            add_action('woocommerce_receipt_em_vivapayments_gateway', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_em_vivapayments_gateway', array($this, 'check_vivapayments_response'));
        }

        /**
         * Admin Panel Options
         * */
        public function admin_options() {
            echo '<h3>' . __('VivaPay Payment Gateway', 'viva-woocommerce-payment-gateway') . '</h3>';
            echo '<p>' . __('VivaPay Payment Gateway allows you to accept payment through various channels such as Maestro, Mastercard, AMex cards, Diners  and Visa cards.', 'viva-woocommerce-payment-gateway') . '</p>';


            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * Initialise Gateway Settings Form Fields
         * */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'viva-woocommerce-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable VivaPayments Payment Gateway', 'viva-woocommerce-payment-gateway'),
                    'description' => __('Enable or disable the gateway.', 'viva-woocommerce-payment-gateway'),
                    'desc_tip' => true,
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'viva-woocommerce-payment-gateway'),
                    'desc_tip' => false,
                    'default' => __('VivaPayments Payment Gateway', 'viva-woocommerce-payment-gateway')
                ),
                'description' => array(
                    'title' => __('Description', 'viva-woocommerce-payment-gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'viva-woocommerce-payment-gateway'),
                    'default' => __('Pay Via Vivapayments: Accepts  Mastercard, Visa cards and etc.', 'viva-woocommerce-payment-gateway')
                ),
                'vivaPayMerchantId' => array(
                    'title' => __('VivaPayments Merchant ID', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter Your VivaPay Merchant ID, this can be gotten on your account page when you login on VivaPay', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'vivaPayAPIKey' => array(
                    'title' => __('VivaPayments API key', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter Your VivaPay API key, this can be gotten on your account page when you login on VivaPay', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'vivaPayCodeId' => array(
                    'title' => __('VivaPayments CodeId', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter Your VivaPay CodeId ,or use "default" , this can be gotten on your account page when you login on VivaPay', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),'customerMessage' => array(
                    'title' => __('Message to Customer', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter a message that will be shown to the customer at the payment receipt from VivaPayments', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ), 'mode' => array(
                    'title' => __('Mode', 'viva-woocommerce-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Test Mode', 'viva-woocommerce-payment-gateway'),
                    'default' => 'yes',
                    'description' => __('This controls  the payment mode as TEST or LIVE.', 'viva-woocommerce-payment-gateway')
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page', 'viva-woocommerce-payment-gateway'),
                    'type' => 'select',
                    'options' => $this->em_get_pages('Select Page'),
                    'description' => __('URL of success page', 'viva-woocommerce-payment-gateway')
                )                ,
                'installments' => array(
                    'title' => __('Installments', 'viva-woocommerce-payment-gateway'),
                    'type' => 'select',
                    'options' => $this->em_get_installments('Select Installments'),
                    'description' => __('1 to 24 Installments,1 for one time payment ', 'viva-woocommerce-payment-gateway')
                )
            );
        }

        function em_get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
			$page_list[-1] = __('Thank you page', 'viva-woocommerce-payment-gateway');
            return $page_list;
        }
        
        function em_get_installments($title = false, $indent = true) {          
           
          
            for($i = 1; $i<=24;$i++) {              
                $installment_list[$i] = $i;
            }
            return $installment_list;
        }

        /**
         * Generate the VivaPay Payment button link
         * */
        function generate_vivapayments_form($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);



            //select demo or live
            if ($this->mode == "yes") {
                $requesturl = 'http://demo.vivapayments.com';// demo environment URL
            } else {
                $requesturl = 'https://www.vivapayments.com'; 
            }

            $request = $requesturl . '/api/orders';

            $MerchantId = $this->vivaPayMerchantId;
            $APIKey = $this->vivaPayAPIKey;
            $srccode = $this->vivaPayCodeId;
            $installments= $this->allowedInstallments;
//Set the Payment Amount
            $Amount = $order->get_total() * 100; // Amount in cents
//Set some optional parameters (Full list available here: https://github.com/VivaPayments/API/wiki/Optional-Parameters)
            $AllowRecurring = 'false'; // This flag will prompt the customer to accept recurring payments in tbe future.
            $RequestLang = 'el-GR'; //This will display the payment page in English (default language is Greek)
			$MerchantTrns ='Vivapayments '.$order_id;
			$CustomerTrns = $this->customerMessage;
            $postargs = 'Amount=' . urlencode($Amount) . '&AllowRecurring=' . $AllowRecurring . '&RequestLang=' . $RequestLang . '&SourceCode=' . $srccode . '&DisableIVR=true&MaxInstallments='.$installments.'&MerchantTrns='.$MerchantTrns.'&CustomerTrns='.$CustomerTrns;
// Get the curl session object
            $session = curl_init($request);
           // echo $postargs;
// Set the POST options.
            curl_setopt($session, CURLOPT_POST, true);
            curl_setopt($session, CURLOPT_POSTFIELDS, $postargs);
            curl_setopt($session, CURLOPT_HEADER, false);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($session, CURLOPT_USERPWD, htmlspecialchars_decode($MerchantId).':'.htmlspecialchars_decode($APIKey));
// Do the POST and then close the session
            $response = curl_exec($session);
            curl_close($session);
// Parse the JSON response


            try {
                if (is_object(json_decode($response))) {
                    $resultObj = json_decode($response);
                } else {
                    return __("Wrong Merchant crendentials or API unavailable", 'viva-woocommerce-payment-gateway');
                }
            } catch (Exception $e) {
              //  echo $e->getMessage();
            }


            global $wpdb;
            if ($resultObj->ErrorCode == 0) { //success when ErrorCode = 0
                $transId = (String)$resultObj->OrderCode;

                if (!is_null($transId)) {

                    $wpdb->insert($wpdb->prefix . 'viva_payment_transactions', array('trans_code' => $transId, 'orderid' => $order_id, 'timestamp' => current_time('mysql', 1)));


                    wc_enqueue_js('
				$.blockUI({
						message: "' . esc_js(__('Thank you for your order. We are now redirecting you to Vivapayments to make payment.', 'viva-woocommerce-payment-gateway')) . '",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"24px",
						}
					});
				jQuery("#submit_vivapayments_payment_form").click();
			');
                    return '<form action="' . $requesturl . '/web/checkout?ref=' . $transId . '" method="post" id="vivapayments_payment_form" target="_top">
				
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_vivapayments_payment_form" value="' . __('Pay via Vivapay', 'viva-woocommerce-payment-gateway') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'viva-woocommerce-payment-gateway') . '</a>
					</div>
					<script type="text/javascript">
						jQuery(".payment_buttons").hide();
					</script>
				</form>';
                } else {
                    return __('Wrong Merchant Credentials or SourceID', 'viva-woocommerce-payment-gateway');
                }
            } else {

                return __('The following error occured: ', 'viva-woocommerce-payment-gateway') . $resultObj->ErrorText;
            }
        }

        /**
         * Process the payment and return the result
         * */
        /**/
        function process_payment($order_id) {

            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Output for the order received page.
         * */
        function receipt_page($order) {
            echo '<p>' . __('Thank you - your order is now pending payment. You should be automatically redirected to Vivapayments to make payment.', 'viva-woocommerce-payment-gateway') . '</p>';
            echo $this->generate_vivapayments_form($order);
        }

        /**
         * Verify a successful Payment!
         * */
        function check_vivapayments_response() {

            global $woocommerce;
            global $wpdb;



            if (isset($_POST['df'])) {

                /*
                 * Just an empty isset Don't know why its needed
                 */
            } else {

                
                $trans_id = $_GET['t'];
                $vivaid = $_GET['s'];

                if ($this->mode == "yes") {
                    $requesturl = 'http://demo.vivapayments.com/api';
                } else {
                    $requesturl = 'https://www.vivapayments.com/api';
                }


                $postargs = 'ordercode=' . $vivaid;
                $request = $requesturl . '/transactions?' . $postargs;

                $MerchantId = $this->vivaPayMerchantId;
                $APIKey = $this->vivaPayAPIKey;
                // Get the curl session object
                $session = curl_init($request);
                curl_setopt($session, CURLOPT_HEADER, false);
                curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($session, CURLOPT_USERPWD, htmlspecialchars_decode($MerchantId).':'.htmlspecialchars_decode($APIKey));
                // Do the POST and then close the session
                $response = curl_exec($session);
                curl_close($session);



                try {
                    if (is_object(json_decode($response))) {
                        $resultObj = json_decode($response);
                    } else {
                        return __("Wrong Merchant crendentials or API unavailable", 'viva-woocommerce-payment-gateway');
                    }
                } catch (Exception $e) {
                   // echo $e->getMessage();
                }




                if ($resultObj->ErrorCode == 0) {
                    $orderquery = "SELECT *
			FROM " . $wpdb->prefix . "viva_payment_transactions
			WHERE `trans_code` = " . $vivaid . "	;";


                    $order = $wpdb->get_results($orderquery);


                    $orderid = $order[0]->orderid;
                    $order = new WC_Order($orderid);

                    $status = $resultObj->Transactions[0]->StatusId;

                    if (isset($status)) {

                        if ($status == "F") {


                            if ($order->status == 'processing') {

                                $order->add_order_note(__('Payment Via Vivapay<br />Transaction ID: ', 'viva-woocommerce-payment-gateway') . $trans_id);

                                //Add customer order note
                                $order->add_order_note(__('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Vivapay Transaction ID: ', 'viva-woocommerce-payment-gateway') . $trans_id, 1);

                                // Reduce stock levels
                                $order->reduce_order_stock();

                                // Empty cart
                                WC()->cart->empty_cart();

                                $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'viva-woocommerce-payment-gateway');
                                $message_type = 'success';
                            } else {

                                if ($order->has_downloadable_item()) {

                                    //Update order status
                                    $order->update_status('completed', __('Payment received, your order is now complete.', 'viva-woocommerce-payment-gateway'));

                                    //Add admin order note
                                    $order->add_order_note(__('Payment Via Vivapay Payment Gateway<br />Transaction ID: ', 'viva-woocommerce-payment-gateway') . $trans_id);

                                    //Add customer order note
                                    $order->add_order_note(__('Payment Received.<br />Your order is now complete.<br />Vivapay Transaction ID: ', 'viva-woocommerce-payment-gateway') . $trans_id, 1);

                                    $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'viva-woocommerce-payment-gateway');
                                    $message_type = 'success';
                                } else {

                                    //Update order status
                                    $order->update_status('processing', __('Payment received, your order is currently being processed.', 'viva-woocommerce-payment-gateway'));

                                    //Add admin order noote
                                    $order->add_order_note(__('Payment Via Vivapay Payment Gateway<br />Transaction ID: ', 'viva-woocommerce-payment-gateway') . $trans_id);

                                    //Add customer order note
                                    $order->add_order_note(__('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Vivapay Transaction ID: ', 'viva-woocommerce-payment-gateway') . $trans_id, 1);

                                    $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'viva-woocommerce-payment-gateway');
                                    $message_type = 'success';
                                }

                                $vivapayments_message = array(
                                    'message' => $message,
                                    'message_type' => $message_type
                                );

                                update_post_meta($order_id, '_em_vivapayments_message', $vivapayments_message);
                                // Reduce stock levels
                                $order->reduce_order_stock();

                                // Empty cart
                                WC()->cart->empty_cart();
                            }
                        } else {


                            $message = __('Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t recieved.', 'viva-woocommerce-payment-gateway');
                            $message_type = 'error';

                            $transaction_id = $transaction['transaction_id'];

                            //Add Customer Order Note
                            $order->add_order_note($message . '<br />Vivapay Transaction ID: ' . $trans_id, 1);

                            //Add Admin Order Note
                            $order->add_order_note($message . '<br />Vivapay Transaction ID: ' . $trans_id);


                            //Update the order status
                            $order->update_status('failed', '');

                            $vivapayments_message = array(
                                'message' => $message,
                                'message_type' => $message_type
                            );

                            update_post_meta($order_id, '_em_vivapayments_message', $vivapayments_message);
                        }
                    }
                }

                if ($this->redirect_page_id=="-1"){				
				$redirect_url = $this->get_return_url( $order );	
				}else	
				{							
                $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']), 'type' => $this->msg['class']), $redirect_url);								
				}
				wp_redirect($redirect_url);
               
                exit;
            }
        }

    }

    function em_vivapayments_message() {
        $order_id = absint(get_query_var('order-received'));
        $order = new WC_Order($order_id);
        $payment_method = $order->payment_method;

        if (is_order_received_page() && ( 'em_vivapayments_gateway' == $payment_method )) {

            $vivapayments_message = get_post_meta($order_id, '_em_vivapayments_message', true);
			 if (!empty($vivapayments_message)) {
            $message = $vivapayments_message['message'];
            $message_type = $vivapayments_message['message_type'];

            delete_post_meta($order_id, '_em_vivapayments_message');

           
                wc_add_notice($message, $message_type);
            }
        }
    }

    add_action('wp', 'em_vivapayments_message');

    /**
     * Add Vivapay Gateway to WC
     * */
    function woocommerce_add_vivapayments_gateway($methods) {
        $methods[] = 'WC_em_Vivapayments_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_vivapayments_gateway');





    /**
     * Add Settings link to the plugin entry in the plugins menu for WC below 2.1
     * */
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

        add_filter('plugin_action_links', 'em_vivapayments_plugin_action_links', 10, 2);

        function em_vivapayments_plugin_action_links($links, $file) {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_em_Vivapayments_Gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
    /**
     * Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
     * */ else {
        add_filter('plugin_action_links', 'em_vivapayments_plugin_action_links', 10, 2);

        function em_vivapayments_plugin_action_links($links, $file) {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_em_vivapayments_gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
}

