import gulp from 'gulp';
import sourcemaps from 'gulp-sourcemaps';
import rollup from 'gulp-rollup-2';
import babel from '@rollup/plugin-babel';
import concatjs from 'gulp-concat';
import uglifyjs from 'gulp-uglify-es';
import resolve from '@rollup/plugin-node-resolve'
import commonjs from '@rollup/plugin-commonjs'
import del from 'del';
import rename from 'gulp-rename';
import filter from 'gulp-filter';

const config = {
    'src': {
        'js': './src/js/*.js'
    },
    'dist': {
        'js': './dist/js'
    },

}

gulp.task('clean', function () {
    return del(
      [
        'dist/*'
      ],
      {
        dot: true
      }
    )
});

gulp.task('js', function () {
    return gulp.src([config.src.js])
        .pipe(
            rollup.rollup({
                  plugins: [
                      babel({ babelHelpers: 'bundled' }),
                      resolve(),
                      commonjs()
                  ],
                  output : [
                    {
                      file: 'app.js',
                      name: 'app',
                      format: 'umd',
                      globals: { window: 'window' }
                    }
                  ]
            })
        )
        .pipe(sourcemaps.init())
        .pipe(concatjs('app.js'))
        // output non-uglified
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(config.dist.js))
        // filter only JS files
        // must add ** to work
        .pipe(filter('**/*.js'))
        // uglify
        .pipe(uglifyjs())
        .pipe(sourcemaps.write('.'))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(config.dist.js))
})

gulp.task('watch', function () {
    gulp.watch('./src/js/*.js', gulp.parallel('js'));
});

gulp.task('build', gulp.parallel('clean', 'js'));
gulp.task('default', gulp.parallel('build', 'watch'));
