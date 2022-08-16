'use strict';

var path = require('path'),
    importOnce = require('node-sass-import-once');

var options = {};

// #############################
// Edit these paths and options.
// #############################

// The root paths are used to construct all the other paths in this
// configuration. The "project" root path is where this gulpfile.js is located.
// While Zen distributes this in the theme root folder, you can also put this
// (and the package.json) in your project's root folder and edit the paths
// accordingly.
options.rootPath = {
  project     : __dirname + '/',
  styleGuide  : __dirname + '/styleguide/',
  theme       : __dirname + '/'
};

options.theme = {
  name       : 'STARTERKIT',
  root       : options.rootPath.theme,
  css        : options.rootPath.theme + 'css/',
  sass       : options.rootPath.theme + 'sass/',
  js         : options.rootPath.theme + 'js/'
};

// Define the node-sass configuration. The includePaths is critical!
options.sassDev = {
  importer: importOnce,
  includePaths: [
    options.theme.css
  ],
  outputStyle: 'expanded'
};

options.sassProduction = {
  importer: importOnce,
  includePaths: [
    options.theme.css
  ],
  outputStyle: 'compressed'
};

// Define which browsers to add vendor prefixes for.
options.autoprefixer = {
  cascade: false
};

// If your files are on a network share, you may want to turn on polling for
// Gulp watch. Since polling is less efficient, we disable polling by default.
// The default interval is 100 ms. If that leads to excessive cpu usage
// try to set a higher value.
options.gulpWatchOptions = {};
// options.gulpWatchOptions = {interval: 1000, mode: 'poll'};
// options.gulpWatchOptions = {interval: 600};

// #############################
// Edit these Materialize URLs.
// #############################
options.materialize = {
  css: 'materialize-v1.0.0-alpha.3.zip',
  src: 'materialize-src-v1.0.0-alpha.3.zip',
  zip: 'materialize-v1.0.0-alpha.3.zip',
  url: 'https://github.com/Dogfalo/materialize/releases/download/1.0.0-alpha.3/'
}

// ################################
// Load Gulp and tools we will use.
// ################################
var gulp       = require('gulp'),
  $            = require('gulp-load-plugins')(),
  // browserSync = require('browser-sync').create(),
  del          = require('del'),
  // gulp-load-plugins will report "undefined" error unless you load gulp-sass manually.
  sass         = require('gulp-sass')(require('sass')),
  source       = require('vinyl-source-stream'),
  request      = require('request'),
  autoprefixer = require('gulp-autoprefixer'),
  runSequence  = require('run-sequence'),
  fs           = require('fs');

// ################################
// Download Materialize tasks
// ################################
gulp.task('materialize-download', function() {
   return request(options.materialize.url + '/' + options.materialize.zip)
   .pipe(source(options.materialize.zip))
   .pipe(gulp.dest('./'))
});

gulp.task('materialize-unzip', function() {
    return gulp.src(options.materialize.zip)
    .pipe($.decompress({strip: 1}))
    .pipe(gulp.dest('./'))
});

gulp.task('materialize-zip-cleanup', function(done) {
  del(options.materialize.zip);
  done();
});

gulp.task('materialize-set:src', function() {
   options.materialize.zip = options.materialize.src;
   return 1;
});

gulp.task('materialize-set:css', function() {
   options.materialize.zip = options.materialize.css;
   return 1;
});

gulp.task('materialize-install:css', function() {
    runSequence('materialize-set:css', 'materialize-download', 'materialize-unzip', 'materialize-zip-cleanup', function(done) {
        console.log('Materialize library installed');
        done();
    });
});

gulp.task('materialize-install:src', function() {
    runSequence('materialize-set:src', 'materialize-download', 'materialize-unzip', 'materialize-zip-cleanup', 'materialize-fix-src', function() {
        console.log('Materialize library installed.');
    });
});

function initialize(done) {
  gulp.src(['node_modules/jquery-touch-events/src/jquery.mobile-events.min.js'])
    .pipe(gulp.dest('js/vendor'));

  gulp.src([
    'node_modules/materialize-css/sass/*'])
    .pipe(gulp.dest('sass/vendor/'));

  gulp.src([
    'node_modules/materialize-css/sass/components/*'])
    .pipe(gulp.dest('sass/vendor/components/'));

  gulp.src([
    'node_modules/materialize-css/sass/components/forms/*'])
    .pipe(gulp.dest('sass/vendor/components/forms/'));

  gulp.src([
    'node_modules/node-waves/dist/*'])
    .pipe(gulp.dest('js/vendor/waves/'));

  console.log('Materialize sub-theme initialized.');
  done();
}

gulp.task('init', gulp.series(initialize));

function help(done) {
    console.log('');
    console.log('gulp usage for this Materialize sub theme');
    console.log('* gulp init - Init theme, move the library elements to the proper directories');
    console.log('* gulp watch - Watch the changes of SASS files and compile to CSS');
    console.log('* gulp styles - Compile SASS files to CSS');
    console.log('* gulp compile - Compile SASS files to CSS (alias of styles)');
    console.log('* gulp compile-prod - Compile SASS files to CSS');
    console.log('* gulp clean - Clean up CSS files');
    console.log('');
    done();
}

// The default task.
gulp.task('help', gulp.series(help));
gulp.task('default', gulp.series(help));

// ##########
// Build CSS.
// ##########
var sassFiles = [
  options.theme.sass + '**/*.scss',
  // Do not open Sass partials as they will be included as needed.
  '!' + options.theme.sass + '**/_*.scss'
];

var sassFilesWatch = [
  options.theme.sass + '**/*.scss'
];

function styles() {
  return gulp.src(sassFiles)
    .pipe($.sourcemaps.init())
    .pipe(sass(options.sassDev).on('error', sass.logError))
    .pipe($.autoprefixer(options.autoprefixer))
    .pipe($.rename({dirname: ''}))
    .pipe($.size({showFiles: true}))
    .pipe($.sourcemaps.write('./'))
    .pipe(gulp.dest(options.theme.css));
}

function stylesProduction() {
  return gulp.src(sassFiles)
    .pipe(sass(options.sassProduction).on('error', sass.logError))
    .pipe($.autoprefixer(options.autoprefixer))
    .pipe($.rename({dirname: ''}))
    .pipe($.size({showFiles: true}))
    .pipe(gulp.dest(options.theme.css));
}

// Alias of styles.
gulp.task('compile', gulp.series(cleanCss, styles));

// Alias of styles.
gulp.task('compile-prod', gulp.series(cleanCss, stylesProduction));

// ##############################
// Watch for changes and rebuild.
// ##############################

function watchCss() {
  gulp.watch(sassFilesWatch, options.gulpWatchOptions, gulp.parallel(styles))
      .on('change', function(path, stats) {
        console.log(path);
        // code to execute on change
      })
      .on('unlink', function(path, stats) {
          console.log(path);
          // code to execute on delete
      });
}

gulp.task('watch', gulp.series(cleanCss, styles, watchCss));

// ######################
// Clean all directories.
// ######################

// Clean CSS files.
function cleanCss(done) {
  del([
    options.theme.css + '**/*.css',
    options.theme.css + '**/*.map'
  ], {force: true});

  done();
}

gulp.task('clean', gulp.series(cleanCss));
