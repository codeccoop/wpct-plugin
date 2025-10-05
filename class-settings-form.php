<?php

namespace WPCT_PLUGIN;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( '\WPCT_PLUGIN\Settings_Form' ) ) {
	require_once 'class-singleton.php';

	class Settings_Form extends Singleton {

		private $store = null;

		/**
		 * Escape admin fields html.
		 *
		 * @param string $html Output rendered field.
		 *
		 * @return string Escaped html.
		 */
		public static function kses( $html ) {
			return wp_kses(
				$html,
				array(
					'table'  => array(
						'id'    => array(),
						'class' => array(),
					),
					'th'     => array(),
					'tr'     => array(),
					'td'     => array(),
					'input'  => array(
						'type'    => array(),
						'value'   => array(),
						'name'    => array(),
						'id'      => array(),
						'checked' => array(),
					),
					'select' => array(
						'name'     => array(),
						'id'       => array(),
						'multiple' => array(),
						'required' => array(),
					),
					'option' => array(
						'value'    => array(),
						'selected' => array(),
					),
					'div'    => array(
						'id'    => array(),
						'class' => array(),
					),
					'button' => array(
						'class'       => array(),
						'data-action' => array(),
					),
				)
			);
		}

		private static function store() {
			return static::get_instance()->store;
		}

		protected function construct( ...$args ) {
			[$store]     = $args;
			$this->store = $store;

			add_action(
				'admin_init',
				static function () use ( $store ) {
					foreach ( $store::settings() as $setting ) {
						$setting_name  = $setting->option();
						$section_name  = $setting_name . '_section';
						$section_title = esc_html(
							static::setting_title( $setting_name )
						);

						add_settings_section(
							$section_name,
							$section_title,
							static function () use ( $setting_name ) {
								$description = static::setting_description(
									$setting_name
								);
								printf( '<p>%s</p>', esc_html( $description ) );
							},
							$setting_name
						);

						$fields = array_keys( $setting->schema()['properties'] );
						foreach ( $fields as $field ) {
							static::add_setting_field( $setting, $field );
						}
					}
				}
			);

			add_action(
				'admin_enqueue_scripts',
				static function () {
					$plugin_url = plugin_dir_url( __FILE__ );

					wp_enqueue_script(
						'wpct-plugin-fieldset-control',
						$plugin_url . 'admin-form.js',
						array(),
						'1.0.0',
						array( 'in_footer' => true )
					);

					wp_enqueue_style(
						'wpct-plugin-admin-style',
						$plugin_url . 'admin-form.css',
						array(),
						'1.0.0'
					);
				},
				10,
				0
			);
		}

		/**
		 * Registers a setting field.
		 *
		 * @param Setting $setting Setting name.
		 * @param string  $field Field name.
		 */
		private static function add_setting_field( $setting, $field ) {
			$setting_name = $setting->option();
			$field_label  = esc_html( static::field_label( $field, $setting_name ) );

			add_settings_field(
				$field,
				$field_label,
				static function () use ( $setting, $field ) {
					$setting_name = $setting->option();
					$schema       = $setting->schema( $field );
					$value        = $setting->data( $field );

					echo static::kses(
						static::field_render(
							$setting_name,
							$field,
							$schema,
							$value
						)
					);
				},
				$setting_name,
				$setting_name . '_section'
			);
		}

		/**
		 * Renders the field HTML.
		 *
		 * @param string $setting Setting name.
		 * @param string $field Field name.
		 * @param array  $schema Field schema.
		 * @param mixed  $value Field value.
		 *
		 * @return string
		 */
		private static function field_render( $setting, $field, $schema, $value ) {
			if ( ! in_array( $schema['type'], array( 'array', 'object' ) ) ) {
				return static::input_render( $setting, $field, $schema, $value );
			} elseif ( $schema['type'] === 'array' && isset( $schema['enum'] ) ) {
				return self::input_render( $setting, $field, $schema, $value );
			} else {
				$fieldset = static::fieldset_render(
					$setting,
					$field,
					$schema,
					$value
				);
				if ( $schema['type'] === 'array' ) {
					$fieldset .= static::control_render( $setting, $field );
				}
				return $fieldset;
			}
		}

