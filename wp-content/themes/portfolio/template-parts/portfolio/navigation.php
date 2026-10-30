<?php $about_url = mim_portfolio_about_url(); ?>
<header class="py-5 lg:py-7">
	<div class="container flex flex-wrap items-center justify-between gap-4">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-18 font-bold">
			مهران محمودی<span class="text-primary">.</span>
		</a>
		<nav aria-label="فهرست پورتفولیو" class="hidden md:flex flex-wrap items-center gap-5 text-14 lg:gap-8">
			<a href="<?php echo esc_url( home_url( '/#projects' ) ); ?>" class="hover:text-primary transition-colors">نمونه‌کارها</a>
			<?php if ( $about_url ) : ?>
				<a href="<?php echo esc_url( $about_url ); ?>" class="hover:text-primary transition-colors" <?php if ( is_page_template( 'page-templates/about.php' ) ) : ?>aria-current="page"<?php endif; ?>>درباره من</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>" class="text-primary hover:text-accent transition-colors">تماس با من</a>
		</nav>
	</div>
</header>
