<?php
/** Portfolio home integration and contact endpoint. */
function mim_is_portfolio_home() {
	return is_front_page() || is_page_template( 'page-templates/homepage.php' );
}
add_filter( 'body_class', function ( $classes ) {
	if ( mim_is_portfolio_home() ) { $classes[] = 'portfolio-home'; }
	return $classes;
} );
add_action( 'wp_enqueue_scripts', function () {
	if ( ! mim_is_portfolio_home() ) { return; }
	wp_dequeue_style( 'mim-header' );
	wp_dequeue_style( 'swiper-css' );
	wp_dequeue_script( 'swiper-js' );
	wp_enqueue_style( 'home-style', get_template_directory_uri() . '/assets/styles/home.css', array(), filemtime( get_template_directory() . '/assets/styles/home.css' ) );
	wp_enqueue_script( 'portfolio-home', get_template_directory_uri() . '/assets/scripts/portfolio.js', array(), filemtime( get_template_directory() . '/assets/scripts/portfolio.js' ), true );
	wp_scripts()->registered['alpine']->deps[] = 'portfolio-home';
}, 20 );
function mim_portfolio_contact() {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! check_ajax_referer( 'portfolio_contact', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'اعتبار فرم منقضی شده؛ صفحه را تازه کنید.' ), 403 );
	}
	$data = array();
	foreach ( array( 'name', 'email', 'subject', 'description', 'website' ) as $field ) {
		if ( isset( $_POST[$field] ) && ! is_string( $_POST[$field] ) ) { wp_send_json_error( array( 'message' => 'اطلاعات فرم معتبر نیست.' ), 400 ); }
		$value = isset( $_POST[$field] ) ? wp_unslash( $_POST[$field] ) : '';
		$data[$field] = 'description' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
	if ( $data['website'] || ! is_email( $data['email'] ) || mb_strlen( $data['name'] ) < 3 || mb_strlen( $data['subject'] ) < 3 || mb_strlen( $data['description'] ) < 10 || mb_strlen( $data['description'] ) > 5000 || strlen( $data['email'] ) > 200 || mb_strlen( $data['name'] ) > 200 || mb_strlen( $data['subject'] ) > 200 ) {
		wp_send_json_error( array( 'message' => 'نام و موضوع حداقل ۳ حرف، پیام حداقل ۱۰ حرف و ایمیل معتبر لازم است.' ), 400 );
	}
	$key = 'portfolio_contact_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '', wp_salt() );
	if ( get_transient( $key ) ) { wp_send_json_error( array( 'message' => 'لطفاً یک دقیقه بعد دوباره تلاش کنید.' ), 429 ); }
	set_transient( $key, 1, MINUTE_IN_SECONDS );
	$sent = wp_mail( 'themestermim@gmail.com', $data['subject'], "Name: " . $data['name'] . "\nEmail: " . $data['email'] . "\n\n" . $data['description'], array( 'Reply-To: ' . sanitize_email( $data['email'] ) ) );
	if ( ! $sent ) { wp_send_json_error( array( 'message' => 'ارسال ایمیل انجام نشد. لطفاً با themestermim@gmail.com تماس بگیرید.' ), 500 ); }
	wp_send_json_success( array( 'message' => 'پیام شما برای ارسال پذیرفته شد. ممنون از تماس شما.' ) );
}
add_action( 'wp_ajax_portfolio_contact', 'mim_portfolio_contact' );
add_action( 'wp_ajax_nopriv_portfolio_contact', 'mim_portfolio_contact' );
