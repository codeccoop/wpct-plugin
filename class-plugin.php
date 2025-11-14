<?php
/**
 * Class Plugin
 *
 * @package wpct-plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

namespace WPCT_PLUGIN;

use Exception;
use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-singleton.php';
require_once 'class-menu.php';
require_once 'class-settings-form.php';
require_once 'class-settings-store.php';

/**
 * Plugin abstract class.
 */
class Plugin extends Singleton {
	/**
	 * Handles plugin's menu class name.
	 *
	 * @var string
	 */
	const MENU = '\WPCT_PLUGIN\Menu';

	/**
	 * Handles plugin's settings store class name.
	 *
	 * @var string
	 */
	const STORE = '\WPCT_PLUGIN\Settings_Store';

	/**
	 * Handles plugin's settings class name.
	 *
	 * @var string
	 */
	const SETTINGS_FORM = '\WPCT_PLUGIN\Settings_Form';

	/**
	 * Handles plugin's headers data.
	 *
	 * @var string
	 */
	private static $data;

	/**
	 * Handles plugin's settings store instance.
	 *
	 * @var Settings_Store
	 */
	private $store;

	/**
	 * Handles plugin's menu instance.
	 *
	 * @var Menu
	 */
	private $menu;

	/**
	 * Handles plugin's settings ui instance.
	 *
	 * @var Settings_Form
	 */
	private $settings_form;

	/**
	 * Plugin initializer.
	 */
	protected static function init() {
	}

	/**
	 * Plugin activation callback.
	 */
	public static function activate() {
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function deactivate() {
	}

	/**
	 * Public plugin's initializer.
	 *
	 * @param mixed[] ...$args Plugin constructor array of arguments.
	 *
	 * @return Plugin
	 */
	final public static function setup( ...$args ) {
		return static::get_instance( ...$args );
	}

	/**
	 * Checks if some plugin is active, also in the network.
	 *
	 * @param string $plugin_name index file of the plugin.
	 *
	 * @return bool activation state of the plugin
	 */
	final public static function is_plugin_active( $plugin_name ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		return is_plugin_active( $plugin_name );
	}

	/**
	 * Plugin constructor. Bind plugin to wp init hook and load textdomain.
	 *
	 * @param mixed[] ...$args Constructor array of arguments.
	 */
	protected function construct( ...$args ) {
		$store_class = static::STORE;
		$this->store = $store_class::get_instance( static::slug() );

		if ( static::is_active() ) {
			$menu_class = static::MENU;
			$this->menu = $menu_class::get_instance(
				static::name(),
				static::slug(),
				$this->store
			);

			$form_class          = static::SETTINGS_FORM;
			$this->settings_form = $form_class::get_instance( $this->store );
		}

		add_action(
			'init',
			function () {
				static::load_textdomain();
				static::init();
			}
		);

		add_filter(
			'plugin_action_links',
			static function ( $links, $file ) {
				if ( ! static::is_active() ) {
					return $links;
				}

				if ( static::index() !== $file ) {
					return $links;
				}

				$url   = admin_url(
					'options-general.php?page=' . static::slug()
				);
				$label = __( 'Settings', 'wpct-plugin' );
				$link  = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					esc_html( $label )
				);

				array_push( $links, $link );

				return $links;
			},
			10,
			2
		);

		register_activation_hook(
			static::index(),
			function () {
				static::activate();
			}
		);

		register_deactivation_hook(
			static::index(),
			function () {
				static::deactivate();
			}
		);
	}

	/**
	 * Plugin name getter.
	 *
	 * @return string $name plugin name
	 */
	final public static function name() {
		return static::data()['Name'];
	}

	/**
	 * Plugin slug getter.
	 *
	 * @return string $slug plugin's textdomain alias
	 */
	final public static function slug() {
		return pathinfo( static::index() )['filename'];
	}

	/**
	 * Plugin index getter.
	 *
	 * @return string plugin's index file
	 */
	final public static function index() {
		$path = static::path();
		$name = basename( $path );
		return plugin_basename( $path . '/' . $name . '.php' );
	}

