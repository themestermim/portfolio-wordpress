<?php
/**
 * Plugin Name: Portfolio Contact Requests
 * Description: Stores contact requests with private admin notes and review tracking.
 */
defined( 'ABSPATH' ) || exit;

function mim_contact_table() {
	global $wpdb;
	return $wpdb->prefix . 'portfolio_messages';
}

function mim_contact_install() {
	global $wpdb;
	if ( wp_next_scheduled( 'mim_contact_dispatch' ) ) {
		wp_clear_scheduled_hook( 'mim_contact_dispatch' );
	}
	if ( '2' !== get_option( 'mim_contact_schema_version' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = mim_contact_table();
		$collate = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_id char(36) NOT NULL,
			payload_hash char(64) NOT NULL,
			full_name varchar(200) NOT NULL,
			phone varchar(20) NOT NULL,
			email varchar(200) NOT NULL DEFAULT '',
			message text NOT NULL,
			created_at datetime NOT NULL,
			admin_note text NOT NULL,
			reviewed_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			locked_at datetime DEFAULT NULL,
			lock_token char(36) DEFAULT NULL,
			next_part int unsigned NOT NULL DEFAULT 0,
			bale_message_ids text NOT NULL,
			sent_at datetime DEFAULT NULL,
			last_error text NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY request_id (request_id),
			KEY queue (status,next_attempt_at),
			KEY lease (status,locked_at)
		) $collate;" );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );
		if ( in_array( 'admin_note', $columns, true ) && in_array( 'reviewed_at', $columns, true ) ) {
			delete_option( 'mim_bale_token' );
			delete_option( 'mim_bale_chat_id' );
			update_option( 'mim_contact_schema_version', '2', false );
		}
	}
}
add_action( 'init', 'mim_contact_install', 5 );

function mim_contact_normalize_phone( $phone ) {
	$phone = strtr( $phone, array_combine(
		preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
		str_split( '01234567890123456789' )
	) );
	$phone = preg_replace( '/[\s()\-]+/u', '', $phone );
	if ( preg_match( '/^(?:\+98|0098|98)([1-9][0-9]{9})$/', $phone, $matches ) ) {
		$phone = '0' . $matches[1];
	}
	return $phone;
}

function mim_contact_validate( $input ) {
	$data = array();
	foreach ( array( 'name', 'phone', 'email', 'description', 'website', 'request_id' ) as $key ) {
		if ( isset( $input[$key] ) && ! is_string( $input[$key] ) ) {
			return new WP_Error( 'invalid', 'اطلاعات فرم معتبر نیست.' );
		}
		$value = trim( $input[$key] ?? '' );
		$data[$key] = 'description' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
	$data['phone'] = mim_contact_normalize_phone( $data['phone'] );
	if ( $data['website'] || mb_strlen( $data['name'] ) < 3 || mb_strlen( $data['name'] ) > 200 ) {
		return new WP_Error( 'invalid_name', 'نام و نام خانوادگی را بین ۳ تا ۲۰۰ حرف وارد کنید.' );
	}
	if ( ! preg_match( '/^0[1-9][0-9]{9}$/', $data['phone'] ) ) {
		return new WP_Error( 'invalid_phone', 'شماره تماس معتبر ایران، همراه با کد، وارد کنید؛ مانند ۰۹۱۲۳۴۵۶۷۸۹.' );
	}
	if ( $data['email'] && ( strlen( $data['email'] ) > 200 || ! is_email( $data['email'] ) ) ) {
		return new WP_Error( 'invalid_email', 'ایمیل واردشده معتبر نیست؛ می‌توانید آن را خالی بگذارید.' );
	}
	if ( mb_strlen( $data['description'] ) < 10 || mb_strlen( $data['description'] ) > 5000 ) {
		return new WP_Error( 'invalid_message', 'پیام را بین ۱۰ تا ۵۰۰۰ حرف وارد کنید.' );
	}
	if ( ! wp_is_uuid( $data['request_id'], 4 ) ) {
		return new WP_Error( 'invalid_request', 'شناسه فرم معتبر نیست؛ صفحه را تازه کنید.' );
	}
	return $data;
}

function mim_contact_store( $data ) {
	global $wpdb;
	$table = mim_contact_table();
	$hash = hash( 'sha256', wp_json_encode( array( $data['name'], $data['phone'], $data['email'], $data['description'] ) ) );
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, payload_hash FROM $table WHERE request_id = %s", $data['request_id'] ) );
	if ( $existing ) {
		return hash_equals( $existing->payload_hash, $hash ) ? (int) $existing->id : new WP_Error( 'conflict', 'این شناسه قبلاً ثبت شده؛ صفحه را تازه کنید.' );
	}
	$rate_key = 'mim_contact_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '', wp_salt() );
	if ( get_transient( $rate_key ) ) {
		return new WP_Error( 'rate_limit', 'لطفاً یک دقیقه بعد دوباره تلاش کنید.' );
	}
	$inserted = $wpdb->insert( $table, array(
		'request_id' => $data['request_id'], 'payload_hash' => $hash,
		'full_name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'],
		'message' => $data['description'], 'created_at' => gmdate( 'Y-m-d H:i:s' ),
		'admin_note' => '',
		'next_attempt_at' => gmdate( 'Y-m-d H:i:s' ), 'bale_message_ids' => '[]', 'last_error' => '',
	) );
	if ( ! $inserted ) {
		// A simultaneous retry may have inserted the same request first.
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, payload_hash FROM $table WHERE request_id = %s", $data['request_id'] ) );
		if ( $existing && hash_equals( $existing->payload_hash, $hash ) ) {
			return (int) $existing->id;
		}
		return new WP_Error( 'storage', 'ثبت پیام انجام نشد. اطلاعات فرم حفظ شده؛ دوباره تلاش کنید.' );
	}
	$id = (int) $wpdb->insert_id;
	set_transient( $rate_key, 1, MINUTE_IN_SECONDS );
	return $id;
}

