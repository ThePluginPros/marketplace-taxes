var pkg = require('./package.json');

module.exports = function (grunt) {
    grunt.initConfig({
        addtextdomain: {
            target: {
                files: {
                    src: [
                        '*.php',
                        '**/*.php',
                        '!node_modules/**',
                        '!vendor/**'
                    ]
                }
            }
        },
        makepot: {
            target: {
                options: {
                    type: 'wp-plugin',
                    exclude: [
                        'node_modules/.*',
                        'vendor/.*',
                        'build/.*'
                    ],
                    potHeaders: {
                        poedit: true,
                        'x-poedit-keywordslist': true,
                        'report-msgid-bugs-to': 'https://github.com/ThePluginPros/marketplace-taxes/issues'
                    }
                }
            }
        },
        uglify: {
            target: {
                files: [{
                    expand: true,
                    cwd: 'assets/js',
                    src: ['*.js', '!*.min.js'],
                    dest: 'assets/js',
                    rename: function (dst, src) {
                        return dst + '/' + src.replace('.js', '.min.js')
                    }
                }]
            }
        },
        clean: ['build/'],
        copy: {
            target: {
                expand: true,
                src: ['assets/**', 'includes/**', 'languages/**', 'vendor/**', 'marketplace-taxes.php', 'readme.txt'],
                dest: 'build/'
            }
        },
        compress: {
            target: {
                options: {
                    archive: function () {
                        return 'releases/marketplace-taxes-' + pkg.version + '.zip'
                    }
                },
                files: [{
                    expand: true,
                    cwd: 'build/',
                    src: '**',
                    dest: 'marketplace-taxes/'
                }]
            }
        },
        wp_deploy: {
            deploy: {
                options: {
                    plugin_slug: 'marketplace-taxes',
                    build_dir: 'build',
                    assets_dir: 'wp-assets'
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-wp-deploy');

    grunt.registerTask('i18n', ['addtextdomain', 'makepot']);
    grunt.registerTask('assets', ['uglify']);
    grunt.registerTask('build', ['i18n', 'assets', 'clean', 'copy', 'compress']);
    grunt.registerTask('deploy', ['wp_deploy']);
    grunt.registerTask('default', ['build']);
};