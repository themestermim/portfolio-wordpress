<?php
/** Local integration tests: MIM_CONTACT_TESTS=1 php <this file>. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'MIM_CONTACT_TESTS' ) ) {
	exit( 'Local CLI tests only.' );
}
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTP_HOST'] = 'portfolio.test';
$_SERVER['SERVER_NAME'] = 'portfolio.test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '192.0.2.123';
define( 'DOING_AJAX', true );
require dirname( __DIR__, 4 ) . '/wp-load.php';

function mim_test_check( $condition, $label ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	echo 'PASS: ' . $label . PHP_EOL;
}
function mim_test_rate_clear() {
	delete_transient( 'mim_contact_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'], wp_salt() ) );
}
function mim_test_data() {
	return array( 'name' => 'کاربر آزمایشی', 'phone' => '۰۹۱۲ ۳۴۵ ۶۷۸۹', 'email' => '', 'description' => 'این پیام فقط برای تست ذخیره‌سازی است.', 'request_id' => wp_generate_uuid4(), 'website' => '' );
}
function mim_test_insert( $overrides = array() ) {
	mim_test_rate_clear();
	$data = mim_contact_validate( array_merge( mim_test_data(), $overrides ) );
	if ( is_wp_error( $data ) ) {
		throw new RuntimeException( 'Invalid test fixture.' );
	}
	$id = mim_contact_store( $data );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Could not store test fixture.' );
	}
	return $id;
}

$http_calls = 0;
$mail_calls = 0;
add_filter( 'pre_wp_mail', function () use ( &$mail_calls ) { $mail_calls++; return false; } );
add_filter( 'pre_http_request', function () use ( &$http_calls ) {
	$http_calls++;
	return new WP_Error( 'test_blocked', 'Network disabled in tests.' );
} );
add_filter( 'wp_die_ajax_handler', function () {
	return function () { throw new RuntimeException( 'ajax_done' ); };
} );

$table = mim_contact_table();
$transaction = false;
try {
	mim_test_check( ! wp_next_scheduled( 'mim_contact_dispatch' ), 'Delivery cron removed' );
	mim_test_check( ! has_action( 'mim_contact_dispatch' ) && ! function_exists( 'mim_contact_bale_request' ), 'Delivery code removed' );
	$engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ) );
	mim_test_check( $engine && 'InnoDB' === $engine->Engine, 'Transactional table installed' );
	$wpdb->query( 'START TRANSACTION' );
	$transaction = true;
	// Test writes are rolled back, leaving existing requests intact.

	$data = mim_contact_validate( mim_test_data() );
	mim_test_check( ! is_wp_error( $data ) && '' === $data['email'] && '09123456789' === $data['phone'], 'Optional email and Persian phone accepted' );
	foreach ( array( '+989123456789', '00989123456789', '۹۸۹۱۲۳۴۵۶۷۸۹', '٠٩١٢٣٤٥٦٧٨٩' ) as $phone ) {
		mim_test_check( '09123456789' === mim_contact_normalize_phone( $phone ), 'International/Arabic phone normalization' );
	}
	foreach ( array( array( 'phone' => 'invalid' ), array( 'name' => '' ), array( 'description' => '' ), array( 'email' => 'broken' ), array( 'phone' => array() ), array( 'website' => 'spam' ), array( 'request_id' => '' ) ) as $invalid ) {
		mim_test_check( is_wp_error( mim_contact_validate( array_merge( mim_test_data(), $invalid ) ) ), 'Invalid input rejected' );
	}
	mim_test_rate_clear();
	$id = mim_contact_store( $data );
	mim_test_check( is_int( $id ), 'Request persisted' );
	mim_test_check( $id === mim_contact_store( $data ), 'Retry is idempotent even during rate limit' );
	$conflict = $data;
	$conflict['description'] .= ' changed';
	mim_test_check( is_wp_error( mim_contact_store( $conflict ) ), 'Same request ID with changed content rejected' );
	$limited = mim_contact_store( mim_contact_validate( mim_test_data() ) );
	mim_test_check( is_wp_error( $limited ) && 'rate_limit' === $limited->get_error_code(), 'New submissions rate limited' );

	mim_test_rate_clear();
	$_POST = wp_slash( array_merge( mim_test_data(), array( 'nonce' => wp_create_nonce( 'portfolio_contact' ) ) ) );
	$_REQUEST = $_POST;
	ob_start();
	try { mim_portfolio_contact(); } catch ( RuntimeException $error ) { if ( 'ajax_done' !== $error->getMessage() ) { throw $error; } }
	$ajax = json_decode( ob_get_clean(), true );
	mim_test_check( true === $ajax['success'] && (int) $ajax['data']['id'] > 0, 'AJAX success follows database insert' );
	$_POST['nonce'] = 'invalid';
	$_REQUEST = $_POST;
	ob_start();
	try { mim_portfolio_contact(); } catch ( RuntimeException $error ) { if ( 'ajax_done' !== $error->getMessage() ) { throw $error; } }
	$ajax_invalid = json_decode( ob_get_clean(), true );
	mim_test_check( false === $ajax_invalid['success'], 'Invalid nonce rejected' );
	mim_test_rate_clear();
	$_POST = wp_slash( array_merge( mim_test_data(), array( 'nonce' => wp_create_nonce( 'portfolio_contact' ) ) ) );
	$_REQUEST = $_POST;
	$original_prefix = $wpdb->prefix;
	$original_suppression = $wpdb->suppress_errors( true );
	$wpdb->prefix = 'mim_missing_test_' . wp_rand( 100000, 999999 ) . '_';
	ob_start();
	try { mim_portfolio_contact(); } catch ( RuntimeException $error ) { if ( 'ajax_done' !== $error->getMessage() ) { throw $error; } }
	$ajax_storage_error = json_decode( ob_get_clean(), true );
	$wpdb->prefix = $original_prefix;
	$wpdb->suppress_errors( $original_suppression );
	mim_test_check( false === $ajax_storage_error['success'], 'Database failure never reports success' );
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	mim_test_check( ! empty( $admin ), 'Administrator available' );
	wp_set_current_user( $admin[0]->ID );
	$input = array( 'message_id' => $id, '_wpnonce' => wp_create_nonce( 'mim_contact_review_' . $id ), 'admin_note' => "تماس گرفته شد\nمنتظر پاسخ", 'operation' => 'save_note' );
	mim_test_check( true === mim_contact_update_request( $input ), 'Private note saved' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	mim_test_check( $input['admin_note'] === $row->admin_note && null === $row->reviewed_at, 'Saving a note does not mark reviewed' );
	$input['operation'] = 'review';
	mim_test_check( true === mim_contact_update_request( $input ), 'Request marked reviewed' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	mim_test_check( ! empty( $row->reviewed_at ) && $input['admin_note'] === $row->admin_note, 'Review time and note persisted together' );
	$reviewed_at = $row->reviewed_at;
	$input['operation'] = 'save_note';
	$input['admin_note'] = '<b>یادداشت ویرایش شد</b>';
	mim_test_check( true === mim_contact_update_request( $input ), 'Reviewed request note remains editable' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	mim_test_check( $reviewed_at === $row->reviewed_at && ! str_contains( $row->admin_note, '<b>' ), 'Review date preserved and note sanitized' );
	$input['_wpnonce'] = 'invalid';
	mim_test_check( 'nonce' === mim_contact_update_request( $input )->get_error_code(), 'Invalid admin nonce rejected' );
	$input['_wpnonce'] = wp_create_nonce( 'mim_contact_review_' . $id );
	$input['admin_note'] = array( 'invalid' );
	mim_test_check( 'invalid' === mim_contact_update_request( $input )->get_error_code(), 'Invalid note rejected' );
	$input['admin_note'] = 'ok';
	$input['operation'] = 'delete';
	mim_test_check( 'invalid' === mim_contact_update_request( $input )->get_error_code(), 'Unknown operation rejected' );
	wp_set_current_user( 0 );
	mim_test_check( 'forbidden' === mim_contact_update_request( $input )->get_error_code(), 'Unauthenticated updates rejected' );
	wp_set_current_user( $admin[0]->ID );
	$_GET['paged'] = 1;
	ob_start();
	mim_contact_admin_page();
	$html = ob_get_clean();
	mim_test_check( str_contains( $html, 'درخواست‌های تماس' ) && str_contains( $html, 'name="admin_note"' ) && str_contains( $html, 'value="review"' ), 'Admin table includes notes and review controls' );
	mim_test_check( 0 === $http_calls && 0 === $mail_calls, 'No external delivery or email' );
	$wpdb->query( 'ROLLBACK' );
	$transaction = false;
	echo 'All integration checks passed; test rows rolled back.' . PHP_EOL;
} catch ( Throwable $error ) {
	if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