function mim_portfolio_contact() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! check_ajax_referer( 'portfolio_contact', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'اعتبار فرم منقضی شده؛ صفحه را تازه کنید.' ), 403 );
	}
	$data = mim_contact_validate( wp_unslash( $_POST ) );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
	}
	$id = mim_contact_store( $data );
	if ( is_wp_error( $id ) ) {
		$status = 'rate_limit' === $id->get_error_code() ? 429 : ( 'conflict' === $id->get_error_code() ? 409 : 500 );
		wp_send_json_error( array( 'message' => $id->get_error_message() ), $status );
	}
	wp_send_json_success( array( 'message' => 'پیام شما با موفقیت ثبت شد. به‌زودی با شما تماس می‌گیریم.', 'id' => $id ) );
}
add_action( 'wp_ajax_portfolio_contact', 'mim_portfolio_contact' );
add_action( 'wp_ajax_nopriv_portfolio_contact', 'mim_portfolio_contact' );

add_action( 'admin_menu', function () {
	add_management_page( 'درخواست‌های تماس', 'درخواست‌های تماس', 'manage_options', 'mim-contact', 'mim_contact_admin_page' );
} );

function mim_contact_update_request( $input ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'forbidden', 'دسترسی مجاز نیست.' );
	}
	$id = isset( $input['message_id'] ) && is_scalar( $input['message_id'] ) ? absint( $input['message_id'] ) : 0;
	if ( ! isset( $input['_wpnonce'] ) || ! is_string( $input['_wpnonce'] ) || ! wp_verify_nonce( $input['_wpnonce'], 'mim_contact_review_' . $id ) ) {
		return new WP_Error( 'nonce', 'اعتبار فرم منقضی شده؛ صفحه را تازه کنید.' );
	}
	$operation = $input['operation'] ?? '';
	if ( ! in_array( $operation, array( 'save_note', 'review' ), true ) || ! isset( $input['admin_note'] ) || ! is_string( $input['admin_note'] ) || mb_strlen( $input['admin_note'] ) > 10000 ) {
		return new WP_Error( 'invalid', 'یادداشت باید حداکثر ۱۰۰۰۰ حرف باشد و عملیات معتبر باشد.' );
	}
	global $wpdb;
	$table = mim_contact_table();
	if ( ! $id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $id ) ) ) {
		return new WP_Error( 'missing', 'درخواست پیدا نشد.' );
	}
	$note = sanitize_textarea_field( $input['admin_note'] );
	if ( 'review' === $operation ) {
		// Preserve the first review time on repeated submissions.
		$result = $wpdb->query( $wpdb->prepare( "UPDATE $table SET admin_note = %s, reviewed_at = COALESCE(reviewed_at, %s) WHERE id = %d", $note, gmdate( 'Y-m-d H:i:s' ), $id ) );
	} else {
		$result = $wpdb->update( $table, array( 'admin_note' => $note ), array( 'id' => $id ) );
	}
	return false === $result ? new WP_Error( 'storage', 'ذخیره انجام نشد؛ دوباره تلاش کنید.' ) : true;
}

