<?php

namespace WPCT_PLUGIN;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( '\WPCT_PLUGIN\Setting' ) ) {
	/**
	 * Plugin's setting class.
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
		 * Handle setting's default values.
		 *
		 * @var array setting's default values
		 */
		private $default;

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

		private $sanitizing = false;

		/**
		 * Stores setting data and bind itself to wp option hooks to update its data.
		 *
		 * @param string $group   setting group
		 * @param string $name    setting name
		 * @param array  $default setting default
		 * @param array  $schema  setting schema
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
		 * Proxies data attributes to class attributes.
		 *
		 * @param string $field field name
		 *
		 * @return mixed data field value or null
		 */
		public function __get( $field ) {
			$data = $this->data();

			return $data[ $field ] ?? null;
		}

		public function __set( $field, $value ) {
			$data           = $this->data();
			$data[ $field ] = $value;
			$this->update( $data );
		}

		/**
		 * Gets the setting group.
		 *
		 * @return string setting group
		 */
		public function group() {
			return $this->group;
		}

		/**
		 * Gets the setting name.
		 *
		 * @return string setting name
		 */
		public function name() {
			return $this->name;
		}

		/**
		 * Gets the concatenation of the group and the setting name.
		 *
		 * @return string setting full name
		 */
		public function option() {
			return $this->group . '_' . $this->name;
		}

		/**
		 * Setting's schema getter.
		 *
		 * @param string $field field name, optional
		 *
		 * @return mixed schema array or field value
		 */
		public function schema( $field = null ) {
			$schema = $this->schema;

			if ( $field === null ) {
				return $schema;
			}

			return $schema['properties'][ $field ] ?? null;
		}

		/**
		 * Setting's data getter.
		 *
		 * @param string $field field name, optional
		 *
		 * @return mixed data array or field value
		 */
		public function data( $field = null ) {
			if ( $this->data === null ) {
				$this->data = get_option( $this->option() );
			}

			if ( $field === null ) {
				return $this->data;
			}

			return $this->data[ $field ] ?? null;
		}

		/**
		 * Registers setting data on the database.
		 *
		 * @param array $data setting data
		 *
		 * @return bool true if the data was added, false otherwise
		 */
		public function add( $data ) {
			return add_option( $this->option(), $data );
		}

		/**
		 * Updates setting data on the database.
		 *
		 * @param array $data new setting data
		 *
		 * @return bool true if the data was updated, false otherwise
		 */
		public function update( $data ) {
			return update_option( $this->option(), $data );
		}

		/**
		 * Deletes the setting from the database.
		 *
		 * @return bool true if the data was deleted, false otherwise
		 */
		public function delete() {
			return delete_option( $this->option() );
		}

		public function flush() {
			$this->data = null;
		}

		public function skip_updates() {
			remove_filter(
				'pre_update_option_' . $this->option(),
				array( $this, 'skip_updates' ),
				99,
				0
			);

			return $this->data();
		}

		private function sanitize( $data ) {
			if ( $this->sanitizing === true ) {
				return $data;
			}

			$this->sanitizing = true;
			$data             = apply_filters( 'wpct_plugin_sanitize_setting', $data, $this );
			$data             = wpct_plugin_sanitize_with_schema( $data, $this->schema() );

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

			return $data;
		}

		public function use_getter( $getter, $p = 10 ) {
			if ( is_callable( $getter ) ) {
				$option = $this->option();
				add_filter( "option_{$option}", $getter, $p, 1 );
				add_filter( "default_option_{$option}", $getter, $p, 1 );
			}
		}

		public function use_setter( $setter, $p = 10 ) {
			if ( is_callable( $setter ) ) {
				$option = $this->option();
				add_filter(
					"sanitize_option_{$option}",
					function ( $data ) use ( $setter ) {
						if ( is_wp_error( $data ) ) {
							return $data;
						}

						return $setter( $data );
					},
					$p,
					1
				);
			}
		}

		public function use_cleaner( $cleaner, $p = 10 ) {
			if ( is_callable( $cleaner ) ) {
				$option = $this->option();
				add_action( "delete_option_{$option}", $cleaner, $p );
			}
		}
	}
}
