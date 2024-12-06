<?php

namespace WPCT_ABSTRACT;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Setting')) {
    /**
     * Plugin's setting class.
     */
    class Setting
    {
        /**
         * Handle setting's group.
         *
         * @var string $group Setting's group.
         */
        private $group;

        /**
         * Handle setting's name.
         *
         * @var string $name Setting's name.
         */
        private $name;

        /**
         * Handle setting's default values.
         *
         * @var array $default Setting's default values.
         */
        private $default;

        /**
         * Handle setting's schema.
         *
         * @var array $schema Setting's schema.
         */
        private $schema;

        /**
         * Handle setting's data.
         *
         * @var array|null $data Setting's data.
         */
        private $data = null;

        /**
         * Stores setting data and bind itself to wp option hooks to update its data.
         *
         * @param string $group Setting group.
         * @param string $name Setting name.
         * @param array $data Setting data.
         * @param array $schema Setting schema.
         */
        public function __construct($group, $name, $data, $schema)
        {
            $this->group = $group;
            $this->name = $name;
            $this->default = $data;
            $this->schema = $schema;

            add_action('add_option', function ($option, $value) {
                if ($option === $this->full_name()) {
                    $this->data = $value;
                }
            }, 5, 2);

            add_action('update_option', function ($option, $from, $to) {
                if ($option === $this->full_name()) {
                    $this->data = $to;
                }
            }, 5, 3);

            add_action('delete_option', function ($option) {
                if ($option === $this->full_name()) {
                    $this->data = null;
                }
            }, 5);

            add_filter('pre_update_option', function ($value, $option) {
                if ($option === $this->full_name()) {
                    return $this->sanitize($value);
                }

                return $value;
            }, 10, 2);
        }

        /**
         * Proxies data attributes to class attributes.
         *
         * @param string $field Field name.
         *
         * @return mixed Data field value or null.
         */
        public function __get($field)
        {
            $data = $this->data();
            return isset($data[$field]) ? $data[$field] : null;
        }

        public function __set($field, $value)
        {
            $data = $this->data();
            if (isset($data[$field])) {
                $data[$field] = $value;
                $this->update($data);
            }
        }

        /**
         * Gets the setting group.
         *
         * @return string Setting group.
         */
        public function group()
        {
            return $this->group;
        }

        /**
         * Gets the setting name.
         *
         * @return string Setting name.
         */
        public function name()
        {
            return $this->name;
        }

        /**
         * Gets the concatenation of the group and the setting name.
         *
         * @return string Setting full name.
         */
        public function full_name()
        {
            return $this->group . '_' . $this->name;
        }

        /**
         * Setting's schema getter.
         *
         * @param string $field Field name, optional.
         *
         * @return mixed Schema array or field value.
         */
        public function schema($field = null)
        {
            return $this->proxy('schema', $field);
        }

        /**
         * Setting's data getter.
         *
         * @param string $field Field name, optional.
         *
         * @return mixed Data array or field value.
         */
        public function data($field = null)
        {
            if ($this->data === null) {
                $this->data = get_option($this->full_name(), $this->default);
            }

            return $this->proxy('data', $field);
        }

        /**
         * Registers setting data on the database.
         *
         * @param array $data Setting data.
         *
         * @return boolean True if the data was added, false otherwise.
         */
        public function add($data)
        {
            return add_option($this->full_name(), $data);
        }

        /**
         * Updates setting data on the database.
         *
         * @param array $data New setting data.
         *
         * @return boolean True if the data was updated, false otherwise.
         */
        public function update($data)
        {
            return update_option($this->full_name(), $data);
        }

        /**
         * Deletes the setting from the database.
         *
         * @return boolean True if the data was deleted, false otherwise.
         */
        public function delete()
        {
            return delete_option($this->full_name());
        }

        /**
         * Proxies setting arrays' with isset checks.
         *
         * @param string $targert Target data array.
         * @param string $field Field name.
         *
         * @return mixed Data if no field is defined, field value otherwise, or null.
         */
        private function proxy($target, $field = null)
        {
            if (!isset($this->$target)) {
                return;
            }

            $name = $target;
            $target = $this->$target;
            if ($name === 'schema') {
                $target = $target['properties'];
            }

            if ($field === null) {
                return $target;
            }

            return isset($target[$field]) ? $target[$field] : null;
        }

        /**
         * Sanitize setting data before database inserts.
         *
         * @param string $option Setting name.
         * @param array $value Setting data.
         *
         * @return array $value Sanitized setting data.
         */
        protected function sanitize($value)
        {
            if (!rest_validate_value_from_schema($value, $this->schema)) {
                return new WP_Error('rest_invalid_schema', 'The setting is not schema conformant', ['value' => $value, 'schema' => $schema]);
            }

            return rest_sanitize_value_from_schema($value, $this->schema);
        }
    }
}
