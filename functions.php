// Show product image on the checkout page using the Elementor Checkout Module

add_filter( 'woocommerce_cart_item_name', function( $product_name, $cart_item, $cart_item_key ) {

    if ( is_checkout() && isset( $cart_item['data'] ) ) {
        $thumbnail = $cart_item['data']->get_image( 'woocommerce_thumbnail' );

        $product_name = '<span class="checkout-hidden-thumb">' . $thumbnail . '</span>' . $product_name;
    }

    return $product_name;

}, 10, 3 );


add_action( 'wp_footer', function() {

    if ( ! is_checkout() ) {
        return;
    }
    ?>

    <script>
    jQuery(function($){

        function addCheckoutImageColumn() {

            $('.woocommerce-checkout-review-order-table tbody tr.cart_item').each(function(){

                var row = $(this);

                if (row.find('td.product-image').length) {
                    return;
                }

                var img = row.find('.checkout-hidden-thumb img').first();

                if (img.length) {
                    row.prepend('<td class="product-image"></td>');
                    row.find('td.product-image').append(img);
                    row.find('.checkout-hidden-thumb').remove();
                }
            });

            $('.woocommerce-checkout-review-order-table thead tr').each(function(){
                if (!$(this).find('th.product-image').length) {
                    $(this).prepend('<th class="product-image"></th>');
                }
            });
        }

        addCheckoutImageColumn();

        $(document.body).on('updated_checkout', function(){
            addCheckoutImageColumn();
        });

    });
    </script>

    <style>
        .woocommerce-checkout-review-order-table td.product-image,
        .woocommerce-checkout-review-order-table th.product-image {
            width: 50px;
            vertical-align: middle;
            text-align: left;
        }

        .woocommerce-checkout-review-order-table td.product-image img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }

        .checkout-hidden-thumb {
            display: none;
        }
    </style>

    <?php
});

add_action( 'wp_enqueue_scripts', function() {
    if ( wp_script_is( 'elementor-frontend', 'registered' ) ) {
        wp_enqueue_script( 'elementor-frontend' );
    }
}, 5 ); // priority 5 = runs early, before most other plugins enqueue theirs

/**
 * Add remove (×) icon on checkout review-order items
 */

add_filter( 'woocommerce_checkout_cart_item_quantity', 'ed_checkout_remove_icon', 10, 3 );
function ed_checkout_remove_icon( $quantity_html, $cart_item, $cart_item_key ) {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        $remove_link = sprintf(
            '<a href="#" class="checkout-remove-item" data-cart_item_key="%s" title="%s">&times;</a>',
            esc_attr( $cart_item_key ),
            esc_attr__( 'Remove this item', 'woocommerce' )
        );
        return $remove_link . $quantity_html;
    }
    return $quantity_html;
}

add_action( 'wp_ajax_ed_remove_checkout_item', 'ed_remove_checkout_item_cb' );
add_action( 'wp_ajax_nopriv_ed_remove_checkout_item', 'ed_remove_checkout_item_cb' );
function ed_remove_checkout_item_cb() {
    check_ajax_referer( 'ed-checkout-remove-nonce', 'security' );

    if ( empty( $_POST['cart_item_key'] ) ) {
        wp_send_json_error();
    }

    $key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) );
    WC()->cart->remove_cart_item( $key );

    wp_send_json_success( array(
        'cart_empty' => WC()->cart->is_empty(),
    ) );
}

