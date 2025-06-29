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
    require_once 'json-schema-utils.php';

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

            $data = static::validate_setting($data, $setting);
            $data = apply_filters('wpct_plugin_validate_setting', $data, $setting);
            $data = wpct_plugin_validate_with_schema($data, $schema);

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
            return $data;
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
