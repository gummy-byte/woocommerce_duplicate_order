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
        $new_order->add_product( $item->get_product(), $item->get_quantity() );
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

    $new_order->calculate_totals();
    $new_order->save();

    wp_redirect( admin_url( 'post.php?post=' . $new_order->get_id() . '&action=edit' ) );
    exit;
}

add_action( 'admin_enqueue_scripts', 'wcdop_enqueue_styles' );

function wcdop_enqueue_styles( $hook ) {
    if ( 'edit.php' !== $hook ) {
        return;
    }

    global $post_type;
    if ( 'shop_order' !== $post_type ) {
        return;
    }

    wp_enqueue_style(
        'wcdop-styles',
        plugin_dir_url( __FILE__ ) . 'css/style.css',
        ['dashicons'],
        '1.0'
    );
}
