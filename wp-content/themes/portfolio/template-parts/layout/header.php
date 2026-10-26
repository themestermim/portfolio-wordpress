<?php
/** Default Mim Theme header. */
get_template_part( 'template-parts/svgs/header' );
?>
<header class="mim-header" x-data="handleHeader" x-bind:class="{'is-menu-open': isMenuOpen}"
	x-on:resize.window.debounce.150ms="updateDesktopState"
	x-on:keydown.escape.window="closeActiveLayer"
	x-on:click.outside="closeAll">
	<div class="mim-header__accent" aria-hidden="true"></div>
	<div class="mim-header__inner">
		<div class="mim-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="mim-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/brand/mim-logo.svg' ); ?>" width="48" height="48" alt="" fetchpriority="high">
					<span class="mim-brand__text"><strong>میم تم</strong><small>MIM THEME</small></span>
				</a>
			<?php endif; ?>
		</div>
		<button class="mim-icon-button mim-menu-toggle" type="button" aria-controls="mim-primary-nav" x-ref="menuButton" x-on:click="toggleMenu"
			x-bind:aria-expanded="isMenuOpen.toString()"
			x-bind:aria-label="isMenuOpen ? 'بستن فهرست' : 'باز کردن فهرست'" aria-label="باز کردن فهرست">
			<svg aria-hidden="true"><use x-bind:href="isMenuOpen ? '#svg-close' : '#svg-menu-lines'" href="#svg-menu-lines"></use></svg>
		</button>
		<div class="mim-menu-backdrop" x-cloak x-show="!isDesktop && isMenuOpen"
			x-on:click="closeMenu" aria-hidden="true"
			x-transition:enter="mim-backdrop-transition" x-transition:enter-start="mim-backdrop-hidden" x-transition:enter-end="mim-backdrop-visible"
			x-transition:leave="mim-backdrop-transition" x-transition:leave-start="mim-backdrop-visible" x-transition:leave-end="mim-backdrop-hidden"></div>
		<nav class="mim-nav" id="mim-primary-nav" aria-label="فهرست اصلی" x-ref="menuPanel"
			x-bind:role="isDesktop ? null : 'dialog'" x-bind:aria-modal="!isDesktop && isMenuOpen ? 'true' : null"
			x-cloak x-show="isDesktop || isMenuOpen" x-on:keydown.tab="trapMenuFocus($event)"
			x-transition:enter="mim-drawer-transition" x-transition:enter-start="mim-drawer-hidden" x-transition:enter-end="mim-drawer-visible"
			x-transition:leave="mim-drawer-transition" x-transition:leave-start="mim-drawer-visible" x-transition:leave-end="mim-drawer-hidden">
			<div class="mim-drawer-heading">
				<strong>فهرست اصلی</strong>
				<button type="button" class="mim-icon-button" aria-label="بستن فهرست" x-ref="menuCloseButton" x-on:click="closeMenu">
					<svg aria-hidden="true"><use href="#svg-close"></use></svg>
				</button>
			</div>
			<?php
			get_template_part('template-parts/layout/header/navbar');
			if ( ! has_nav_menu( 'menu-1' ) && current_user_can( 'edit_theme_options' ) ) :
				?>
				<a class="mim-menu-setup" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>">تنظیم فهرست اصلی</a>
			<?php endif; ?>
		</nav>
		<div class="mim-header__actions">
			<div class="mim-search">
				<button type="button" class="mim-icon-button" aria-label="جستجو در سایت" x-ref="searchButton" x-on:click="toggleSearch" aria-controls="mim-search-panel" x-bind:aria-expanded="isSearchOpen.toString()"><svg aria-hidden="true"><use href="#svg-magnify"></use></svg></button>
				<form class="mim-search__form" id="mim-search-panel" x-cloak x-show="isSearchOpen" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label for="mim-header-search">جستجو در سایت</label>
					<div class="mim-search__row">
						<input id="mim-header-search" x-ref="searchInput" type="search" name="s" placeholder="دنبال چه چیزی می‌گردید؟" value="<?php echo esc_attr( get_search_query() ); ?>" required>
						<button class="mim-icon-button" type="submit" aria-label="جستجو"><svg aria-hidden="true"><use href="#svg-magnify"></use></svg></button>
					</div>
				</form>
			</div>
			<a class="mim-account" aria-label="حساب کاربری" href="<?php echo esc_url( is_user_logged_in() ? admin_url( 'profile.php' ) : wp_login_url() ); ?>">
				<svg aria-hidden="true"><use href="#svg-user-01"></use></svg>
				<span><?php echo is_user_logged_in() ? 'حساب کاربری' : 'ورود به حساب'; ?></span>
			</a>
		</div>
	</div>
</header>
