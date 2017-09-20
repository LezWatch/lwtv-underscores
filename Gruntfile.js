'use strict';
module.exports = function(grunt) {

    grunt.initConfig({
	
        // js minification
        uglify: {
            dist: {
                files: {
					// admin scripts
                    'inc/js/yikes-theme-scripts.min.js': [ // widget specific script
                        'inc/js/navigation.js' , 'inc/js/skip-link-focus-fix.js'
                    ],
					 'inc/js/customizer.min.js': [ // widget specific script
                        'inc/js/customizer.js'
                    ],
                }
            }
        },

		sass: {                              // Task
			dist: {                            // Target
				options: {                       // Target options
					style: 'expanded'
				},
				files: {                         // Dictionary of files
					'style.css': 'style.scss',       // 'destination': 'source'
				}
			}
		},
		
		// css minify contents of our directory and add .min.css extension
		cssmin: {
			target: {
				files: [
					// admin css files
					{
						expand: true,
						cwd: '',
						src: [
							'style.css'
						], // main style declaration file
						dest: '',
						ext: '.min.css'
					}
				]
			}
		},

        // watch our project for changes
       watch: {
			admin_css: { // admin css
				files: 'style.scss',
				tasks: ['postcss','sass','cssmin'],
				options: {
					spawn:false,
					event:['all']
				},
			},
			sass_partials: { // sass partial mixins
				files: 'partials/*.scss',
				tasks: ['sass','cssmin'],
				options: {
					spawn:false,
					event:['all']
				},
			},
		},
		
		// Borwser Sync
		/* Optional -- http://www.browsersync.io/docs/grunt/ */
		browserSync: {
			bsFiles: {
				src : [ 'style.min.css' ],
			},
			options: {
				proxy: "localhost/lezwatchtv/",
				watchTask : true
			}
		},

		
		// Autoprefixer for our CSS files
		postcss: {
			options: {
                map: true,
                processors: [
                    require('autoprefixer-core')({
                        browsers: ['last 2 versions']
                    })
                ]
            },
			dist: {
			  src: [ 'style.css' ]
			}
		},
		  		
    });

    // load tasks
    grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-browser-sync'); // browser-sync auto refresh
	grunt.loadNpmTasks('grunt-postcss'); // CSS autoprefixer plugin (cross-browser auto pre-fixes)

    // register task
    grunt.registerTask('default', [
		'uglify',
		'sass',
        'cssmin',
		'postcss',
		'browserSync',
        'watch',
    ]);

};