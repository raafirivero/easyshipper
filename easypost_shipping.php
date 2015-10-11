<?php
require_once('lib/easypost-php/lib/easypost.php');

function easypost_init(){

	if ( ! class_exists( 'ES_WC_EasyPost' ) ) {
		class ES_WC_EasyPost extends WC_Shipping_Method {
			function __construct() {

				$this->id = 'easypost';
				$this->method_title = 'EasyPost';
				$this->has_fields      = true;
				$this->init_form_fields();
				$this->init_settings();

				$this->title = __('EasyPost Integration', 'woocommerce');

				$this->usesandboxapi = strcmp($this->settings['test'], 'yes') == 0;
				$this->testApiKey       = $this->settings['test_api_key'  ];
				$this->liveApiKey       = $this->settings['live_api_key'  ];
				$this->handling   = $this->settings['handling'] ? $this->settings['handling'] : 0;
				$this->filters   = explode(",", $this->settings['filter_rates']);

				$this->secret_key  = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

				\EasyPost\EasyPost::setApiKey($this->secret_key);

				$this->enabled = $this->settings['enabled'];

				add_action('woocommerce_update_options_shipping_' . $this->id , array($this, 'process_admin_options'));

			} // end construct

			public function init_form_fields()
			{
				$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable/Disable', 'woocommerce' ),
						'type' => 'checkbox',
						'label' => __( 'Enabled', 'woocommerce' ),
						'default' => 'yes'
					),
					'filter_rates' => array(
						'title' => __( 'Filter these rates', 'woocommerce' ),
						'type' => 'text',
						'label' => __( 'Fitler (Comma Seperated)', 'woocommerce' ),
						'default' => ('CriticalMail,LibraryMail,MediaMail,ParcelSelect'),
					),
					'test' => array(
						'title' => __( 'Test Mode', 'woocommerce' ),
						'type' => 'checkbox',
						'label' => __( 'Enabled', 'woocommerce' ),
						'default' => 'yes'
					),
					'test_api_key' => array(
						'title' => "Test Api Key",
						'type' => 'text',
						'label' => __( 'Test Api Key', 'woocommerce' ),
						'default' => ''
					),
					'live_api_key' => array(
						'title' => "Live Api Key",
						'type' => 'text',
						'label' => __( 'Live Api Key', 'woocommerce' ),
						'default' => ''
					),
					'handling' => array(
						'title' => "Handling Charge",
						'type' => 'text',
						'label' => __( 'Handling Charge', 'woocommerce' ),
						'default' => '0'
					),
					'company' => array(
						'title' => "Company",
						'type' => 'text',
						'label' => __( 'Company', 'woocommerce' ),
						'default' => ''
					),
					'street1' => array(
						'title' => 'Address',
						'type' => 'text',
						'label' => __( 'Address', 'woocommerce' ),
						'default' => ''
					),
					'street2' => array(
						'title' => 'Address2',
						'type' => 'text',
						'label' => __( 'Address2', 'woocommerce' ),
						'default' => ''
					),
					'city' => array(
						'title' => 'City',
						'type' => 'text',
						'label' => __( 'City', 'woocommerce' ),
						'default' => ''
					),
					'state' => array(
						'title' => 'State',
						'type' => 'text',
						'label' => __( 'State', 'woocommerce' ),
						'default' => ''
					),
					'zip' => array(
						'title' => 'Zip',
						'type' => 'text',
						'label' => __( 'ZipCode', 'woocommerce' ),
						'default' => ''
					),
					'phone' => array(
						'title' => 'Phone',
						'type' => 'text',
						'label' => __( 'Phone', 'woocommerce' ),
						'default' => ''
					),
					'country' => array(
						'title' => 'Two-Letter Country Code',
						'type' => 'text',
						'label' => __( 'Country', 'woocommerce' ),
						'default' => 'US'
					),
					'customs_signer' => array(
						'title' => 'Customs Signature',
						'type' => 'text',
						'label' => __( 'Customs Signature', 'woocommerce' ),
						'default' => ''
					)

				);

			} // init form fields


			public function admin_options() { ?>
         		<h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce' ) ; ?></h3>

         		<?php echo ( ! empty( $this->method_description ) ) ? wpautop( $this->method_description ) : ''; ?>

         		<table class="form-table">
            		 <?php $this->generate_settings_html(); ?>
            		 <tr>
            		 <th>&nbsp;</th>
            		 <td width="50%">
            		 Note: in order for EasyPost to create a proper shipping labels, you must enter 
            		 parcel dimensions and weight under the shipping tab for each product in your
            		 store. If you plan to ship outside of the US, you must also enter a six-digit
            		 HS Tariff number and item description for each product as well.
            		 
            		 </td>
            		 <td></td>
            		 </tr>
        		</table><?php
			}

			function calculate_shipping($packages = array())
			{

				/*
				// debuggers
				if(class_exists("PC")) {
					null;
				} else {
					// ... any PHP Console initialization & configuration code
					require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php');
					$handler = PhpConsole\Handler::getInstance();
					$handler->setHandleErrors(false);  // disable errors handling
					$handler->start(); // initialize handlers
					$connector = PhpConsole\Connector::getInstance();
					$registered = PhpConsole\Helper::register();
				}

				require_once( $_SERVER['DOCUMENT_ROOT'].'/FirePHPCore/FirePHP.class.php');
				$firephp = FirePHP::getInstance(true);
				*/


				global $woocommerce;
				$customer = $woocommerce->customer;

				try
				{
				
					// Get a name from the form
					parse_str($_POST['post_data'],$addressform);
					$namebilling = $addressform['billing_first_name'].' '.$addressform['billing_last_name'];
					$nameshipping = $addressform['shipping_first_name'].' '.$addressform['shipping_last_name'];
					$billphone = $addressform['billing_phone'];
															
					
					if($addressform['ship_to_different_address']) {
						$fullname = $nameshipping;
					} else {
						$fullname = $namebilling;
					}
										
					
					$to_address = \EasyPost\Address::create(
						array(
							"name"	  => $fullname,
							"street1" => $customer->get_shipping_address(),
							"street2" => $customer->get_shipping_address_2(),
							"city"    => $customer->get_shipping_city(),
							"state"   => $customer->get_shipping_state(),
							"zip"     => $customer->get_shipping_postcode(),
							"country" => $customer->get_shipping_country(),
							"phone"   => $billphone
						)
					);

					$from_address = \EasyPost\Address::create(
						array(
							"company" => $this->settings['company'],
							"street1" => $this->settings['street1'],
							"street2" => $this->settings['street2'],
							"city"    => $this->settings['city'],
							"state"   => $this->settings['state'],
							"zip"     => $this->settings['zip'],
							"phone"   => $this->settings['phone'],
							"country" => $this->settings['country']
						)
					);


					$cart_weight = $woocommerce->cart->cart_contents_weight;

					$length = array();
					$width  = array();
					$height = array();
					foreach($woocommerce->cart->get_cart() as $package)
					{
						$item = get_product($package['product_id']);
						$dimensions = explode('x', trim(str_replace('cm','',$item->get_dimensions())));
						$length[] = $dimensions[0];
						$width[]  = $dimensions[1];
						$height[] = $dimensions[2] * $package['quantity'];

					}
					$parcel = \EasyPost\Parcel::create(
						array(
							"length"             => max($length),
							"width"              => max($width),
							"height"             => array_sum($height),
							"predefined_package" => null,
							"weight"             => $cart_weight
						)
					);

					if($to_address->country != $from_address->country)
					{

						//create customs form
						$shipping_abroad = true;
						$signature = $this->settings['customs_signer'];

						// Get the Customs item descriptions and tarrif numbers entered on product pages.
						$cart_group = $woocommerce->cart->cart_contents;
						$tariff = '';
						$from_country = $from_address->country;
						$customs_item = array();
						$multicust = array();
						
						if($addressform['ship_to_different_address']){
						
							$buyer_address = \EasyPost\Address::create(
								array(
									"name"	  => $namebilling,
									"street1" => $addressform['billing_address_1'],
									"street2" => $addressform['billing_address_2'],
									"city"    => $addressform['billing_city'],
									"state"   => $addressform['billing_state'],
									"zip"     => $addressform['billing_postcode'],
									"country" => $addressform['billing_country'],
									"phone"   => $billphone
								)
							);
						}

						foreach($cart_group as $c)
						{
							// create customs items from everything in the cart
							$itemid = $c['product_id'];
							$itemdesc = get_post_meta($itemid, 'contents_description');
							$totaldesc .= $itemdesc[0]. '. ';

							// pull tariff no. from the db, convert to string
							$tariff = get_post_meta($itemid, 'tariff_number');
							$tariff = (string) $tariff[0];

							// get rid of periods
							$cleantariff = str_replace('.','',$tariff);

							// make tariff number 6-digits long by adding zeros if short
							$cleantariff = str_pad( $cleantariff , 6 , "0" , STR_PAD_RIGHT);

							$cart_howmany = $c['quantity'];
							$weight = get_post_meta( $itemid, '_weight', true);
							$price = get_post_meta( $itemid, '_price', true);


							// create a customs item array for each item in the cart.
							$params = array(
								"description"      => $itemdesc[0],
								"quantity"         => $cart_howmany,
								"value"            => $price,
								"weight"           => $weight,
								"hs_tariff_number" => $cleantariff,
								"origin_country"   => $from_country,
							);

							$customs_item = \EasyPost\CustomsItem::create($params);

							// Array of all CustomsItem objects
							$multicust[] = $customs_item;


						} // endforeach

						// smart customs opbject
						$infoparams = array(
							"eel_pfc" => 'NOEEI 30.37(a)',
							"customs_certify" => true,
							"customs_signer" => $signature,
							"contents_type" => 'merchandise',
							"contents_explanation" => '', // only necessary for contents_type=other
							"restriction_type" => 'none',
							"non_delivery_option" => 'return',
							"customs_items" => $multicust
						);
						$customs_info = \EasyPost\CustomsInfo::create($infoparams);

					} // end if (foreign) section

					// create shipment with customs form
					$shipment =\EasyPost\Shipment::create(array(
							"to_address" => $to_address,
							"from_address" => $from_address,
							"buyer_address" => $buyer_address,
							"parcel" => $parcel,
							"customs_info" => $customs_info
						));

					if (count($shipment->rates) === 0) {
						$shipment->get_rates();
					}

					$created_rates = \EasyPost\Rate::create($shipment);

					foreach($created_rates as $r)
					{
						$rate = array(
							'id' => sprintf("%s-%s|%s", $r->carrier, $r->service, $shipment->id),
							'label' => sprintf("%s %s", $r->carrier , $r->service),
							'cost' => $r->rate + $this->handling,
							'calc_tax' => 'per_item'
						);

						$filter_out = !empty($this->filters) ? $this->filters : array('LibraryMail', 'MediaMail');


						if (!in_array($r->service, $filter_out))
						{
							// Register the rate
							$this->add_rate( $rate );
						}
					}
					
					// PC::debug($shipment->to_address,'calculated address');

				} // end try

				catch(Exception $e)
				{
					// EasyPost Error - Lets Log.
					error_log(var_export($e,1));
					// mail('youremail@gmail.com', 'Error from WordPress - EasyPost', var_export($e,1));
				}

			} // calculate_shipping

		} // end create class

	}  //class exists

}  // EasyPost init()

