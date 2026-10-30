<?php $projects = $args['projects'] ?? array(); ?>
<div x-data="portfolioProjects">
	<div class="flex flex-wrap items-end justify-between gap-3 mb-4 lg:mb-8">
		<h2 class="portfolio-section-title">نمونه‌کارها</h2>
		<?php if ( $projects ) : ?>
			<p class="text-14 text-neutral-200"><?php echo esc_html( number_format_i18n( count( $projects ) ) ); ?> پروژه</p>
		<?php endif; ?>
	</div>
	<?php if ( ! $projects ) : ?>
		<p class="rounded-xl bg-neutral-800 p-5 lg:p-8 text-16 text-neutral-100">نمونه‌کارها به‌زودی اینجا قرار می‌گیرند.</p>
	<?php else : ?>
		<div id="portfolio-project-grid" class="grid grid-cols-1 gap-x-6 gap-y-6 md:grid-cols-2 lg:grid-cols-3 lg:gap-y-12">
			<?php foreach ( $projects as $index => $project ) : ?>
				<?php
				$project_title = get_the_title( $project->ID );
				$description = mim_portfolio_field( 'short_description', $project->ID );
				?>
				<article class="group min-w-0" <?php if ( $index >= 6 ) : ?>x-show="expanded" x-cloak<?php endif; ?>>
					<a href="<?php echo esc_url( get_permalink( $project->ID ) ); ?>" class="block rounded-xl focus-visible:outline-offset-8">
						<div class="aspect-[4/3] overflow-hidden rounded-xl bg-neutral-800">
							<?php if ( has_post_thumbnail( $project->ID ) ) : ?>
								<?php echo get_the_post_thumbnail( $project->ID, 'large', array( 'class' => 'h-full w-full object-cover object-top transition-transform duration-300 motion-safe:group-hover:scale-[1.03]', 'loading' => 'lazy', 'sizes' => '(min-width: 1024px) 400px, (min-width: 768px) 50vw, 100vw', 'alt' => $project_title ) ); ?>
							<?php else : ?>
								<div class="flex h-full items-center justify-center p-8 text-center text-24 font-bold text-neutral-300" aria-hidden="true"><?php echo esc_html( $project_title ); ?></div>
							<?php endif; ?>
						</div>
						<div class="mt-3 lg:mt-5 flex items-start justify-between gap-3">
							<h3 class="text-20 font-medium group-hover:text-primary transition-colors"><?php echo esc_html( $project_title ); ?></h3>
							<span class="text-primary text-24" aria-hidden="true">↖</span>
						</div>
					</a>
					<?php if ( $description ) : ?>
						<p class="mt-2 text-14 leading-7 text-neutral-200 line-clamp-2"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $projects ) > 6 ) : ?>
			<div class="mt-6 flex justify-center lg:mt-12" x-cloak x-show="!expanded">
				<button type="button" class="c-btn btn-primary-fill btn-md gap-3" x-on:click="showAll" x-bind:aria-expanded="expanded.toString()" aria-expanded="false" aria-controls="portfolio-project-grid">
					<span>مشاهده همه</span>
					<span aria-hidden="true">↓</span>
				</button>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
