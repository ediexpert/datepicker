<?php
// Add the custom field to the product general tab
add_action('woocommerce_product_options_general_product_data', 'add_custom_general_fields');

function add_custom_general_fields() {
    global $woocommerce, $post;
    
    echo '<div class="options_group">';
    
    // Checkbox
    woocommerce_wp_checkbox( 
        array( 
            'id'            => '_is_tour', 
            'wrapper_class' => 'show_if_simple', 
            'label'         => __('Is Tour?', 'woocommerce' ), 
            'description'   => __('Check this box if this product is a tour.', 'woocommerce' ) 
        )
    );
	
	// Text field for tour start time
    woocommerce_wp_text_input(
        array(
            'id'                => '_tour_start_time',
            'label'             => __('Tour Start Time', 'woocommerce'),
            'placeholder'       => __('e.g., 13:30 , in 24 hour format', 'woocommerce'),
            'description'       => __('Enter the start time of the tour.', 'woocommerce'),
            'desc_tip'          => 'true'
        )
    );
	
	// Text field for tour end time
    woocommerce_wp_text_input(
        array(
            'id'                => '_tour_end_time',
            'label'             => __('Tour End Time', 'woocommerce'),
            'placeholder'       => __('e.g., 23:30 , in 24 hour format', 'woocommerce'),
            'description'       => __('Enter the end time of the tour.', 'woocommerce'),
            'desc_tip'          => 'true'
        )
    );
	
	// Number field for cutoff days
    woocommerce_wp_text_input(
        array(
            'id'                => '_cutoff_days',
            'label'             => __('Cutoff Days', 'woocommerce'),
            'placeholder'       => __('e.g., 7', 'woocommerce'),
            'description'       => __('Enter the number of cutoff days before the tour.', 'woocommerce'),
            'desc_tip'          => 'true',
            'type'              => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '0'
            )
        )
    );
	
	// Checkboxes for available days
    $days_of_week = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    foreach ($days_of_week as $day) {
        woocommerce_wp_checkbox(
            array(
                'id'            => '_available_' . strtolower($day),
                'label'         => __($day, 'woocommerce'),
                'description'   => sprintf(__('Check this box if the tour is available on %s.', 'woocommerce'), $day)
            )
        );
    }

	// Textarea for blackout dates
	// 
   /*
	woocommerce_wp_textarea_input(
        array(
            'id'            => '_blackout_dates',
            'label'         => __('Blackout Dates', 'woocommerce'),
            'placeholder'   => __('YYYY-MM-DD', 'woocommerce'),
            'description'   => __('Enter blackout dates (one per line).', 'woocommerce'),
            'desc_tip'      => 'true'
        )
    );
*/
    
    echo '</div>';
}



// Save the custom field values
add_action('woocommerce_process_product_meta', 'save_custom_general_fields');

function save_custom_general_fields($post_id) {
    $is_tour = isset($_POST['_is_tour']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_tour', $is_tour);

    if (isset($_POST['_tour_start_time'])) {
        update_post_meta($post_id, '_tour_start_time', sanitize_text_field($_POST['_tour_duration']));
    }

    if (isset($_POST['_tour_end_time'])) {
        update_post_meta($post_id, '_tour_end_time', sanitize_text_field($_POST['_tour_guide_name']));
    }

    if (isset($_POST['_departure_date'])) {
        update_post_meta($post_id, '_departure_date', sanitize_text_field($_POST['_departure_date']));
    }

    if (isset($_POST['_cutoff_days'])) {
        update_post_meta($post_id, '_cutoff_days', intval($_POST['_cutoff_days']));
    }

    $days_of_week = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
    foreach ($days_of_week as $day) {
        $is_available = isset($_POST['_available_' . $day]) ? 'yes' : 'no';
        update_post_meta($post_id, '_available_' . $day, $is_available);
    }

	/*
    if (isset($_POST['_blackout_dates'])) {
        $blackout_dates = array_map('sanitize_text_field', explode("\n", $_POST['_blackout_dates']));
        update_post_meta($post_id, '_blackout_dates', $blackout_dates);
    }
	*/
}


// Enqueue datepicker script on the frontend
add_action('wp_enqueue_scripts', 'enqueue_frontend_datepicker');

function enqueue_frontend_datepicker() {
    if (is_product()) {
		
		 global $product;
        $available_days = array(); // Array to hold available days to enable in the picker
        $days_of_week = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');

        foreach ($days_of_week as $index => $day) {
            if ('yes' === get_post_meta($product->get_id(), '_available_' . $day, true)) {
                $available_days[] = $index; // Add day index if available
            }
        }

        $available_days_js = json_encode($available_days); // Convert to JSON for JavaScript usage

		// Get cutoff days and calculate the minDate
        $cutoff_days = (int) get_post_meta($product->get_id(), '_cutoff_days', true);
        $minDate = $cutoff_days ? "+$cutoff_days" : "+1"; // Default to 1 if no cutoff days are set

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_add_inline_script('jquery-ui-datepicker', "jQuery(document).ready(function($) {
            var availableDays = $available_days_js;
            
            function enableAvailableDays(date) {
                var day = date.getDay();
                return [availableDays.includes(day)];
            }

            $('#inline_datepicker').datepicker({
                dateFormat : 'yy-mm-dd',
                beforeShowDay: enableAvailableDays,
                minDate: '$minDate', // Set the minimum date based on cutoff days
				onSelect: function(dateText) {
                    $('#tour_date').val(dateText); // Save selected date to hidden input
                }
            });

            $('form.cart').submit(function(e) {
                if ($('#tour_date').length > 0 && $('#tour_date').val() === '') {
                    e.preventDefault();
                    alert('Please select a tour date.');
                    $('#tour_date').focus();
                }
            });
        });");
    }
}


// Display the date picker on the product page if the product is a tour
add_action('woocommerce_before_add_to_cart_button', 'display_tour_date_picker');

function display_tour_date_picker() {
    global $product;

    $is_tour = get_post_meta($product->get_id(), '_is_tour', true);

    if ($is_tour === 'yes') {
         echo '<div class="form-row form-row-wide">
                <label>Choose Tour Date:</label>
                <div id="inline_datepicker"></div>
                <input type="hidden" id="tour_date" name="tour_date" required />
              </div>';
    }
}


// Validate the date input and add it to the cart item data
add_filter('woocommerce_add_cart_item_data', 'save_tour_date_to_cart_item_data', 10, 2);

function save_tour_date_to_cart_item_data($cart_item_data, $product_id) {
    if (isset($_POST['tour_date'])) {
        $cart_item_data['tour_date'] = sanitize_text_field($_POST['tour_date']);
    }
    return $cart_item_data;
}


// Display the tour date in the cart
add_filter('woocommerce_get_item_data', 'display_tour_date_in_cart', 10, 2);

function display_tour_date_in_cart($item_data, $cart_item) {
    if (isset($cart_item['tour_date'])) {
        $item_data[] = array(
            'key'     => __('Tour Date', 'woocommerce'),
            'value'   => sanitize_text_field($cart_item['tour_date']),
            'display' => sanitize_text_field($cart_item['tour_date'])
        );
    }
    return $item_data;
}


// Save the tour date to the order
add_action('woocommerce_checkout_create_order_line_item', 'save_tour_date_to_order_items', 10, 4);

function save_tour_date_to_order_items($item, $cart_item_key, $values, $order) {
    if (isset($values['tour_date'])) {
        $item->add_meta_data(__('Tour Date', 'woocommerce'), sanitize_text_field($values['tour_date']));
    }
}
