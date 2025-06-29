<?php

/**
 * Applies validation and sanitization to data based on json schemas.
 *
 * @param array $data Target data.
 * @param array $schema JSON schema.
 *
 * @return array|WP_Error Validation result.
 */
function wpct_plugin_validate_with_schema($data, $schema, $name = 'Data')
{
    if (!isset($schema['type'])) {
        return new WP_Error('rest_invalid_schema', '`type` is a required attribute of a schema', $schema);
    }

    if (isset($schema['anyOf'])) {
        $matching_schema = rest_find_any_matching_schema($data, $schema);
        if (is_wp_error($matching_schema)) {
            return $matching_schema;
        }

        if (!isset($schema['type']) && isset($matching_schema['type'])) {
            $schema['type'] = $matching_schema['type'];
        }
    }

    if (isset($schema['oneOf'])) {
        $matching_schema = rest_find_one_matching_schema($data, $schema);
        if (is_wp_error($matching_schema)) {
            return $matching_schema;
        }

        if (!isset($schema['type']) && isset($matching_schema['type'])) {
            $schema['type'] = $matching_schema['type'];
        }
    }

    if (is_array($schema['type'])) {
        $type = rest_get_best_type_for_value($data, $schema['type']);
        if (!$type) {
            return new WP_Error('rest_invalid_type', "{$name} is not of type " . implode($schema['type']), $data);
        } else {
            $schema['type'] = $type;
        }
    }

    if ($schema['type'] === 'object') {
        if (!rest_is_object($data)) {
            return new WP_Error('rest_invalid_type', "{$name} is not of type object", $data);
        }

        $data = rest_sanitize_object($data);

        $required = wp_is_numeric_array($schema['required'] ?? false) ? $schema['required'] : [];
        $props = is_array($schema['properties'] ?? false) ? $schema['properties'] : [];
        $additionalProperties = $schema['additionalProperties'] ?? true;
        $minProps = $schema['minProps'] ?? 0;
        $maxProps = $schema['maxProps'] ?? INF;

        if (count($data) > $maxProps) {
            return new WP_Error(
                'rest_too_few_properties',
                "{$name} has less properties than required",
                ['minProps' => $minProps, 'value' => $data],
            );
        } elseif (count($data) < $minProps) {
            return new WP_Error(
                'rest_too_many_properties',
                "{$name} exceed the allowed number of properties",
                ['maxProps' => $maxProps, 'value' => $data],
            );
        }

        foreach ($data as $prop => $val) {
            $is_required = array_search($prop, $required, true);
            if (is_int($is_required)) {
                array_splice($required, $is_required, 1);
            }

            $prop_schema = $props[$prop] ?? rest_find_matching_pattern_property_schema($prop, $schema);
            if ($prop_schema) {
                $val = wpct_plugin_validate_with_schema($val, $prop_schema, $name . '.' . $prop);
                if ($error = is_wp_error($val) ? $val : null) {
                    if (isset($props[$prop]['default'])) {
                        $data[$prop] = $props[$prop]['default'];
                    } else {
                        return $error;
                    }
                } else {
                    $data[$prop] = $val;
                }
            } elseif ($additionalProperties === false) {
                unset($data[$prop]);
                // return new WP_Error('rest_additional_properties_forbidden', "{$prop} is not a valid property of {$name}", ['value' => $data]);
            }
        }

        if (count($required)) {
            foreach ($required as $prop) {
                if (!isset($props[$prop]['default'])) {
                    return new WP_Error('rest_property_required', "{$prop} is required property of {$name}", ['value' => $data]);
                }

                $data[$prop] = $props[$prop]['default'];
            }
        }

        foreach ($props as $prop => $prop_schema) {
            if (isset($prop_schema['required']) && $prop_schema['required'] === true && !isset($data[$prop])) {
                if (isset($prop_schema['default'])) {
                    $data[$prop] = $prop_schema['default'];
                } else {
                    return new WP_Error('rest_property_required', "{$prop} is required property of {$name}", ['value' => $data]);
                }
            }
        }

        return rest_sanitize_value_from_schema($data, $schema);
    } elseif ($schema['type'] === 'array') {
        if (!rest_is_array($data)) {
            return new WP_Error('rest_invalid_type', "{$name} is not of type array", ['value' => $data]);
        }

        $data = rest_sanitize_array($data);

        $items = $schema['items'] ?? [];
        $additionalItems = $data['additionalItems'] ?? true;
        $minItems = $data['minItems'] ?? 0;
        $maxItems = $data['maxItems'] ?? INF;

        if (isset($schema['uniqueItems']) && !rest_validate_array_contains_unique_items($data)) {
            return new WP_Error('rest_duplicate_items', "{$name} has duplicate items", ['value' => $data]);
        }

        // support for array enums
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $items = [];
            foreach ($data as $item) {
                if (in_array($item, $schema['enum'], true)) {
                    $items[] = $item;
                }
            }

            $data = $items;
        }

        if (count($data) > $maxItems) {
            return new WP_Error('rest_too_many_items', "{$name} contains more items than allowed", ['maxItems' => $maxItems, 'value' => $data]);
        } elseif (count($data) < $minItems) {
            return new WP_Error('rest_too_few_items', "{$name} contains less items than required", ['minItems' => $minItems, 'value' => $data]);
        }

        if (wp_is_numeric_array($items)) {
            if ($additionalItems === false && count($data) > count($items)) {
                return new WP_Error('rest_invalid_items_count', "{$name} contains invalid count items", ['items' => $items, 'value' => $data]);
            }
        } else {
            $i = 0;
            $_items = [];
            while ($i < count($data)) {
                $_items[] = $items;
                $i++;
            }

            $items = $_items;
        }

        $i = 0;
        while ($i < count($data)) {
            if (isset($items[$i])) {
                $val = wpct_plugin_validate_with_schema($data[$i], $items[$i], $name . "[{$i}]");
                if (is_wp_error($val)) {
                    return $val;
                }

                $data[$i] = $val;
            }
            $i++;
        }

        return rest_sanitize_value_from_schema($data, $schema, $name);
    }

    $is_valid = rest_validate_value_from_schema($data, $schema, $name);
    if ($error = is_wp_error($is_valid) ? $is_valid : null) {
        if (isset($schema['default'])) {
            return $schema['default'];
        }

        return $error;
    }

    return rest_sanitize_value_from_schema($data, $schema, $name);
}

