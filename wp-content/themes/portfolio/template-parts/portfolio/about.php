<?php
$about_me = mim_portfolio_field( 'about_me' );
$experience = mim_portfolio_field( 'experience', 'option' );
$state = mim_portfolio_field( 'state', 'option' );
$email = sanitize_email( mim_portfolio_field( 'email', 'option' ) );
$phone_label = mim_portfolio_field( 'phone', 'option' );
$phone = mim_portfolio_phone_number();
?>
<div>
	<h2 class="portfolio-section-title">درباره من</h2>
	<section class="w-full max-w-4xl rounded-xl p-4 mt-4 lg:mt-6 bg-secondary text-accent text-16">
		<?php if ( $about_me ) : ?>
			<div class="space-y-4 leading-8">
				<?php echo wp_kses_post( $about_me ); ?>
			</div>
		<?php endif; ?>
		<div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 *:space-y-1.5">
			<ul>
				<li>
					<span class="font-medium">نام:</span>
					<span>مهران محمودی</span>
				</li>
				<?php if ( $experience ) : ?>
					<li>
						<span class="font-medium">تجربه:</span>
						<span><?php echo esc_html( $experience ); ?></span>
					</li>
				<?php endif; ?>
				<?php if ( $state ) : ?>
					<li>
						<span class="font-medium">استان:</span>
						<span><?php echo esc_html( $state ); ?></span>
					</li>
				<?php endif; ?>
			</ul>
			<ul>
				<?php if ( is_email( $email ) ) : ?>
					<li>
						<span class="font-medium">ایمیل:</span>
						<a href="<?php echo esc_url( 'mailto:' . $email ); ?>" class="underline underline-offset-4 cursor-pointer"><?php echo esc_html( $email ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( $phone ) : ?>
					<li>
						<span class="font-medium">تماس:</span>
						<a href="<?php echo esc_url( 'tel:' . $phone ); ?>" class="cursor-pointer"><?php echo esc_html( $phone_label ); ?></a>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	</section>
</div>
