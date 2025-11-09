<?php
/**
 * JSON Schema util functions
 *
 * @package wpct-plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Applies validation and sanitization to data based on json schemas.
 *
 * @param array  $data   Target data.
 * @param array  $schema JSON schema.
 * @param string $name   Self name.
 *
 * @return array|WP_Error validation result
 */
function wpct_plugin_sanitize_with_schema( $data, $schema, $name = '#' ) {
	if ( isset( $schema['const'] ) && $data !== $schema['const'] ) {
		return $schema['const'];
	}

	if ( isset( $schema['anyOf'] ) ) {
		$matching_schema = rest_find_any_matching_schema( $data, $schema, $name );

		if ( is_wp_error( $matching_schema ) ) {
			return $matching_schema;
		}

		if ( ! isset( $schema['type'] ) && isset( $matching_schema['type'] ) ) {
			$schema = $matching_schema;
		}
	}

	if ( isset( $schema['oneOf'] ) ) {
		$matching_schema = rest_find_one_matching_schema( $data, $schema, $name );

		if ( is_wp_error( $matching_schema ) ) {
			return $matching_schema;
		}

		if ( ! isset( $schema['type'] ) && isset( $matching_schema['type'] ) ) {
			$schema = $matching_schema;
		}
	}

	if ( ! isset( $schema['type'] ) ) {
		return new WP_Error(
			'rest_invalid_schema',
			'`type` is a required attribute of a schema',
			$schema
		);
	}

	if ( is_array( $schema['type'] ) ) {
		$type = rest_get_best_type_for_value( $data, $schema['type'] );

		if ( ! $type ) {
			return new WP_Error(
				'rest_invalid_type',
				"{$name} is not of type " . implode( $schema['type'] ),
				$data
			);
		} else {
			$schema['type'] = $type;
		}
	}

	$sanitize_callback = $schema['sanitize_callback'] ?? null;
	if ( is_callable( $sanitize_callback ) ) {
		$data = $sanitize_callback( $data, $schema, $name );

		if ( ! $data || is_wp_error( $data ) ) {
			if ( isset( $schema['default'] ) ) {
				return $schema['default'];
			}

			return new WP_Error( 'rest_invalid_value', "{$name} is invalid" );
		}

		return $data;
	}

	$validate_callback = $schema['validate_callback'] ?? null;
	if ( is_callable( $validate_callback ) ) {
		$is_valid = $validate_callback( $data, $schema, $name );

		if ( ! $is_valid || is_wp_error( $is_valid ) ) {
			if ( isset( $schema['default'] ) ) {
				return $schema['default'];
			}

			return new WP_Error( 'rest_invalid_value', "{$name} is invalid" );
		}
	}

	if ( 'object' === $schema['type'] ) {
		if ( ! rest_is_object( $data ) ) {
			return new WP_Error(
				'rest_invalid_type',
				"{$name} is not of type object",
				$data
			);
		}

		$data = rest_sanitize_object( $data );

		$required = wp_is_numeric_array( $schema['required'] ?? false )
			? $schema['required']
			: array();

		$props = is_array( $schema['properties'] ?? false )
			? $schema['properties']
			: array();

		$additional_properties = $schema['additionalProperties'] ?? true;
		$min_props             = $schema['minProps'] ?? 0;
		$max_props             = $schema['maxProps'] ?? INF;

		foreach ( $data as $prop => $val ) {
			$prop_schema =
				$props[ $prop ] ??
				rest_find_matching_pattern_property_schema(
					$prop,
					$schema,
					$name . '.' . $prop
				);

			if ( $prop_schema ) {
				$is_required =
					in_array( $prop, $required, true ) ||
					( $prop_schema['required'] ?? false ) === true;

				$val = wpct_plugin_sanitize_with_schema(
					$val,
					$prop_schema,
					$name . '.' . $prop
				);

				if ( is_wp_error( $val ) ) {
					$error = $val;

					if ( isset( $props[ $prop ]['default'] ) ) {
						$data[ $prop ] = $props[ $prop ]['default'];
					} elseif ( ! $is_required ) {
						unset( $data[ $prop ] );
					} else {
						return $error;
					}
				} else {
					$data[ $prop ] = $val;
				}

				if ( $is_required ) {
					$index = array_search( $prop, $required, true );
					array_splice( $required, $index, 1 );
				}
			} elseif ( false === $additional_properties ) {
				unset( $data[ $prop ] );
			}
		}

		if ( count( $required ) ) {
			foreach ( $required as $prop ) {
				if ( isset( $props[ $prop ]['default'] ) ) {
					$data[ $prop ] = $props[ $prop ]['default'];
				} elseif ( 'boolean' === $props[ $prop ]['type'] ) {
					$data[ $prop ] = false;
				} else {
					return new WP_Error(
						'rest_property_required',
						"{$prop} is required property of {$name}",
						array( 'value' => $data )
					);
				}
			}
		}

		foreach ( $props as $prop => $prop_schema ) {
			if (
				isset( $prop_schema['required'] ) &&
				true === $prop_schema['required'] &&
				! isset( $data[ $prop ] )
			) {
				if ( isset( $prop_schema['default'] ) ) {
					$data[ $prop ] = $prop_schema['default'];
				} elseif ( 'boolean' === $prop_schema['type'] ) {
					$data[ $prop ] = false;
				} else {
					return new WP_Error(
						'rest_property_required',
						"{$prop} is required property of {$name}",
						array( 'value' => $data )
					);
				}
			}
		}

		if ( count( $data ) < $min_props ) {
			return new WP_Error(
				'rest_too_few_properties',
				"{$name} has less properties than required",
				array(
					'minProps' => $min_props,
					'value'    => $data,
				)
			);
		} elseif ( count( $data ) > $max_props ) {
			return new WP_Error(
				'rest_too_many_properties',
				"{$name} exceed the allowed number of properties",
				array(
					'maxProps' => $max_props,
					'value'    => $data,
				)
			);
		}

		return rest_sanitize_value_from_schema( $data, $schema );
	} elseif ( 'array' === $schema['type'] ) {
		if ( ! rest_is_array( $data ) ) {
			return new WP_Error(
				'rest_invalid_type',
				"{$name} is not of type array",
				array( 'value' => $data )
			);
		}

		$data = rest_sanitize_array( $data );

		$items            = $schema['items'] ?? array();
		$additional_items = $data['additionalItems'] ?? true;
		$min_items        = $schema['minItems'] ?? 0;
		$max_items        = $schema['maxItems'] ?? INF;

		// support for array enums.
		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
			$enum_items = array();

			foreach ( $data as $item ) {
				if ( in_array( $item, $schema['enum'], true ) ) {
					$enum_items[] = $item;
				}
			}

			$data = $enum_items;
		}

		if ( wp_is_numeric_array( $items ) ) {
			if ( false === $additional_items && count( $data ) > count( $items ) ) {
				return new WP_Error(
					'rest_invalid_items_count',
					"{$name} contains invalid count items",
					array(
						'items' => $items,
						'value' => $data,
					)
				);
			}
		} else {
			$i      = 0;
			$len    = count( $data );
			$_items = array();

			while ( $i < $len ) {
				$_items[] = $items;
				++$i;
			}

			$items = $_items;
		}

		$i   = 0;
		$len = count( $data );

		while ( $i < $len ) {
			if ( isset( $items[ $i ] ) ) {
				$val = wpct_plugin_sanitize_with_schema(
					$data[ $i ],
					$items[ $i ],
					$name . "[{$i}]"
				);

				if ( is_wp_error( $val ) ) {
					unset( $data[ $i ] );
				} else {
					$data[ $i ] = $val;
				}
			}
			++$i;
		}

		$data = array_values( $data );

		if ( count( $data ) > $max_items ) {
			return new WP_Error(
				'rest_too_many_items',
				"{$name} contains more items than allowed",
				array(
					'maxItems' => $max_items,
					'value'    => $data,
				)
			);
		} elseif ( count( $data ) < $min_items ) {
			return new WP_Error(
				'rest_too_few_items',
				"{$name} contains less items than required",
				array(
					'minItems' => $min_items,
					'value'    => $data,
				)
			);
		}

		if (
			isset( $schema['uniqueItems'] ) &&
			! rest_validate_array_contains_unique_items( $data )
		) {
			return new WP_Error(
				'rest_duplicate_items',
				"{$name} has duplicate items",
				array( 'value' => $data )
			);
		}

		return rest_sanitize_value_from_schema( $data, $schema, $name );
	} elseif ( 'boolean' === $schema['type'] ) {
		$data = (bool) $data;
	}

	$is_valid = rest_validate_value_from_schema( $data, $schema, $name );

	$error = is_wp_error( $is_valid ) ? $is_valid : null;
	if ( $error ) {
		if ( isset( $schema['default'] ) ) {
			return $schema['default'];
		}

		return $error;
	}

	return rest_sanitize_value_from_schema( $data, $schema, $name );
}

