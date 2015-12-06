<?php
// create Customs shipping options

// Display Fields
add_action( 'woocommerce_product_options_shipping', 'woo_add_custom_shipping_fields' );
 
// Save Fields
add_action( 'woocommerce_process_product_meta', 'woo_add_custom_general_fields_save' );

function woo_add_custom_shipping_fields() {
 
  global $woocommerce, $post;
  
  echo '</div>'; // close previous group, start new one
  echo '<div class="options_group">';
  echo '<p>Note: in order to ship outside the US, you must include a six-digit 
		  HS Tariff number and item description on your shipping label. A good place to 
		  look up HS Tariff numbers is <a href="http://hts.usitc.gov/hts_search.asp">here</a>
		</p>';

  // Custom fields will be created here...
 
	// Tariff Number field
	woocommerce_wp_text_input( 
		array( 
			'id'          => 'tariff_number', 
			'label'       => __( 'HS Tariff Number', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Enter the HS Tariff number here.', 'woocommerce' ) 
		)
	);
	
	
	// Contents Field
	woocommerce_wp_text_input( 
		array( 
			'id'          => 'contents_description', 
			'label'       => __( 'Contents Description', 'woocommerce' ), 
			'placeholder' => '',
			'desc_tip'    => 'true',
			'description' => __( 'Short description of the product - i.e. "Unisex cotton t-shirt."', 'woocommerce' ) 
		)
	);
		
}

function woo_add_custom_general_fields_save( $post_id ){
	
	// Tariff Field
	$woocommerce_text_field = $_POST['tariff_number'];
	if( !empty( $woocommerce_text_field ) )
		update_post_meta( $post_id, 'tariff_number', esc_attr( $woocommerce_text_field ) );
		
	// Content Description Field
	$woocommerce_text_field = $_POST['contents_description'];
	if( !empty( $woocommerce_text_field ) )
		update_post_meta( $post_id, 'contents_description', esc_attr( $woocommerce_text_field ) );
	
}