add_action( 'woocommerce_shipping_init', 'easypost_init' );


function epcus_purchase_order($order_id)
{
	/*
	// debuggers
	if(class_exists("PC")) {
		null;
	} else {
		// ... any PHP Console initialization & configuration code
		require( $_SERVER['DOCUMENT_ROOT'].'/php-console/src/PhpConsole/__autoload.php');
		$handler = PhpConsole\Handler::getInstance();
		$handler->setHandleErrors(false);  // disable errors handling
		$handler->start(); // initialize handlers
		$connector = PhpConsole\Connector::getInstance();
		$registered = PhpConsole\Helper::register();
	}

	require_once( $_SERVER['DOCUMENT_ROOT'].'/FirePHPCore/FirePHP.class.php');
	$firephp = FirePHP::getInstance(true);
	// end debuggers
	*/

	try
	{
		global $woocommerce;
		$order = new WC_Order($order_id);

		// confirm shipping method
		$method = $order->get_shipping_methods();
		$method = array_values($method);
		$shipping_method = $method[0]['method_id'];
		$ship_arr = explode('|',$shipping_method);

		if(count($ship_arr) >= 2)
		{
			$opt = get_option('woocommerce_easypost_settings',array());

			if(empty($opt)) return;

			$usesandboxapi = strcmp($opt['test'], 'yes') == 0;
			$testApiKey  = $opt['test_api_key'  ];
			$liveApiKey  = $opt['live_api_key'  ];
			$secret_key  = $usesandboxapi ? $testApiKey : $liveApiKey;

			\EasyPost\EasyPost::setApiKey($secret_key);

			// use shipment id that was saved with order
			$savedid = $ship_arr[1];
			$shipment = \EasyPost\Shipment::retrieve($savedid);

			// explicitly save EasyPost ID to visible field within the order
			update_post_meta( $order_id, 'EasyPost_Shipment_ID', $savedid );

			// for whatever reason, WC loses the addressee's name on checkout
			// pull the name in from the saved entry in the database
			$shipaddress = $order->get_formatted_shipping_address();
			$address_arr = explode('<br/>',$shipaddress);
			$receivername = $address_arr[0];
			$shipment->to_address->name = $receivername;

			$rates = $shipment->rates;

			foreach($shipment->rates as $idx => $r)
			{
				if(sprintf("%s-%s", $r->carrier , $r->service) == $ship_arr[0])
				{
					$index = $idx;
					break;
				}
			}

			/*
			error_log( 'pre-buy label?' );
			error_log( var_export( $shipment->to_address,TRUE ) );
			PC::debug($shipment->to_address,'pre-buy label?');
			*/

			$shipment->buy($shipment->rates[$index]);
			update_post_meta( $order_id, 'easypost_shipping_label', $shipment->postage_label->label_url);
			$order->add_order_note(
				sprintf(
					"Shipping label available at: '%s'",
					$shipment->postage_label->label_url
				)
			);

		} // endif

	}
	catch(Exception $e)
	{
		error_log(var_export($e,1));
		// mail('youremail@gmail.com', 'Checkout Error - EasyPost', var_export($e,1));
	}
}

add_action('woocommerce_checkout_order_processed', 'epcus_purchase_order');



function add_easypost_method( $methods ) {
	$methods[] = 'ES_WC_EasyPost'; return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_easypost_method' );
