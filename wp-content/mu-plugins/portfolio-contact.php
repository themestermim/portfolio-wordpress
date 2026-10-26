<?php
/**
 * Plugin Name: Portfolio Contact Queue
 * Description: Stores contact requests and delivers them to Bale every thirty minutes.
 */
defined( 'ABSPATH' ) || exit;

function mim_contact_table() {
	global $wpdb;
	return $wpdb->prefix . 'portfolio_messages';
}

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['mim_contact_half_hour'] = array( 'interval' => 1800, 'display' => 'هر نیم ساعت' );
	return $schedules;
} );

function mim_contact_install() {
	global $wpdb;
	if ( '1' !== get_option( 'mim_contact_schema_version' ) ) {
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
			update_option( 'mim_contact_schema_version', '1', false );
		}
	}
	if ( ! wp_next_scheduled( 'mim_contact_dispatch' ) ) {
		wp_schedule_event( time() + 1800, 'mim_contact_half_hour', 'mim_contact_dispatch' );
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

function mim_contact_bale_config() {
	return array(
		'token' => defined( 'MIM_BALE_BOT_TOKEN' ) ? MIM_BALE_BOT_TOKEN : get_option( 'mim_bale_token', '' ),
		'chat_id' => defined( 'MIM_BALE_CHAT_ID' ) ? MIM_BALE_CHAT_ID : get_option( 'mim_bale_chat_id', '' ),
	);
}

function mim_contact_bale_request( $method, $body ) {
	$config = mim_contact_bale_config();
	if ( ! $config['token'] ) {
		return new WP_Error( 'configuration', 'توکن ربات تنظیم نشده است.' );
	}
	if ( ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]+$/', $config['token'] ) || ! in_array( $method, array( 'sendMessage', 'getUpdates' ), true ) ) {
		return new WP_Error( 'permanent', 'تنظیمات اتصال بله معتبر نیست.' );
	}
	$response = wp_remote_post( 'https://tapi.bale.ai/bot' . $config['token'] . '/' . $method, array(
		'timeout' => 10, 'redirection' => 0,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body' => wp_json_encode( $body ),
	) );
	if ( is_wp_error( $response ) ) {
		// Do not persist transport errors that could contain the bot token in a URL.
		return new WP_Error( 'transport', 'ارتباط با بله برقرار نشد.' );
	}
	$code = wp_remote_retrieve_response_code( $response );
	$result = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code >= 200 && $code < 300 && is_array( $result ) && true === ( $result['ok'] ?? false ) && isset( $result['result'] ) ) {
		return $result['result'];
	}
	$error_code = (int) ( $result['error_code'] ?? $code );
	$permanent = in_array( $error_code, array( 400, 401, 403, 404 ), true );
	return new WP_Error( $permanent ? 'permanent' : 'temporary', 'خطای بله: ' . $error_code, array(
		'retry_after' => max( 1800, (int) ( $result['parameters']['retry_after'] ?? 1800 ) ),
	) );
}

function mim_contact_message_parts( $row ) {
	$chunks = mb_str_split( $row->message, 1500, 'UTF-8' );
	$parts = array();
	foreach ( $chunks as $index => $chunk ) {
		$parts[] = "درخواست تماس #{$row->id}\nنام: {$row->full_name}\nشماره تماس: {$row->phone}\nایمیل: " . ( $row->email ?: 'ثبت نشده' ) . "\nزمان ثبت (UTC): {$row->created_at}\nبخش " . ( $index + 1 ) . '/' . count( $chunks ) . "\n\n" . $chunk;
	}
	return $parts;
}

