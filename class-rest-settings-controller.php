<?php

namespace WPCT_PLUGIN;

use Error;
use Exception;
use WP_Error;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Plugin REST settings controller.
 */
class REST_Settings_Controller extends Singleton {


	/**
	 * Handle plugin settings group name.
	 *
	 * @var string
	 */
	private $group;

	/**
	 * Handles plugin settings instances.
	 */
	private $settings = array();

	/**
	 * Setup a new rest settings controller.
	 *
	 * @param string $group plugin settings group name
	 *
	 * @return object instance of REST_Controller
	 */
	final public static function setup( $group ) {
		return self::get_instance( $group );
	}

	/**
	 * Internal WP_Error proxy.
	 *
	 * @param string $code
	 * @param string $message
	 * @param mixed  $data
	 *
	 * @return WP_Error
	 */
	final protected static function error(
		$code,
		$message = '',
		$status = 500,
		$data = array()
	) {
		$data = array_merge( $data, array( 'status' => $status ) );

		return new WP_Error( (string) $code, $message, $data );
	}

	final public static function bad_request( $message = '', $data = array() ) {
		return self::error( 'rest_bad_request', $message, 400, $data );
	}

	final public static function not_found( $message = '', $data = array() ) {
		return self::error( 'rest_not_found', $message, 404, $data );
	}

	final public static function unauthorized( $message = '', $data = array() ) {
		return self::error( 'rest_unauthorized', $message, 401, $data );
	}

	final public static function forbidden( $message = '', $data = array() ) {
		return self::error( 'rest_forbidden', $message, 403, $data );
	}

	final public static function internal_server_error(
		$message = '',
		$data = array()
	) {
		return self::error( 'rest_internal_server_error', $message, 500, $data );
	}

	/**
	 * Store the group name and binds class initializer to the rest_api_init hook
	 *
	 * @param string $group settings group name
	 */
	protected function construct( ...$args ) {
		list( $group ) = $args;
		$this->group   = $group;

		add_action(
			'rest_api_init',
			static function () {
				static::init();
			}
		);

		add_action(
			'wpct_plugin_registered_settings',
			function ( $settings, $group ) {
				if ( $group === $this->group ) {
					if ( $settings instanceof Setting ) {
						$settings = array( $settings );
					}

					$this->settings = $settings;
				}
			},
			10,
			2
		);
	}

	final protected static function group() {
		return self::get_instance()->group;
	}

	final public static function namespace() {
		return apply_filters( 'wpct_plugin_rest_namespace', self::group() );
	}

	final public static function version() {
		return (int) apply_filters(
			'wpct_plugin_rest_version',
			1,
			self::group()
		);
	}

	final protected static function settings() {
		return self::get_instance()->settings;
	}

	/**
	 * REST_Settings_Controller initializer.
	 */
	protected static function init() {
		// register settings endpoint
		$namespace = self::namespace();
		$version   = self::version();

		$args   = array();
		$schema = self::schema();

		foreach ( $schema['properties'] as $prop => $prop_schema ) {
			$args[ $prop ]                      = $prop_schema;
			$args[ $prop ]['sanitize_callback'] = fn ( $data ) => $data;
			$args[ $prop ]['validate_callback'] = '__return_true';
		}

		register_rest_route(
			"{$namespace}/v{$version}",
			'/settings/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						return self::get_settings();
					},
					'permission_callback' => array(
						self::class,
						'permission_callback',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static function ( $request ) {
						return self::set_settings( $request );
					},
					'permission_callback' => array(
						self::class,
						'permission_callback',
					),
					'validate_callback'   => '__return_true',
					'args'                => $args,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => static function () {
						return self::delete_settings();
					},
					'permission_callback' => array(
						self::class,
						'permission_callback',
					),
				),
				'schema' => static function () {
					return wpct_plugin_prune_rest_private_schema_properties(
						self::schema()
					);
				},
				// 'allow_batch' => ['v1' => false],
			)
		);
	}

	/**
	 * Setting rest schema getter.
	 *
	 * @return array
	 */
	private static function schema() {
		$settings = self::settings();

		$properties = array();

		foreach ( $settings as $setting ) {
			$properties[ $setting->name() ] = $setting->schema();
		}

		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => self::group(),
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * GET requests settings endpoint callback.
	 *
	 * @return array<string, array> $settings associative array with settings data
	 */
	private static function get_settings() {
		$data     = array();
		$settings = self::settings();

		foreach ( $settings as $setting ) {
			$data[ $setting->name() ] = $setting->data();
		}

		return $data;
	}

	/**
	 * POST requests settings endpoint callback. Store settings on the options table.
	 *
	 * @param REST_Request $request input rest request
	 *
	 * @return array new settings state
	 */
	private static function set_settings( $request ) {
		try {
			$data = $request->get_json_params();

			$settings = self::settings();

			foreach ( $settings as $setting ) {
				if ( ! isset( $data[ $setting->name() ] ) ) {
					continue;
				}

				$to = $data[ $setting->name() ];
				$setting->update( $to );
				$setting->flush();
			}

			$response = array();

			foreach ( $settings as $setting ) {
				$response[ $setting->name() ] = $setting->data();
			}

			return $response;
		} catch ( Error | Exception $e ) {
			return self::error(
				'internal_server_error',
				$e->getMessage(),
				500,
				$data
			);
		}
	}

	private static function delete_settings() {
		$settings = self::settings();

		foreach ( $settings as $setting ) {
			$setting->delete();
		}

		return array( 'success' => true );
	}

	/**
	 * Check if current user can manage options.
	 *
	 * @return bool
	 */
	final public static function permission_callback() {
		return current_user_can( 'manage_options' )
			? true
			: self::error(
				'rest_unauthorized',
				'You can\'t manage wp options',
				403
			);
	}

	public static function is_doing_rest() {
		$ns  = static::get_instance()->namespace();
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: null;

		return $uri && preg_match( "/\/wp-json\/{$ns}\//", $uri );
	}
}
