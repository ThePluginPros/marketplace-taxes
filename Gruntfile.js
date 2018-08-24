module.exports = function( grunt ) {
    grunt.initConfig( {
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
                        'vendor/.*'
                    ],
                    potHeaders: {
                        poedit: true,
                        'x-poedit-keywordslist': true,
                        'report-msgid-bugs-to': 'https://bitbucket.org/thepluginpros/taxjar-for-marketplaces/issues'
                    }
                }
            }
        },
        uglify: {
            target: {
                files: [ {
                    expand: true,
                    cwd: 'assets/js',
                    src: [ '*.js', '!*.min.js' ],
                    dest: 'assets/js',
                    rename: function( dst, src ) {
                        return dst + '/' + src.replace( '.js', '.min.js' )
                    }
                } ]
            }
        }
    } );

    grunt.loadNpmTasks( 'grunt-wp-i18n' );
    grunt.loadNpmTasks( 'grunt-contrib-uglify' );

    grunt.registerTask( 'i18n', [ 'addtextdomain', 'makepot' ] );
    grunt.registerTask( 'default', [ 'i18n', 'uglify' ] );
};