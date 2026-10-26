<?php
/** Template Name: Home Page */
get_header();
get_template_part( 'template-parts/portfolio/svgs' );
?>
<a href="#portfolio-main" class="sr-only focus:not-sr-only focus:block focus:p-4">رفتن به محتوای اصلی</a>
<main id="portfolio-main" dir="rtl" class="space-y-10 lg:space-y-24 pb-8">
	<?php foreach ( array( 'hero', 'about', 'services', 'projects', 'contact' ) as $section ) : ?>
		<section id="<?php echo esc_attr( $section ); ?>" class="<?php echo 'hero' === $section ? 'py-12' : 'py-8'; ?>">
			<div class="container">
				<?php get_template_part( 'template-parts/portfolio/' . $section ); ?>
			</div>
		</section>
	<?php endforeach; ?>
</main>
<?php get_footer(); ?>

