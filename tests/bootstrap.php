<?php
/**
 * PHPUnit boostrap file
 *
 * @package wpct-plugin-tests
 */

// phpcs:disable WordPress.NamingConventions

/**
 * Handles the parent plugin store instance.
 *
 * @var WPCT_PLUGIN\Settings_Store|null
 */
$wpct_plugin_test_store = null;

tests_add_filter(
	'wpct_plugin_register_settings',
	function ( $settings ) {
		$settings[] = include 'data/setting.php';
		return $settings;
	},
	90,
	2
);

tests_add_filter(
	'wpct_plugin_registered_settings',
	function ( $settings, $group, $store ) {
		global $wpct_plugin_test_store;
		$wpct_plugin_test_store = $store;
	},
	5,
	3,
);
