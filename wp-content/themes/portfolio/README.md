# WordPress theme build tools

This theme uses Tailwind CSS 4 for styles and Gulp 5 with Babel 8 for JavaScript.
The JavaScript workflow is based on [WPGulp](https://github.com/ahmadawais/WPGulp).

## Setup

Use Node.js 22.18+ within the 22.x release line, or Node.js 24.11+.
Run the following commands from this theme directory:

```sh
npm ci
```

## Commands

- `npm run tailwind:dev:home`: watch and compile the home stylesheet.
- `npm run tailwind:build:home`: build the minified home stylesheet.
- `npm run js-custom`: transpile custom JavaScript into `assets/scripts/`.

JavaScript source and output paths are configured in `wpgulp.config.js`.
The existing vendor JavaScript task is also available via `npx gulp vendorsJS`.
ESLint uses `eslint.config.cjs`; PostCSS uses `postcss.config.js`.
