<?php

namespace WPCT_PLUGIN;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\WPCT_PLUGIN\Setting')) {
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

        private $sanitizing = false;

        /**
         * Stores setting data and bind itself to wp option hooks to update its data.
         *
         * @param string $group Setting group.
         * @param string $name Setting name.
         * @param array $default Setting default.
         * @param array $schema Setting schema.
         */
        public function __construct($group, $name, $default, $schema)
        {
            $this->group = $group;
            $this->name = $name;
            $this->schema = $schema;

            $option = $this->option();

            register_setting('options', $option, [
                'type' => 'object',
                'show_in_rest' => false,
                'sanitize_callback' => function ($data) {
                    return $this->sanitize($data);
                },
                'default' => $default,
            ]);

            add_action(
                "add_option_{$option}",
                function ($option, $data) {
                    $this->data = $data;
                    $this->sanitizing = false;
                },
                5,
                2
            );

            add_action(
                "update_option_{$option}",
                function ($from, $to) {
                    $this->data = $to;
                    $this->sanitizing = false;
                },
                5,
                2
            );

            add_action(
                "delete_option_{$option}",
                function () {
                    $this->data = null;
                    $this->sanitizing = false;
                },
                5,
                0
            );

            add_filter(
                "option_{$option}",
                function ($data) {
                    if (!is_array($data)) {
                        return [];
                    }

                    return $data;
                },
                0,
                1
            );

            add_filter(
                "default_option_{$option}",
                function ($data) {
                    if (!is_array($data)) {
                        return [];
                    }

                    return $data;
                },
                0,
                1,
            );
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
            return $data[$field] ?? null;
        }

        public function __set($field, $value)
        {
            $data = $this->data();
            $data[$field] = $value;
            $this->update($data);
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
        public function option()
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
            $schema = $this->schema;

            if ($field === null) {
                return $schema;
            }

            return $schema['properties'][$field] ?? null;
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
                $this->data = get_option($this->option());
            }

            if ($field === null) {
                return $this->data;
            }

            return $this->data[$field] ?? null;
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
            return add_option($this->option(), $data);
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
            return update_option($this->option(), $data);
        }

        /**
         * Deletes the setting from the database.
         *
         * @return boolean True if the data was deleted, false otherwise.
         */
        public function delete()
        {
            return delete_option($this->option());
        }

        public function flush()
        {
            $this->data = null;
        }

        private function sanitize($data)
        {
            if ($this->sanitizing === true) {
                return $data;
            }

            $this->sanitizing = true;
            $data = apply_filters('wpct_plugin_sanitize_setting', $data, $this);
            return wpct_plugin_sanitize_with_schema($data, $this->schema());
        }

        public function use_getter($getter, $p = 10)
        {
            if (is_callable($getter)) {
                $option = $this->option();
                add_filter("option_{$option}", $getter, $p, 1);
                add_filter("default_option_{$option}", $getter, $p, 1);
            }
        }

        public function use_setter($setter, $p = 10)
        {
            if (is_callable($setter)) {
                $option = $this->option();
                add_filter("sanitize_option_{$option}", $setter, $p, 1);
            }
        }
    }
}