add_action( 'wp_enqueue_scripts', 'ed_enqueue_checkout_remove_script', 20 );
function ed_enqueue_checkout_remove_script() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
        return;
    }

    wp_enqueue_script(
        'ed-checkout-remove-item',
        get_stylesheet_directory_uri() . '/checkout-remove-item.js',
        array( 'jquery', 'wc-checkout' ),
        '1.0.3',
        true
    );

    wp_localize_script( 'ed-checkout-remove-item', 'edCheckoutRemove', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ed-checkout-remove-nonce' ),
        'cart_url' => wc_get_cart_url(),
    ) );
	
	wp_localize_script( 'ed-checkout-remove-item', 'ed_checkout_qty_vars', array(
		'nonce' => wp_create_nonce( 'ed-checkout-remove-nonce' ),
	) );

    wp_add_inline_style( 'woocommerce-general', '
        .checkout-remove-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e94b35;
            color: #fff !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            line-height: 1;
            vertical-align: middle;
			position: absolute;
			top: 50%;
			transform: translateY(-50%);
			left: -20px;
        }
        .checkout-remove-item:hover { background: #000; }
		.checkout-qty-wrapper {
			display: inline-block;
			margin-top: 4px;
		}
		.checkout-item-qty {
			width: 55px !important;
			padding: 4px 6px !important;
			border: 1px solid #ddd !important;
			border-radius: 4px !important;
			text-align: center;
			font-size: 13px;
		}
    ' );
}

add_filter( 'woocommerce_checkout_cart_item_quantity', 'ed_checkout_editable_quantity', 20, 3 );
function ed_checkout_editable_quantity( $quantity_html, $cart_item, $cart_item_key ) {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        $product      = $cart_item['data'];
        $max_qty      = $product->get_max_purchase_quantity(); // -1 if unlimited
        $current_qty  = $cart_item['quantity'];
        $max_attr     = ( $max_qty > 0 ) ? $max_qty : '';

        $remove_icon = '';
        if ( preg_match( '/<a[^>]*checkout-remove-item[^>]*>.*?<\/a>/', $quantity_html, $matches ) ) {
            $remove_icon = $matches[0];
        }

        $qty_input = sprintf(
            '<div class="checkout-qty-wrapper">
                <button type="button" class="qty-btn qty-btn-minus" data-cart_item_key="%1$s" aria-label="Decrease quantity">&#8722;</button>
                <input type="number"
                    class="checkout-item-qty"
                    data-cart_item_key="%1$s"
                    min="1"
                    max="%2$s"
                    step="1"
                    value="%3$d"
                    inputmode="numeric" />
                <button type="button" class="qty-btn qty-btn-plus" data-cart_item_key="%1$s" aria-label="Increase quantity">&#43;</button>
            </div>',
            esc_attr( $cart_item_key ),
            esc_attr( $max_attr ),
            absint( $current_qty )
        );

        return $remove_icon . $qty_input;
    }
    return $quantity_html;
}


add_action( 'wp_ajax_ed_update_checkout_qty', 'ed_update_checkout_qty_cb' );
add_action( 'wp_ajax_nopriv_ed_update_checkout_qty', 'ed_update_checkout_qty_cb' );
function ed_update_checkout_qty_cb() {
    check_ajax_referer( 'ed-checkout-remove-nonce', 'security' );

    if ( empty( $_POST['cart_item_key'] ) || ! isset( $_POST['quantity'] ) ) {
        wp_send_json_error();
    }

    $key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) );
    $qty = absint( $_POST['quantity'] );

    if ( $qty < 1 ) {
        wp_send_json_error( array( 'message' => 'Invalid quantity' ) );
    }

    $cart = WC()->cart;

    if ( ! isset( $cart->get_cart()[ $key ] ) ) {
        wp_send_json_error( array( 'message' => 'Item not found in cart' ) );
    }

    $updated = $cart->set_quantity( $key, $qty, true );

    if ( ! $updated ) {
        wp_send_json_error( array( 'message' => 'Could not update quantity (stock limit?)' ) );
    }

    // Recalculate totals so the response (and the next update_checkout) reflects the new price
    $cart->calculate_totals();

    wp_send_json_success( array(
        'quantity' => $qty,
        'subtotal' => $cart->get_cart_subtotal(),
        'total'    => $cart->get_total(),
    ) );
}
