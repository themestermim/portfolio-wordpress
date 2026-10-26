import {createRequire} from 'node:module';

const require = createRequire(import.meta.url);
const config = require('./wpgulp.config.js');
const gulp = require('gulp');
const concat = require('gulp-concat');
const terser = require('gulp-terser');
const babel = require('gulp-babel');
const rename = require('gulp-rename');
const lineec = require('gulp-line-ending-corrector');
const notify = require('gulp-notify');
const remember = require('gulp-remember');
const plumber = require('gulp-plumber');
const beep = require('beepbeep');

const errorHandler = error => {
	notify.onError('ERROR: <%= error.message %>')(error);
	beep();
};

 gulp.task('vendorsJS', () => {
	 return gulp
		 .src(config.jsVendorSRC, {since: gulp.lastRun('vendorsJS')}) // Only run on changed files.
		 .pipe(plumber(errorHandler))
		 .pipe(remember(config.jsVendorSRC)) // Bring all files back to stream.
		 .pipe(concat(config.jsVendorFile + '.js'))
		 .pipe(lineec()) // Consistent Line Endings for non UNIX systems.
		 .pipe(gulp.dest(config.jsVendorDestination))
		 .pipe(
			 rename({
				 basename: config.jsVendorFile,
				 suffix: '.min'
			 })
		 )
		 .pipe(terser())
		 .pipe(lineec()) // Consistent Line Endings for non UNIX systems.
		 .pipe(gulp.dest(config.jsVendorDestination))
		 .pipe(
			 notify({
				 message: '\n\n✅  ===> VENDOR JS — completed!\n',
				 onLast: true
			 })
		 );
 });

 /**
	* Task: `customJS`.
	*
	* Concatenate and uglify custom JS scripts.
	*
	* This task does the following:
	*     1. Gets the source folder for JS custom files
	*     2. Concatenates all the files and generates custom.js
	*     3. Renames the JS file with suffix .min.js
	*     4. Uglifes/Minifies the JS file and generates custom.min.js
	*/
 gulp.task('customJS', () => {
	 return gulp
		 .src(config.jsCustomSRC, {since: gulp.lastRun('customJS')})
		 .pipe(plumber(errorHandler))
		 .pipe(babel({
			 presets: ['@babel/env']
		 }))
		 .pipe(remember(config.jsCustomSRC))
		 .pipe(lineec())
		 .pipe(rename({suffix: '.min', extname: '.js'}))
		 .pipe(lineec())
		 .pipe(gulp.dest(config.jsCustomDestination))
		 .pipe(
			 notify({
				 message: '\n\n✅  ===> CUSTOM JS — completed!\n',
				 onLast: true
			 })
		 );
 });
