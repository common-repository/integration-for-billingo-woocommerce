<?php
/*
Plugin Name: Integration for Billingo & WooCommerce
Plugin URI: http://visztpeter.me
Description: Billingo összeköttetés WooCommercehez
Author: Viszt Péter
Version: 1.1
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//Load billingo API
require_once plugin_dir_path( __FILE__ ) . 'lib/autoload.php';

//Setup Billingo API
use Billingo\API\Connector\HTTP\Request;

class WC_Billingo {

	public static $plugin_prefix;
	public static $plugin_url;
	public static $plugin_path;
	public static $plugin_basename;
	public static $version;
	private $billingo;

    //Construct
    public function __construct() {

			//Default variables
			self::$plugin_prefix = 'wc_billingo_';
			self::$plugin_basename = plugin_basename(__FILE__);
			self::$plugin_url = plugin_dir_url(self::$plugin_basename);
			self::$plugin_path = trailingslashit(dirname(__FILE__));
			self::$version = '1.1';

			//Wordpress and Woocommerce hooks&filter
			add_action( 'admin_init', array( $this, 'wc_billingo_admin_init' ) );

			add_filter( 'woocommerce_general_settings', array( $this, 'billingo_settings' ), 20, 1 );
			add_action( 'add_meta_boxes', array( $this, 'wc_billingo_add_metabox' ) );

			add_action( 'wp_ajax_wc_billingo_generate_invoice', array( $this, 'generate_invoice_with_ajax' ) );
			add_action( 'wp_ajax_nopriv_wc_billingo_generate_invoice', array( $this, 'generate_invoice_with_ajax' ) );

			add_action( 'wp_ajax_wc_billingo_already', array( $this, 'wc_billingo_already' ) );
			add_action( 'wp_ajax_nopriv_wc_billingo_already', array( $this, 'wc_billingo_already' ) );

			add_action( 'wp_ajax_wc_billingo_already_back', array( $this, 'wc_billingo_already_back' ) );
			add_action( 'wp_ajax_nopriv_wc_billingo_already_back', array( $this, 'wc_billingo_already_back' ) );

			add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_complete' ) );
			add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_processing' ) );

			add_action( 'woocommerce_admin_order_actions_end', array( $this, 'add_listing_actions' ) );

			if(get_option('wc_billingo_vat_number_form')) {
				add_filter( 'woocommerce_checkout_fields' , array( $this, 'add_vat_number_checkout_field' ) );
				add_filter( 'woocommerce_before_checkout_form' , array( $this, 'add_vat_number_info_notice' ) );
				add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_vat_number' ) );
				add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_vat_number' ) );
			}

			add_action( 'admin_notices', array( $this, 'nag' ) );

    }

		//Nag
		public function nag() {
			if ( version_compare( get_option( 'wc_billingo_version' ), WC_Billingo::$version, '<' ) ) {
				?>
				<div class="update-nag notice wc-billingo-nag">
					<p><strong>WooCommerce + Billingo</strong><br> Ez a bővítmény nem hivatalos, csak szabadidőmben fejlesztem és pénzt nem kapok érte. Ha úgy érzed, hogy sokat segít a webshopodon ez a bővítmény, támogatást szívesen elfogadok <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MVB2A4BJZD3GW" target="_blank">Paypal</a>-on, de egy kuponkódnak is örülök, hátha pont kell a webshopodból valami:) Egyedi WooCommerce / WordPress oldalak és bővítmények készítését is vállalom, ezzel kapcsolatban az <strong>info@visztpeter.me</strong> címen kereshetsz! Köszi</p>
					<a href="<?php echo admin_url( 'edit.php?post_type=shop_order&wc_billingo_nag=hide' ); ?>" class="notice-dismiss"><span class="screen-reader-text">Megjegyzés figyelmen kívül hagyása</span></a>
				</div>
				<?php
			}
		}

  //Add CSS & JS
	public function wc_billingo_admin_init() {
        wp_enqueue_script( 'billingo_js', plugins_url( '/global.js',__FILE__ ), array('jquery'), TRUE );
        wp_enqueue_style( 'billingo_css', plugins_url( '/global.css',__FILE__ ) );

				$wc_billingo_local = array( 'loading' => plugins_url( '/images/ajax-loader.gif',__FILE__ ) );
				wp_localize_script( 'billingo_js', 'wc_billingo_params', $wc_billingo_local );
    }

	//Settings
	public function billingo_settings( $settings ) {
		$settings[] = array(
			'type' => 'title',
			'title' => __( 'Billingo Beállítások', 'wc-billingo' ),
			'id' => 'woocommerce_billingo_options',
			'desc'     => __( 'A nyilvános és privát kulcs megadása és elmentése után megjelenik a fizetési módok szekció. Ha nem látszik, rossz valamelyik kulcs.', 'wc-billingo' ),
		);

		$settings[] = array(
			'title'    => __( 'Nyilvános kulcs', 'wc-billingo' ),
			'id'       => 'wc_billingo_public_key',
			'type'     => 'text',
			'desc'     => __( 'Vezérlőpulton az API menüben találod meg', 'wc-billingo' ),
		);

		$settings[] = array(
			'title'    => __( 'Privát kulcs', 'wc-billingo' ),
			'id'       => 'wc_billingo_secret_key',
			'type'     => 'password',
			'desc'     => __( 'Vezérlőpulton az API menüben találod meg', 'wc-billingo' ),
		);

		$settings[] = array(
			'title'    => __( 'Fizetési határidő(nap)', 'wc-billingo' ),
			'id'       => 'wc_billingo_payment_deadline',
			'type'     => 'text'
		);

		$settings[] = array(
			'title'    => __( 'Megjegyzés', 'wc-billingo' ),
			'id'       => 'wc_billingo_note',
			'type'     => 'text'
		);

		$settings[] = array(
			'title'    => __( 'Elektronikus számla', 'wc-billingo' ),
			'id'       => 'wc_billingo_electronic',
			'type'     => 'checkbox'
		);

		$settings[] = array(
			'title'    => __( 'Számla küldése emailben', 'wc-billingo' ),
			'id'       => 'wc_billingo_email',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor a billingo emailben elküldi a vásárlónak a generált számlát.', 'wc-billingo' ),
		);

		$settings[] = array(
			'title'    => __( 'Automata számlakészítés', 'wc-billingo' ),
			'id'       => 'wc_billingo_auto',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor a rendelés lezárásakor automatán kiállításra kerül a számla.', 'wc-billingo' ),
		);

		$settings[] = array(
			'title'    => __( 'Díjbekérő létrehozása', 'wc-billingo' ),
			'id'       => 'wc_billingo_payment_request_auto',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor ha a rendelés függőben lévő státuszra vált, automatán kiállításra kerül egy díjbekérő.', 'wc-billingo' ),
		);

		$settings[] = array(
			'title'    => __( 'Fejlesztői mód', 'wc-billingo' ),
			'id'       => 'wc_billingo_debug',
			'type'     => 'checkbox'
		);

		$settings[] = array(
			'title'    => __( 'Számlatömb', 'wc-billingo' ),
			'id'       => 'wc_billingo_invoice_block',
			'type'     => 'text',
			'desc'     => __( 'Számlatömb ID', 'wc-billingo' )
		);

		$payment_methods = $this->get_available_payment_methods();
		$billingo_payment_methods = $this->get_billingo_payment_methods();
		if($billingo_payment_methods) {
			$settings[] =  array( 'type' => 'sectionend', 'id' => 'woocommerce_billingo_options');

			$settings[] = array(
				'type' => 'title',
				'title' => __( 'Fizetési módok összehangolása', 'wc-billingo' ),
				'id' => 'woocommerce_billingo_options',
				'desc'     => __( 'A Billingo-n előre meghatározott fizetési módok közül lehet választani. Párosítsd össze a webshopodban lévő fizetési módokat a billingo-val megegyezőre', 'wc-billingo' ),
			);

			foreach($payment_methods as $payment_method_id => $payment_method) {
				$settings[] = array(
					'title'    => $payment_method,
					'id'       => 'wc_billingo_payment_method_'.$payment_method_id,
					'type'     => 'select',
					'options'  => $billingo_payment_methods
				);
			}
		}

		$settings[] = array(
			'title'    => __( 'Adószám mező vásárláskor', 'wc-billingo' ),
			'id'       => 'wc_billingo_vat_number_form',
			'type'     => 'checkbox',
			'desc'     => __( 'Vásárláskor 100e ft áfa feletti rendeléskor a számlázási adatok megadásakor, megjelenik egy üzenet a fizetés oldalon, hogy adószámot meg kell adni, ha van, ami a számlázási adatok alján egy új mezőben lesz bekérve. Eltárolja a rendelés adataiban, illetve számlára is ráírja. Ha kézzel kell megadni utólag a rendeléskezelőben, akkor az egyedi mezőknél az "adoszam" mezőt kell kitölteni.', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'title'    => __( 'Adószám figyelmeztetés', 'wc-billingo' ),
			'id'       => 'wc_billingo_vat_number_notice',
			'type'     => 'text',
			'default'	 => __( 'A vásárlás áfatartalma több, mint 100.000 Ft, ezért amennyiben rendelkezik adószámmal, azt kötelező megadni a számlázási adatoknál.', 'wc-szamlazz'),
			'desc'     => __( 'Ez az üzenet jelenik meg, ha az adószám mező be van pipálva felül a fizetés oldalon.', 'wc-szamlazz' ),
		);


		$settings[] =  array( 'type' => 'sectionend', 'id' => 'woocommerce_billingo_options');

		return $settings;

	}

	//Meta box on order page
	public function wc_billingo_add_metabox( $post_type ) {

		add_meta_box('custom_order_option', 'Billingo számla', array( $this, 'render_meta_box_content' ), 'shop_order', 'side');

	}

	//Render metabox content
	public function render_meta_box_content($post) {
		?>

		<?php if(!get_option('wc_billingo_public_key') || !get_option('wc_billingo_secret_key')): ?>
			<p style="text-align: center;"><?php _e('A számlakészítéshez meg kell adnod a Billingo API kulcsokat a Woocommerce beállításokban!','wc-billingo'); ?></p>
		<?php else: ?>
			<div id="wc-billingo-messages"></div>
			<?php if(get_post_meta($post->ID,'_wc_billingo_own',true)): ?>
				<div style="text-align:center;" id="billingo_already_div">
					<?php $note = get_post_meta($post->ID,'_wc_billingo_own',true); ?>
					<p><?php _e('A számlakészítés ki lett kapcsolva, mert: ','wc-billingo'); ?><strong><?php echo $note; ?></strong><br>
					<a id="wc_billingo_already_back" href="#" data-nonce="<?php echo wp_create_nonce( "wc_already_invoice" ); ?>" data-order="<?php echo $post->ID; ?>"><?php _e('Visszakapcsolás','wc-billingo'); ?></a>
					</p>
				</div>
			<?php endif; ?>
			<?php if(get_post_meta($post->ID,'_wc_billingo_dijbekero_pdf',true)): ?>
			<p>Díjbekérő <span class="alignright"><?php echo get_post_meta($post->ID,'_wc_billingo_dijbekero',true); ?> - <a href="<?php echo $this->generate_download_link($post->ID,true); ?>">Letöltés</a></span></p>
			<hr/>
			<?php endif; ?>

			<?php if($this->is_invoice_generated($post->ID) && !get_post_meta($post->ID,'_wc_billingo_own',true)): ?>
				<div style="text-align:center;">
					<p><?php echo __('Számla sikeresen létrehozva.','wc-billingo'); ?></p>
					<p><?php _e('A számla sorszáma:','wc-billingo'); ?> <strong><?php echo get_post_meta($post->ID,'_wc_billingo',true); ?></strong></p>
					<p><a href="<?php echo $this->generate_download_link($post->ID); ?>" id="wc_billingo_download" data-nonce="<?php echo wp_create_nonce( "wc_generate_invoice" ); ?>" class="button button-primary"><?php _e('Számla megtekintése','wc-billingo'); ?></a></p>
				</div>
			<?php else: ?>
				<div style="text-align:center;<?php if(get_post_meta($post->ID,'_wc_billingo_own',true)): ?>display:none;<?php endif; ?>" id="wc-billingo-generate-button">
					<p><a href="#" id="wc_billingo_generate" data-order="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce( "wc_generate_invoice" ); ?>" class="button button-primary"><?php _e('Számlakészítés','wc-billingo'); ?></a><br><a href="#" id="wc_billingo_options"><?php _e('Opciók','wc-billingo'); ?></a></p>
					<div id="wc_billingo_options_form" style="display:none;">
						<div class="fields">
						<h4><?php _e('Megjegyzés','wc-billingo'); ?></h4>
						<input type="text" id="wc_billingo_invoice_note" value="<?php echo get_option('wc_billingo_note'); ?>" />
						<h4><?php _e('Fizetési határidő(nap)','wc-billingo'); ?></h4>
						<input type="text" id="wc_billingo_invoice_deadline" value="<?php echo get_option('wc_billingo_payment_deadline'); ?>" />
						<h4><?php _e('Teljesítés dátum','wc-billingo'); ?></h4>
						<input type="text" class="date-picker" id="wc_billingo_invoice_completed" maxlength="10" value="<?php echo date('Y-m-d'); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
						<h4><?php _e('Díjbekérő számla','wc-billingo'); ?></h4>
						<input type="checkbox" id="wc_billingo_invoice_request" value="1" />
						</div>
						<a id="wc_billingo_already" href="#" data-nonce="<?php echo wp_create_nonce( "wc_already_invoice" ); ?>" data-order="<?php echo $post->ID; ?>"><?php _e('Számlakészítés kikapcsolása','wc-billingo'); ?></a>
					</div>
					<?php if(get_option('wc_billingo_auto') == 'yes'): ?>
					<p><small><?php _e('A számla automatikusan elkészül és el lesz küldve a vásárlónak, ha a rendelés állapota befejezettre lesz átállítva.','wc-billingo'); ?></small></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php

	}

	//Generate Invoice with Ajax
	public function generate_invoice_with_ajax() {
		check_ajax_referer( 'wc_generate_invoice', 'nonce' );
		if( true ) {
			$orderid = $_POST['order'];
			$return_info = $this->generate_invoice($orderid);
			wp_send_json_success($return_info);
		}
	}

	//Generate XML for Szamla Agent
	public function generate_invoice($orderId,$payment_request = false) {
		global $wpdb, $woocommerce;
		$order = new WC_Order($orderId);
		$order_items = $order->get_items();

		//Load billingo API
		$this->billingo = new Request( array(
			'public_key' => get_option('wc_billingo_public_key'),
			'private_key' => get_option('wc_billingo_secret_key')
		));

		//Create response
		$response = array();
		$response['error'] = false;

		//Setup client first
		$clientData = [
		  "name" => ($this->get_order_property('billing_company',$order) ? $this->get_order_property('billing_company',$order).' - ' : '').$this->get_order_property('billing_first_name',$order).' '.$this->get_order_property('billing_last_name',$order),
		  "email" => $this->get_order_property('billing_email',$order),
		  "billing_address" => [
		      "street_name" => $this->get_order_property('billing_address_1',$order),
					"street_type" => "",
					"house_nr"    => "",
		      "city" => $this->get_order_property('billing_city',$order),
		      "postcode" => $this->get_order_property('billing_postcode',$order),
		      "country" => WC()->countries->countries[ $this->get_order_property('billing_country',$order) ]
		  ]
		];

		//If VAT number
		if($adoszam = get_post_meta( $orderId, 'adoszam', true )) {
			$clientData['taxcode'] = $adoszam;
		}

		//Create client
		try {
			$client = $this->billingo->post('clients', apply_filters('wc_billingo_clientdata',$clientData,$order));
		} catch ( Exception $e ) {
			$response['error'] = true;
			$response['messages'][] = $e->getMessage();
			$order->add_order_note( __( 'Billingo számlakészítés sikertelen! Hibakód: ', 'wc-billingo' ).$e->getMessage() );
			return $response;
		}

		//Get client ID
		if($client['id']) {
			$clientID = $client['id'];
		} else {
			$response['error'] = true;
			$response['messages'][] = __('Nem sikerült létrehozni az ügyfelet.','billingo');
			return $response;
		}

		//If custom details
		if(isset($_POST['note']) && isset($_POST['deadline']) && isset($_POST['completed'])) {
			$note = $_POST['note'];
			$deadline = $_POST['deadline'];
			$complated_date = $_POST['completed'];
		} else {
			$note = get_option('wc_billingo_note');
			$deadline = get_option('wc_billingo_payment_deadline');
			$complated_date = date('Y-m-d');
		}

		if(!$note) {
			$note = "";
		}

		//Get billingo payment method id & block id
		$paymentMethod = get_option('wc_billingo_payment_method_'.$this->get_order_property('payment_method',$order));
		$invoice_block = intval( get_option( 'wc_billingo_invoice_block' ) );

		//Create invoce data array
		$invoiceData = [
			"fulfillment_date" => $complated_date,
			"due_date" => date('Y-m-d'),
			"payment_method" => (int)$paymentMethod,
			"comment" => $note,
			"template_lang_code" => "hu",
			"electronic_invoice" => 0,
			"currency" => "HUF",
			"client_uid" => $clientID,
			"block_uid" => $invoice_block,
			"type" => 3
		];

		//If deadline is set
		if($deadline) {
			$invoiceData['due_date'] = date('Y-m-d', strtotime('+'.$deadline.' days'));
		}

		//If electronic_invoice
		if(get_option('wc_billingo_electronic') == 'yes') {
			$invoiceData['electronic_invoice'] = 1;
		}

		//If its a proforma invoice
		if($payment_request) {
			$invoiceData['type'] = 1;
		} else {
			if(isset($_POST['request']) && $_POST['request'] == 'on') {
				$invoiceData['type'] = 1;
				$payment_request = true;
			} else {
				$invoiceData['type'] = 3;
			}
		}

		//Add products
		foreach( $order_items as $termek ) {
			$product_item = array();


			if(method_exists( $order, 'get_id' )) {

				//WooCommerce >= 3.0
				$product_item['description'] = $termek->get_name();
				$product_item['qty'] = (int)$termek->get_quantity();
				if(round($termek->get_subtotal(),2) == 0) {
					$product_item['net_unit_price'] = 0;
					$product_item['vat_id'] = 1;
				} else {
					$product_net_price = $order->get_item_total( $termek, false, true );
					$vat_percentage = $termek->get_subtotal_tax()/$termek->get_subtotal();
					$single_product_total_price = round($product_net_price*(1+$vat_percentage),2);
					$single_product_net_total = $single_product_total_price/(1+$vat_percentage);
					$product_item['net_unit_price'] = $single_product_net_total;
					$product_item['vat_id'] = $this->get_billingo_vat_id(round(($termek->get_subtotal_tax()/$termek->get_subtotal())*100)+'%');
				}

			} else {

				//Woocmmerce < 3.0
				$product_item['description'] = $termek["name"];
				$product_item['qty'] = (int)$termek["qty"];
				if(round($termek["line_total"],2) == 0) {
					$product_item['net_unit_price'] = 0;
					$product_item['vat_id'] = 1;
				} else {
					$product_net_price = $order->get_item_total( $termek, false, true );
					$vat_percentage = $termek["line_tax"]/$termek["line_total"];
					$single_product_total_price = round($product_net_price*(1+$vat_percentage),2);
					$single_product_net_total = $single_product_total_price/(1+$vat_percentage);
					$product_item['net_unit_price'] = $single_product_net_total;
					$product_item['vat_id'] = $this->get_billingo_vat_id(round(($termek["line_tax"]/$termek["line_total"])*100)+'%');
				}

			}

			$product_item['unit'] = "db";
			$invoiceData['items'][] = $product_item;

		}

		//Shipping
		if($order->get_shipping_methods()) {
			$product_item = array();
			$product_item['description'] = $order->get_shipping_method();
			$product_item['qty'] = 1;
			$order_shipping = method_exists( $order, 'get_shipping_total' ) ? $order->get_shipping_total() : $order->order_shipping;
			$order_shipping_tax = method_exists( $order, 'get_shipping_tax' ) ? $order->get_shipping_tax() : $order->order_shipping_tax;

			if ($order_shipping > 0){
				$shipping_total = round($order_shipping_tax+$order_shipping,2);
				$vat_percentage = round($order_shipping_tax/$order_shipping,2);
				$shipping_net_total = $shipping_total/(1+$vat_percentage);
			} else {
				$shipping_net_total = 0;
			}

			$product_item['net_unit_price'] = $shipping_net_total;
			if($order_shipping == 0) {
				$product_item['vat_id'] = 1;
			} else {
				$product_item['vat_id'] = $this->get_billingo_vat_id(round(($order_shipping_tax/$order_shipping)*100)+'%');
			}
			$product_item['unit'] = "";
			$invoiceData['items'][] = $product_item;
		}

		//Extra Fees
		$fees = $order->get_fees();
		if(!empty($fees)) {
			foreach( $fees as $fee ) {
				$product_item = array();
				$product_item['description'] = $fee["name"];
				$product_item['qty'] = 1;
				$product_item['net_unit_price'] = round($fee["line_total"],2);
				$product_item['vat_id'] = $this->get_billingo_vat_id(round(($fee["line_tax"]/$fee["line_total"])*100)+'%');
				$product_item['unit'] = "";
				$invoiceData['items'][] = $product_item;
			}
		}

		//Create invoice
		try {
			$invoice = $this->billingo->post('invoices', apply_filters('wc_billingo_invoicedata',$invoiceData,$order));
		} catch ( Exception $e ) {
			$response['error'] = true;
			$response['messages'][] = $e->getMessage();
			$order->add_order_note( __( 'Billingo számlakészítés sikertelen! Hibakód: ', 'wc-billingo' ).$e->getMessage() );
			return $response;
		}

		//Get client ID
		if(!$invoice['id']) {
			$response['error'] = true;
			$response['messages'][] = __('Nem sikerült létrehozni a számlát.','billingo');
			return $response;
		}

		//Create download link
		try {
			$file_name = $this->billingo->get( "invoices/{$invoice['id']}/code" );
			$file_name = $file_name['code'];
		} catch ( Exception $e ) {
			$response['messages'][] = __('Nem sikerült létrehozni a letöltési linket a számlához.','billingo');
		}

		//Send via email if needed
		try {
			if(get_option('wc_billingo_email') == 'yes') {
				$this->billingo->get( "invoices/{$invoice['id']}/send" );
			}
		} catch ( Exception $e ) {
			$response['messages'][] = __('Nem sikerült elküldeni emailben a számlát','billingo');
			return false;
		}

		//Create response
		$szlahu_szamlaszam = $invoice['attributes']['invoice_no'];
		if(!$szlahu_szamlaszam) {
			$szlahu_szamlaszam = $invoice['id'];
		}
		$response['invoice_name'] = $szlahu_szamlaszam;

		//Save data
		if($payment_request) {
				$response['messages'][] = __('Díjbekérő sikeresen létrehozva.','wc-billingo');

				//Store as a custom field
				update_post_meta( $orderId, '_wc_billingo_dijbekero', $szlahu_szamlaszam );

				//Update order notes
				$order->add_order_note( __( 'Billingo díjbekérő sikeresen létrehozva. A számla sorszáma: ', 'wc-billingo' ).$szlahu_szamlaszam );

				//Store the filename
				update_post_meta( $orderId, '_wc_billingo_dijbekero_pdf', $file_name );
				update_post_meta( $orderId, '_wc_billingo_dijbekero_id', $invoice['id'] );

			} else {
				$response['messages'][] = __('Számla sikeresen létrehozva.','wc-billingo');

				//Store as a custom field
				update_post_meta( $orderId, '_wc_billingo', $szlahu_szamlaszam );

				//Update order notes
				$order->add_order_note( __( 'Billingo számla sikeresen létrehozva. A számla sorszáma: ', 'wc-billingo' ).$szlahu_szamlaszam );

				//Store the filename
				update_post_meta( $orderId, '_wc_billingo_pdf', $file_name );
				update_post_meta( $orderId, '_wc_billingo_id', $invoice['id'] );

			}

			//Return the download url
			$response['link'] = '<p><a href="'.$this->generate_download_link($orderId).'" id="wc_billingo_download" class="button button-primary">'.__('Számla megtekintése','wc-billingo').'</a></p>';
			return $response;
	}

	//Autogenerate invoice
	public function on_order_complete( $order_id ) {

		//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
		if(get_option('wc_billingo_auto') == 'yes') {
			if(!$this->is_invoice_generated($order_id)) {
				$return_info = $this->generate_invoice($order_id);
			}
		}

	}

	//Autogenerate invoice
	public function on_order_processing( $order_id ) {

		//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
		if(get_option('wc_billingo_payment_request_auto') == 'yes') {
			if(!$this->is_invoice_generated($order_id)) {
				$return_info = $this->generate_invoice($order_id,true);
			}
		}

	}

	//Check if it was already generated or not
	public function is_invoice_generated( $order_id ) {
		$invoice_name = get_post_meta($order_id,'_wc_billingo',true);
		$invoice_own = get_post_meta($order_id,'_wc_billingo_own',true);
		if($invoice_name || $invoice_own) {
			return true;
		} else {
			return false;
		}
	}

	//Add icon to order list to show invoice
	public function add_listing_actions( $order ) {
		$order_id = $this->get_order_id($order);

		if($this->is_invoice_generated($order_id)):
		?>
			<a href="<?php echo $this->generate_download_link($order_id); ?>" class="button tips wc_szamlazz" target="_blank" alt="" data-tip="<?php _e('Billingo számla','wc-billingo'); ?>">
				<img src="<?php echo WC_Billingo::$plugin_url . 'images/invoice.png'; ?>" alt="" width="16" height="16">
			</a>
		<?php
		endif;

		if(get_post_meta($order_id,'_wc_billingo_dijbekero_pdf',true)):
		?>
			<a href="<?php echo $this->generate_download_link($order_id,true); ?>" class="button tips wc_szamlazz" target="_blank" alt="" data-tip="<?php _e('Billingo díjbekérő','wc-billingo'); ?>">
				<img src="<?php echo WC_Billingo::$plugin_url . 'images/payment_request.png'; ?>" alt="" width="16" height="16">
			</a>
		<?php
		endif;
	}


	//Generate download url
	public function generate_download_link( $order_id, $payment_request = false ) {
		if($order_id) {
			if($payment_request) {
				$pdf_name = get_post_meta($order_id,'_wc_billingo_dijbekero_pdf',true);
			} else {
				$pdf_name = get_post_meta($order_id,'_wc_billingo_pdf',true);
			}
			return 'https://www.billingo.hu/access/c:'.$pdf_name;
		} else {
			return false;
		}
	}

	//Get available checkout methods and payment gateways
	public function get_available_payment_methods() {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		$available = array();
		foreach ($available_gateways as $available_gateway) {
			if($available_gateway->enabled == 'yes') {
				$available[$available_gateway->id] = $available_gateway->title;
			}
		}
		return $available;
	}

	//Get billingo payment methods and cache it
	public function get_billingo_payment_methods() {
		$payment_methods = get_transient( 'wc_billingo_payment_methods' );
		if(!$payment_methods) {

			//Load billingo API
			$this->billingo = new Request( array(
				'public_key' => get_option('wc_billingo_public_key'),
				'private_key' => get_option('wc_billingo_secret_key')
			));

			try {
				$billingo_payment_methods = $this->billingo->get('payment_methods/hu');
			} catch ( Exception $e ) {
				error_log( $e->getMessage(), true );
			}

			if ( empty( $billingo_payment_methods ) ) {
				return false;
			}

			//Create a simple array
			$payment_methods = array();
			foreach($billingo_payment_methods as $payment_method) {
				$payment_methods[$payment_method['id']] = $payment_method['attributes']['name'];
			}

			//Save payment methods for a day
			set_transient( 'wc_billingo_payment_methods', $payment_methods, 60*60*24 );
    }

		return $payment_methods;
	}

	//Get billingo VAT id's
	public function get_billingo_vat_id($percentage) {
		$vat_ids = get_transient( 'wc_billingo_vat_ids' );
		if(!$vat_ids) {

			//Load billingo API
			$this->billingo = new Request( array(
				'public_key' => get_option('wc_billingo_public_key'),
				'private_key' => get_option('wc_billingo_secret_key')
			));

			try {
				$billingo_vat_ids = $this->billingo->get('vat');
			} catch ( Exception $e ) {
				error_log( $e->getMessage(), true );
			}

			//Create a simple array
			$vat_ids = array();
			foreach($billingo_vat_ids as $billingo_vat_id) {
				$vat_ids[$billingo_vat_id['id']] = $billingo_vat_id['attributes']['description'];
			}

			//Save vat ids for a day
			set_transient( 'wc_billingo_vat_ids', $vat_ids, 60*60*24 );
    }

		//Find the closest one
		$vat_id = 1;
		foreach($vat_ids as $billingo_vat_id => $billingo_vat_id_name) {
			if($billingo_vat_id_name == $percentage) {
				$vat_id = $billingo_vat_id;
			}
		}

		return (int)$vat_id;
	}

	//If the invoice is already generated without the plugin
	public function wc_billingo_already() {
		check_ajax_referer( 'wc_already_invoice', 'nonce' );
		if( true ) {
			if ( !current_user_can( 'edit_shop_orders' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			$orderid = $_POST['order'];
			$note = $_POST['note'];
			update_post_meta( $orderid, '_wc_billingo_own', $note );

			$response = array();
			$response['error'] = false;
			$response['messages'][] = __('Saját számla sikeresen hozzáadva.','wc-billingo');
			$response['invoice_name'] = $note;

			wp_send_json_success($response);
		}

	}

	//If the invoice is already generated without the plugin, turn it off
	public function wc_billingo_already_back() {
		check_ajax_referer( 'wc_already_invoice', 'nonce' );
		if( true ) {
			if ( !current_user_can( 'edit_shop_orders' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			$orderid = $_POST['order'];
			$note = $_POST['note'];
			update_post_meta( $orderid, '_wc_billingo_own', '' );

			$response = array();
			$response['error'] = false;
			$response['messages'][] = __('Visszakapcsolás sikeres.','wc-billingo');

			wp_send_json_success($response);
		}

	}

	//Add vat number field to checkout page
	public function add_vat_number_checkout_field($fields) {

		if(WC()->cart->get_taxes_total() > 100000) {
			$fields['billing']['adoszam'] = array(
				 'label'     => __('Adószám', 'wc-billingo'),
				 'placeholder'   => _x('12345678-1-23', 'placeholder', 'wc-billingo'),
				 'required'  => false,
				 'class'     => array('form-row-wide'),
				 'clear'     => true
			);
		}

		return $fields;
	}

	public function add_vat_number_info_notice($checkout) {
		if(WC()->cart->get_taxes_total() > 100000) {
			wc_print_notice( get_option('wc_billingo_vat_number_notice'), 'notice' );
		}
	}

	public function save_vat_number( $order_id ) {
		if ( ! empty( $_POST['adoszam'] ) ) {
			update_post_meta( $order_id, 'adoszam', sanitize_text_field( $_POST['adoszam'] ) );
		}
	}

	public function display_vat_number($order){
		$order_id = $this->get_order_id($order);
		if($adoszam = get_post_meta( $order_id, 'adoszam', true )) {
			echo '<p><strong>'.__('Adószám').':</strong> ' . $adoszam . '</p>';
		}
	}

	//Get order ID(backward compatibility), for WC3.0+
	public function get_order_id($order) {
		$id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
		return $id;
	}

	//Get order details(backward compatiblity), for WC3.0+
	public function get_order_property($property,$order) {

		//3.0+
		$value = '';
		if(method_exists( $order, 'get_id' )) {
			$property = 'get_'.$property;
			$value = $order->$property();
		} else {
			$value = $order->$property;
		}

		return $value;

	}
}

$GLOBALS['wc_billingo'] = new WC_Billingo();

?>
