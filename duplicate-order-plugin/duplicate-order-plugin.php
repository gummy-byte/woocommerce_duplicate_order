<?php
/**
 * Plugin Name: WooCommerce Duplicate Order
 * Description: A plugin to duplicate a WooCommerce order and set the new order status to pending.
 * Version: 1.0
 * Author: Jules
 */

add_filter( 'woocommerce_admin_order_actions', 'wcdop_add_duplicate_order_action', 10, 2 );

function wcdop_add_duplicate_order_action( $actions, $order ) {
    $actions['duplicate'] = array(
        'url'    => wp_nonce_url( admin_url( 'admin.php?action=wcdop_duplicate_order&order_id=' . $order->get_id() ), 'duplicate-order' ),
        'name'   => __( 'Duplicate', 'woocommerce-duplicate-order' ),
        'action' => 'duplicate',
    );
    return $actions;
}

add_action( 'admin_action_wcdop_duplicate_order', 'wcdop_duplicate_order' );

function wcdop_duplicate_order() {
    if ( ! isset( $_GET['order_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
        wp_die( esc_html__( 'Invalid request.', 'woocommerce-duplicate-order' ) );
    }

    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'duplicate-order' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'woocommerce-duplicate-order' ) );
    }

    $order_id = absint( $_GET['order_id'] );
    $order    = wc_get_order( $order_id );

    if ( ! $order ) {
        wp_die( esc_html__( 'Invalid order.', 'woocommerce-duplicate-order' ) );
    }

    $new_order = wc_create_order( [
        'status'      => 'pending',
        'customer_id' => $order->get_customer_id(),
    ] );

    foreach ( $order->get_items() as $item ) {
        $new_item_id = $new_order->add_product( $item->get_product(), $item->get_quantity(), [
            'variation' => $item->get_variation_id() ? wc_get_product( $item->get_variation_id() )->get_attributes() : [],
            'totals'    => [
                'subtotal'     => $item->get_subtotal(),
                'subtotal_tax' => $item->get_subtotal_tax(),
                'total'        => $item->get_total(),
                'total_tax'    => $item->get_total_tax(),
            ],
        ] );

        if ( $new_item_id ) {
            $new_item = $new_order->get_item( $new_item_id );
            foreach ( $item->get_meta_data() as $meta ) {
                $new_item->add_meta_data( $meta->key, $meta->value );
            }
            $new_item->save();
        }
    }

    foreach ( $order->get_items( 'fee' ) as $item ) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( $item->get_name() );
        $fee->set_amount( $item->get_total() );
        $fee->set_tax_class( $item->get_tax_class() );
        $fee->set_tax_status( $item->get_tax_status() );
        $fee->set_total( $item->get_total() );
        $new_order->add_item( $fee );
    }

    foreach ( $order->get_items( 'shipping' ) as $item ) {
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title( $item->get_method_title() );
        $shipping->set_method_id( $item->get_method_id() );
        $shipping->set_total( $item->get_total() );
        $new_order->add_item( $shipping );
    }

    foreach ( $order->get_items( 'tax' ) as $item ) {
        $tax = new WC_Order_Item_Tax();
        $tax->set_rate_id( $item->get_rate_id() );
        $tax->set_tax_total( $item->get_tax_total() );
        $tax->set_shipping_tax_total( $item->get_shipping_tax_total() );
        $new_order->add_item( $tax );
    }

    foreach ( $order->get_items( 'coupon' ) as $item ) {
        $coupon = new WC_Order_Item_Coupon();
        $coupon->set_code( $item->get_code() );
        $coupon->set_discount( $item->get_discount() );
        $coupon->set_discount_tax( $item->get_discount_tax() );
        $new_order->add_item( $coupon );
    }

    $billing_address = $order->get_address( 'billing' );
    if ( ! empty( $billing_address ) ) {
        $new_order->set_billing_first_name( $billing_address['first_name'] );
        $new_order->set_billing_last_name( $billing_address['last_name'] );
        $new_order->set_billing_company( $billing_address['company'] );
        $new_order->set_billing_address_1( $billing_address['address_1'] );
        $new_order->set_billing_address_2( $billing_address['address_2'] );
        $new_order->set_billing_city( $billing_address['city'] );
        $new_order->set_billing_state( $billing_address['state'] );
        $new_order->set_billing_postcode( $billing_address['postcode'] );
        $new_order->set_billing_country( $billing_address['country'] );
        $new_order->set_billing_phone( $billing_address['phone'] );
        $new_order->set_billing_email( $billing_address['email'] );
    }

    $shipping_address = $order->get_address( 'shipping' );
    if ( ! empty( $shipping_address ) ) {
        $new_order->set_shipping_first_name( $shipping_address['first_name'] );
        $new_order->set_shipping_last_name( $shipping_address['last_name'] );
        $new_order->set_shipping_company( $shipping_address['company'] );
        $new_order->set_shipping_address_1( $shipping_address['address_1'] );
        $new_order->set_shipping_address_2( $shipping_address['address_2'] );
        $new_order->set_shipping_city( $shipping_address['city'] );
        $new_order->set_shipping_state( $shipping_address['state'] );
        $new_order->set_shipping_postcode( $shipping_address['postcode'] );
        $new_order->set_shipping_country( $shipping_address['country'] );
    }
    $new_order->set_payment_method( $order->get_payment_method() );
    $new_order->set_payment_method_title( $order->get_payment_method_title() );

    $meta_blacklist = [
        '_transaction_id',
        '_payment_method_token',
        '_date_paid',
    ];

    foreach ( $order->get_meta_data() as $meta ) {
        if ( ! in_array( $meta->key, $meta_blacklist, true ) ) {
            $new_order->add_meta_data( $meta->key, $meta->value );
        }
    }

    $new_order->calculate_totals();
    $new_order->save();

    wp_redirect( admin_url( 'post.php?post=' . $new_order->get_id() . '&action=edit' ) );
    exit;
}
