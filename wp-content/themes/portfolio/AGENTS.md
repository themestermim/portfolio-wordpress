# Mim Theme — Development Guidelines

These rules apply to all files in this theme and its subdirectories. Follow them when adding or modifying code. Keep changes focused on the requested task.

## Design system and colors

- Use the theme's existing design tokens instead of introducing unrelated styles.
- Color values are defined in `src/styles/components/colors.css` and exposed to Tailwind through `src/styles/components/theme.css`.
- Use the existing `primary`, `neutral`, and `error` palettes and their available shades. Use the `error` palette for validation errors.
- Prefer utilities such as `bg-primary-500`, `text-neutral-800`, and `border-error-500`. Do not hardcode hex, RGB, or OKLCH colors in components when a theme token can be used.
- Use the numeric typography utilities defined in the theme: `text-10`, `text-12`, `text-14`, and so on through `text-40`. They already include a default line-height; override it only when the content needs a different value.

## Tailwind CSS first

- This project uses Tailwind CSS. Prefer existing utility classes for layout, spacing, typography, colors, responsive behavior, and interaction states.
- Avoid raw CSS and inline styles whenever Tailwind utilities can express the same behavior clearly.
- For reusable component styles, use `@layer components` and prefer `@apply` with existing utilities where appropriate.
- Use raw CSS only when needed for behavior that utilities cannot express cleanly. Keep it minimal and scoped to the component.

## Alpine.js and JavaScript

- Use the Alpine.js library already installed in the theme for interactive UI whenever possible.
- Place `x-data` on the component's parent element. Define its named function in the relevant source JavaScript file, following the existing `handleHeader()` pattern.
- Return a simple object containing state, `init()` when needed, and clearly named methods such as `openMenu()`, `closeMenu()`, `toggleItem()`, and `isOpen()`.
- Declare UI events and bindings in the template with `x-on`, `x-show`, `x-bind`, `x-ref`, `x-cloak`, and `x-transition` as appropriate.
- Avoid manual event listeners, direct class manipulation, or creating UI elements in JavaScript when Alpine directives and template markup can handle them.
- Keep JavaScript simple, readable, and easy to extend. Use small helpers only when needed, such as `fixBody()` and `unfixBody()` for scroll locking.
- Match the existing Arasb-style Alpine conventions used by the theme. Do not add another JavaScript framework for component interactions.

## Section layout and containers

- Each top-level content section must have its own outer wrapper and an inner `.container`.
- Put section spacing, backgrounds, and full-width decoration on the outer wrapper. Put the section's constrained content inside its `.container`.
- Do not apply `.container` to `<body>` or use one shared container around all page sections.
- Use the same pattern independently for every sibling section:

```html
<div class="py-6 lg:py-8">
	<div class="container">
		<p>Section content</p>
	</div>
</div>

<div class="bg-neutral-50 py-6 lg:py-8">
	<div class="container">
		<p>Next section content</p>
	</div>
</div>
```

## HTML formatting

- Write nested HTML with clear line breaks and indentation. Use tabs to indent each nesting level.
- Every opening or closing tag must be followed by a line break before the next tag. Do not place multiple nested or sibling tags on the same line.
- Exception: an element containing only plain text may have its opening tag, text, and closing tag on one line.
- Keep empty elements on separate opening and closing lines. Void elements such as `<img>` and `<input>` occupy their own lines and do not need closing tags.
- Apply these rules to HTML written inside PHP templates as well.

Incorrect:

```html
<div><section><span></span></section></div>
```

Correct:

```html
<div>
	<section>
		<span>
		</span>
	</section>
</div>
```

Correct exception for plain text:

```html
<button type="button">
	<span>Submit</span>
</button>
```
