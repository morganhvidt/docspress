/* eslint-disable import/no-extraneous-dependencies */
const path = require('path');

const pkg = require('json-file').read('./package.json').data;

const cfg = {};

// Build Paths.
cfg.name = 'docspress';
cfg.src = './src';
cfg.dist_root = './dist';
cfg.dist = '{dist_root}/{name}';

// Template variables that will be automatically replaced.
cfg.template_files_src = '{dist}/**/*.{md,php,js,css,pot,json}';
cfg.template_files_variables = {
  text_domain: pkg.name,
  plugin_version: pkg.version,
  plugin_name: pkg.name,
  plugin_title: pkg.title,
  plugin_author: pkg.author,
};

// Copy files.
cfg.copy_files_src = [
  '{src}/**/*',
  '!{src}/**/*.{js,scss}',
  '{src}/**/vendor/**/*.{js,scss}',
  './node_modules/*vue/dist/vue.min.js',
  './node_modules/*sweetalert2/dist/sweetalert2.min.js',
  './node_modules/*sweetalert2/dist/sweetalert2.min.css',
  './node_modules/*anchor-js/anchor.min.js',
  './node_modules/*ivent/dist/ivent.min.js',
  './node_modules/*ivent/dist/ivent.min.js.map',
];

cfg.copy_files_dist = (file) => {
  let destPath = `${cfg.dist_root}/${cfg.name}`;
  const filePath = path.relative(process.cwd(), file.path);

  if (filePath && /^node_modules/g.test(filePath)) {
    destPath += '/assets/vendor';
  }

  return destPath;
};

// Compile SCSS files.
cfg.compile_scss_files_src = ['{src}/*assets/**/*.scss'];
cfg.compile_scss_files_rtl = true;

// Compile JS files.
cfg.compile_js_files_src = [
  '{src}/*assets/**/*.js',
  '{src}/*gutenberg/blocks/**/*.js',
  '!{src}/**/vendor/**/*',
];

// Correct line endings files.
cfg.correct_line_endings_files_src = '{dist}/**/*.{js,css}';

// ZIP files.
cfg.zip_files = [
  {
    src: '{dist}/**/*',
    src_opts: {
      base: '{dist_root}',
    },
    dist: '{dist_root}/{name}.zip',
  },
];

// Watch files.
cfg.watch_files = ['{src}/**/*', '!{src}/**/*.{js,scss}'];

cfg.watch_js_files = ['{src}/**/*.js', '!{src}/*vendor/**/*'];

cfg.watch_scss_files = '{src}/**/*.scss';

module.exports = cfg;
