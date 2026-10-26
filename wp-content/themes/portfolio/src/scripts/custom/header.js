/* eslint-env browser */
function handleHeader() {
	return {
		isMenuOpen: false,
		isSearchOpen: false,
		isDesktop: false,
		openItems: {},

		init() {
			this.updateDesktopState();
		},
		updateDesktopState() {
			const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

			if (this.isDesktop !== isDesktop) {
				this.closeAll();
			}

			this.isDesktop = isDesktop;
		},
		openMenu() {
			if (this.isDesktop) {
				return;
			}

			this.closeSearch(false);
			this.isMenuOpen = true;
			fixBody();
			this.$nextTick(() => this.$refs.menuCloseButton.focus());
		},
		closeMenu(restoreFocus = true) {
			this.isMenuOpen = false;
			this.resetSubmenus();
			unfixBody();

			if (restoreFocus) {
				this.$refs.menuButton.focus();
			}
		},
		destroy() {
			if (this.isMenuOpen) {
				unfixBody();
			}
		},
		trapMenuFocus(event) {
			if (this.isDesktop || !this.isMenuOpen) {
				return;
			}

			const elements = Array.from(this.$refs.menuPanel.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]'))
				.filter(element => element.getClientRects().length > 0);
			const firstElement = elements[0];
			const lastElement = elements[elements.length - 1];

			if (event.shiftKey && document.activeElement === firstElement) {
				event.preventDefault();
				lastElement.focus();
			} else if (!event.shiftKey && document.activeElement === lastElement) {
				event.preventDefault();
				firstElement.focus();
			}
		},
		toggleMenu() {
			if (this.isMenuOpen) {
				this.closeMenu();
			} else {
				this.openMenu();
			}
		},
		openSearch() {
			this.closeMenu(false);
			this.isSearchOpen = true;
			this.$nextTick(() => this.$refs.searchInput.focus());
		},
		closeSearch(restoreFocus = true) {
			this.isSearchOpen = false;

			if (restoreFocus) {
				this.$refs.searchButton.focus();
			}
		},
		toggleSearch() {
			if (this.isSearchOpen) {
				this.closeSearch();
			} else {
				this.openSearch();
			}
		},
		closeActiveLayer() {
			if (this.isSearchOpen) {
				this.closeSearch();
			} else if (this.isMenuOpen) {
				this.closeMenu();
			} else {
				this.resetSubmenus();
			}
		},
		closeAll() {
			this.closeSearch(false);
			this.closeMenu(false);
		},
		resetSubmenus(fromLevel = 1) {
			Object.keys(this.openItems).forEach(key => {
				if (Number(key.replace('level', '')) >= fromLevel) {
					delete this.openItems[key];
				}
			});
		},
		toggleItem(level, itemId) {
			if (this.isOpen(level, itemId)) {
				this.closeItem(level, itemId);
			} else {
				this.openItem(level, itemId);
			}
		},
		openItem(level, itemId) {
			this.closeSearch(false);
			this.resetSubmenus(level);
			this.openItems['level' + level] = itemId;
		},
		closeItem(level, itemId) {
			if (this.isOpen(level, itemId)) {
				this.resetSubmenus(level);
			}
		},
		openDesktopItem(level, itemId) {
			if (this.isDesktop && !this.isOpen(level, itemId)) {
				this.openItem(level, itemId);
			}
		},
		closeDesktopItem(level, itemId, event) {
			if (this.isDesktop && !event.currentTarget.contains(document.activeElement)) {
				this.closeItem(level, itemId);
			}
		},
		isOpen(level, itemId) {
			return this.openItems['level' + level] === itemId;
		},
	};
}

// Lock the page while keeping its current scroll position and inline styles.
let bodyScrollState = null;

function fixBody() {
	if (bodyScrollState) {
		return;
	}

	const body = document.body;
	const root = document.documentElement;
	bodyScrollState = {
		x: window.scrollX,
		y: window.scrollY,
		overflow: root.style.overflow,
		position: body.style.position,
		top: body.style.top,
		left: body.style.left,
		width: body.style.width,
		paddingRight: body.style.paddingRight,
	};
	const scrollbarWidth = window.innerWidth - root.clientWidth;

	if (scrollbarWidth > 0) {
		body.style.paddingRight = (parseFloat(getComputedStyle(body).paddingRight) + scrollbarWidth) + 'px';
	}

	root.style.overflow = 'hidden';
	body.style.position = 'fixed';
	body.style.top = -bodyScrollState.y + 'px';
	body.style.left = -bodyScrollState.x + 'px';
	body.style.width = '100%';
}

function unfixBody() {
	if (!bodyScrollState) {
		return;
	}

	const body = document.body;
	const root = document.documentElement;
	const state = bodyScrollState;
	const scrollBehavior = root.style.scrollBehavior;
	bodyScrollState = null;
	root.style.overflow = state.overflow;
	['position', 'top', 'left', 'width', 'paddingRight'].forEach(property => {
		body.style[property] = state[property];
	});
	root.style.scrollBehavior = 'auto';
	window.scrollTo({left: state.x, top: state.y, behavior: 'instant'});
	root.style.scrollBehavior = scrollBehavior;
}
