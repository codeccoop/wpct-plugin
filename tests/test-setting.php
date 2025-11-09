<?php
/**
 * Class SettingTest
 *
 *  @package wpct-plugin-tests
 */

use WPCT_PLUGIN\Settings_Store;

/**
 * Setting test case.
 */
class SettingTest extends WP_UnitTestCase {
	/**
	 * Handle's the store name of the test case.
	 *
	 * @var string|null
	 */
	private static $group = null;

	public static function provider() {
		return array(
			'name'       => 'store-test-case',
			'properties' => array(
				'foo'     => array(
					'type'    => 'string',
					'default' => 'bar',
				),
				'email'   => array(
					'type'    => 'string',
					'format'  => 'email',
					'default' => 'johndoe@email.me',
				),
				'serie'   => array(
					'type'        => 'array',
					'items'       => array(
						'type'    => 'integer',
						'min'     => 0,
						'max'     => 10,
						'default' => 0,
					),
					'uniqueItems' => true,
					'minItems'    => 1,
				),
				'address' => array(
					'type'       => 'object',
					'properties' => array(
						'street'  => array( 'type' => 'string' ),
						'zip'     => array( 'type' => 'string' ),
						'state'   => array( 'type' => 'string' ),
						'country' => array(
							'type'    => 'string',
							'default' => 'ES',
						),
					),
					'required'   => array(
						'street',
						'zip',
						'state',
						'country',
					),
				),
			),
			'required'   => array(
				'foo',
				'email',
				'serie',
			),
			'default'    => array(
				'foo'   => 'bar',
				'email' => 'johndoe@email.me',
				'serie' => array( 1, 2, 3, 4, 5 ),
			),
		);
	}

	public static function set_up_before_class() {
		add_action(
			'init',
			function () {
				$a = 1;
			}
		);

		add_filter(
			'wpct_plugin_register_settings',
			static function ( $settings, $group ) {
				self::$group = $group;
				$settings[]  = self::provider();
				return $settings;
			},
			20,
			1
		);
	}

	public static function tear_down_after_class() {
		if ( self::$group ) {
			delete_option( self::$group . '_store-test-case' );
		}
	}

	public function test_setting_registry() {
		$this->assertFalse( empty( self::$group ) );

		$setting = get_option( self::$group . '_store-test-case' );

		$this->assertFalse( empty( $setting ) );
	}
}
