<?php
/** Template Name: About Me */
get_header();
get_template_part( 'template-parts/portfolio/svgs' );
get_template_part( 'template-parts/portfolio/navigation' );
?>
<a href="#portfolio-main" class="sr-only focus:not-sr-only focus:block focus:p-4">رفتن به محتوای اصلی</a>
<main id="portfolio-main" dir="rtl" class="pb-6 lg:pb-10">
	<?php foreach ( array( 'hero', 'about' ) as $section ) : ?>
		<section id="<?php echo esc_attr( $section ); ?>" class="py-5 md:py-8 lg:py-12">
			<div class="container">
				<?php get_template_part( 'template-parts/portfolio/' . $section ); ?>
			</div>
		</section>
	<?php endforeach; ?>
</main>
<?php get_footer(); ?>
