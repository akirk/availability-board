<?php
/** Real WordPress/WooCommerce checks: run with wp --user=admin eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    throw new RuntimeException( 'Run through WP-CLI with WordPress and WooCommerce active.' );
}
if ( getenv( 'AVAILABILITY_BOARD_TEST_DATABASE' ) !== DB_NAME ) {
    throw new RuntimeException( 'Confirm the disposable test database with AVAILABILITY_BOARD_TEST_DATABASE.' );
}

$checks = 0;
$assert = static function ( $condition, string $message ) use ( &$checks ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$checks;
};
$admin_id = get_current_user_id();
$assert( current_user_can( 'manage_woocommerce' ), 'The test runner must start as a store manager.' );
$customer_id = wp_insert_user( [
    'user_login' => 'availability_board_ci_customer',
    'user_email' => 'availability-board-customer@example.invalid',
    'user_pass'  => wp_generate_password(),
    'role'      => 'customer',
] );
$assert( ! is_wp_error( $customer_id ), 'The customer fixture must be created.' );
$product = new WC_Product_Simple();
$product->set_name( 'Unchanged product name' );
$product->set_regular_price( '12.50' );
$product->set_stock_status( 'instock' );
$product->save();
$request = static function ( int $id, bool $in_stock ) {
    $request = new WP_REST_Request( 'POST', '/availability-board/v1/products/' . $id . '/availability' );
    $request->set_param( 'in_stock', $in_stock );
    return rest_do_request( $request );
};

try {
    foreach ( [ [ false, 'outofstock' ], [ true, 'instock' ] ] as $case ) {
        $result = $request( $product->get_id(), $case[0] );
        $assert( 200 === $result->get_status(), 'A manager can change availability in either direction.' );
        $assert( [ 'id' => $product->get_id(), 'stock_status' => $case[1] ] === $result->get_data(), 'The response must describe the requested stock state exactly.' );
        $saved = wc_get_product( $product->get_id() );
        $assert( $case[1] === $saved->get_stock_status(), 'The stock update must persist.' );
        $assert( 'Unchanged product name' === $saved->get_name() && '12.50' === $saved->get_regular_price(), 'Availability changes must not edit product details.' );
    }

    wp_set_current_user( $customer_id );
    $denied = $request( $product->get_id(), false );
    $assert( 403 === $denied->get_status(), 'A customer must not change product availability.' );
    $assert( 'rest_forbidden' === $denied->get_data()['code'], 'Denial must come from the route permission check.' );
    $assert( 'instock' === wc_get_product( $product->get_id() )->get_stock_status(), 'Permission denial must leave availability unchanged.' );
    WP_CLI::success( $checks . ' real Availability Board REST and persistence checks passed.' );
} finally {
    wp_set_current_user( $admin_id );
    $product->delete( true );
    if ( ! function_exists( 'wp_delete_user' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    wp_delete_user( $customer_id );
}
