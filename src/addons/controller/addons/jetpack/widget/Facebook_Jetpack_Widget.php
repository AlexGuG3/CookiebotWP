<?php

namespace cybot\cookiebot\addons\controller\addons\jetpack\widget;

use cybot\cookiebot\lib\Addon_With_Extra_Information_Interface;
use function cybot\cookiebot\lib\cookiebot_addons_cookieconsent_optout;

class Facebook_Jetpack_Widget extends Base_Jetpack_Widget implements Addon_With_Extra_Information_Interface {
	const LABEL               = 'Facebook';
	const WIDGET_OPTION_NAME  = 'facebook';
	const DEFAULT_PLACEHOLDER = 'Please accept [renew_consent]%cookie_types[/renew_consent] cookies to see facebook widget.';


	public function load_configuration() {
		/**
		 * The widget is active
		 */
		if ( is_active_widget( false, false, 'facebook-likebox', true ) ) {
			/**
			 * The widget is enabled in Prior consent
			 */
			if ( $this->is_widget_enabled() ) {
				/**
				 * The visitor didn't check the required cookie types
				 */
				if ( ! $this->cookie_consent->are_cookie_states_accepted( $this->get_widget_cookie_types() ) ) {
					/**
					 * Manipulate script attribute
					 */
					$this->add_consent_attribute_to_facebook_embed_javascript();

					/**
					 * Display placeholder if allowed in the backend settings
					 */
					if ( $this->is_widget_placeholder_enabled() ) {
						add_action( 'jetpack_stats_extra', array( $this, 'cookie_consent_div' ), 10, 2 );
					}
				}
			}
		}
	}

	/**
	 * Tag external Facebook javascript file with cookiebot consent.
	 *
	 * @since 1.2.0
	 */
	private function add_consent_attribute_to_facebook_embed_javascript() {
		$this->script_loader_tag->add_tag( 'jetpack-facebook-embed', $this->get_widget_cookie_types() );
	}

	/**
	 * Show consent message when the consent is not given.
	 *
	 * @param $view     string
	 * @param $widget   string
	 *
	 * @since 1.6.0
	 */
	public function cookie_consent_div( $view, $widget ) {
		if ( $widget === 'facebook-likebox' && $view === 'widget_view' ) {
			if ( is_array( $this->get_widget_cookie_types() ) && count( $this->get_widget_cookie_types() ) > 0 ) {
				$classname  = cookiebot_addons_cookieconsent_optout( $this->get_widget_cookie_types() );
				$inner_html = $this->get_widget_placeholder();
				echo '<div class="' . esc_attr( $classname ) . '">
						  ' . esc_html( $inner_html ) . '
						</div>';
			}
		}
	}

	/**
	 * Adds extra information under the label
	 *
	 * @return string[]
	 *
	 * @since 1.8.0
	 */
	public function get_extra_information() {
		return array(
			__( 'Facebook widget.', 'cookiebot' ),
		);
	}

}
