<?php
$projects = $args['projects'] ?? array();
if ( ! $projects ) {
	return;
}
?>
<div>
	<h2 class="unique-title text-center relative font-bold text-24">نمونه کار های من</h2>
	<div class="marquee-list mt-6 overflow-x-auto" tabindex="0" aria-label="نمونه‌کارها؛ برای توقف حرکت روی این بخش مکث کنید">
		<div class="marquee flex w-max">
			<?php foreach ( array( false, true ) as $is_copy ) : ?>
				<div class="<?php echo $is_copy ? 'marquee-copy flex shrink-0' : 'flex shrink-0'; ?>"<?php if ( $is_copy ) : ?> aria-hidden="true"<?php endif; ?>>
					<?php foreach ( $projects as $project ) : ?>
						<?php
						$project_title = get_the_title( $project->ID );
						$description = mim_portfolio_field( 'short_description', $project->ID );
						?>
						<div class="w-76 px-2 flex-none lg:w-84 lg:px-3">
							<article class="w-full rounded-xl bg-secondary text-accent overflow-hidden">
								<?php if ( has_post_thumbnail( $project->ID ) ) : ?>
									<?php echo get_the_post_thumbnail( $project->ID, 'large', array( 'class' => 'w-full aspect-video object-cover', 'loading' => 'lazy', 'alt' => $project_title ) ); ?>
								<?php else : ?>
									<div class="w-full aspect-video bg-background" aria-hidden="true">
									</div>
								<?php endif; ?>
								<div class="p-4 flex flex-col items-start">
									<h3 class="text-18 font-medium"><?php echo esc_html( $project_title ); ?></h3>
									<p class="text-14 mt-2 h-10 line-clamp-2"><?php echo esc_html( $description ); ?></p>
									<a href="<?php echo esc_url( get_permalink( $project->ID ) ); ?>"<?php if ( $is_copy ) : ?> tabindex="-1"<?php endif; ?> class="c-btn btn-dark-fill btn-sm btn-full btn-center mt-4">
										<span>مشاهده پروژه</span>
									</a>
								</div>
							</article>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