function mim_contact_dispatch() {
	global $wpdb;
	$config = mim_contact_bale_config();
	if ( ! $config['token'] || ! $config['chat_id'] ) {
		return;
	}
	$table = mim_contact_table();
	$now = gmdate( 'Y-m-d H:i:s' );
	$expired = gmdate( 'Y-m-d H:i:s', time() - 600 );
	$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'pending', lock_token = NULL, locked_at = NULL WHERE status = 'processing' AND locked_at < %s", $expired ) );
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE status = 'pending' AND next_attempt_at <= %s ORDER BY id ASC LIMIT 20", $now ) );
	$started = microtime( true );
	foreach ( $ids as $id ) {
		if ( microtime( true ) - $started > 40 ) {
			break;
		}
		$token = wp_generate_uuid4();
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'processing', lock_token = %s, locked_at = %s, attempts = attempts + 1 WHERE id = %d AND status = 'pending' AND next_attempt_at <= %s", $token, gmdate( 'Y-m-d H:i:s' ), $id, $now ) );
		if ( 1 !== $claimed ) {
			continue;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND lock_token = %s", $id, $token ) );
		if ( ! $row ) {
			continue;
		}
		$parts = mim_contact_message_parts( $row );
		$message_ids = json_decode( $row->bale_message_ids, true ) ?: array();
		$error = null;
		$checkpoint_failed = false;
		for ( $part = (int) $row->next_part; $part < count( $parts ); $part++ ) {
			$result = mim_contact_bale_request( 'sendMessage', array( 'chat_id' => $config['chat_id'], 'text' => $parts[$part] ) );
			if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['message_id'] ) ) {
				$error = is_wp_error( $result ) ? $result : new WP_Error( 'temporary', 'پاسخ ارسال بله معتبر نیست.' );
				break;
			}
			$message_ids[] = $result['message_id'];
			$saved = $wpdb->update( $table, array( 'next_part' => $part + 1, 'bale_message_ids' => wp_json_encode( $message_ids ), 'locked_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id, 'lock_token' => $token ) );
			if ( 1 !== $saved ) {
				$checkpoint_failed = true;
				break;
			}
		}
		if ( $checkpoint_failed ) {
			// Keep the lease: a later recovery may retry this part after a DB failure.
			continue;
		}
		if ( $error ) {
			$permanent = 'permanent' === $error->get_error_code() || (int) $row->attempts >= 10;
			$error_data = $error->get_error_data();
			$delay = max( 1800, min( DAY_IN_SECONDS, 1800 * ( 2 ** min( (int) $row->attempts - 1, 5 ) ) ), (int) ( $error_data['retry_after'] ?? 0 ) );
			$wpdb->update( $table, array( 'status' => $permanent ? 'failed' : 'pending', 'last_error' => $error->get_error_message(), 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ), 'lock_token' => null, 'locked_at' => null ), array( 'id' => $id, 'lock_token' => $token ) );
		} else {
			$wpdb->update( $table, array( 'status' => 'sent', 'sent_at' => gmdate( 'Y-m-d H:i:s' ), 'last_error' => '', 'lock_token' => null, 'locked_at' => null ), array( 'id' => $id, 'lock_token' => $token ) );
		}
	}
}
add_action( 'mim_contact_dispatch', 'mim_contact_dispatch' );

add_action( 'admin_menu', function () {
	add_management_page( 'پیام‌های تماس و بله', 'پیام‌های تماس و بله', 'manage_options', 'mim-contact', 'mim_contact_admin_page' );
} );

function mim_contact_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mim_contact_settings' );
	$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( $_POST['operation'] ) : '';
	$notice = 'تنظیمات ذخیره شد.';
	if ( 'save' === $operation ) {
		$token = isset( $_POST['bot_token'] ) && is_string( $_POST['bot_token'] ) ? trim( wp_unslash( $_POST['bot_token'] ) ) : '';
		$chat = isset( $_POST['chat_id'] ) && is_string( $_POST['chat_id'] ) ? trim( wp_unslash( $_POST['chat_id'] ) ) : '';
		if ( ( $token && ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]+$/', $token ) ) || ( $chat && ! preg_match( '/^(?:-?[0-9]+|@[A-Za-z0-9_]+)$/', $chat ) ) ) {
			$notice = 'توکن یا شناسه گفتگو معتبر نیست.';
		} else {
			if ( $token && ! defined( 'MIM_BALE_BOT_TOKEN' ) ) {
				update_option( 'mim_bale_token', $token, false );
			}
			if ( ! defined( 'MIM_BALE_CHAT_ID' ) ) {
				update_option( 'mim_bale_chat_id', $chat, false );
			}
		}
	} elseif ( 'discover' === $operation ) {
		$result = mim_contact_bale_request( 'getUpdates', array( 'limit' => 100, 'timeout' => 0 ) );
		if ( is_wp_error( $result ) ) {
			$notice = $result->get_error_message();
		} else {
			$chats = array();
			foreach ( (array) $result as $update ) {
				$chat = $update['message']['chat'] ?? null;
				if ( is_array( $chat ) && isset( $chat['id'] ) ) {
					$chats[(string) $chat['id']] = sanitize_text_field( $chat['title'] ?? trim( ( $chat['first_name'] ?? '' ) . ' ' . ( $chat['last_name'] ?? '' ) ) );
				}
			}
			set_transient( 'mim_contact_chats_' . get_current_user_id(), $chats, 10 * MINUTE_IN_SECONDS );
			$notice = $chats ? 'شناسه‌های گفتگو پایین صفحه نمایش داده شدند؛ شناسه مقصد خود را ذخیره کنید.' : 'گفتگویی پیدا نشد. ابتدا در بله به ربات خود پیام بدهید و دوباره تلاش کنید.';
		}
	} elseif ( 'retry' === $operation ) {
		global $wpdb;
		$table = mim_contact_table();
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'pending', attempts = 0, next_attempt_at = %s, last_error = '' WHERE status = 'failed'", gmdate( 'Y-m-d H:i:s' ) ) );
		$notice = 'پیام‌های ناموفق برای تلاش مجدد در صف قرار گرفتند.';
	}
	set_transient( 'mim_contact_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'tools.php?page=mim-contact' ) );
	exit;
}
add_action( 'admin_post_mim_contact_settings', 'mim_contact_admin_action' );

