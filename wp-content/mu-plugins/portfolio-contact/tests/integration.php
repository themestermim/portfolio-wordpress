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

$http_calls = array();
$http_mode = 'success';
$message_id = 100;
$mail_calls = 0;
add_filter( 'pre_option_mim_bale_token', function () { return '12345:local_test_only'; } );
add_filter( 'pre_option_mim_bale_chat_id', function () { return '12345'; } );
add_filter( 'pre_wp_mail', function () use ( &$mail_calls ) { $mail_calls++; return false; } );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$http_calls, &$http_mode, &$message_id ) {
	if ( ! str_starts_with( $url, 'https://tapi.bale.ai/bot' ) ) {
		return new WP_Error( 'test_blocked', 'Network disabled in tests.' );
	}
	$http_calls[] = json_decode( $args['body'], true );
	if ( 'transport' === $http_mode ) {
		return new WP_Error( 'http_request_failed', 'Connection failed: secret token must not persist.' );
	}
	if ( 'partial' === $http_mode && count( $http_calls ) === 2 ) {
		return new WP_Error( 'http_request_failed', 'Simulated interruption.' );
	}
	$code = 'permanent' === $http_mode ? 401 : ( 'limited' === $http_mode ? 429 : 200 );
	$body = 200 === $code ? array( 'ok' => true, 'result' => array( 'message_id' => ++$message_id ) ) : array( 'ok' => false, 'error_code' => $code, 'parameters' => array( 'retry_after' => 7200 ) );
	return array( 'response' => array( 'code' => $code ), 'body' => wp_json_encode( $body ), 'headers' => array() );
}, 10, 3 );
add_filter( 'wp_die_ajax_handler', function () {
	return function () { throw new RuntimeException( 'ajax_done' ); };
} );

$table = mim_contact_table();
$transaction = false;
try {
	mim_test_check( (bool) wp_next_scheduled( 'mim_contact_dispatch' ), 'Cron scheduled' );
	mim_test_check( 1800 === wp_get_schedules()['mim_contact_half_hour']['interval'], 'Cron interval is thirty minutes' );
	$engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ) );
	mim_test_check( $engine && 'InnoDB' === $engine->Engine, 'Transactional table installed' );
	$wpdb->query( 'START TRANSACTION' );
	$transaction = true;
	// Isolate existing real requests from the simulated worker, then restore via rollback.
	$wpdb->query( "UPDATE $table SET status = 'test_held'" );
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
	$no_token = function () { return ''; };
	add_filter( 'pre_option_mim_bale_token', $no_token, 20 );
	$before = count( $http_calls );
	mim_contact_dispatch();
	mim_test_check( $before === count( $http_calls ) && 'pending' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $id ) ), 'Missing Bale configuration preserves pending requests' );
	remove_filter( 'pre_option_mim_bale_token', $no_token, 20 );

	mim_contact_dispatch();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	mim_test_check( 'sent' === $row->status && $row->sent_at && 1 === (int) $row->next_part, 'Successful delivery checkpointed and marked sent' );
	$call_count = count( $http_calls );
	mim_contact_dispatch();
	mim_test_check( $call_count === count( $http_calls ), 'Already sent messages skipped' );
	$http_mode = 'transport';
	$retry_id = mim_test_insert();
	mim_contact_dispatch();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $retry_id ) );
	mim_test_check( 'pending' === $row->status && 1 === (int) $row->attempts && ! str_contains( $row->last_error, 'secret token' ), 'Transport failure queued without leaking token' );
	mim_test_check( strtotime( $row->next_attempt_at . ' UTC' ) >= time() + 1790, 'Retry delayed at least thirty minutes' );
	$http_mode = 'permanent';
	$failed_id = mim_test_insert();
	mim_contact_dispatch();
	mim_test_check( 'failed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $failed_id ) ), 'Permanent error marked failed' );
	$http_mode = 'transport';
	$exhausted_id = mim_test_insert();
	$wpdb->update( $table, array( 'attempts' => 9 ), array( 'id' => $exhausted_id ) );
	mim_contact_dispatch();
	mim_test_check( 'failed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $exhausted_id ) ), 'Ten failed attempts require manual retry' );
	$http_mode = 'limited';
	$limited_id = mim_test_insert();
	mim_contact_dispatch();
	$next_attempt = $wpdb->get_var( $wpdb->prepare( "SELECT next_attempt_at FROM $table WHERE id = %d", $limited_id ) );
	mim_test_check( strtotime( $next_attempt . ' UTC' ) >= time() + 7190, 'Bale retry_after respected' );
	$http_mode = 'partial';
	$http_calls = array();
	$long_id = mim_test_insert( array( 'description' => str_repeat( 'متن😀', 1250 ) ) );
	mim_contact_dispatch();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $long_id ) );
	mim_test_check( 'pending' === $row->status && 1 === (int) $row->next_part, 'Partial delivery preserves progress' );
	foreach ( $http_calls as $call ) {
		mim_test_check( strlen( mb_convert_encoding( $call['text'], 'UTF-16LE', 'UTF-8' ) ) / 2 <= 4096, 'Message part fits Bale length limit' );
	}
	$wpdb->update( $table, array( 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ), array( 'id' => $long_id ) );
	$http_mode = 'success';
	$http_calls = array();
	mim_contact_dispatch();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $long_id ) );
	mim_test_check( 'sent' === $row->status && 3 === count( $http_calls ), 'Retry sends only remaining parts' );
	$locked_id = mim_test_insert();
	$wpdb->update( $table, array( 'status' => 'processing', 'lock_token' => wp_generate_uuid4(), 'locked_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $locked_id ) );
	$before = count( $http_calls );
	mim_contact_dispatch();
	mim_test_check( $before === count( $http_calls ), 'Active processing lease skipped' );
	$wpdb->update( $table, array( 'locked_at' => gmdate( 'Y-m-d H:i:s', time() - 601 ) ), array( 'id' => $locked_id ) );
	mim_contact_dispatch();
	mim_test_check( 'sent' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $locked_id ) ), 'Expired processing lease recovered' );
	mim_test_check( 0 === $mail_calls, 'No email sent' );
	$wpdb->query( 'ROLLBACK' );
	$transaction = false;
	echo 'All integration checks passed; test rows rolled back.' . PHP_EOL;
} catch ( Throwable $error ) {
	if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
