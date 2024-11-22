<?php

namespace WPCT_ABSTRACT;

use Exception;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Singleton')) :

    /**
     * Singleton abstract class.
     */
    abstract class Singleton
    {
        /**
         * Handle singleton instances map.
         */
        private static $_instances = [];

        /**
         * Class contructor.
         */
        protected function __construct()
        {
        }

        /**
         * Prevent class clonning.
         */
        protected function __clone()
        {
        }

        /**
         * Prevent class serialization.
         */
        public function __wakeup()
        {
            throw new Exception('Cannot unserialize a singleton.');
        }

        /**
         * Get class instance.
         *
         * @return object $instance Class instance.
         */
        public static function get_instance()
        {
            $args = func_get_args();
            $cls = static::class;
            if (!isset(self::$_instances[$cls])) {
                self::$_instances[$cls] = new static(...$args);
            }

            return self::$_instances[$cls];
        }
    }

endif;