/**
 * Merge numeric arrays with default values and returns the union of
 * the two arrays without repetitions.
 *
 * @param array $list    Numeric array with values.
 * @param array $default Default values for the list.
 *
 * @return array
 */
function wpct_plugin_merge_array( $list, $default ) {
	if ( ! is_array( $list ) ) {
		if ( is_array( $default ) ) {
			return $default;
		}

		return array();
	}

	if ( ! is_array( $default ) ) {
		return $list;
	}

	return array_values( array_unique( array_merge( $list, $default ) ) );
}

/**
 * Merge collection of arrays with its defaults, apply defaults to
 * each item of the collection and return the collection without
 * repetitions.
 *
 * @param array $collection Input collection of arrays.
 * @param array $default    Default values for the collection.
 * @param array $schema     JSON schema of the collection.
 *
 * @return array
 */
function wpct_plugin_merge_collection( $collection, $default, $schema = array() ) {
	if ( ! isset( $schema['type'] ) ) {
		if ( isset( $default[0] ) ) {
			$schema['type'] = wpct_plugin_get_json_schema_type( $default[0] );
		} else {
			$schema['type'] = wpct_plugin_get_json_schema_type( $collection[0] );
		}
	}

	if ( ! in_array( $schema['type'], array( 'array', 'object' ), true ) ) {
		return wpct_plugin_merge_array( $collection, $default );
	}

	if ( 'object' === $schema['type'] ) {
		foreach ( $default as $default_item ) {
			$col_item = null;

			$l = count( $collection );
			for ( $i = 0; $i < $l; $i++ ) {
				$col_item = $collection[ $i ];

				if ( ! isset( $col_item['name'] ) ) {
					continue;
				}

				if (
					$col_item['name'] === $default_item['name'] &&
					( $col_item['ref'] ?? false ) ===
						( $default_item['ref'] ?? false )
				) {
					break;
				}
			}

			$c = count( $collection );
			if ( $i === $c ) {
				$collection[] = $default_item;
			} else {
				$collection[ $i ] = wpct_plugin_merge_object(
					$col_item,
					$default_item,
					$schema
				);
			}
		}
	// phpcs:disable Generic.CodeAnalysis.EmptyStatement
	} elseif ( 'array' === $schema['type'] ) {
		// TODO: Handle matrix case.
	}
	// phpcs:enable Generic.CodeAnalysis.EmptyStatement

	return $collection;
}

