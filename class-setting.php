<?php
/**
 * Class Setting
 *
 * @package wpct-plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

namespace WPCT_PLUGIN;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Setting base class.
 */
class Setting {


	/**
	 * Handle setting's group.
	 *
	 * @var string setting's group
	 */
	private $group;

	/**
	 * Handle setting's name.
	 *
	 * @var string setting's name
	 */
	private $name;

	/**
	 * Handle setting's schema.
	 *
	 * @var array setting's schema
	 */
	private $schema;

	/**
	 * Handle setting's data.
	 *
	 * @var array|null setting's data
	 */
	private $data = null;

	/**
	 * Handles the a boolean value that indicates if a sanitization is taking place.
	 *
	 * @var boolean
	 */
	private $sanitizing = false;

	/**
	 * Stores setting data and bind itself to wp option hooks to update its data.
	 *
	 * @param string $group   setting group.
	 * @param string $name    setting name.
	 * @param array  $default setting default.
	 * @param array  $schema  setting schema.
	 */
	public function __construct( $group, $name, $default, $schema ) {
		$this->group  = $group;
		$this->name   = $name;
		$this->schema = $schema;

		$option = $this->option();

		register_setting(
			$option,
			$option,
			array(
				'type'              => 'object',
				'show_in_rest'      => false,
				'sanitize_callback' => function ( $data ) {
					return $this->sanitize( $data );
				},
				'default'           => $default,
			)
		);

		add_action(
			"add_option_{$option}",
			function ( $option, $data ) {
				$this->data       = $data;
				$this->sanitizing = false;
			},
			5,
			2
		);

		add_action(
			"update_option_{$option}",
			function ( $from, $to ) {
				$this->data       = $to;
				$this->sanitizing = false;
			},
			5,
			2
		);

		add_action(
			"delete_option_{$option}",
			function () {
				$this->data       = null;
				$this->sanitizing = false;
			},
			5,
			0
		);

		add_filter(
			"option_{$option}",
			function ( $data ) {
				if ( ! is_array( $data ) ) {
					return array();
				}

				return $data;
			},
			0,
			1
		);

		add_filter(
			"default_option_{$option}",
			function ( $data ) {
				if ( ! is_array( $data ) ) {
					return array();
				}

				return $data;
			},
			0,
			1
		);
	}

	/**
	 * Proxy class attributes reads to setting fields.
	 *
	 * @param string $field Field name.
	 *
	 * @return mixed|null Field value or null.
	 */
	public function __get( $field ) {
		$data = $this->data();

		return $data[ $field ] ?? null;
	}

	/**
	 * Proxy instance attributes writes to setting fields.
	 *
	 * @param string     $field Setting field name.
	 * @param mixed|null $value field value.
	 */
	public function __set( $field, $value ) {
		$data           = $this->data();
		$data[ $field ] = $value;
		$this->update( $data );
	}

	/**
	 * Gets the setting group.
	 *
	 * @return string Setting group.
	 */
	public function group() {
		return $this->group;
	}

	/**
	 * Gets the setting name.
	 *
	 * @return string Setting name.
	 */
	public function name() {
		return $this->name;
	}

	/**
	 * Gets the concatenation of the group and the setting name.
	 *
	 * @return string Setting full name.
	 */
	public function option() {
		return $this->group . '_' . $this->name;
	}

	/**
	 * Setting's schema getter.
	 *
	 * @param string $field Field name, optional.
	 *
	 * @return mixed Schema array or field value.
	 */
	public function schema( $field = null ) {
		$schema = $this->schema;

		if ( null === $field ) {
			return $schema;
		}

		return $schema['properties'][ $field ] ?? null;
	}

	/**
	 * Setting's data getter.
	 *
	 * @param string $field Field name, optional.
	 *
	 * @return mixed Setting data or field value
	 */
	public function data( $field = null ) {
		if ( null === $this->data ) {
			$this->data = get_option( $this->option() );
		}

		if ( null === $field ) {
			return $this->data;
		}

		return $this->data[ $field ] ?? null;
	}

