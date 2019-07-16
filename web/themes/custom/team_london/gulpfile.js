"use strict";

/**
 * Gulpfile.
 * Used for Team London sub-theme, this contains config and tasks for all the
 * frontend-specific plugins, tests, etc that can be run.
 *
 */

// Import plugins.
const gulp = require('gulp');
const sass = require('gulp-sass');
const stylelint = require('stylelint');
const postcss = require('gulp-postcss');
const reporter = require('postcss-reporter');
const sourcemaps = require('gulp-sourcemaps');
const stylelint_scss = require('postcss-scss');
const stylelint_config_scss = require('stylelint-config-recommended-scss');
const imagemin = require('gulp-imagemin');
const babel = require('gulp-babel');
const eslint = require('gulp-eslint');
const eslint_airbnb = require('eslint-config-airbnb');
const eslintprettier = require('eslint-config-prettier');
const strip_css_comments = require('gulp-strip-css-comments');
const clean = require('gulp-clean');

/**
 * Global path config.
 */
const paths = {
  styles: 'styles',
  scripts: 'scripts',
  css: 'css',
  js: 'js',
  images: 'images',
  output: 'dist',
};


const processors = [
  stylelint({}),
  reporter({
    throwError: true,
    clearMessages: true
  })
];

/**
 * SCSS Linting
 *
 * Uses postCSS e.g. check CSS formatting is compliant with Coding standards.
 */
gulp.task('lint:styles', function() {
  return gulp.src([`./${paths.styles}/**/*.scss`, `!./${paths.styles}/drupal/*.scss`])
    .pipe(postcss(processors, {syntax: stylelint_scss}));
});

/**
 * Sass compilation.
 *
 * This task compiles with the same structure, to the output folder.
 * This allows us later to bundle up the individual CSS files in Drupal
 * or some other application.
 */
gulp.task('sass', function() {
  return gulp.src(`./${paths.styles}/**/*.scss`)
    .pipe(sourcemaps.init())
    .pipe(sass().on('error', sass.logError))
    .pipe(sourcemaps.write("sourcemaps"))
    .pipe(strip_css_comments())
    .pipe(gulp.dest(`${paths.output}/${paths.css}`));
});

/**
 * JS Linting task.
 * Lint with Eslint using the eslint-config-airbnb ruleset
 * as suggested on drupal.org.
 */
gulp.task('lint:js', function() {
  return gulp.src(`${paths.scripts}/**/*.js`)
    .pipe(eslint())
    .pipe(eslint.format());
});

/**
 * JS Compilation task.
 *
 * If there is Babel in play, compile with babel, otherwise
 * just copy the files across to the output folder.
 *
 */
gulp.task('build:js', function() {
  return gulp.src(`${paths.scripts}/**/*.js`)
    .pipe(sourcemaps.init())
    .pipe(babel())
    .pipe(sourcemaps.write("."))
    .pipe(gulp.dest(`${paths.output}/${paths.js}`));
});

/**
 * Image minification / optimisation tasks.
 */
gulp.task('image:min', function() {
  return gulp.src(`${paths.images}/**/*`)
      .pipe(imagemin([
        imagemin.gifsicle({interlaced: true}),
        imagemin.jpegtran({progressive: true}),
        imagemin.optipng({optimizationLevel: 5}),
        imagemin.svgo({
          plugins: [
            {removeViewBox: false},
            {cleanupIDs: false}
          ]
        })
      ],
      {
        verbose: true
      }))
      .pipe(gulp.dest(`${paths.output}/${paths.images}`));
});



/**
 * Watch tasks.
 *
 * Assigning the watch command to a const / var allows you to do things like
 * add onChange events, etc etc
 *
 * Split each task out into its own watch task to keep things streamlined.
 */
gulp.task('watch', function () {
  const styleWatcher = gulp.watch(`${paths.styles}/**/*.scss`, gulp.series('css'));
  const scriptWatcher = gulp.watch(`${paths.scripts}/**/*.js`, gulp.series('js'));
  const imageWatcher = gulp.watch(`${paths.images}/**/*`, gulp.series('image:min'));
});


/**
 * Clean tasks.
 *
 * Remove any files / folders before recompiling.
 */
gulp.task('clean:js', function() {
  return gulp.src(`${paths.output}/$(paths.js}/**/*.js*`)
             .pipe(clean());
});

gulp.task('clean:css', function() {
  return gulp.src(`${paths.output}/${paths.css}/**/*.css*`)
             .pipe(clean());
});

/**
 * Setup combination tasks.
 */
gulp.task('css', gulp.series('lint:styles', 'sass'));
gulp.task('js', gulp.series('lint:js', 'build:js'));
gulp.task('dev', gulp.series('css', 'js', 'image:min'));
gulp.task('initial', gulp.series('dev', 'watch'));
gulp.task('clean', gulp.series('clean:css', 'clean:js'));
gulp.task('build', gulp.series('clean', 'dev'));
