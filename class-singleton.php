<?php

namespace WPCT_ABSTRACT;

use Exception;

if (!class_exists('\WPCT_ABSTRACT\Singleton')) :

    /**
     * Singleton abstract class.
     *
     * @since 1.0.0
     */
    abstract class Singleton
    {
        /**
         * Handle singleton instances map.
         *
         * @since 1.0.0
         */
        private static $_instances = [];

        /**
         * Class contructor.
         *
         * @since 1.0.0
         */
        protected function __construct()
        {
        }

        /**
         * Prevent class clonning.
         *
         * @since 1.0.0
         */
        protected function __clone()
        {
        }

        /**
         * Prevent class serialization.
         *
         * @since 1.0.0
         */
        public function __wakeup()
        {
            throw new Exception('Cannot unserialize a singleton.');
        }

        /**
         * Get class instance.
         *
         * @since 1.0.0
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
