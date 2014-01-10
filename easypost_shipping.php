<?php
require_once('lib/easypost-php/lib/easypost.php');
class ES_WC_EasyPost extends WC_Shipping_Method {
	function __construct() {
		$this->id = 'easypost';
		$this->has_fields      = true;
		$this->init_form_fields();
		$this->init_settings();

		$this->title = __('Easy Post Integration', 'woocommerce');

		$this->usesandboxapi      = strcmp($this->settings['test'], 'yes') == 0;
		$this->testApiKey       = $this->settings['test_api_key'  ];
		$this->liveApiKey       = $this->settings['live_api_key'  ];
		$this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

		\EasyPost\EasyPost::setApiKey($this->secret_key);

		$this->enabled = $this->settings['enabled'];


		add_action('woocommerce_update_options_shipping_' . $this->id , array($this, 'process_admin_options'));
		add_action('woocommerce_checkout_order_processed', array(&$this, 'purchase_order' ));

	}
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enabled', 'woocommerce' ),
				'default' => 'yes'
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
				'title' => 'Customs Signer',
				'type' => 'text',
				'label' => __( 'Customs Signature', 'woocommerce' ),
				'default' => ''
			),

		);

	}

	function calculate_shipping($packages = array())
	{
		
		global $woocommerce;

		$customer = $woocommerce->customer;

		try
		{

			// Get a name from the form
			$poststring = parse_str($_POST['post_data'],$addressform);
			$namebilling = $addressform['billing_first_name'].' '.$addressform['billing_last_name'];
			$nameshipping = $addressform['shipping_first_name'].' '.$addressform['shipping_last_name'];

			if($addressform['shiptobilling']){
				$fullname = $namebilling;
			} else {
				$fullname = $nameshipping;
			}

			$billphone = $addressform['billing_phone'];


			$to_address = \EasyPost\Address::create(
				array(
					"name"    => $fullname,
					"street1" => $customer->get_address(),
					"street2" => $customer->get_address_2(),
					"city"    => $customer->get_city(),
					"state"   => $customer->get_state(),
					"zip"     => $customer->get_postcode(),
					"country" => $customer->get_country(),
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

			$shipping_abroad = false;
			$customs_info = null;

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
					"parcel" => $parcel,
					"customs_info" => $customs_info
				));
				
				
			$shippingservice = array(
				'First',
				'Priority',
				'FirstClassPackageInternationalService',
				'PriorityMailInternational'
			);

			// cuter names for the shipping services. Ideally user can set these. Raw names are too long.
			$shortnames = array(
				'First' => 'First Class',
				'Priority' => 'Priority',
				'FirstClassPackageInternationalService'  => 'First Class Int\'l',
				'PriorityMailInternational' => 'Priority International'
			);

			// Unset, then reset rates in case user has changed country (by accident)
			// unset($shipment->rates);
			
			$created_rates = \EasyPost\Rate::create($shipment);


			foreach($created_rates as $r)
			{
				if (!in_array($r->service, $shippingservice)) {
					continue;
				}			

				$rate = array(
					'id' => sprintf("%s-%s|%s", $r->carrier, $r->service, $shipment->id),
					'label' => sprintf("%s %s", $r->carrier , $shortnames[$r->service]),
					'cost' => $r->rate,
					'calc_tax' => 'per_item'
				);

				// Register the rate
				$this->add_rate( $rate );
			}

			// store shipment id to call when ready to purchase
			$_SESSION['shipmentid'] = $shipment->id;

		}
		catch(Exception $e)
		{
			// EasyPost Error - Lets Log.
			error_log(var_export($e,1));
			// mail('raafi.rivero@gmail.com', 'Error from WordPress - EasyPost', var_export($e,1));

		}

	}



	function purchase_order($order_id)
	{

		try
		{
			$order        = &new WC_Order($order_id);
			$shipping     = $order->get_shipping_address();
			if($ship_arr = explode('|',$order->shipping_method))
			{

				// pull in shipment from session, reactivate it with API
				$shipmentid = $_SESSION['shipmentid'];
				$shipment = \EasyPost\Shipment::retrieve($shipmentid);


				$rates = $shipment->get_rates();
				foreach($shipment->rates as $idx => $r)
				{
					if(sprintf("%s-%s", $r->carrier , $r->service) == $ship_arr[0])
					{
						$index = $idx;
						break;
					}
				}

				$shipment->buy($shipment->rates[$index]);

				update_post_meta( $order_id, 'easypost_shipping_label', $shipment->postage_label->label_url);
				$order->add_order_note(
					sprintf(
						"Shipping label available at: '%s'",
						$shipment->postage_label->label_url
					)
				);
			}

		}

		catch(Exception $e)
		{
			error_log(var_export($e,1));
			//  mail('raafi.rivero@gmail.com', 'Error from Buy Rate - EasyPost', var_export($e,1));
		}
	}
}
function add_easypost_method( $methods ) {
	$methods[] = 'ES_WC_EasyPost'; return $methods;
}

add_filter('woocommerce_shipping_methods',         'add_easypost_method' );
