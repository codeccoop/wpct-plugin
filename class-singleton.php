<?php

namespace WPCT_ABSTRACT;

use Exception;

if (!class_exists('\WPCT_ABSTRACT\Singleton')) :

    abstract class Singleton
    {
        private static $_instances = [];

        protected function __construct()
        {
        }

        protected function __clone()
        {
        }

        public function __wakeup()
        {
            throw new Exception('Cannot unserialize a singleton.');
        }

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
