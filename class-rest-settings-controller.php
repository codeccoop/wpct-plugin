<?php

namespace WPCT_ABSTRACT;

use WP_Error;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\REST_Settings_Controller')) :

    require_once 'class-singleton.php';

    /**
     * Plugin REST settings controller.
     */
    class REST_Settings_Controller extends Singleton
    {
        /**
         * Handle REST API controller namespace.
         *
         * @var string $namespace REST API namespace.
         */
        protected static $namespace = 'wpct';

        /**
         * Handle REST API controller namespace version.
         *
         * @var int $version REST API namespace version.
         */
        protected static $version = 1;

        /**
        * Handle plugin settings group name.
        *
        * @var string $group_name Settings group name.
        */
        private $group_name;

        /**
         * Handle plugin settings names.
         *
         * @var array<string> $settings Plugin settings names list.
         */
        protected static $settings = ['general'];

        /**
         * Setup a new rest settings controller.
         *
         * @param string $group_name Plugin settings group name.
         * @return object $controller Instance of REST_Controller.
         */
        public static function setup($group_name)
        {
            return new (static::class)($group_name);
        }

        /**
         * Internal WP_Error proxy.
         *
         * @param string $code
         * @param string $message
         * @param int $status
         */
        private static function error($code, $message, $status, $textdomain)
        {
            return new WP_Error($code, __($message, $textdomain), [
                'status' => $status,
            ]);
        }

        /**
         * Store the group name and binds class initializer to the rest_api_init hook
         *
         * @param string $group_name Settings group name.
         */
        public function __construct($group_name)
        {
            $this->group_name = $group_name;
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
            $schema = array_reduce(static::$settings, function ($schema, $setting_name) {
                $setting_schema = Settings::get_schema($this->group_name, $setting_name);
                $schema['properties'][$setting_name] = [
                    'type' => 'object',
                    'properties' => $setting_schema,
                ];
                return $schema;
            }, [
                '$schema' => 'http://json-schema.org/draft-04/schema#',
                'title' => $this->group_name,
                'type' => 'object',
                'properties' => [],
            ]);

            register_rest_route(
                "{$namespace}/v{$version}",
                "/{$this->group_name}/settings/",
                [
                    [
                        'methods' => WP_REST_Server::READABLE,
                        'callback' => function () {
                            return $this->get_settings();
                        },
                        'permission_callback' => function () {
                            return $this->permission_callback();
                        },
                    ],
                    [
                        'methods' => WP_REST_Server::CREATABLE,
                        'callback' => function () {
                            return $this->set_settings();
                        },
                        'permission_callback' => function () {
                            return $this->permission_callback();
                        },
                    ],
                    'schema' => $schema,
                    'allow_batch' => ['v1' => false],
                ],
            );
        }

        /**
         * GET requests settings endpoint callback.
         *
         * @return array<string, array> $settings Associative array with settings data.
         */
        private function get_settings()
        {
            $settings = [];
            foreach (static::$settings as $setting) {
                $settings[$setting] = Settings::get_setting(
                    $this->group_name,
                    $setting
                );
            }
            return $settings;
        }

        /**
         * POST requests settings endpoint callback. Store settings on the options table.
         *
         * @return array $response New settings state.
         */
        private function set_settings()
        {
            $data = (array) json_decode(file_get_contents('php://input'), true);
            $response = [];
            foreach (static::$settings as $setting) {
                if (!isset($data[$setting])) {
                    continue;
                }

                $from = Settings::get_setting($this->group_name, $setting);
                $to = $data[$setting];
                foreach (array_keys($from) as $key) {
                    $to[$key] = isset($to[$key]) ? $to[$key] : $from[$key];
                }
                update_option($this->group_name . '_' . $setting, $to);
                $response[$setting] = $to;
            }

            return $response;
        }

        /**
         * Check if current user can manage options.
         *
         * @return boolean $allowed
         */
        protected function permission_callback()
        {
            return current_user_can('manage_options')
                ? true
                : static::error(
                    'rest_unauthorized',
                    'You can\'t manage wp options',
                    403,
                    $this->group_name,
                );
        }
    }

endif;