/**
 * Merge numeric arrays with default values and returns the union of
 * the two arrays without repetitions.
 *
 * @param array $list Numeric array with values.
 * @param array $default Default values for the list.
 *
 * @return array
 */
function wpct_plugin_merge_array($list, $default)
{
    return array_values(array_unique(array_merge($list, $default)));
}

/**
 * Merge collection of arrays with its defaults, apply defaults to
 * each item of the collection and return the collection without
 * repetitions.
 *
 * @param array $collection Input collection of arrays.
 * @param array $default Default values for the collection.
 * @param array $schema JSON schema of the collection.
 *
 * @return array
 */
function wpct_plugin_merge_collection($collection, $default, $schema = [])
{
    if (!isset($schema['type'])) {
        $schema['type'] = wpct_plugin_get_json_schema_type($default[0]);
    }

    if (!in_array($schema['type'], ['array', 'object'])) {
        return wpct_plugin_merge_array($collection, $default);
    }

    if ($schema['type'] === 'object') {
        foreach ($default as $default_item) {
            $col_item = null;
            for ($i = 0; $i < count($collection); $i++) {
                $col_item = $collection[$i];

                if (!isset($col_item['name'])) {
                    continue;
                }

                if (
                    $col_item['name'] === $default_item['name'] &&
                    ($col_item['ref'] ?? false) ===
                        ($default_item['ref'] ?? false)
                ) {
                    break;
                }
            }

            if ($i === count($collection)) {
                $collection[] = $default_item;
            } else {
                $collection[$i] = wpct_plugin_merge_object(
                    $col_item,
                    $default_item,
                    $schema
                );
            }
        }
    } elseif ($schema['type'] === 'array') {
        // TODO: Handle matrix case
    }

    return $collection;
}

/**
 * Generic array default values merger. Switches between merge_collection and merge_list
 * based on the list items' data type.
 *
 * @param array $array Input array.
 * @param array $default Default array values.
 * @param array $schema JSON schema of the array values.
 *
 * @return array Array fullfilled with defaults.
 */
function wpct_plugin_merge_object($array, $default, $schema = [])
{
    foreach ($default as $key => $default_value) {
        if (empty($array[$key])) {
            $array[$key] = $default_value;
        } else {
            $value = $array[$key];
            $type =
                $schema['properties'][$key]['type'] ??
                wpct_plugin_get_json_schema_type($default_value);

            if ($type === 'object') {
                if (!is_array($value) || wp_is_numeric_array($value)) {
                    $array[$key] = $default_value;
                } else {
                    $array[$key] = wpct_plugin_merge_object(
                        $value,
                        $default_value,
                        $schema['properties'][$key] ?? []
                    );
                }
            } elseif ($type === 'array') {
                if (!wp_is_numeric_array($value)) {
                    $array[$key] = $default_value;
                } else {
                    $array[$key] = wpct_plugin_merge_collection(
                        $value,
                        $default_value,
                        $schema['properties'][$key]['items'] ?? []
                    );
                }
            }
        }
    }

    if (isset($schema['properties'])) {
        foreach ($array as $key => $value) {
            if (!isset($schema['properties'][$key])) {
                unset($array[$key]);
            }
        }
    }

    return $array;
}

/**
 * Gets the corresponding JSON schema type from a given value.
 *
 * @param mixed $value
 *
 * @return string JSON schema value type.
 */
function wpct_plugin_get_json_schema_type($value)
{
    if (wp_is_numeric_array($value)) {
        return 'array';
    } elseif (is_array($value)) {
        return 'object';
    } else {
        $type = gettype($value);
        switch ($type) {
            case 'double':
                return 'number';
            default:
                return strtolower($type);
        }
    }
}
