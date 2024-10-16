'use strict';
module.exports = function(grunt) {

    grunt.initConfig({

        // js minification
        terser: {
            dist: {
                files: {
                    // admin scripts
                    'inc/js/yikes-theme-scripts.min.js': [ // theme specific scripts
                        'inc/js/navigation.js' , 'inc/js/skip-link-focus-fix.js' , 'inc/js/lwtv-theme-scripts.js' , 'inc/js/a11y.js'
                    ],
                    'inc/js/customizer.min.js': [ // customizer specific script
                        'inc/js/customizer.js'
                    ],
                    'inc/js/bootstrap-color-mode.min.js': [ // bootstrap color mode script
                        'inc/js/bootstrap-color-mode.js'
                    ],
                }
            }
        },

        sass: {                                // Task
            dist: {                            // Target
                options: {                     // Target options
                    style: 'expanded'
                },
                files: {                       // Dictionary of files
                    'style.css': 'style.scss', // 'destination': 'source'
                    'style-editor.css' : 'style-editor.scss',
                }
            }
        },

        // css minify contents of our directory and add .min.css extension
        cssmin: {
            target: {
                files: {
                    'style.min.css': ['style.css'],
                    'style-editor.min.css': ['style-editor.css']
                }
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
            editor_css: {
                files: 'style-editor.scss',
                tasks: ['postcss','sass','cssmin'],
                options: {
                    spawn:false,
                    event:['all']
                },
            },
            general_js: {
                files: 'inc/js/*.js',
                tasks: ['terser'],
                options: {
                    spawn:false,
                    event:['all']
                },
            },
        },

        // Autoprefixer for our CSS files
        postcss: {
            options: {
                map: true,
                processors: require('autoprefixer')
            },
            dist: {
              src: [ 'style*.css' ]
            }
        },

    });


    // load tasks
    grunt.loadNpmTasks('grunt-terser');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('@lodder/grunt-postcss');  // CSS autoprefixer plugin (cross-browser auto pre-fixes)

    // register task
    grunt.registerTask('build', [
        'terser',
        'sass',
        'cssmin',
        'postcss',
    ]);

    // register task
    grunt.registerTask('watch', [
        'terser',
        'sass',
        'cssmin',
        'postcss',
        'watch',
    ]);

    // register task
    grunt.registerTask('default', [
        'terser',
        'sass',
        'cssmin',
        'postcss',
        'watch',
    ]);

};
