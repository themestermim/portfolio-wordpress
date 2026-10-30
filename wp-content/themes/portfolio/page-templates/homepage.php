<?php
/** Template Name: Home Page */
$projects_query = new WP_Query( array(
	'post_type'      => 'projects',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
) );
get_header();
get_template_part( 'template-parts/portfolio/svgs' );
get_template_part( 'template-parts/portfolio/navigation' );
?>
<a href="#portfolio-main" class="sr-only focus:not-sr-only focus:block focus:p-4">رفتن به محتوای اصلی</a>
<main id="portfolio-main" dir="rtl" class="pb-6 lg:pb-10">
	<?php foreach ( array( 'services', 'projects', 'contact' ) as $section ) : ?>
		<?php if ( 'projects' === $section && ! $projects_query->have_posts() ) { continue; } ?>
		<section id="<?php echo esc_attr( $section ); ?>" class="py-5 scroll-mt-6 md:py-8 lg:py-12">
			<div class="container">
				<?php get_template_part( 'template-parts/portfolio/' . $section, null, array( 'projects' => $projects_query->posts ) ); ?>
			</div>
		</section>
	<?php endforeach; ?>
</main>
<?php get_footer(); ?>

