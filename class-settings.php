<?php

namespace WPCT_ABSTRACT;

if (!class_exists('\WPCT_ABSTRACT\Settings')) :

    class Undefined
    {
    };

    abstract class Settings extends Singleton
    {
        protected $group_name;
        public static $schemas = [];
        public static $defaults = [];

        abstract public function register();

        public static function get_setting($setting)
        {
            return get_option($setting, []);
        }

        public static function option_getter($setting, $option)
        {
            $default = static::get_default($setting, $option);
            $setting = self::get_setting($setting);
            if (!key_exists($option, $setting)) {
                return null;
            }

            if (empty($setting[$option])) {
                return $default;
            } elseif (is_list($setting[$option])) {
                // $setting[$option] = array_map($setting[$option]);
            }

            return $setting[$option];
        }

        public static function get_default($setting_name, $field = null)
        {
            $default = isset(static::$defaults[$setting_name]) ? static::$defaults[$setting_name] : [];
            $default = apply_filters($setting_name . '_default', $default);

            if ($field && isset($default[$field])) {
                return $default[$field];
            }

            return $default;
        }

        public static function get_schema($setting_name, $prop = null)
        {
            $schema = isset(static::$schemas[$setting_name]) ? static::$schemas[$setting_name] : [];
            $schema = apply_filters($setting_name . '_schema', $schema);

            if ($prop && isset($schema['properties'][$prop])) {
                return $schema['properties'][$prop];
            }

            return $schema;
        }

        public function __construct($group_name)
        {
            $this->group_name = $group_name;
        }

        public function get_group_name()
        {
            return $this->group_name;
        }

        public function get_settings()
        {
            $settings = [];
            foreach (array_keys(self::$defaults) as $setting) {
                if (strstr($setting, $this->group_name)) {
                    $settings[] = $setting;
                }
            }

            return $settings;
        }

        public function register_setting($name, $schema = null, $default = [])
        {
            self::$schemas[$name] = $schema;
            self::$defaults[$name] = $default;

            $default = self::get_default($name);
            $schema = self::get_schema($name);

            register_setting(
                $name,
                $name,
                [
                    'type' => 'object',
                    'show_in_rest' => $schema ? [
                        'name' => $name,
                        'schema' => [
                            'type' => 'object',
                            'properties' => $schema
                        ],
                    ] : false,
                    'type' => 'object',
                    'default' => $default,
                ],
            );

            add_action('admin_init', function () use ($name) {
                add_settings_section(
                    $name . '_section',
                    __($name . '--title', $this->group_name),
                    function () use ($name) {
                        $title = __($name . '--description', $this->group_name);
                        echo "<p>{$title}</p>";
                    },
                    $name,
                );

                foreach (array_keys(self::$defaults[$name]) as $field) {
                    $this->add_settings_field($field, $name);
                }
            });
        }

        private function add_settings_field($field_name, $setting_name)
        {
            $field_id = $setting_name . '__' . $field_name;
            add_settings_field(
                $field_name,
                __($field_id . '--label', $this->group_name),
                function () use ($setting_name, $field_name) {
                    echo $this->field_render($setting_name, $field_name);
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
                $value = self::option_getter($setting, $field);
                $is_root = true;
            }

            if (!is_array($value)) {
                return $this->input_render($setting, $field, $value);
            } else {
                $fieldset = $this->fieldset_render($setting, $field, $value);
                if ($is_root) {
                    $fieldset = $this->control_style($setting, $field)
                        . $fieldset . $this->control_render($setting, $field);
                }

                return $fieldset;
            }
        }

        protected function input_render($setting, $field, $value)
        {
            $default_value = self::get_schema($setting);
            $keys = explode('][', $field);
            $is_list = is_list($default_value);
            for ($i = 0; $i < count($keys); $i++) {
                $key = $keys[$i];
                if ($is_list) {
                    $key = (int) $key;
                }
                $default_value = isset($default_value[$key]) ? $default_value[$key] : $default_value[0];
                $is_list = is_list($default_value);
            }
            $is_bool = is_bool($default_value);
            if ($is_bool) {
                $is_bool = true;
                $value = 'on' === $value;
            }

            if ($is_bool) {
                return "<input type='checkbox' name='{$setting}[$field]' " . ($value ? 'checked' : '') . " />";
            } else {
                return "<input type='text' name='{$setting}[{$field}]' value='{$value}' />";
            }
        }

        private function fieldset_render($setting, $field, $data)
        {
            $table_id = $setting . '__' . str_replace('][', '_', $field);
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
            $default = self::get_default($setting);
            ob_start();
            ?>
        <div class="<?= $setting; ?>__<?= $field ?>--controls">
            <button class="button button-primary" data-action="add">Add</button>
            <button class="button button-secondary" data-action="remove">Remove</button>
        </div>
        <?php include 'fieldset-control-js.php' ?>
<?php
            return ob_get_clean();
        }

        private function control_style($setting, $field)
        {
            return "<style>#{$setting}__{$field} td td,#{$setting}__{$field} td th{padding:0}#{$setting}__{$field} table table{margin-bottom:1rem}</style>";
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
