<?php
/**
 * Class JsonSchemaUtilsTest
 *
 * @package wpct-plugin-tests
 */

/**
 * Json schema utils test case.
 */
class JsonSchemaUtilsTest extends WP_UnitTestCase {
	public static function provider() {
		return include 'data/data.php';
	}

	/**
	 * @dataProvider provider
	 */
	public function test_json_sanitization( $value, $schema, $result ) {
		if ( $schema['type'] === 'array' ) {
			$a = 1;
		}

		$value = wpct_plugin_sanitize_with_schema( $value, $schema );

		if ( WP_Error::class === $result ) {
			$this->assertWPError( $value );
		} else {
			$this->assertEquals( $result, $value );
		}
	}
}
