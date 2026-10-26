<h2 class="unique-title text-center relative font-bold text-24">تماس با من</h2>
<div class="flex justify-center mt-6">
	<form class="bg-secondary p-4 rounded-xl w-full max-w-2xl md:p-6 grid grid-cols-1 gap-4 md:grid-cols-2" x-data="portfolioContact" x-on:submit.prevent="submit" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="action" value="portfolio_contact">
		<input type="hidden" name="request_id" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>" x-bind:value="requestId">
		<?php wp_nonce_field( 'portfolio_contact', 'nonce', false ); ?>
		<div class="hidden" aria-hidden="true">
			<label for="portfolio-website">وب‌سایت</label>
			<input id="portfolio-website" name="website" tabindex="-1" autocomplete="off">
		</div>
		<?php foreach ( array( 'name' => 'نام و نام خانوادگی (اجباری)', 'phone' => 'شماره تماس (اجباری)', 'email' => 'ایمیل (اختیاری)', 'description' => 'پیام / توضیحات (اجباری)' ) as $field => $label ) : ?>
		<div class="c-form-group <?php echo in_array( $field, array( 'email', 'description' ), true ) ? 'col-span-full' : ''; ?>">
			<label class="sr-only" for="portfolio-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( 'description' === $field ) : ?>
			<textarea id="portfolio-description" name="description" class="input description" placeholder="<?php echo esc_attr( $label ); ?>" minlength="10" maxlength="5000" required></textarea>
			<?php else : ?>
			<input id="portfolio-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" class="input text" placeholder="<?php echo esc_attr( $label ); ?>" type="<?php echo 'phone' === $field ? 'tel' : ( 'email' === $field ? 'email' : 'text' ); ?>" maxlength="<?php echo 'phone' === $field ? '30' : '200'; ?>" autocomplete="<?php echo 'phone' === $field ? 'tel' : esc_attr( $field ); ?>" <?php if ( 'email' !== $field ) : ?>required<?php endif; ?> <?php if ( 'name' === $field ) : ?>minlength="3"<?php endif; ?> <?php if ( 'phone' === $field || 'email' === $field ) : ?>dir="ltr"<?php endif; ?>>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
		<p class="col-span-full text-14" x-cloak x-show="message" x-text="message" x-bind:class="failed ? 'text-error' : 'text-accent'" role="status" aria-live="polite"></p>
		<div class="flex justify-center col-span-full">
			<button type="submit" class="c-btn btn-primary-fill btn-sm w-full md:w-auto" x-bind:disabled="busy">
				<span x-text="busy ? 'در حال ارسال…' : 'ارسال پیام'">ارسال پیام</span>
			</button>
		</div>
		<noscript>
			<p class="col-span-full">
				برای ثبت پیام، جاوااسکریپت مرورگر را فعال کنید.
			</p>
		</noscript>
	</form>
</div>
