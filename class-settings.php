<?php

namespace WPCT_ABSTRACT;

if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('\WPCT_ABSTRACT\Settings')) {

    require_once 'class-singleton.php';
    require_once 'class-setting.php';
    require_once 'class-rest-settings-controller.php';
    require_once 'class-undefined.php';

    /**
     * Plugin settings abstract class.
     */
    abstract class Settings extends Singleton
    {
        /**
         * Handle settings group name.
         *
         * @var string $group_name Settings group name.
         */
        private $group;

        /**
         * Handle plugin settings rest controller class name.
         *
         * @var string $rest_controller_class Settings REST Controller class name.
         */
        protected static $rest_controller_class = '\WPCT_ABSTRACT\REST_Settings_Controller';

        /**
         * Handle settings cached values.
         *
         * @var array $cache Settings cached values.
         */
        private static $cache = [];

        /**
         * Register settings method.
         */
        abstract public function register();

        /**
         * Get setting values.
         *
         * @param string $group Setting's group name.
         * @param string $name Setting's name.
         *
         * @return Setting $value Setting instace.
         */
        public static function get_setting($group, $name)
        {
            $setting_name = $group . '_' . $name;
            if (!isset(self::$cache[$setting_name])) {
                self::$cache[$setting_name] = new Setting($group, $name, [], []);
            }
            return self::$cache[$setting_name];
        }

        /**
         * Class constructor. Store the group name and hooks to pre_update_option.
         *
         * @param string $group Settings group name.
         */
        protected function construct(...$args)
        {
            [$group] = $args;
            $this->group = $group;
            static::$rest_controller_class::setup($group);

            add_action('init', function () {
                $this->register();
                do_action('wpct_register_settings', $this->group, $this);
            });
        }

        /**
         * Get settings group name.
         *
         * @return string $group_name Settings group name.
         */
        public function group()
        {
            return $this->group;
        }

        /**
         * Return group settings instances.
         *
         * @return array Group settings.
         */
        public function settings()
        {
            $settings = [];
            foreach (array_keys(self::$cache) as $full_name) {
                if (strstr($full_name, $this->group)) {
                    $settings[] = self::$cache[$full_name];
                }
            }

            return $settings;
        }

        public function setting($name)
        {
            return self::get_setting($this->group, $name);
        }

        /**
         * Registers a setting and its fields.
         *
         * @param string $name Setting name.
         * @param array $schema Setting schema.
         * @param array $default Setting's default values.
         */
        public function register_setting($name, $schema, $default = [])
        {
            $setting = new Setting($this->group(), $name, $default, ['type' => 'object', 'properties' => $schema, 'additionalProperties' => false]);
            $setting_name = $setting->full_name();
            $schema = $setting->schema();

            // Register setting
            register_setting(
                $setting_name,
                $setting_name,
                [
                    'type' => 'object',
                    'show_in_rest' => [
                        'name' => $setting_name,
                        'schema' => $schema,
                    ],
                    'default' => $default,
                ],
            );

            self::$cache[$setting_name] = $setting;

            // Add settings section on admin init
            add_action('admin_init', function () use ($setting, $default) {
                $setting_name = $setting->full_name();

                $section_name = $setting_name . '_section';
                $section_label = __($setting_name . '--title', $this->group);
                add_settings_section(
                    $section_name,
                    $section_label,
                    function () use ($setting_name) {
                        $title = __($setting_name . '--description', $this->group);
                        printf('<p>%s</p>', esc_html($title));
                    },
                    $setting_name,
                );

                foreach (array_keys($default) as $field) {
                    $this->add_setting_field($setting, $field);
                }
            });
        }

        /**
         * Registers a setting field.
         *
         * @param Setting $setting Setting name.
         * @param string $field Field name.
         */
        private function add_setting_field($setting, $field)
        {
            $setting_name = $setting->full_name();
            $field_id = $setting_name . '__' . $field;
            $field_label = __($field_id . '--label', $this->group);

            add_settings_field(
                $field,
                $field_label,
                function () use ($setting, $field) {
                    echo $this->field_render($setting, $field);
                },
                $setting_name,
                $setting_name . '_section',
                [
                    'class' => $field_id,
                ]
            );
        }

        /**
         * Renders the field HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param string|Undefined $value Field value.
         *
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
         * Renders the field HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param string|Undefined $value Field value.
         *
         * @return string $html Input HTML.
         */
        private function _field_render($setting, $field, $value)
        {
            $is_root = false;
            if ($value instanceof Undefined) {
                $value = $setting->data($field);
                $is_root = true;
            }

            if (!is_array($value)) {
                return $this->input_render($setting, $field, $value);
            } else {
                $fieldset = $this->fieldset_render($setting, $field, $value);
                if ($is_root && is_list($value)) {
                    $this->control_style($setting, $field);
                    $fieldset .= $this->control_render($setting, $field);
                }

                return $fieldset;
            }
        }

        /**
         * Render input HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param string $value Field value.
         *
         * @return string $html Input HTML.
         */
        protected function input_render($setting, $field, $value)
        {
            $setting_name = $setting->full_name();
            $data = $setting->data();
            $schema = $setting->schema();
            $keys = explode('][', $field);

            $is_list = is_list($data);
            for ($i = 0; $i < count($keys); $i++) {
                $key = $keys[$i];
                if ($is_list) {
                    $key = (int) $key;
                }
                $data = $data[$key];
                if ($i === 0) {
                    $schema = $schema[$key];
                }
                $is_list = is_list($data);
            }
            $is_bool = is_bool($data);

            if ($is_bool) {
                return sprintf(
                    '<input type="checkbox" name="%s" ' . ($value ? 'checked' : '') . ' />',
                    esc_attr($setting_name . "[{$field}]"),
                );
            } else {
                return sprintf(
                    '<input type="text" name="%s" value="%s" />',
                    esc_attr($setting_name . "[{$field}]"),
                    esc_attr($value),
                );
            }
        }

        /**
         * Render fieldset HTML.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         * @param array $data Setting data.
         *
         * @return string $html Fieldset HTML.
         */
        private function fieldset_render($setting, $field, $data)
        {
            $setting_name = $setting->full_name();
            $table_id = $setting_name . '__' . str_replace('][', '_', $field);
            $fieldset = '<table id="' . esc_attr($table_id) . '">';
            $is_list = is_list($data);
            foreach (array_keys($data) as $key) {
                $fieldset .= '<tr>';
                if (!$is_list) {
                    $fieldset .= '<th>' . esc_html($key) . '</th>';
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
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         *
         * @return string $html Control HTML.
         */
        private function control_render($setting, $field)
        {
            $setting_name = $setting->full_name();
            $control_class = esc_attr($setting_name . '__' . $field . '--controls');
            $field_value = $setting->data()[$field][0];

            ?>
			<div class="<?php echo $control_class ?>>
				<button class="button button-primary" data-action="add">Add</button>
				<button class="button button-secondary" data-action="remove">Remove</button>
			</div>
			<?php
            $control_script = include 'fieldset-control-js.php';
            wp_print_inline_script_tag($control_script, [
                'data-control' => $control_class,
            ]);
        }

        /**
         * Render control style tag.
         *
         * @param Setting $setting Setting instance.
         * @param string $field Field name.
         *
         * @return string $tag Style HTML tag with control styles.
         */
        private function control_style($setting, $field)
        {
            $setting_name = $setting->full_name();
            add_action('admin_print_styles', function () use ($setting_name, $field) {
                echo "#{$setting_name}__{$field} td td,#{$setting_name}__{$field} td th{padding:0}#{$setting_name}__{$field} table table{margin-bottom:1rem}";
            });
        }
    }
}

if (!function_exists('\WPCT_ABSTRACT\is_list')) {

    /**
     * Check if array is positional.
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
}
