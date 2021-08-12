<?php

namespace cybot\cookiebot\addons\config;

use cybot\cookiebot\addons\controller\addons\Base_Cookiebot_Addon;
use cybot\cookiebot\addons\controller\addons\jetpack\Jetpack;
use cybot\cookiebot\addons\controller\addons\jetpack\widget\Jetpack_Widget_Interface;
use cybot\cookiebot\addons\lib\Settings_Page_Tab;
use cybot\cookiebot\addons\lib\Settings_Service_Interface;
use cybot\cookiebot\Cookiebot_WP;
use Exception;
use RuntimeException;
use ReflectionClass;
use function cybot\cookiebot\addons\lib\cookiebot_addons_get_dropdown_languages;
use function cybot\cookiebot\addons\lib\include_view;

class Settings_Config {

	/**
	 * @var Settings_Service_Interface
	 */
	protected $settings_service;

	/**
	 * Settings_Config constructor.
	 *
	 * @param Settings_Service_Interface $settings_service
	 *
	 * @since 1.3.0
	 */
	public function __construct( Settings_Service_Interface $settings_service ) {
		$this->settings_service = $settings_service;
	}

	/**
	 * Load data for settings page
	 *
	 * @since 1.3.0
	 */
	public function load() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_wp_admin_style_script' ) );
		add_action(
			'update_option_cookiebot_available_addons',
			array(
				$this,
				'post_hook_available_addons_update_option',
			),
			10,
			3
		);
	}

	/**
	 * Registers submenu in options menu.
	 *
	 * @since 1.3.0
	 */
	public function add_submenu() {
		/*add_submenu_page('cookiebot', 'Prior Consent', esc_html__( 'Prior Consent', 'cookiebot' ), 'manage_options', 'cookiebot_addons', array(
			$this,
			'setting_page'
		) );*/

		add_submenu_page(
			'cookiebot',
			esc_html__( 'Prior Consent', 'cookiebot' ),
			esc_html__( 'Prior Consent', 'cookiebot' ),
			'manage_options',
			'cookiebot-addons',
			array(
				$this,
				'setting_page',
			)
		);

	}

	/**
	 * Load css styling to the settings page
	 *
	 * @since 1.3.0
	 */
	public function add_wp_admin_style_script( $hook ) {
		if ( $hook !== 'cookiebot_page_cookiebot-addons' ) {
			return;
		}

		wp_enqueue_script(
			'cookiebot_tiptip_js',
			plugins_url( 'js/jquery.tipTip.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.8',
			true
		);
		wp_enqueue_script(
			'cookiebot_addons_custom_js',
			plugins_url( 'js/settings.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.8',
			true
		);
		wp_localize_script(
			'cookiebot_addons_custom_js',
			'php',
			array( 'remove_link' => ' <a href="" class="submitdelete deletion">' . esc_html__( 'Remove language', 'cookiebot-addons' ) . '</a>' )
		);
		wp_enqueue_style(
			'cookiebot_addons_custom_css',
			COOKIEBOT_PLUGIN_URL . 'assets/css/admin_styles.css',
			null,
			Cookiebot_WP::COOKIEBOT_PLUGIN_VERSION
		);
	}

	/**
	 * Registers addons for settings page.
	 *
	 * @throws Exception
	 * @since 1.3.0
	 */
	public function register_settings() {
		global $pagenow;

		if ( ( isset( $_GET['page'] ) && $_GET['page'] === 'cookiebot-addons' ) || $pagenow === 'options.php' ) {
			if ( isset( $_GET['tab'] ) && 'unavailable_addons' === $_GET['tab'] ) {
				$this->register_unavailable_addons();
			} elseif ( ( isset( $_GET['tab'] ) && 'jetpack' === $_GET['tab'] ) ) {
				$this->register_jetpack_addon();
			} else {
				$this->register_available_addons();
			}

			if ( $pagenow === 'options.php' ) {
				$this->register_jetpack_addon();
			}
		}
	}

	/**
	 * Register available addons
	 *
	 * @throws Exception
	 * @since 1.3.0
	 */
	private function register_available_addons() {
		add_settings_section(
			'available_addons',
			'Available plugins',
			array(
				$this,
				'header_available_addons',
			),
			'cookiebot-addons'
		);

		/* @var Base_Cookiebot_Addon $addon */
		foreach ( $this->settings_service->get_addons() as $addon ) {
			if ( $addon->is_addon_installed() && $addon->is_addon_activated() ) {
				add_settings_field(
					$addon::OPTION_NAME,
					$addon::ADDON_NAME . $this->get_extra_information( $addon ),
					array(
						$this,
						'available_addon_callback',
					),
					'cookiebot-addons',
					'available_addons',
					array(
						'addon' => $addon,
					)
				);

				register_setting(
					'cookiebot_available_addons',
					'cookiebot_available_addons',
					array(
						$this,
						'sanitize_cookiebot',
					)
				);
			}
		}
	}

	/**
	 * Register jetpack addon - new tab for jetpack specific settings
	 *
	 * @throws Exception
	 * @since 1.3.0
	 */
	private function register_jetpack_addon() {
		add_settings_section(
			'jetpack_addon',
			'Jetpack',
			array(
				$this,
				'header_jetpack_addon',
			),
			'cookiebot-addons'
		);

		/* @var Jetpack $addon */
		foreach ( $this->settings_service->get_addons() as $addon ) {
			if ( 'Jetpack' === ( new ReflectionClass( $addon ) )->getShortName() ) {
				if ( $addon->is_addon_installed() && $addon->is_addon_activated() ) {

					foreach ( $addon->get_widgets() as $widget ) {
						add_settings_field(
							$widget->get_widget_option_name(),
							$widget->get_label() . $this->get_extra_information( $widget ),
							array(
								$this,
								'jetpack_addon_callback',
							),
							'cookiebot-addons',
							'jetpack_addon',
							array(
								'widget' => $widget,
								'addon'  => $addon,
							)
						);

						register_setting( 'cookiebot_jetpack_addon', 'cookiebot_jetpack_addon' );
					}
				}
			}
		}
	}

	/**
	 * Registers unavailabe addons
	 *
	 * @throws Exception
	 * @version 2.1.3
	 * @since 1.3.0
	 */
	private function register_unavailable_addons() {
		add_settings_section(
			'unavailable_addons',
			'Unavailable plugins',
			array(
				$this,
				'header_unavailable_addons',
			),
			'cookiebot-addons'
		);

		$addons = $this->settings_service->get_addons();

		/** @var Base_Cookiebot_Addon $addon */
		foreach ( $addons as $addon ) {
			if (
					( ! $addon->is_addon_installed() || ! $addon->is_addon_activated() ) &&
					$addon->is_latest_plugin_version() &&
					! ( $addon->has_previous_version_plugin() && $addon->is_previous_version_plugin_activated() )
			) {
				// not installed plugins
				add_settings_field(
					$addon::ADDON_NAME,
					$addon::ADDON_NAME . $this->get_extra_information( $addon ),
					array(
						$this,
						'unavailable_addon_callback',
					),
					'cookiebot-addons',
					'unavailable_addons',
					array( 'addon' => $addon )
				);
				register_setting( $addon::OPTION_NAME, 'cookiebot_unavailable_addons' );
			}
		}
	}

	/**
	 * Adds extra information under the label.
	 *
	 * @param Base_Cookiebot_Addon $addon
	 *
	 * @return string
	 */
	private function get_extra_information( $addon ) {
		return $addon->get_extra_information()
			? '<div class="extra_information">' . $addon->get_extra_information() . '</div>'
			: '';
	}

	/**
	 * Jetpack tab - header
	 *
	 * @since 1.3.0
	 */
	public function header_jetpack_addon() {
		echo '<p>' . esc_html__( 'Jetpack settings.', 'cookiebot' ) . '</p>';
	}

	/**
	 * Jetpack tab - widget callback
	 *
	 * @param $args array   Information about the widget addon and the option
	 *
	 * @since 1.3.0
	 */
	public function jetpack_addon_callback( $args ) {
		$widget = isset( $args['widget'] ) ? $args['widget'] : null;
		$addon  = isset( $args['addon'] ) ? $args['addon'] : null;

		if ( ! is_a( $widget, Jetpack_Widget_Interface::class ) ) {
			throw new RuntimeException();
		}

		if ( ! is_a( $addon, Base_Cookiebot_Addon::class ) ) {
			throw new RuntimeException();
		}

		$widget_is_enabled                    = $widget->is_widget_enabled();
		$widget_placeholder_is_enabled        = $widget->is_widget_placeholder_enabled();
		$widget_has_placeholder               = $widget->widget_has_placeholder();
		$widget_default_placeholder           = $widget->get_widget_default_placeholder();
		$widget_option_name                   = $widget->get_widget_option_name();
		$widget_placeholders_array            = $widget->get_widget_placeholders();
		$site_default_languages_dropdown_html = 'cookiebot_jetpack_addon[' . $widget_option_name . '][placeholder][languages][site-default]';
		$widget_placeholders                  = is_array( $widget_placeholders_array )
			? array_map(
				function( $language, $placeholder ) use ( $widget_option_name, $widget_placeholders_array ) {
					$removable               = array_key_first( $widget_placeholders_array ) !== $language;
					$name                    = 'cookiebot_jetpack_addon[' . $widget_option_name . '][placeholder][languages][' . $language . ']';
					$languages_dropdown_html = cookiebot_addons_get_dropdown_languages(
						'placeholder_select_language',
						$name,
						$language
					);
					return array(
						'name'                    => $name,
						'removable'               => $removable,
						'language'                => $language,
						'placeholder'             => $placeholder,
						'languages_dropdown_html' => $languages_dropdown_html,
					);
				},
				array_keys( $widget_placeholders_array ),
				array_values( $widget_placeholders_array )
			)
		: array();

		$view_args = array(
			'widget_is_enabled'                    => $widget_is_enabled,
			'widget_placeholder_is_enabled'        => $widget_placeholder_is_enabled,
			'widget_has_placeholder'               => $widget_has_placeholder,
			'widget_placeholders'                  => $widget_placeholders,
			'widget_default_placeholder'           => $widget_default_placeholder,
			'site_default_languages_dropdown_html' => $site_default_languages_dropdown_html,
			'widget_option_name'                   => $widget_option_name,
			'widget_cookie_types'                  => $widget->get_widget_cookie_types(),
			'addon_placeholder_helper'             => $addon->get_placeholder_helper(),
		);

		include_view( 'admin/settings/prior-consent-tabs/jetpack-addon-settings-tab.php', $view_args );
	}

	/**
	 * Returns header for installed plugins
	 *
	 * @since 1.3.0
	 */
	public function header_available_addons() {
		?>
		<p>
			<?php esc_html_e( 'Below is a list of addons for Cookiebot. Addons help you make installed plugins GDPR compliant.', 'cookiebot' ); ?>
			<br/>
			<?php esc_html_e( 'These addons are available because you have the corresponding plugins installed and activated.', 'cookiebot' ); ?>
			<br/>
			<?php esc_html_e( 'Deactivate an addon if you want to handle GDPR compliance yourself, or through another plugin.', 'cookiebot' ); ?>
		</p>
		<?php
	}

	/**
	 * Available addon callback:
	 * - checkbox to enable
	 * - select field for cookie type
	 *
	 * @param $args
	 *
	 * @since 1.3.0
	 */
	public function available_addon_callback( $args ) {
		include COOKIEBOT_ADDONS_DIR . 'view/admin/settings/available-addon-callback.php';
	}

	/**
	 * Returns header for unavailable plugins
	 *
	 * @since 1.3.0
	 */
	public function header_unavailable_addons() {
		echo '<p>' . esc_html__( 'The following addons are unavailable. This is because the corresponding plugin is not installed.', 'cookiebot' ) . '</p>';
	}

	/**
	 * Unavailable addon callback
	 *
	 * @param $args
	 *
	 * @since 1.3.0
	 */
	public function unavailable_addon_callback( $args ) {
		$addon = $args['addon'];

		?>
		<div class="postbox cookiebot-addon">
			<i>
			<?php
			if ( ! $addon->is_addon_installed() ) {
				esc_html_e( 'The plugin is not installed.', 'cookiebot' );
			} elseif ( ! $addon->is_addon_activated() ) {
				esc_html_e( 'The plugin is not activated.', 'cookiebot' );
			}
			?>
				</i>
		</div>
		<?php
	}

	/**
	 * Build up settings page
	 *
	 * @since 1.3.0
	 */
	public function setting_page() {
		$available_addons_tab   = new Settings_Page_Tab(
			'available_addons',
			esc_html__( 'Available Addons', 'cookiebot' ),
			'cookiebot_available_addons',
			'cookiebot-addons'
		);
		$unavailable_addons_tab = new Settings_Page_Tab(
			'unavailable_addons',
			esc_html__( 'Unavailable Addons', 'cookiebot' ),
			'cookiebot_not_installed_options',
			'cookiebot-addons',
			false
		);
		$settings_page_tabs     = array(
			$available_addons_tab,
			$unavailable_addons_tab,
		);
		if ( is_plugin_active( Jetpack::PLUGIN_FILE_PATH ) ) {
			$settings_page_tabs[] = new Settings_Page_Tab(
				'jetpack',
				esc_html__( 'Jetpack', 'cookiebot' ),
				'cookiebot_jetpack_addon',
				'cookiebot-addons'
			);
		}
		$active_tab = array_reduce(
			$settings_page_tabs,
			function( $active_tab, Settings_Page_Tab $settings_page_tab ) {
				if ( ! is_null( $active_tab ) ) {
					return $active_tab;
				}
				if ( $settings_page_tab->is_active() ) {
					return $settings_page_tab;
				}
				return null;
			},
			null
		);
		if ( ! $active_tab ) {
			$available_addons_tab->set_is_active( true );
			$active_tab = $available_addons_tab;
		}
		$view_args = array(
			'settings_page_tabs' => $settings_page_tabs,
			'active_tab'         => $active_tab,
		);
		include_view( 'admin/settings/prior-consent-settings-page.php', $view_args );
	}

	/**
	 * Post action hook after enabling the addon on the settings page.
	 *
	 * @param $old_value
	 * @param $value
	 * @param $option_name
	 *
	 * @throws Exception
	 * @since 2.2.0
	 */
	public function post_hook_available_addons_update_option( $old_value, $value, $option_name ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $addon_option_name => $addon_settings ) {
				if ( isset( $addon_settings['enabled'] ) ) {
					$this->settings_service->post_hook_after_enabling_addon_on_settings_page( $addon_option_name );
				}
			}
		}
	}
}