function mim_contact_admin_action() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'روش درخواست مجاز نیست.', '', array( 'response' => 405 ) );
	}
	$result = mim_contact_update_request( wp_unslash( $_POST ) );
	if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'forbidden', 'nonce' ), true ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
	}
	$notice = is_wp_error( $result ) ? $result->get_error_message() : 'یادداشت و وضعیت درخواست ذخیره شد.';
	set_transient( 'mim_contact_notice_' . get_current_user_id(), array( 'message' => $notice, 'error' => is_wp_error( $result ) ), MINUTE_IN_SECONDS );
	$page = isset( $_POST['paged'] ) && is_scalar( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
	wp_safe_redirect( add_query_arg( 'paged', $page, admin_url( 'tools.php?page=mim-contact' ) ) );
	exit;
}
add_action( 'admin_post_mim_contact_review', 'mim_contact_admin_action' );

function mim_contact_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table = mim_contact_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	$pages = max( 1, (int) ceil( $total / 20 ) );
	$page = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? min( $pages, max( 1, absint( $_GET['paged'] ) ) ) : 1;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, full_name, phone, email, message, created_at, admin_note, reviewed_at FROM $table ORDER BY id DESC LIMIT 20 OFFSET %d", ( $page - 1 ) * 20 ) );
	$notice = get_transient( 'mim_contact_notice_' . get_current_user_id() );
	delete_transient( 'mim_contact_notice_' . get_current_user_id() );
	?>
	<div class="wrap" dir="rtl">
		<h1>درخواست‌های تماس</h1>
		<?php if ( is_array( $notice ) ) : ?>
			<div class="notice <?php echo ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>
		<p>درخواست‌های فرم تماس، از جدیدترین به قدیمی‌ترین. یادداشت‌ها فقط برای مدیران سایت قابل مشاهده هستند.</p>
		<h2>درخواست‌های ثبت‌شده (<?php echo esc_html( $total ); ?>)</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col">شناسه / تاریخ</th>
					<th scope="col">فرستنده</th>
					<th scope="col">پیام</th>
					<th scope="col">وضعیت بررسی</th>
					<th scope="col">یادداشت و عملیات</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr>
						<td colspan="5">هنوز درخواستی ثبت نشده است.</td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td>
							<p>#<?php echo esc_html( $row->id ); ?></p>
							<p><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y/m/d H:i' ) ); ?></p>
						</td>
						<td>
							<p><?php echo esc_html( $row->full_name ); ?></p>
							<p dir="ltr"><?php echo esc_html( $row->phone ); ?></p>
							<p dir="ltr"><?php echo esc_html( $row->email ); ?></p>
						</td>
						<td><?php echo nl2br( esc_html( $row->message ) ); ?></td>
						<td>
							<strong><?php echo $row->reviewed_at ? 'بررسی شد' : 'بررسی نشده'; ?></strong>
							<?php if ( $row->reviewed_at ) : ?>
								<p><?php echo esc_html( get_date_from_gmt( $row->reviewed_at, 'Y/m/d H:i' ) ); ?></p>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="mim_contact_review">
								<input type="hidden" name="message_id" value="<?php echo esc_attr( $row->id ); ?>">
								<input type="hidden" name="paged" value="<?php echo esc_attr( $page ); ?>">
								<?php wp_nonce_field( 'mim_contact_review_' . $row->id ); ?>
								<label class="screen-reader-text" for="note-<?php echo esc_attr( $row->id ); ?>">یادداشت درخواست #<?php echo esc_html( $row->id ); ?></label>
								<textarea class="large-text" id="note-<?php echo esc_attr( $row->id ); ?>" name="admin_note" rows="4" maxlength="10000"><?php echo esc_textarea( $row->admin_note ); ?></textarea>
								<p>
									<button type="submit" class="button" name="operation" value="save_note">ذخیره یادداشت</button>
									<?php if ( ! $row->reviewed_at ) : ?>
										<button type="submit" class="button button-primary" name="operation" value="review">بررسی شد</button>
									<?php endif; ?>
								</p>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', admin_url( 'tools.php?page=mim-contact' ) ), 'total' => $pages, 'current' => $page ) ) ); ?>
	</div>
	<?php
}
