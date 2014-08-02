<?php
/*
Plugin Name: PayMaster Payment Gateway
Plugin URI:
Description: Allows you to use PayMaster payment gateway with the WooCommerce plugin.
Version: 1.0.0
Author: Andry Chuyko
Author URI: http://proficomp.ru
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Add roubles in currencies
 *
 * @since 0.3
 */

function paymaster_rub_currency_symbol($currency_symbol, $currency)
	{
	if ($currency == "RUB")
		{
		$currency_symbol = 'р.';
		}

	return $currency_symbol;
	}

function paymaster_rub_currency($currencies)
	{
	$currencies["RUB"] = 'Russian Roubles';
	return $currencies;
	}

add_filter('woocommerce_currency_symbol', 'paymaster_rub_currency_symbol', 10, 2);
add_filter('woocommerce_currencies', 'paymaster_rub_currency', 10, 1);
/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_paymaster', 0);

function woocommerce_paymaster()
	{
	if (!class_exists('WC_Payment_Gateway')) return; // if the WC payment gateway class is not available, do nothing
	if (class_exists('WC_PayMaster')) return;
	class WC_PayMaster extends WC_Payment_Gateway

		{
		public

		function __construct()
			{
			$plugin_dir = plugin_dir_url(__FILE__);
			global $woocommerce;
			$this->id = 'paymaster';
			$this->icon = apply_filters('woocommerce_paymaster_icon', '' . $plugin_dir . 'paymaster.png');
			$this->has_fields = false;
			$this->liveurl = ' https://paymaster.ru/Payment/Init';
			$this->testurl = ' https://paymaster.ru/Payment/Init';

			// Load the settings

			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables

			$this->title = $this->get_option('title');
			$this->paymaster_merchant = $this->get_option('paymaster_merchant');
			$this->paymaster_key = $this->get_option('paymaster_key');
			$this->testmode = $this->get_option('testmode');
			$this->debug = $this->get_option('debug');
			$this->description = $this->get_option('description');
			$this->instructions = $this->get_option('instructions');

			// Logs

			if ($this->debug == 'yes')
				{
				$this->log = $woocommerce->logger();
				}

			// Actions

			add_action('valid-paymaster-standard-ipn-request', array(
				$this,
				'successful_request'
			));
			add_action('woocommerce_receipt_' . $this->id, array(
				$this,
				'receipt_page'
			));

			// Save options

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			));

			// Payment listener/API hook

			add_action('woocommerce_api_wc_' . $this->id, array(
				$this,
				'check_ipn_response'
			));
			if (!$this->is_valid_for_use())
				{
				$this->enabled = false;
				}
			}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_valid_for_use()
			{
			if (!in_array(get_option('woocommerce_currency') , array(
				'RUB'
			)))
				{
				return false;
				}

			return true;
			}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 0.1
		 *
		 */
		public

		function admin_options()
			{
?>
		<h3><?php
			_e('PayMaster', 'woocommerce'); ?></h3>
		<p><?php
			_e('Настройка приема электронных платежей через Merchant PayMaster.', 'woocommerce'); ?></p>

	  <?php
			if ($this->is_valid_for_use()): ?>

		<table class="form-table">

		<?php

				// Generate the HTML For the settings form.

				$this->generate_settings_html();
?>
    </table><!--/.form-table-->
    		
    <?php
			else: ?>
		<div class="inline error"><p><strong><?php
				_e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php
				_e('PayMaster не поддерживает валюты Вашего магазина.', 'woocommerce'); ?></p></div>
		<?php
			endif;
			} // End admin_options()
		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields()
			{
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Включить/Выключить', 'woocommerce') ,
					'type' => 'checkbox',
					'label' => __('Включен', 'woocommerce') ,
					'default' => 'yes'
				) ,
				'title' => array(
					'title' => __('Название', 'woocommerce') ,
					'type' => 'text',
					'description' => __('Это название, которое пользователь видит во время проверки.', 'woocommerce') ,
					'default' => __('PayMaster', 'woocommerce')
				) ,
				'paymaster_merchant' => array(
					'title' => __('Идентификатор', 'woocommerce') ,
					'type' => 'text',
					'description' => __('Он находится в личном кабинете в настройках сайта', 'woocommerce') ,
					'default' => 'demo'
				) ,
				'paymaster_key' => array(
					'title' => __('Секретный ключ', 'woocommerce') ,
					'type' => 'password',
					'description' => __('Пожалуйста введите секретный ключ', 'woocommerce') ,
					'default' => ''
				) ,
				'testmode' => array(
					'title' => __('Тест режим', 'woocommerce') ,
					'type' => 'checkbox',
					'label' => __('Включен', 'woocommerce') ,
					'description' => __('В этом режиме плата за товар не снимается.', 'woocommerce') ,
					'default' => 'no'
				) ,
				'debug' => array(
					'title' => __('Debug', 'woocommerce') ,
					'type' => 'checkbox',
					'label' => __('Включить логирование (<code>woocommerce/logs/paypal.txt</code>)', 'woocommerce') ,
					'default' => 'no'
				) ,
				'description' => array(
					'title' => __('Description', 'woocommerce') ,
					'type' => 'textarea',
					'description' => __('Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce') ,
					'default' => 'Оплата с помощью paymaster.'
				) ,
				'instructions' => array(
					'title' => __('Instructions', 'woocommerce') ,
					'type' => 'textarea',
					'description' => __('Инструкции, которые будут добавлены на страницу благодарностей.', 'woocommerce') ,
					'default' => 'Оплата с помощью paymaster.'
				)
			);
			}

		/**
		 * There are no payment fields for sprypay, but we want to show the description if set.
		 *
		 */
		function payment_fields()
			{
			if ($this->description)
				{
				echo wpautop(wptexturize($this->description));
				}
			}

		/**
		 * Generate the dibs button link
		 *
		 */
		public

		function generate_form($order_id)
			{
			global $woocommerce;
			$order = new WC_Order($order_id);
			if ($this->testmode == 'yes')
				{
				$action_adr = $this->testurl;
				}
			  else
				{
				$action_adr = $this->liveurl;
				}

			$out_summ = number_format($order->order_total, 2, '.', '');
			$args = array(

				// Merchant

				'LMI_MERCHANT_ID' => $this->paymaster_merchant,
				'LMI_PAYMENT_AMOUNT' => $out_summ,
				'LMI_CURRENCY' => 'RUB',
				'LMI_PAYMENT_NO' => $order_id,
				'LMI_PAYMENT_DESC' => 'Оплата заказа '.$order_id,
			);
			$paypal_args = apply_filters('woocommerce_paymaster_args', $args);
			$args_array = array();
			foreach($args as $key => $value)
				{
				$args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
				}

			return '<form action="' . esc_url($action_adr) . '" method="POST" id="paymaster_payment_form">' . "\n" . implode("\n", $args_array) . '<input type="submit" class="button" id="submit_paymaster_payment_form" value="' . __('Оплатить', 'woocommerce') . '" /> <a href="' . $order->get_cancel_order_url() . '">' . __('Отказаться от оплаты и вернуться в корзину', 'woocommerce') . '</a>' . "\n" . '</form>';
			}

		/**
		 * Process the payment and return the result
		 *
		 */
		function process_payment($order_id)
			{
			$order = new WC_Order($order_id);
			return array(
				'result' => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
			}

		/**
		 * receipt_page
		 *
		 */
		function receipt_page($order)
			{
			echo '<p>' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы оплатить.', 'woocommerce') . '</p>';
			echo $this->generate_form($order);
			}

		/**
		 * Check PayMaster IPN validity
		 *
		 */
		function check_ipn_request_is_valid($posted)
			{
			$LMI_MERCHANT_ID = $posted['LMI_MERCHANT_ID'];
			$LMI_PAYMENT_NO = $posted['LMI_PAYMENT_NO'];
			$LMI_SYS_PAYMENT_ID = $posted['LMI_SYS_PAYMENT_ID'];
			$LMI_SYS_PAYMENT_DATE = $posted['LMI_SYS_PAYMENT_DATE'];
			$LMI_PAYMENT_AMOUNT = $posted['LMI_PAYMENT_AMOUNT'];
			$LMI_CURRENCY = $posted['LMI_CURRENCY'];
			$LMI_PAID_AMOUNT = $posted['LMI_PAID_AMOUNT'];
			$LMI_PAID_CURRENCY = $posted['LMI_PAID_CURRENCY'];
			$LMI_PAYMENT_SYSTEM = $posted['LMI_PAYMENT_SYSTEM'];
			$LMI_SIM_MODE = $posted['LMI_SIM_MODE'];
			$str = $LMI_MERCHANT_ID.";".$LMI_PAYMENT_NO.";".$LMI_SYS_PAYMENT_ID.";".$LMI_SYS_PAYMENT_DATE.";".$LMI_PAYMENT_AMOUNT.";".$LMI_CURRENCY.";".$LMI_PAID_AMOUNT.";".$LMI_PAID_CURRENCY.";".$LMI_PAYMENT_SYSTEM.";".$LMI_SIM_MODE.";".$this->paymaster_key;
			if ($posted['LMI_HASH'] ==  base64_encode(md5($str, true)))
				{
				echo 'OK' . $LMI_PAYMENT_NO;
				return true;
				}

			return false;
			}

		/**
		 * Check Response
		 *
		 */
		function check_ipn_response()
			{
			global $woocommerce;
			if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'result')
				{
				@ob_clean();
				$_POST = stripslashes_deep($_POST);
				if ($this->check_ipn_request_is_valid($_POST))
					{
					do_action('valid-paymaster-standard-ipn-request', $_POST);
					}
				  else
					{
					wp_die('IPN Request Failure');
					}
				}
			  else
			if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'success')
				{
				$LMI_PAYMENT_NO = $_POST['LMI_PAYMENT_NO'];
				$order = new WC_Order($LMI_PAYMENT_NO);
				$order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
				WC()->cart->empty_cart();
				wp_redirect($this->get_return_url($order));
				}
			  else
			if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'fail')
				{
				$LMI_PAYMENT_NO = $_POST['LMI_PAYMENT_NO'];
				$order = new WC_Order($LMI_PAYMENT_NO);
				$order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
				wp_redirect($order->get_cancel_order_url());
				exit;
				}
			}

		/**
		 * Successful Payment!
		 *
		 */
		function successful_request($posted)
			{
			global $woocommerce;
			$LMI_PAYMENT_NO = $posted['LMI_PAYMENT_NO'];
			$order = new WC_Order($LMI_PAYMENT_NO);

			// Check order not already completed

			if ($order->status == 'completed')
				{
				exit;
				}

			// Payment completed

			$order->add_order_note(__('Платеж успешно завершен.', 'woocommerce'));
			$order->payment_complete();
			exit;
			}
		}


	/**
	 * Add the gateway to WooCommerce
	 *
	 */
	function add_paymaster_gateway($methods)
		{
		$methods[] = 'WC_PayMaster';
		return $methods;
		}

	add_filter('woocommerce_payment_gateways', 'add_paymaster_gateway');
	}

?>