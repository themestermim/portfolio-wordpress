<?php
/** Portfolio home integration and contact endpoint. */
function mim_portfolio_field( $name, $post_id = false ) {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}
	$value = get_field( $name, $post_id ?: get_queried_object_id() );
	return is_string( $value ) ? $value : '';
}

function mim_portfolio_phone_number() {
	$phone = strtr( mim_portfolio_field( 'phone', 'option' ), array_combine(
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' )
	) );
	return preg_replace( '/[^0-9+]/', '', $phone );
}

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