/**
 * Generic array default values merger. Switches between merge_collection and merge_list
 * based on the list items' data type.
 *
 * @param array $array   Input array.
 * @param array $default Default array values.
 * @param array $schema  JSON schema of the array values.
 *
 * @return array Array fullfilled with defaults.
 */
function wpct_plugin_merge_object( $array, $default, $schema = array() ) {
	foreach ( $default as $key => $default_value ) {
		if ( empty( $array[ $key ] ) ) {
			$array[ $key ] = $default_value;
		} else {
			$value = $array[ $key ];
			$type  =
				$schema['properties'][ $key ]['type'] ??
				wpct_plugin_get_json_schema_type( $default_value );

			if ( 'object' === $type ) {
				if ( ! is_array( $value ) || wp_is_numeric_array( $value ) ) {
					$array[ $key ] = $default_value;
				} else {
					$array[ $key ] = wpct_plugin_merge_object(
						$value,
						$default_value,
						$schema['properties'][ $key ] ?? array()
					);
				}
			} elseif ( 'array' === $type ) {
				if ( ! wp_is_numeric_array( $value ) ) {
					$array[ $key ] = $default_value;
				} else {
					$array[ $key ] = wpct_plugin_merge_collection(
						$value,
						$default_value,
						$schema['properties'][ $key ]['items'] ?? array()
					);
				}
			}
		}
	}

	if ( isset( $schema['properties'] ) ) {
		foreach ( $array as $key => $value ) {
			if ( ! isset( $schema['properties'][ $key ] ) ) {
				unset( $array[ $key ] );
			}
		}
	}

	return $array;
}

/**
 * Gets the corresponding JSON schema type from a given value.
 *
 * @param mixed $value Target value.
 *
 * @return string JSON schema value type.
 */
function wpct_plugin_get_json_schema_type( $value ) {
	if ( wp_is_numeric_array( $value ) ) {
		return 'array';
	} elseif ( is_array( $value ) ) {
		return 'object';
	} else {
		$type = gettype( $value );
		switch ( $type ) {
			case 'double':
				return 'number';
			default:
				return strtolower( $type );
		}
	}
}

