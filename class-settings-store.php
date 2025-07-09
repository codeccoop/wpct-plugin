<?php

namespace WPCT_PLUGIN;

if (!defined('ABSPATH')) {
    exit;
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
        protected const rest_controller_class = '\WPCT_PLUGIN\REST_Settings_Controller';

        /**
         * Handle settings' group name.
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

        private static function _store_setting($setting_name, $setting)
        {
            static::get_instance()->store[$setting_name] = $setting;
        }

        final public static function use_setter($name, $setter, $priority = 10)
        {
            if ($setting = static::setting($name)) {
                $setting->use_setter($setter, $priority);
            }
        }

        final public static function use_getter($name, $getter, $priority = 10)
        {
            if ($setting = static::setting($name)) {
                $setting->use_getter($getter, $priority);
            }
        }

        final public static function use_cleaner($name, $cleaner, $priority = 10)
        {
            if ($setting = static::setting($name)) {
                $setting->use_cleaner($cleaner, $priority);
            }
        }

        final public static function register_setting($setting)
        {
            add_filter(
                'wpct_plugin_register_settings',
                static function ($settings, $group) use ($setting) {
                    if (static::group() === $group) {
                        if (is_array($setting)) {
                            $settings[] = $setting;
                        } elseif ($filter = is_callable($setting) ? $setting : null) {
                            $settings = $filter($settings);
                        }
                    }

                    return $settings;
                },
                10,
                2
            );
        }

        final public static function ready($callback)
        {
            if (!is_callable($callback)) {
                return;
            }

            add_filter(
                'wpct_plugin_registered_settings',
                static function ($settings, $group, $store) use ($callback) {
                    if (static::group() === $group) {
                        $callback($store, $group);
                    }
                },
                10,
                3
            );
        }

        /**
         * Class constructor. Store the group name and hooks to pre_update_option.
         *
         * @param string $group Settings group name.
         */
        protected function construct(...$args)
        {
            [$group] = $args;
            $this->group = $group;

            static::rest_controller_class::setup($group);

            add_action(
                'init',
                function () {
                    $settings = static::register_settings();
                    do_action('wpct_plugin_registered_settings', $settings, $this->group, $this);
                },
                10,
                5
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
         * Instance's store getter
         *
         * @return array
         */
        final public static function store()
        {
            return static::get_instance()->store ?: [];
        }

        /**
         * Instance's store settings collection getter.
         *
         * @return Setting[]
         */
        final public static function settings()
        {
            return array_values(static::store());
        }

        /**
         * Instance's settings getter.
         *
         * @return Setting|null
         */
        final public static function setting($name)
        {
            $store = static::store();
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
            $group = static::group();

            $schemas = apply_filters(
                'wpct_plugin_register_settings',
                [],
                $group
            );

            $settings = [];
            foreach ($schemas as $schema) {
                if (!is_array($schema) || !is_string($schema['name'] ?? null)) {
                    continue;
                }

                $name = $schema['name'];

                if ($setting = static::setting($name)) {
                    $settings[] = $setting;
                    continue;
                }

                $schema = array_merge([
                    '$id' => $group . '_' . $name,
                    '$schema' => 'http://json-schema.org/draft-04/schema#',
                    'title' => "Setting {$name} of {$group}",
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                    'additionalProperties' => false,
                    'default' => [],
                ], $schema);

                $default = is_array($schema['default']) ? $schema['default'] : [];

                $setting = new Setting($group, $name, $default, $schema);
                static::_store_setting($name, $setting);

                $settings[] = $setting;
            }

            return $settings;
        }
    }
}
