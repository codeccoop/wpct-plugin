<?php

namespace WPCT_PLUGIN;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_PLUGIN\Settings_Store')) {
    require_once 'class-singleton.php';
    require_once 'class-setting.php';
    require_once 'class-rest-settings-controller.php';
    require_once 'class-undefined.php';

    /**
     * Plugin settings store class.
     */
    class Settings_Store extends Singleton
    {
        /**
         * Handle plugin settings rest controller class name.
         *
         * @var string
         */
        protected static $rest_controller_class = '\WPCT_PLUGIN\REST_Settings_Controller';

        /**
         * Handle settings group name.
         *
         * @var string
         */
        private $group;

        /**
         * Handle settings instanes store.
         *
         * @var array
         */
        private $store = [];

        private static function store($setting_name, $setting)
        {
            static::get_instance()->store[$setting_name] = $setting;
        }

        /**
         * Register settings method.
         */
        public static function config()
        {
            return [];
        }

        /**
         * Validates setting data before database inserts.
         *
         * @param array $data Setting data.
         * @param Setting $setting Setting instance.
         *
         * @return array $value Validated setting data.
         */
        protected static function validate_setting($data, $setting)
        {
            return $data;
        }

        /**
         * Private setting sanitization method.
         *
         * @param array $data Setting data.
         * @param string $name Setting name.
         *
         * @return array Sanitized data.
         */
        private static function sanitize_setting($data, $name)
        {
            $instance = static::get_instance();

            // Prevent double sanitization
            if ($instance->sanitizing === $name) {
                return $data;
            }

            $instance->sanitizing = $name;
            $setting = static::setting($name);
            $schema = $setting->schema();
            $default = $setting->default();

            $data = static::validate_setting($data, $setting);
            $data = apply_filters('wpct_plugin_validate_setting', $data, $setting);

            $sanitized = [];
            foreach ($data as $field => $value) {
                if (!isset($schema['properties'][$field])) {
                    continue;
                } else {
                    $field_schema = $schema['properties'][$field];
                }

                $is_valid = rest_validate_value_from_schema($value, $field_schema, $field);
                if (is_wp_error($is_valid)) {
                    $value = self::replace_invalid_with_defaults($value, $field_schema, $is_valid);
                }

                $value = rest_sanitize_value_from_schema($value, $field_schema, $field);
                if (is_wp_error($value)) {
                    // support for schema default values
                    $sanitized[$field] = $field_schema['default'] ?? $default[$field] ?? null;
                } else {
                    $sanitized[$field] = $value;

                    // support for array enums
                    if ($field_schema['type'] === 'array' && isset($field_schema['enum']) && is_array($field_schema['enum'])) {
                        $value = array_values(
                            array_filter($value, static function ($item) use (
                                $field_schema
                            ) {
                                return in_array($item, $field_schema['enum'], true);
                            })
                        );
                    }
                }
            }

            $full_name = $setting->full_name();
            add_action(
                "add_option_{$full_name}",
                static function () use ($instance) {
                    $instance->sanitizing = null;
                },
                10,
                0
            );

            add_action(
                "update_option_{$full_name}",
                static function () use ($instance) {
                    $instance->sanitizing = null;
                },
                10,
                0
            );

            $setting->flush();
            return $sanitized;
        }

        /**
         * Loop over validation error params and replace its setting values by schema defaults.
         *
         * @param mixed $value Validation target value.
         * @param array $schema Value schema.
         * @param WP_Error $error Validation error.
         *
         * @return mixed
         */
        private static function replace_invalid_with_defaults($value, $schema, $error)
        {
            if ($default = $field_schema['default'] ?? null) {
                return $default;
            }

            $revalidate = false;
            foreach (array_keys($error->errors) as $error_code) {
                if (!isset($error->error_data[$error_code])) {
                    continue;
                }

                $error_data = $error->error_data[$error_code];
                if (empty($error_data['param'])) {
                    return $value;
                }

                preg_match_all('/\[([^\]+)\]/', $error_data['param'], $matches);
                $keys = $matches[1];

                $parent = null;
                $leaf = &$value;
                $leaf_schema = $schema;
                foreach ($keys as $key) {
                    $parent = &$leaf;
                    $leaf = &$parent[$key];
                    $leaf_schema = $leaf_schema['properties'][$key];
                }

                if ($default = $leaf_schema['default'] ?? null) {
                    $parent[$key] = $default;
                    $revalidate = true;
                }
            }

            if ($revalidate) {
                $is_valid = rest_validate_value_from_schema($value, $schema);
                if (is_wp_error($is_valid)) {
                    $value = self::replace_invalid_with_defaults($value, $schema, $is_valid);
                }
            }

            return $value;
        }

        /**
         * Handles current sanitizing setting name to prevent double sanitization loops.
         *
         * @var string|null
         */
        private $sanitizing = null;

        /**
         * Class constructor. Store the group name and hooks to pre_update_option.
         *
         * @param string $group Settings group name.
         */
        protected function construct(...$args)
        {
            [$group] = $args;
            $this->group = $group;

            static::$rest_controller_class::setup($group);

            add_action(
                'init',
                static function () use ($group) {
                    $settings = static::register_settings();
                    do_action('wpct_plugin_registered_settings', $settings, $group);
                },
                10,
                0
            );
        }

        /**
         * Get settings group name.
         *
         * @return string $group_name Settings group name.
         */
        final public static function group()
        {
            return static::get_instance()->group;
        }

        /**
         * Return group settings instances.
         *
         * @return array Group settings.
         */
        final public static function settings()
        {
            return static::get_instance()->store;
        }

        final public static function setting($name)
        {
            $store = static::settings();
            if (empty($store)) {
                return;
            }

            return $store[$name] ?? null;
        }

        /**
         * Registers a setting and its fields.
         *
         * @return array List with setting instances.
         */
        private static function register_settings()
        {
            $config = apply_filters(
                'wpct_plugin_settings_config',
                static::config(),
                static::group()
            );

            $settings = [];
            foreach ($config as $setting_config) {
                $group = static::group();
                [$name, $schema, $default] = $setting_config;

                if ($setting = static::setting($name)) {
                    $settings[$setting->name()] = $setting;
                } else {
                    $setting = new Setting($group, $name, $default, [
                        '$id' => $group . '_' . $name,
                        '$schema' => 'http://json-schema.org/draft-04/schema#',
                        'title' => "Setting {$name} of {$group}",
                        'type' => 'object',
                        'properties' => $schema,
                        'required' => array_keys($schema),
                        'additionalProperties' => false,
                    ]);

                    $setting_name = $setting->full_name();

                    // Register setting
                    register_setting($setting_name, $setting_name, [
                        'type' => 'object',
                        'show_in_rest' => [
                            'name' => $setting_name,
                            'schema' => $setting->schema(),
                        ],
                        'sanitize_callback' => function ($value) use ($name) {
                            return static::sanitize_setting($value, $name);
                        },
                        'default' => $setting->default(),
                    ]);

                    static::store($name, $setting);
                }

                $settings[$setting->name()] = $setting;
            }

            return $settings;
        }
    }
}
