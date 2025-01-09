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
         * Handle REST API controller namespace.
         *
         * @var string
         */
        protected static $namespace = 'wpct';

        /**
         * Handle REST API controller namespace version.
         *
         * @var int
         */
        protected static $version = 1;

        /**
        * Handle plugin settings group name.
        *
        * @var string
        */
        protected $group;

        /**
         * Handle plugin settings names.
         *
         * @var array<string>
         */
        protected static $settings = ['general'];

        /**
         * Setup a new rest settings controller.
         *
         * @param string $group Plugin settings group name.
         *
         * @return object Instance of REST_Controller.
         */
        public static function setup($group)
        {
            return static::class::get_instance($group);
        }


        /**
         * Internal WP_Error proxy.
         *
         * @param string $code
         * @param string $message
         * @param int $status
         */
        private static function error($code, $message, $status)
        {
            return new WP_Error($code, $message, [
                'status' => $status,
            ]);
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

            add_action('rest_api_init', function () {
                $this->init();
            });
        }

        /**
         * REST_Settings_Controller initializer.
         */
        private function init()
        {
            // register settings endpoint
            $namespace = static::$namespace;
            $version = static::$version;

            register_rest_route(
                "{$namespace}/v{$version}",
                "/{$this->group}/settings/",
                [
                    [
                        'methods' => WP_REST_Server::READABLE,
                        'callback' => function () {
                            return $this->get_settings();
                        },
                        'permission_callback' => static function () {
                            return self::permission_callback();
                        },
                    ],
                    [
                        'methods' => WP_REST_Server::CREATABLE,
                        'callback' => function ($request) {
                            return $this->set_settings($request);
                        },
                        'permission_callback' => static function () {
                            return self::permission_callback();
                        },
                    ],
                    'schema' => function () {
                        return $this->schema();
                    },
                    'allow_batch' => ['v1' => false],
                ],
            );
        }

        private function schema()
        {
            $settings = apply_filters('wpct_rest_settings', static::$settings, $this->group);
            $schema = array_reduce($settings, function ($schema, $setting_name) {
                $setting_schema = Settings::get_setting($this->group, $setting_name)->schema();
                unset($setting_schema['additionalProperties']);
                $schema['properties'][$setting_name] = $setting_schema;
                return $schema;
            }, [
                '$schema' => 'http://json-schema.org/draft-04/schema#',
                'title' => $this->group,
                'type' => 'object',
                'properties' => [],
            ]);
            return $schema;
        }

        /**
         * GET requests settings endpoint callback.
         *
         * @return array<string, array> $settings Associative array with settings data.
         */
        private function get_settings()
        {
            $data = [];
            $settings = apply_filters('wpct_rest_settings', static::$settings, $this->group);
            foreach ($settings as $setting) {
                $data[$setting] = Settings::get_setting(
                    $this->group,
                    $setting
                )->data();
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
        private function set_settings($request)
        {
            try {
                $data = $request->json_params();

                $settings = apply_filters('wpct_rest_settings', static::$settings, $this->group);
                foreach ($settings as $setting) {
                    if (!isset($data[$setting])) {
                        continue;
                    }

                    $from = Settings::get_setting($this->group, $setting)->data();
                    $to = $data[$setting];
                    foreach (array_keys($from) as $key) {
                        $to[$key] = isset($to[$key]) ? $to[$key] : $from[$key];
                    }
                    $option = $this->group . '_' . $setting;
                    update_option($option, $to);
                }
                return ['success' => true];
            } catch (Error $e) {
                return self::error($e->getCode(), $e->getMessage(), ['data' => $data]);
            }
        }

        /**
         * Check if current user can manage options.
         *
         * @return boolean
         */
        protected static function permission_callback()
        {
            return current_user_can('manage_options')
                ? true
                : static::error(
                    'rest_unauthorized',
                    'You can\'t manage wp options',
                    403,
                );
        }
    }
}