	/**
	 * Registers setting data on the database.
	 *
	 * @param array $data Setting data.
	 * @param bool  $autoload Whether to load the option when WordPress starts up.
	 *
	 * @return bool Success status.
	 */
	public function add( $data, $autoload = false ) {
		return add_option( $this->option(), $data, '', $autoload );
	}

	/**
	 * Updates setting data on the database.
	 *
	 * @param array $data New setting data.
	 * @param bool  $autoload Whether to load the option when WordPress starts up.
	 *
	 * @return bool Success status.
	 */
	public function update( $data, $autoload = false ) {
		return update_option( $this->option(), $data, $autoload );
	}

	/**
	 * Deletes the setting from the database.
	 *
	 * @return bool Success status.
	 */
	public function delete() {
		return delete_option( $this->option() );
	}

	/**
	 * Flush the setting cache.
	 */
	public function flush() {
		$this->data = null;
	}

	/**
	 * Internal method to be hooked on the 'pre_update_option' hook and used to prevent
	 * setting updates after failed data sanitizations.
	 *
	 * @return array Setting data.
	 */
	public function skip_updates() {
		remove_filter(
			'pre_update_option_' . $this->option(),
			array( $this, 'skip_updates' ),
			99,
			0
		);

		return $this->data();
	}

	/**
	 * Setting data sanitizer.
	 *
	 * @param array $data Setting data.
	 *
	 * @return array Sanitized data.
	 */
	private function sanitize( $data ) {
		if ( true === $this->sanitizing ) {
			return $data;
		}

		$backup           = $this->data();
		$this->sanitizing = true;

		$data = apply_filters( 'wpct_plugin_sanitize_setting', $data, $this );
		$data = wpct_plugin_sanitize_with_schema( $data, $this->schema() );

		if ( is_wp_error( $data ) ) {
			add_filter(
				'pre_update_option_' . $this->option(),
				array( $this, 'skip_updates' ),
				99,
				0
			);

			add_settings_error(
				$this->name(),
				esc_attr( $this->option() ),
				$data->get_error_message(),
				'error'
			);
		}

		if ( ! wpct_plugin_diff_arrays( $backup, $data ) ) {
			$this->sanitizing = false;
		}

		return $data;
	}

	/**
	 * Registers a getter callback bound to the setting data. A getter is a
	 * function to be called each time a setting data is accessed. The getter will
	 * recive the setting data as input argument and has to return the data as its
	 * output. The output of the getters chain will be the public setting data.
	 *
	 * @param callable $getter Callback function.
	 * @param integer  $p Priority of the callback in the getters chain.
	 */
	public function use_getter( $getter, $p = 10 ) {
		if ( is_callable( $getter ) ) {
			$option = $this->option();
			add_filter( "option_{$option}", $getter, $p, 1 );
			add_filter( "default_option_{$option}", $getter, $p, 1 );
		}
	}

	/**
	 * Register a setter callback bound to the setting data. A setter is a
	 * function to be called on each setting value assignation. The setter will
	 * receive the data as an input argument and has to return the data as its
	 * output. The output of the setters chain will be the setting data stored once
	 * the database.
	 *
	 * @param callable $setter Callback function.
	 * @param integer  $p Priority of the callabck in the setters chain.
	 */
	public function use_setter( $setter, $p = 10 ) {
		if ( is_callable( $setter ) ) {
			$option = $this->option();
			add_filter(
				"sanitize_option_{$option}",
				function ( $data ) use ( $setter ) {
					if ( $this->sanitizing || is_wp_error( $data ) ) {
						return $data;
					}

					return $setter( $data );
				},
				$p,
				1
			);
		}
	}

	/**
	 * Registers a cleaner callback bound to the setting data. A cleaner is a
	 * function to be called each time a setting is deleted from the database.
	 *
	 * @param callable $cleaner Callback function.
	 * @param integer  $p Priority of the cleaner in the cleaners chain.
	 */
	public function use_cleaner( $cleaner, $p = 10 ) {
		if ( is_callable( $cleaner ) ) {
			$option = $this->option();
			add_action( "delete_option_{$option}", $cleaner, $p );
		}
	}
}
