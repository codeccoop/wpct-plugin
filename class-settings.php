<?php

namespace WPCT_ABSTRACT;

if (!class_exists('\WPCT_ABSTRACT\Settings')) :

    /**
     * Undefined value.
     *
     * @since 1.0.0
     */
    class Undefined
    {
    };

    /**
     * Plugin settings abstract class.
     *
     * @since 1.0.0
     */
    abstract class Settings extends Singleton
    {
        /**
         * Handle settings group name.
         *
         * @since 1.0.0
         *
         * @var string $group_name Settings group name.
         */
        protected $group_name;

        /**
         * Handle settings schemas.
         *
         * @since 1.0.0
         *
         * @var string $schemas Settings schemas.
         */
        public static $schemas = [];

        /**
         * Handle settings default values.
         *
         * @since 1.0.0
         *
         * @var string $defaults Settings default values.
         */
        public static $defaults = [];

        /**
         * Handle settings cached values.
         *
         * @since 1.0.0
         *
         * @var string $cache Settings cached values.
         */
        private static $cache = [];

        /**
         * Handle settings group name.
         *
         * @since 1.0.0
         *
         * @var string $group_name Settings group name.
         */
        abstract public function register();

        /**
         * Get setting values.
         *
         * @since 1.0.0
         *
         * @param string $group_name Settings group name.
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @return array|string $value Setting default values.
         */
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

        /**
         * Get setting default values.
         *
         * @since 1.0.0
         *
         * @param string $group_name Settings group name.
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @return array|string $value Setting default values.
         */
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

        /**
         * Get setting schema.
         *
         * @since 1.0.0
         *
         * @param string $group_name Settings group name.
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @return array $schema Setting or field schema.
         */
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

        /**
         * Class constructor. Store the group name and hooks to pre_update_option.
         *
         * @since 1.0.0
         *
         * @param string $group_name Settings group name.
         */
        public function __construct($group_name)
        {
            $this->group_name = $group_name;

            add_filter('pre_update_option', function ($value, $option, $from) {
                return $this->sanitize_setting($option, $value);
            }, 10, 3);
        }

        /**
         * Get settings group name.
         *
         * @since 1.0.0
         *
         * @return string $group_name Settings group name.
         */
        public function get_group_name()
        {
            return $this->group_name;
        }

        /**
         * Return group settings names.
         *
         * @since 1.0.0
         *
         * @return array $names Settings names.
         */
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

        /**
         * Register setting.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param array|null $schema Setting schema.
         * @defaults array $defaults Setting default values.
         */
        public function register_setting($setting, $schema = null, $default = [])
        {
            $setting_name = $this->setting_name($setting);
            self::$schemas[$setting_name] = $schema;
            self::$defaults[$setting_name] = $default;

            $default = self::get_default($this->group_name, $setting);
            $schema = self::get_schema($this->group_name, $setting);

            // Register setting
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

            // Cache data on option creation
            add_action('add_option', function ($option, $value) use ($setting_name) {
                if ($option === $setting_name && !empty($to)) {
                    static::$cache[$setting_name] = $value;
                }
            }, 5, 2);

            // Cache data on option update
            add_action('update_option', function ($option, $from, $to) use ($setting_name) {
                if ($option === $setting_name && !empty($to)) {
                    static::$cache[$setting_name] = $to;
                }
            }, 5, 3);

            // Add settings section on admin init
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

        /**
         * Register setting field.
         *
         * @since 1.0.0
         *
         * @param string $field_name Field name.
         * @param string $setting Setting name.
         */
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

        /**
         * Render field HTML.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @param string|Undefined $value Field value.
         * @return string $html Input HTML.
         */
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

        /**
         * Render field HTML.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @param string|Undefined $value Field value.
         * @return string $html Input HTML.
         */
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

        /**
         * Render input HTML.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @param string $value Field value.
         * @return string $html Input HTML.
         */
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
                return "<input type='checkbox' name='{$setting_name}[{$field}]' " . ($value ? 'checked' : '') . " />";
            } else {
                return "<input type='text' name='{$setting_name}[{$field}]' value='{$value}' />";
            }
        }

        /**
         * Render fieldset HTML.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @param array $data Setting data.
         * @return string $html Fieldset HTML.
         */
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

        /**
         * Render control HTML.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @return string $html Control HTML.
         */
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

        /**
         * Render control style tag.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @param string $field Field name.
         * @return string $tag Style HTML tag with control styles.
         */
        private function control_style($setting, $field)
        {
            $setting_name = $this->setting_name($setting);
            return "<style>#{$setting_name}__{$field} td td,#{$setting_name}__{$field} td th{padding:0}#{$setting_name}__{$field} table table{margin-bottom:1rem}</style>";
        }

        /**
         * Return setting full name.
         *
         * @since 1.0.0
         *
         * @param string $setting Setting name.
         * @return string $setting_name Setting full name.
         */
        protected function setting_name($setting)
        {
            return $this->group_name . '_' . $setting;
        }

        /**
         * Sanitize setting data before database inserts.
         *
         * @since 1.0.0
         *
         * @param string $option Setting name.
         * @param array $value Setting data.
         * @return array $value Sanitized setting data.
         */
        private function sanitize_setting($option, $value)
        {
            $settings = $this->get_settings();
            if (!in_array($option, $settings)) {
                return $value;
            }

            [$group, $setting] = explode('_', $option);
            $schema = Settings::get_schema($group, $setting);

            if (!rest_validate_value_from_schema($value, $schema)) {
                return new WP_Error('rest_invalid_schema', 'The setting is not schema conformant', ['value' => $value, 'schema' => $schema]);
            }

            return rest_sanitize_value_from_schema($value, $schema);
        }
    }

endif;

if (!function_exists('\WPCT_ABSTRACT\is_list')) :

    /**
     * Check if array is positional.
     *
     * @since 1.0.0
     *
     * @param array $arr Target array.
     * @return boolean $is_list Result.
     */
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
