<?php
/**
 * Product Data Save
 * 
 * Function for processing and storing all product data.
 *
 * DISCLAIMER
 *
 * Do not edit or add directly to this file if you wish to upgrade Jigoshop to newer
 * versions in the future. If you wish to customise Jigoshop core for your needs,
 * please use our GitHub repository to publish essential changes for consideration.
 *
 * @package    Jigoshop
 * @category   Admin
 * @author     Jigowatt
 * @copyright  Copyright (c) 2011 Jigowatt Ltd.
 * @license    http://jigoshop.com/license/commercial-edition
 */

add_action('jigoshop_process_product_meta', 'jigoshop_process_product_meta', 1, 2);

//@this needs refactoring pretty bad
function jigoshop_process_product_meta( $post_id, $post ) {
	global $wpdb;

	$jigoshop_errors = array();
	
	// Get old data + attributes
	$data = (array) maybe_unserialize( get_post_meta($post_id, 'product_data', true) );
	$attributes = (array) maybe_unserialize( get_post_meta($post_id, 'product_attributes', true) );
	
	// Add/Replace data to array
    $product_fields = array(
        'regular_price',
        'sale_price',
        'weight',
        'tax_status',
        'tax_class',
        'stock_status'
    );
    
    //run stripslashes on all valid fields
    foreach ($product_fields as $field_name) {
        $field_value = '';
        if(isset($_POST[$field_name])) {
            $field_value = $_POST[$field_name];
        }
        
        $data[$field_name] = stripslashes($field_value);
    }

    // Attributes
    $attributes = array();
		
		if (isset($_POST['attribute_names'])) :
			 $attribute_names = $_POST['attribute_names'];
			 if (isset($_POST['attribute_values'])) $attribute_values = $_POST['attribute_values'];
			 else $attribute_values = null;
			 if (isset($_POST['attribute_visibility'])) $attribute_visibility = $_POST['attribute_visibility'];
			 if (isset($_POST['attribute_variation'])) $attribute_variation = $_POST['attribute_variation'];
			 $attribute_is_taxonomy = $_POST['attribute_is_taxonomy'];
			 $attribute_position = $_POST['attribute_position'];
	
			 for ($i=0; $i<sizeof($attribute_names); $i++) :
			 	if (!($attribute_names[$i])) continue;
			 	if (isset($attribute_visibility[$i])) $visible = 'yes'; else $visible = 'no';
			 	if (isset($attribute_variation[$i])) $variation = 'yes'; else $variation = 'no';
			 	if ($attribute_is_taxonomy[$i]) $is_taxonomy = 'yes'; else $is_taxonomy = 'no';
			 	
			 	if (is_array($attribute_values[$i])) :
			 		$attribute_values[$i] = array_map('htmlspecialchars', array_map('stripslashes', $attribute_values[$i]));
			 	else :
			 		$attribute_values[$i] = trim(htmlspecialchars(stripslashes($attribute_values[$i])));
			 	endif;
			 	
			 	if (empty($attribute_values[$i]) || ( is_array($attribute_values[$i]) && sizeof($attribute_values[$i])==0) ) :
			 		if ($is_taxonomy=='yes' && taxonomy_exists('pa_'.sanitize_title($attribute_names[$i]))) :
			 			wp_set_object_terms( $post_id, 0, 'pa_'.sanitize_title($attribute_names[$i]) );
			 		endif;
			 		continue;
			 	endif;
			 	
			 	$attributes[ sanitize_title( $attribute_names[$i] ) ] = array(
			 		'name' => htmlspecialchars(stripslashes($attribute_names[$i])), 
			 		'value' => $attribute_values[$i],
			 		'position' => $attribute_position[$i],
			 		'visible' => $visible,
			 		'variation' => $variation,
			 		'is_taxonomy' => $is_taxonomy
			 	);
			 	
			 	if ($is_taxonomy=='yes') :
			 		// Update post terms
			 		$tax = $attribute_names[$i];
			 		$value = $attribute_values[$i];
			 		
			 		if (taxonomy_exists('pa_'.sanitize_title($tax))) :
			 			
			 			wp_set_object_terms( $post_id, $value, 'pa_'.sanitize_title($tax) );
			 			
			 		endif;
			 		
			 	endif;
			 	
			 endfor; 
		endif;	
		
		if (!function_exists('attributes_cmp')) {
			function attributes_cmp($a, $b) {
			    if ($a['position'] == $b['position']) {
			        return 0;
			    }
			    return ($a['position'] < $b['position']) ? -1 : 1;
			}
		}
		uasort($attributes, 'attributes_cmp');
	
		// Product type
		$product_type = sanitize_title( stripslashes( $_POST['product-type'] ) );
		if( !$product_type ) $product_type = 'simple';
		
		wp_set_object_terms($post_id, $product_type, 'product_type');
		
		// visibility
		$visibility = stripslashes( $_POST['visibility'] );
		update_post_meta( $post_id, 'visibility', $visibility );
		
		// Featured
		$featured = stripslashes( $_POST['featured'] );
		update_post_meta( $post_id, 'featured', $featured );
		
		// Unique SKU 
		$SKU = get_post_meta($post_id, 'SKU', true);
		$new_sku = stripslashes( $_POST['sku'] );
		if ($new_sku!==$SKU) :
			if ($new_sku && !empty($new_sku)) :
				if ($wpdb->get_var("SELECT * FROM $wpdb->postmeta WHERE meta_key='SKU' AND meta_value='".$new_sku."';") || $wpdb->get_var("SELECT * FROM $wpdb->posts WHERE ID='".$new_sku."' AND ID!=".$post_id.";")) :
					$jigoshop_errors[] = __('Product SKU must be unique.', 'jigoshop');
				else :
					update_post_meta( $post_id, 'SKU', $new_sku );
				endif;
			else :
				update_post_meta( $post_id, 'SKU', '' );
			endif;
		endif;
		
		// Sales and prices
		
		if ($product_type!=='grouped') :
			
			$date_from = (isset($_POST['sale_price_dates_from'])) ? $_POST['sale_price_dates_from'] : '';
			$date_to = (isset($_POST['sale_price_dates_to'])) ? $_POST['sale_price_dates_to'] : '';
			
			// Dates
			if ($date_from) :
				update_post_meta( $post_id, 'sale_price_dates_from', strtotime($date_from) );
			else :
				update_post_meta( $post_id, 'sale_price_dates_from', '' );	
			endif;
			
			if ($date_to) :
				update_post_meta( $post_id, 'sale_price_dates_to', strtotime($date_to) );
			else :
				update_post_meta( $post_id, 'sale_price_dates_to', '' );	
			endif;
			
			if ($date_to && !$date_from) :
				update_post_meta( $post_id, 'sale_price_dates_from', strtotime('NOW') );
			endif;
	
			// Update price if on sale
			if ($data['sale_price'] && $date_to == '' && $date_from == '') :
				update_post_meta( $post_id, 'price', $data['sale_price'] );
			else :
				update_post_meta( $post_id, 'price', $data['regular_price'] );
			endif;	
	
			if ($date_from && strtotime($date_from) < strtotime('NOW')) :
				update_post_meta( $post_id, 'price', $data['sale_price'] );
			endif;
			
			if ($date_to && strtotime($date_to) < strtotime('NOW')) :
				update_post_meta( $post_id, 'price', $data['regular_price'] );
				update_post_meta( $post_id, 'sale_price_dates_from', '');
				update_post_meta( $post_id, 'sale_price_dates_to', '');
			endif;
		
		else :
			
			$data['sale_price'] = '';
			$data['regular_price'] = '';
			update_post_meta( $post_id, 'sale_price_dates_from', '' );	
			update_post_meta( $post_id, 'sale_price_dates_to', '' );
			update_post_meta( $post_id, 'price', '' );

		endif;
		
		// Stock Data
		
		if (get_option('jigoshop_manage_stock')=='yes') :
			// Manage Stock Checkbox
			if ($product_type!=='grouped' && isset($_POST['manage_stock']) && $_POST['manage_stock']) :

				update_post_meta( $post_id, 'stock', $_POST['stock'] );
				$data['manage_stock'] = 'yes';
				$data['backorders'] = stripslashes( $_POST['backorders'] );
				
			else :
				
				update_post_meta( $post_id, 'stock', '0' );
				$data['manage_stock'] = 'no';
				$data['backorders'] = 'no';
				
			endif;
		endif;
		
		// Apply filters to data
		$data = apply_filters( 'process_product_meta', $data, $post_id );
		
		// Apply filter to data for product type
		$data = apply_filters( 'filter_product_meta_' . $product_type, $data, $post_id );
		
		// Do action for product type
        if(function_exists('process_product_meta_' . $product_type)) {            
            $meta_errors = call_user_func('process_product_meta_' . $product_type, $data, $post_id);
            
            if(is_array($meta_errors)) {
                $jigoshop_errors = array_merge($jigoshop_errors, $meta_errors);
            }
        }
		
	// Save
	update_post_meta( $post_id, 'product_attributes', $attributes );
	update_post_meta( $post_id, 'product_data', $data );
	update_option('jigoshop_errors', $jigoshop_errors);
}