function mim_contact_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$config = mim_contact_bale_config();
	$table = mim_contact_table();
	$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT 20 OFFSET %d", ( $page - 1 ) * 20 ) );
	$notice = get_transient( 'mim_contact_notice_' . get_current_user_id() );
	delete_transient( 'mim_contact_notice_' . get_current_user_id() );
	$chats = get_transient( 'mim_contact_chats_' . get_current_user_id() );
	$statuses = array( 'pending' => 'در انتظار ارسال', 'processing' => 'در حال ارسال', 'sent' => 'ارسال شده', 'failed' => 'ناموفق؛ نیازمند بررسی' );
	?>
	<div class="wrap" dir="rtl">
		<h1>پیام‌های تماس و اتصال بله</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-info">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>
		<p>فرم‌ها ابتدا ذخیره می‌شوند و هر نیم ساعت برای ارسال بررسی می‌شوند. بدون تنظیم ربات نیز ثبت فرم فعال است.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mim_contact_settings">
			<?php wp_nonce_field( 'mim_contact_settings' ); ?>
			<p>
				<label for="mim-bot-token">توکن ربات بله؛ برای حفظ توکن فعلی خالی بگذارید</label>
			</p>
			<input type="password" id="mim-bot-token" name="bot_token" class="regular-text" dir="ltr" autocomplete="new-password" value="" <?php disabled( defined( 'MIM_BALE_BOT_TOKEN' ) ); ?>>
			<p><?php echo $config['token'] ? 'توکن تنظیم شده است.' : 'توکن هنوز تنظیم نشده است.'; ?></p>
			<p>
				<label for="mim-chat-id">شناسه گفتگوی مقصد (chat_id)</label>
			</p>
			<input type="text" id="mim-chat-id" name="chat_id" class="regular-text" dir="ltr" value="<?php echo esc_attr( $config['chat_id'] ); ?>" <?php disabled( defined( 'MIM_BALE_CHAT_ID' ) ); ?>>
			<p>
				<button class="button button-primary" name="operation" value="save">ذخیره تنظیمات</button>
				<button class="button" name="operation" value="discover">دریافت شناسه گفتگوها</button>
				<button class="button" name="operation" value="retry">تلاش مجدد پیام‌های ناموفق</button>
			</p>
		</form>
		<?php if ( $chats ) : ?>
			<ul>
				<?php foreach ( $chats as $chat_id => $name ) : ?>
					<li><?php echo esc_html( $name . ' — chat_id: ' . $chat_id ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p>نوبت بعدی بررسی صف: <?php echo esc_html( wp_date( 'Y/m/d H:i', wp_next_scheduled( 'mim_contact_dispatch' ) ?: time() ) ); ?>؛ اجرای دقیق به کرون سرور نیاز دارد.</p>
		<h2>پیام‌های ثبت‌شده (<?php echo esc_html( $total ); ?>)</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>شناسه / تاریخ</th>
					<th>فرستنده</th>
					<th>پیام</th>
					<th>وضعیت بله</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td>
							<p>#<?php echo esc_html( $row->id ); ?></p>
							<p><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y/m/d H:i' ) ); ?></p>
						</td>
						<td>
							<p><?php echo esc_html( $row->full_name ); ?></p>
							<p><?php echo esc_html( $row->phone ); ?></p>
							<p><?php echo esc_html( $row->email ); ?></p>
						</td>
						<td><?php echo nl2br( esc_html( $row->message ) ); ?></td>
						<td>
							<p><?php echo esc_html( $statuses[$row->status] ?? $row->status ); ?></p>
							<p>تعداد تلاش: <?php echo esc_html( $row->attempts ); ?></p>
							<p><?php echo esc_html( $row->last_error ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', admin_url( 'tools.php?page=mim-contact' ) ), 'total' => max( 1, (int) ceil( $total / 20 ) ), 'current' => $page ) ) ); ?>
	</div>
	<?php
}
