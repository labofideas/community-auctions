<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _community_auctions_load_plugin() {
    require dirname( __DIR__, 2 ) . '/community-auctions.php';
}

tests_add_filter( 'muplugins_loaded', '_community_auctions_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