		/**
		 * Render input HTML.
		 *
		 * @param string $setting Setting name.
		 * @param string $field Field name.
		 * @param array  $schema Field schema.
		 * @param mixed  $value Field value.
		 *
		 * @return string
		 */
		protected static function input_render(
			$setting,
			$field,
			$schema,
			$value
		) {
			if ( $schema['type'] === 'boolean' ) {
				return sprintf(
					'<input type="checkbox" name="%s" ' .
						( $value ? 'checked="true"' : '' ) .
						' />',
					esc_attr( $setting . "[{$field}]" )
				);
			} elseif ( $schema['type'] === 'string' && isset( $schema['enum'] ) ) {
				$options = implode(
					'',
					array_map(
						function ( $opt ) use ( $value ) {
							$is_selected = $value === $opt;
							return sprintf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $opt ),
								$is_selected ? 'selected' : '',
								esc_html( $opt )
							);
						},
						(array) $schema['enum']
					)
				);

				return sprintf(
					'<select name="%s">%s</select>',
					esc_attr( $setting . "[{$field}]" ),
					$options
				);
			} elseif ( $schema['type'] === 'array' && isset( $schema['enum'] ) ) {
				$value = (array) $value;

				$options = implode(
					'',
					array_map(
						function ( $opt ) use ( $value ) {
							$is_selected = in_array( $opt, $value );
							return sprintf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $opt ),
								$is_selected ? 'selected' : '',
								esc_html( $opt )
							);
						},
						(array) $schema['enum']
					)
				);

				return sprintf(
					'<select name="%s[]" multiple required>%s</select>',
					esc_attr( $setting . "[{$field}]" ),
					$options
				);
			} else {
				return sprintf(
					'<input type="text" name="%s" value="%s" />',
					esc_attr( $setting . "[{$field}]" ),
					esc_attr( $value )
				);
			}
		}

		/**
		 * Render fieldset HTML.
		 *
		 * @param Setting $setting Setting instance.
		 * @param string  $field Field name.
		 * @param array   $schema Field schema.
		 * @param mixed   $value Field value.
		 *
		 * @return string $html Fieldset HTML.
		 */
		private static function fieldset_render(
			$setting,
			$field,
			$schema,
			$value
		) {
			$is_list = $schema['type'] === 'array';

			$table_id = $setting . '__' . str_replace( '][', '_', $field );
			$fieldset = '<table id="' . esc_attr( $table_id ) . '"';

			if ( $is_list ) {
				$fieldset .= ' class="is-list"';
			}

			$fieldset .= '>';

			$value = (array) $value;
			foreach ( array_keys( $value ) as $key ) {
				$fieldset .= '<tr>';

				if ( ! $is_list ) {
					$fieldset .= '<th>' . esc_html( $key ) . '</th>';
				} else {
					$key = (int) $key;
				}

				if ( $schema['type'] === 'object' ) {
					$sub_schema = $schema['properties'][ $key ];
				} else {
					$sub_schema = $schema['items'];
				}

				$sub_value = $value[ $key ];
				$sub_field = $field . '][' . $key;

				$fieldset .= sprintf(
					'<td>%s</td></td>',
					self::field_render(
						$setting,
						$sub_field,
						$sub_schema,
						$sub_value
					)
				);
			}

			return $fieldset . '</table>';
		}

		/**
		 * Render control HTML.
		 *
		 * @param Setting $setting Setting instance.
		 * @param string  $field Field name.
		 *
		 * @return string $html Control HTML.
		 */
		private static function control_render( $setting, $field ) {
			$field_id = str_replace( '][', '_', $field );
			ob_start();
			?>
			<div id="
			<?php
			echo esc_attr(
				$setting . '__' . $field_id . '--controls'
			);
			?>
			" class="wpct-plugin-fieldset-control">
				<button class="button button-primary" data-action="add">
				<?php
				echo esc_html(
					__( 'Add', 'wpct-plugin' )
				);
				?>
				</button>
				<button class="button button-secondary" data-action="remove">
				<?php
				echo esc_html(
					__( 'Remove', 'wpct-plugin' )
				);
				?>
				</button>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * To be overwriten by the child class. Should return the localized setting title
		 * for the menu page.
		 *
		 * @param string $setting_name
		 *
		 * @return string
		 */
		protected static function setting_title( $setting_name ) {
			return $setting_name;
		}

		/**
		 * To be overwriten by the child class. Should return the localized setting description
		 * for the menu page.
		 *
		 * @param string $setting_name
		 *
		 * @return string
		 */
		protected static function setting_description( $setting_name ) {
			return 'Setting description';
		}

		/**
		 * To be overwriten by the child class. Should return the localized
		 * field label for the menu page.
		 *
		 * @param string $field_name Name of the field.
		 * @param string $setting_name Name of the parent setting.
		 *
		 * @return string
		 */
		protected static function field_label( $field_name, $setting_name ) {
			return $field_name;
		}
	}
}
