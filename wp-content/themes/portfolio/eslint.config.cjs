const {FlatCompat} = require('@eslint/eslintrc');
const js = require('@eslint/js');

const compat = new FlatCompat({
	baseDirectory: __dirname,
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all
});

module.exports = [
	{ignores: ['**/*.min.js', '**/node_modules/**', '**/vendor/**']},
	...compat.config({
		env: {browser: true, commonjs: true, es6: true, node: true},
		extends: ['eslint:recommended', 'wordpress'],
		parserOptions: {sourceType: 'module'},
		rules: {
			indent: ['error', 'tab'],
			semi: ['error', 'always'],
			quotes: ['error', 'single'],
			'linebreak-style': ['error', 'unix']
		}
	})
];