	/**
	 * Plugin's path getter.
	 *
	 * @return string
	 *
	 * @throws Exception In case not plugin's main file found.
	 */
	final public static function path() {
		$reflection = new ReflectionClass( static::class );

		$filepath = wp_normalize_path( $reflection->getFileName() );

		$is_installed    = str_starts_with( $filepath, WP_PLUGIN_DIR );
		$is_mu_installed = str_starts_with( $filepath, WPMU_PLUGIN_DIR );

		if ( $is_installed ) {
			$relative_path = plugin_basename( $filepath );
			return WP_PLUGIN_DIR . '/' . explode( '/', $relative_path )[0];
		} elseif ( $is_mu_installed ) {
			$relative_path = plugin_basename( $filepath );
			return WPMU_PLUGIN_DIR . '/' . explode( '/', $relative_path )[0];
		} else {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$dirname = dirname( $filepath );
			$name    = get_plugin_data( $filepath )['Name'] ?? null;

			if ( $name ) {
				return $dirname;
			}

			while ( ! $name ) {
				foreach ( array_diff( scandir( $dirname ), array( '.', '..' ) ) as $filename ) {
					$filepath = $dirname . '/' . $filename;

					if ( is_dir( $filepath ) || ! is_readable( $filepath ) ) {
						continue;
					}

					$name = get_plugin_data( $filepath )['Name'] ?? null;

					if ( $name ) {
						return $dirname;
					}
				}

				if ( dirname( $dirname ) === $dirname ) {
					throw new Exception( 'Not a plugin' );
				}

				$dirname = dirname( $dirname );
			}
		}
	}

	/**
	 * Plugin's public url getter.
	 *
	 * @return string
	 */
	final public static function url() {
		return plugin_dir_url( static::index() );
	}

	/**
	 * Plugin textdomain getter.
	 *
	 * @return string plugin's textdomain
	 */
	final public static function textdomain() {
		return static::data()['TextDomain'];
	}

	/**
	 * Plugin version getter.
	 *
	 * @return string plugin's version
	 */
	final public static function version() {
		return static::data()['Version'];
	}

	/**
	 * Plugin dependencies getter.
	 *
	 * @return array plugin's dependencies
	 */
	final public static function dependencies() {
		$dependencies = static::data()['RequiresPlugins'];

		if ( empty( $dependencies ) ) {
			return array();
		}

		return array_map(
			function ( $plugin ) {
				$plugin = trim( $plugin );

				return $plugin . '/' . $plugin . '.php';
			},
			explode( ',', $dependencies )
		);
	}

	/**
	 * Plugin data getter.
	 *
	 * @return array $data plugin data
	 */
	private static function data() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin_dir = static::path() . '/' . basename( static::index() );

		return get_plugin_data( $plugin_dir, false, false );
	}

	/**
	 * Active state getter.
	 *
	 * @return bool $is_active plugin active state
	 */
	final public static function is_active() {
		return static::is_plugin_active( static::index() );
	}

	/**
	 * Load plugin textdomain.
	 */
	private static function load_textdomain() {
		$data        = static::data();
		$domain_path =
			isset( $data['DomainPath'] ) && ! empty( $data['DomainPath'] )
				? $data['DomainPath']
				: '/languages';

		load_plugin_textdomain(
			static::textdomain(),
			false,
			dirname( static::index() ) . $domain_path
		);
	}

	/**
	 * Public getter of the plugin's menu instance.
	 *
	 * @return Menu|null
	 */
	final public static function menu() {
		return static::get_instance()->menu;
	}

	/**
	 * Public getter of the plugin's store instance.
	 *
	 * @return Settings_Store|null
	 */
	final public static function store() {
		$store = static::get_instance()->store;

		if ( empty( $store ) ) {
			return;
		}

		return $store;
	}

	/**
	 * Public getter of the plugin's store settings.
	 *
	 * @param string $name Setting name.
	 *
	 * @return Setting|null
	 */
	final public static function setting( $name ) {
		$store = static::get_instance()->store;

		if ( empty( $store ) ) {
			return;
		}

		return $store::setting( $name );
	}
}
