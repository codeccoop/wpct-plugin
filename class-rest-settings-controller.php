<?php

namespace WPCT_ABSTRACT;

use Error;
use WP_Error;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\REST_Settings_Controller')) {
    require_once 'class-singleton.php';

    /**
     * Plugin REST settings controller.
     */
    class REST_Settings_Controller extends Singleton
    {
        /**
         * Handle plugin settings group name.
         *
         * @var string
         */
        private $group;

        /**
         * Handles plugin settings instances.
         */
        private $settings = [];

        /**
         * Setup a new rest settings controller.
         *
         * @param string $group Plugin settings group name.
         *
         * @return object Instance of REST_Controller.
         */
        final public static function setup($group)
        {
            return self::get_instance($group);
        }

        /**
         * Internal WP_Error proxy.
         *
         * @param string $code
         * @param string $message
         * @param mixed $data
         *
         * @return WP_Error
         */
        final protected static function error($code, $message, $data)
        {
            return new WP_Error((string) $code, $message, $data);
        }

        /**
         * Store the group name and binds class initializer to the rest_api_init hook
         *
         * @param string $group Settings group name.
         */
        protected function construct(...$args)
        {
            [$group] = $args;
            $this->group = $group;

            add_action('rest_api_init', static function () {
                static::init();
            });

            add_action(
                'wpct_register_settings',
                function ($settings, $group) {
                    if ($group === $this->group) {
                        $this->settings = $settings;
                    }
                },
                10,
                2
            );
        }

        final protected static function group()
        {
            return self::get_instance()->group;
        }

        final public static function namespace()
        {
            return apply_filters('wpct_rest_namespace', self::group());
        }

        final public static function version()
        {
            return (int) apply_filters('wpct_rest_version', 1, self::group());
        }

        final protected static function settings()
        {
            return self::get_instance()->settings;
        }

        /**
         * REST_Settings_Controller initializer.
         */
        protected static function init()
        {
            // register settings endpoint
            $namespace = self::namespace();
            $version = self::version();

            register_rest_route("{$namespace}/v{$version}", '/settings/', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => static function () {
                        return self::get_settings();
                    },
                    'permission_callback' => static function () {
                        return self::permission_callback();
                    },
                ],
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => static function ($request) {
                        return self::set_settings($request);
                    },
                    'permission_callback' => static function () {
                        return self::permission_callback();
                    },
                    'args' => self::schema()['properties'],
                ],
                'schema' => static function () {
                    return self::schema();
                },
                'allow_batch' => ['v1' => false],
            ]);
        }

        /**
         * Setting rest schema getter.
         *
         * @return array
         */
        private static function schema()
        {
            $settings = self::settings();

            return array_reduce(
                $settings,
                static function ($schema, $setting) {
                    $setting_schema = $setting->schema();
                    unset($setting_schema['additionalProperties']);
                    $schema['properties'][
                        $setting->full_name()
                    ] = $setting_schema;
                    return $schema;
                },
                [
                    '$schema' => 'http://json-schema.org/draft-04/schema#',
                    'title' => self::group(),
                    'type' => 'object',
                    'properties' => [],
                ]
            );
        }

        /**
         * GET requests settings endpoint callback.
         *
         * @return array<string, array> $settings Associative array with settings data.
         */
        private static function get_settings()
        {
            $data = [];
            $settings = self::settings();
            foreach ($settings as $name => $setting) {
                $data[$name] = $setting->data();
            }

            return $data;
        }

        /**
         * POST requests settings endpoint callback. Store settings on the options table.
         *
         * @param REST_Request $request Input rest request.
         *
         * @return array New settings state.
         */
        private static function set_settings($request)
        {
            try {
                $data = $request->get_json_params();

                $settings = self::settings();
                foreach ($settings as $setting) {
                    if (!isset($data[$setting->name()])) {
                        continue;
                    }

                    $from = $setting->data();
                    $to = $data[$setting->name()];
                    foreach (array_keys($from) as $key) {
                        $to[$key] = isset($to[$key]) ? $to[$key] : $from[$key];
                    }
                    update_option($setting->full_name(), $to);
                }
                return ['success' => true];
            } catch (Error $e) {
                return self::error(
                    'internal_server_error',
                    $e->getMessage(),
                    $data
                );
            }
        }

        /**
         * Check if current user can manage options.
         *
         * @return boolean
         */
        final public static function permission_callback()
        {
            return current_user_can('manage_options')
                ? true
                : self::error(
                    'rest_unauthorized',
                    'You can\'t manage wp options',
                    403
                );
        }

        public static function is_doing_rest()
        {
            $ns = static::get_instance()->namespace();
            $uri = isset($_SERVER['REQUEST_URI'])
                ? sanitize_text_field($_SERVER['REQUEST_URI'])
                : null;
            return $uri && preg_match("/\/wp-json\/{$ns}\//", $uri);
        }
    }
}
