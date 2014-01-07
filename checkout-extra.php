<?php
// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );

// Our hooked in function - $fields is passed via the filter!
function custom_override_checkout_fields( $fields ) {
     $fields['billing']['easypost'] = array(
        'label'     => __('easypost', 'woocommerce'),
    'required'  => false,
    'label_class' => array('hidden')
     );

     return $fields;
}