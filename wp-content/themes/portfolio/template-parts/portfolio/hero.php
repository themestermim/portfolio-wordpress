<?php
$hero_description = mim_portfolio_field( 'hero_description' );
$phone = mim_portfolio_phone_number();
$whatsapp_phone = preg_replace( '/^00/', '', ltrim( $phone, '+' ) );
if ( preg_match( '/^09[0-9]{9}$/', $whatsapp_phone ) ) {
	$whatsapp_phone = '98' . substr( $whatsapp_phone, 1 );
}
?>
<section class="flex flex-col gap-4 lg:items-center lg:flex-row-reverse lg:gap-8">
	<div class="w-full flex justify-center relative lg:w-auto lg:px-8 lg:flex-none before:absolute before:content-[''] before:size-48 before:bg-primary/75 before:rounded-full before:top-1/2 before:-translate-y-1/2 before:blur-[7rem]">
		<div class="border-4 border-primary rounded-full p-1 relative z-10 lg:p-2 lg:border-[6px]">
			<div class="size-40 flex-none overflow-hidden rounded-full lg:size-72">
				<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/portfolio/img/mehran-mahmoudi.png' ); ?>" alt="مهران محمودی" class="size-full object-cover" width="288" height="288" fetchpriority="high" />
			</div>
		</div>
	</div>

	<div class="w-full flex flex-col lg:w-auto lg:flex-1">
		<span class="block text-14 text-primary font-medium">سلام، من</span>
		<h1 class="text-primary text-30 font-bold mt-6">مهران محمودی</h1>
		<div class="text-white text-20 font-medium mt-2">
			من&nbsp;
			<span class="text-primary text-24 font-bold inline">برنامه نویس</span>&nbsp;
			فرانت اند هستم
		</div>
		<?php if ( $hero_description ) : ?>
			<div class="text-white text-16 font-medium mt-4 lg:mt-6">
				<?php echo wp_kses_post( $hero_description ); ?>
			</div>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<ul class="mt-4 flex items-start flex-wrap gap-4 lg:gap-6 *:text-primary *:border *:border-primary *:flex *:items-center *:justify-center *:size-14 *:rounded-full *:relative *:overflow-hidden *:before:absolute *:before:content-[''] *:before:size-8 *:before:rounded-full *:before:bg-primary *:before:blur-xl">
				<li>
					<a href="<?php echo esc_url( 'https://wa.me/' . $whatsapp_phone . '?text=' . rawurlencode( 'سلام' ) ); ?>" title="واتس اپ" target="_blank" rel="noopener noreferrer" class="relative z-10 flex size-full items-center justify-center">
						<svg class="size-7">
							<use href="#svg-whatsapp">
							</use>
						</svg>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( 'tel:' . $phone ); ?>" title="تماس" class="relative z-10 flex size-full items-center justify-center">
						<svg class="size-7">
							<use href="#svg-phone">
							</use>
						</svg>
					</a>
				</li>
			</ul>
		<?php endif; ?>
		<div class="flex items-center gap-4 mt-4 lg:mt-8">
			<a href="#projects" class="c-btn btn-primary-fill btn-md">
				<span>نمونه کار ها</span>
			</a>
		</div>
	</div>
</section>
