<?php
$services = array(
	array( 'title' => 'توسعه فرانت‌اند', 'description' => 'ساخت رابط‌های ماژولار و قابل توسعه با Vue و Nuxt.', 'tools' => 'Vue / Nuxt', 'symbol' => '</>' ),
	array( 'title' => 'بهینه‌سازی سرعت', 'description' => 'بهبود سرعت بارگذاری و شاخص‌های Core Web Vitals.', 'tools' => 'Performance', 'symbol' => '↗' ),
	array( 'title' => 'سئوی فنی و SSR', 'description' => 'رندر سمت سرور و ساختار مناسب برای موتورهای جستجو.', 'tools' => 'SSR / SEO', 'symbol' => '⌕' ),
	array( 'title' => 'بازنویسی پروژه‌های قدیمی', 'description' => 'مرتب‌سازی کدبیس و بازطراحی ساختار برای توسعه آسان‌تر.', 'tools' => 'Refactoring', 'symbol' => '{ }' ),
	array( 'title' => 'توسعه قالب وردپرس', 'description' => 'پیاده‌سازی رابط اختصاصی بدون افزونه‌های سنگین.', 'tools' => 'WordPress', 'symbol' => 'W' ),
	array( 'title' => 'اتصال به API', 'description' => 'اتصال فرانت‌اند به بک‌اند و مدیریت جریان داده‌ها.', 'tools' => 'API Integration', 'symbol' => '⇄' ),
);
?>
<div class="grid gap-4 lg:grid-cols-4 lg:gap-10">
	<div>
		<h1 class="portfolio-section-title">خدمات من</h1>
		<a href="#contact" class="mt-2 lg:mt-6 inline-flex min-h-11 items-center gap-3 text-14 text-primary hover:text-accent transition-colors">
			<span>گفتگو درباره پروژه</span>
			<span aria-hidden="true">←</span>
		</a>
	</div>
	<ul class="grid gap-3 sm:grid-cols-2 lg:col-span-3 lg:grid-cols-3">
		<?php foreach ( $services as $service ) : ?>
			<li class="grid grid-cols-[1.5rem_1fr] items-start gap-x-3 rounded-xl bg-neutral-800/60 p-4 sm:flex sm:flex-col lg:p-6">
				<span class="text-20 sm:mb-3 lg:text-24 text-primary font-medium" dir="ltr" aria-hidden="true"><?php echo esc_html( $service['symbol'] ); ?></span>
				<h2 class="text-18 font-medium"><?php echo esc_html( $service['title'] ); ?></h2>
				<p class="col-span-full mt-2 lg:mt-3 text-14 leading-7 text-neutral-100"><?php echo esc_html( $service['description'] ); ?></p>
				<p class="col-span-full mt-auto pt-2 lg:pt-5 text-12 text-neutral-300" lang="en" dir="ltr"><?php echo esc_html( $service['tools'] ); ?></p>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
