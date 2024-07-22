<?php

namespace WPCT_ABSTRACT;

use Error;

if (!class_exists('\WPCT_ABSTRACT\Settings')) :

    class Undefined
    {
    };

    abstract class Settings extends Singleton
    {
        protected $group_name;
        public static $schemas = [];
        public static $defaults = [];
        private static $cache = [];

        abstract public function register();

        public static function get_setting($group_name, $setting, $field = null)
        {
            $default = static::get_default($group_name, $setting);

            $setting_name = $group_name . '_' . $setting;
            $setting = isset(static::$cache[$setting_name]) ? static::$cache[$setting_name] : get_option($setting_name, $default);
            static::$cache[$setting_name] = $setting;

            if ($field === null) {
                return $setting;
            }

            return isset($setting[$field]) ? $setting[$field] : null;
        }

        public static function get_default($group_name, $setting, $field = null)
        {
            $setting_name = $group_name . '_' . $setting;
            $default = isset(static::$defaults[$setting_name]) ? static::$defaults[$setting_name] : [];
            $default = apply_filters($setting_name . '_default', $default);

            if ($field === null) {
                return $default;
            }
            return isset($default[$field]) ? $default[$field] : null;
        }

        public static function get_schema($group_name, $setting, $field = null)
        {
            $setting_name = $group_name . '_' . $setting;
            $schema = isset(static::$schemas[$setting_name]) ? static::$schemas[$setting_name] : [];
            $schema = apply_filters($setting_name . '_schema', $schema);

            if ($field === null) {
                return $schema;
            }

            return isset($schema[$field]) ? $schema[$field] : null;
        }

        public function __construct($group_name)
        {
            $this->group_name = $group_name;

            add_filter('pre_update_option', function ($value, $option, $from) {
                return $this->sanitize_option($option, $value);
            }, 10, 3);
        }

        public function get_group_name()
        {
            return $this->group_name;
        }

        public function get_settings()
        {
            $settings = [];
            foreach (array_keys(self::$defaults) as $setting) {
                if (strstr($setting, $this->group_name) !== false) {
                    $settings[] = $setting;
                }
            }

            return $settings;
        }

        public function register_setting($setting, $schema = null, $default = [])
        {
            $setting_name = $this->setting_name($setting);
            self::$schemas[$setting_name] = $schema;
            self::$defaults[$setting_name] = $default;

            $default = self::get_default($this->group_name, $setting);
            $schema = self::get_schema($this->group_name, $setting);

            register_setting(
                $setting_name,
                $setting_name,
                [
                    'type' => 'object',
                    'show_in_rest' => $schema ? [
                        'name' => $setting_name,
                        'schema' => [
                            'type' => 'object',
                            'properties' => $schema
                        ],
                    ] : false,
                    'type' => 'object',
                    'default' => $default,
                ],
            );

            add_action('add_option', function ($option, $value) use ($setting_name) {
                if ($option === $setting_name && !empty($to)) {
                    static::$cache[$setting_name] = $value;
                }
            }, 5, 2);

            add_action('update_option', function ($option, $from, $to) use ($setting_name) {
                if ($option === $setting_name && !empty($to)) {
                    static::$cache[$setting_name] = $to;
                }
            }, 5, 3);

            add_action('admin_init', function () use ($setting_name, $setting) {
                add_settings_section(
                    $setting_name . '_section',
                    __($setting_name . '--title', $this->group_name),
                    function () use ($setting_name) {
                        $title = __($setting_name . '--description', $this->group_name);
                        echo "<p>{$title}</p>";
                    },
                    $setting_name,
                );

                foreach (array_keys(self::$defaults[$setting_name]) as $field) {
                    $this->add_settings_field($field, $setting);
                }
            });
        }

        private function add_settings_field($field_name, $setting)
        {
            $setting_name = $this->setting_name($setting);
            $field_id = $setting_name . '__' . $field_name;
            add_settings_field(
                $field_name,
                __($field_id . '--label', $this->group_name),
                function () use ($setting, $field_name) {
                    echo $this->field_render($setting, $field_name);
                },
                $setting_name,
                $setting_name . '_section',
                [
                    'class' => $field_id,
                ]
            );
        }

        protected function field_render()
        {
            $args = func_get_args();
            $setting = $args[0];
            $field = $args[1];
            if (count($args) >= 3) {
                $value = $args[2];
            } else {
                $value = new Undefined();
            }

            return $this->_field_render($setting, $field, $value);
        }

        private function _field_render($setting, $field, $value)
        {
            $is_root = false;
            if ($value instanceof Undefined) {
                $value = self::get_setting($this->group_name, $setting, $field);
                $is_root = true;
            }

            if (!is_array($value)) {
                return $this->input_render($setting, $field, $value);
            } else {
                $fieldset = $this->fieldset_render($setting, $field, $value);
                if ($is_root && is_list($value)) {
                    $fieldset = $this->control_style($setting, $field)
                        . $fieldset . $this->control_render($setting, $field);
                }

                return $fieldset;
            }
        }

        protected function input_render($setting, $field, $value)
        {
            $setting_name = $this->setting_name($setting);
            $schema = self::get_schema($this->group_name, $setting);
            $default_value = self::get_default($this->group_name, $setting);
            $keys = explode('][', $field);
            $is_list = is_list($default_value);
            for ($i = 0; $i < count($keys); $i++) {
                $key = $keys[$i];
                if ($is_list) {
                    $key = (int) $key;
                }
                $default_value = $default_value[$key];
                if ($i === 0) {
                    $schema = $schema[$key];
                }
                $is_list = is_list($default_value);
            }
            $is_bool = is_bool($default_value);
            if ($is_bool) {
                $is_bool = true;
                $value = 'on' === $value;
            }

            if ($is_bool) {
                return "<input type='checkbox' name='{$setting_name}[{$field}]' " . ($value ? 'checked' : '') . " />";
            } else {
                return "<input type='text' name='{$setting_name}[{$field}]' value='{$value}' />";
            }
        }

        private function fieldset_render($setting, $field, $data)
        {
            $setting_name = $this->setting_name($setting);
            $table_id = $setting_name . '__' . str_replace('][', '_', $field);
            $fieldset = "<table id='{$table_id}'>";
            $is_list = is_list($data);
            foreach (array_keys($data) as $key) {
                $fieldset .= '<tr>';
                if (!$is_list) {
                    $fieldset .= "<th>{$key}</th>";
                }
                $_field = $field . '][' . $key;
                $fieldset .= "<td>{$this->field_render($setting, $_field, $data[$key])}</td>";
                $fieldset .= '</tr>';
            }
            $fieldset .= '</table>';

            return $fieldset;
        }

        private function control_render($setting, $field)
        {
            $setting_name = $this->setting_name($setting);
            $default = self::get_default($this->group_name, $setting);
            ob_start();
            ?>
        <div class="<?= $setting_name; ?>__<?= $field ?>--controls">
            <button class="button button-primary" data-action="add">Add</button>
            <button class="button button-secondary" data-action="remove">Remove</button>
        </div>
        <?php include 'fieldset-control-js.php' ?>
<?php
            return ob_get_clean();
        }

        private function control_style($setting, $field)
        {
            $setting_name = $this->setting_name($setting);
            return "<style>#{$setting_name}__{$field} td td,#{$setting_name}__{$field} td th{padding:0}#{$setting_name}__{$field} table table{margin-bottom:1rem}</style>";
        }

        protected function setting_name($setting)
        {
            return $this->group_name . '_' . $setting;
        }

        private function sanitize_option($option, $value)
        {
            $settings = $this->get_settings();
            if (in_array($option, $settings)) {
                [$group, $setting] = explode('_', $option);
                $default = Settings::get_default($group, $setting);
                if (empty($value)) {
                    return $default;
                }

                $schema = Settings::get_schema($group, $setting);

                try {
                    return $this->sanitize_object($schema, $value, $default);
                } catch (Error) {
                    return $default;
                }
            }

            return $value;
        }

        private function sanitize_object($schema, $value, $default)
        {
            foreach ($schema as $key => $defn) {
                if (empty($value[$key])) {
                    $value[$key] = $default[$key];
                } else {
                    if ($defn['type'] === 'array') {
                        $value[$key] = $this->sanitize_array($defn['items'], $value[$key], $default[$key] ?: []);
                    } elseif ($defn['type'] === 'object') {
                        $value[$key] = $this->sanitize_object($defn['properties'], $value[$key], $default[$key] ?: []);
                    } else {
                        $value[$key] = empty($value[$key]) ? $default[$key] : $value[$key];
                    }
                }
            }

            foreach (array_keys($value) as $key) {
                if (!in_array($key, array_keys($schema))) {
                    unset($value[$key]);
                };
            }

            return $value;
        }

        private function sanitize_array($schema, $value, $defaults)
        {
            $default = null;
            for ($i = 0; $i < count($value); $i++) {
                $default = count($defaults) > $i ? array_shift($defaults) : $default;
                if ($schema['type'] === 'array') {
                    $value[$i] = $this->sanitize_array($schema['items'], $value[$i], $default ?: []);
                } elseif ($schema['type'] === 'object') {
                    $value[$i] = $this->sanitize_object($schema['properties'], $value[$i], $default ?: []);
                } else {
                    $value[$i] = empty($value[$i]) ? $default[0] : $value[$i];
                }
            }

            return $value;
        }
    }

endif;

if (!function_exists('\WPCT_ABSTRACT\is_list')) :

    function is_list($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        if (sizeof($arr) === 0) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }

endif;