/**
 * Remove private properties from an array based on a schema.
 *
 * @param array $data Target data.
 * @param array $schema Data JSON schema.
 *
 * @return array Sanitized data.
 */
function wpct_plugin_prune_rest_private_properties( $data, $schema ) {
	if ( $schema['anyOf'] ) {
		$schema = rest_find_any_matching_schema( $data, $schema, '.' );

		if ( is_wp_error( $schema ) ) {
			return $data;
		}
	}

	if ( $schema['oneOf'] ) {
		$schema = rest_find_one_matching_schema( $data, $schema, '.' );

		if ( is_wp_error( $schema ) ) {
			return $data;
		}
	}

	if ( ! isset( $schema['type'] ) ) {
		return $data;
	}

	$public = boolval( $schema['public'] ?? true );

	if ( ! $public ) {
		return;
	}

	if ( 'object' === $schema['type'] ) {
		if ( ! is_array( $data ) || ! isset( $schema['properties'] ) ) {
			return $data;
		}

		foreach ( array_keys( $data ) as $prop ) {
			$prop_schema = $schema['properties'][ $prop ] ?? array();
			$value       = wpct_plugin_prune_rest_private_properties(
				$data[ $prop ],
				$prop_schema
			);

			if ( ! $value && $value !== $data[ $prop ] ) {
				unset( $data[ $prop ] );
			}
		}
	} elseif ( 'array' === $schema['type'] ) {
		if ( ! wp_is_numeric_array( $data ) || ! isset( $schema['items'] ) ) {
			return $data;
		}

		if ( wp_is_numeric_array( $schema['items'] ) ) {
			$items = $schema['items'];
		} else {
			$i = 0;

			$l = count( $data );
			while ( $i < $l ) {
				$items[] = $schema['items'];
				++$i;
			}
		}

		$l = count( $data );
		for ( $i = 0; $i < $l; $i++ ) {
			$value = wpct_plugin_prune_rest_private_properties(
				$data[ $i ],
				$items[ $i ]
			);

			if ( ! $value && $data[ $i ] !== $value ) {
				unset( $data[ $i ] );
			}
		}

		$data = array_values( $data );
	}

	return $data;
}

/**
 * Remove private properties from a JSON schema.
 *
 * @param array $schema Schema definition.
 *
 * @return array Sanitized schema.
 */
function wpct_plugin_prune_rest_private_schema_properties( $schema ) {
	if ( is_array( $schema['anyOf'] ?? null ) ) {
		$prop_schemas = array();

		foreach ( $schema['anyOf'] as $prop_schema ) {
			$prop_schema = wpct_plugin_prune_rest_private_schema_properties(
				$prop_schema
			);

			if ( $prop_schema ) {
				$schema['anyOf'][] = $prop_schema;
			}
		}

		$schema['anyOf'] = array_values( $schema['anyOf'] );
	} elseif ( is_array( $schema['oneOf'] ?? null ) ) {
		$prop_schemas = array();

		foreach ( $schema['oneOf'] as $prop_schema ) {
			$prop_schema = wpct_plugin_prune_rest_private_schema_properties(
				$prop_schema
			);

			if ( $prop_schema ) {
				$prop_schemas = $prop_schema;
			}
		}

		$schema['oneOf'] = $prop_schemas;
	}

	if ( ! isset( $schema['type'] ) ) {
		return $schema;
	}

	$public = boolval( $schema['public'] ?? true );

	if ( ! $public ) {
		return;
	}

	if (
		'object' === $schema['type'] &&
		is_array( $schema['properties'] ?? null )
	) {
		foreach ( $schema['properties'] as $prop => $prop_schema ) {
			$prop_schema = wpct_plugin_prune_rest_private_schema_properties(
				$prop_schema
			);

			if ( ! $prop_schema ) {
				unset( $schema['properties'][ $prop ] );
				$schema['additionalProperties'] = true;
			}
		}
	} elseif ( 'array' === $schema['type'] && isset( $schema['items'] ) ) {
		if ( wp_is_numeric_array( $schema['items'] ) ) {
			$schema_items = array();

			foreach ( $schema['items'] as $schema_item ) {
				$schema_item = wpct_plugin_prune_rest_private_schema_properties(
					$schema_item
				);

				if ( $schema_item ) {
					$schema_items[] = $schema_item;
				} else {
					$schema['additionalItems'] = true;
				}
			}
			$schema['items'] = $schema_items;
		} else {
			$item_schema = wpct_plugin_prune_rest_private_schema_properties(
				$schema['items']
			);

			if ( ! $item_schema ) {
				return;
			}
		}
	}

	return $schema;
}
