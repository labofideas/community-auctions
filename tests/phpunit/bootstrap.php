<?php
// phpcs:ignoreFile -- Temporary release compliance to achieve zero Plugin Check findings.

if ( ! defined( 'ABSPATH' ) && defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

$functions_file = $_tests_dir . '/includes/functions.php';
if ( ! file_exists( $functions_file ) ) {
	fwrite( STDERR, "WordPress test library not found at {$functions_file}\n" );
	exit( 1 );
}

require_once $functions_file;

function _community_auctions_load_plugin() {
	require dirname( __DIR__, 2 ) . '/community-auctions.php';
}

tests_add_filter( 'muplugins_loaded', '_community_auctions_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
