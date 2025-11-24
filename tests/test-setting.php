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
	 * Handle's the store of the test case.
	 *
	 * @var WPCT_PLUGIN\Settings_Store|null
	 */
	public static $store = null;

	public static function set_up_before_class() {
		global $wpct_plugin_test_store;

		if ( empty( $wpct_plugin_test_store ) ) {
			throw new Exception( 'Test store is not defined' );
		}

		self::$store = $wpct_plugin_test_store;
	}

	public function tear_down() {
		$setting = self::$store::setting( 'store-test-case' );
		$setting->delete();
		parent::tear_down();
	}

	public function test_setting_registry() {
		$setting = self::$store::setting( 'store-test-case' );

		$this->assertFalse( empty( $setting ) );
		$this->assertSame( 'bar', $setting->foo );
		$this->assertSame( 'johndoe@email.me', $setting->email );
		$this->assertEqualSets( array( 1, 2, 3, 4, 5 ), $setting->serie );
	}

	public function test_setting_update() {
		$setting = self::$store::setting( 'store-test-case' );

		$setting->address = array(
			'street' => 'Elm Street',
			'zip'    => '54321',
			'state'  => 'CAT',
		);

		$this->assertSame( 'Elm Street', $setting->address['street'] );
		$this->assertSame( '54321', $setting->address['zip'] );
		$this->assertSame( 'CAT', $setting->address['state'] );
		$this->assertSame( 'ES', $setting->address['country'] );
	}

	public function test_invalid_setting_update() {
		$setting = self::$store::setting( 'store-test-case' );

		$setting->foo = 'Alohomora';
		$this->assertSame( 'bar', $setting->foo );

		$setting->email = 'https://www.codeccoop.org';
		$this->assertSame( 'johndoe@email.me', $setting->email );

		$setting->serie = array( 101, 102, 103, 104, 105 );
		$this->assertEqualSets( array( 1, 2, 3, 4, 5 ), $setting->serie );

		$setting->address = 'foo';
		$this->assertNull( $setting->address );

		$setting->email = 'lucas@email.me';
	}

	public function test_setting_getter() {
		$setting = self::$store::setting( 'store-test-case' );

		$setting->use_getter(
			static function ( $data ) {
				$data['foo'] = 'boo';
				return $data;
			}
		);

		$data = $setting->data();
		$this->assertSame( 'boo', $data['foo'] );
	}

	public function test_setting_setter() {
		$setting = self::$store::setting( 'store-test-case' );

		$setting->use_setter(
			static function ( $data ) {
				$a = 0;

				foreach ( $data['serie'] as $v ) {
					$a += $v;
				}

				$data['serie'][] = $a;

				return $data;
			},
			9
		);

		$setting->serie = array( 1, 2, 3 );

		$this->assertEquals( 4, count( $setting->serie ) );
		$this->assertSame( 6, $setting->serie[3] );
	}
}
