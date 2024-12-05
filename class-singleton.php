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
         * Controlled class contructor.
         */
        public function __construct(&$singleton)
        {
            $singleton = true;
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
         * Abstract singleton class constructor.
         */
        abstract protected function construct(...$args);

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
                // Pass $singleton reference to prevent singleton classes constructor overwrites
                self::$_instances[$cls] = new static($singleton);
                if (!$singleton) {
                    throw new Exception('Cannot create uncontrolled instances from a singleton.');
                }
                (self::$_instances[$cls])->construct(...$args);
            }

            return self::$_instances[$cls];
        }
    }

endif;
