<?php

namespace WPWC\iikoCloud\Admin;

defined( 'ABSPATH' ) || exit;

class Inactive {

	private string $message = '';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $required_name Required plugin or module name.
	 * @param string $required_version Required plugin or module version.
	 */
	public function __construct( string $plugin_name, string $required_name, string $required_version = '' ) {

		// Warning when the site doesn't have a minimum required ionCube Loader for PHP version.
		if ( 'ionCube Loader for PHP' === $required_name ) {

			$this->message = sprintf(
				esc_html__(
					'%1$s error: the %2$s version %3$s or higher needs to be installed. The ionCube Loader is the industry standard PHP extension for running protected PHP code, and can usually be added easily to a PHP installation. For Loaders please visit %4$s and for an instructional video please see %5$s',
					'wc-iikocloud'
				),
				'<b>' . $plugin_name . '</b>',
				'<b>' . $required_name . '</b>',
				'<b>' . $required_version . '</b>',
				'<a href="https://get-loader.ioncube.com" target="_blank">get-loader.ioncube.com</a>',
				'<a href="https://ioncu.be/LV" target="_blank">ioncu.be/LV</a>'
			);

		} else {

			if ( '' === $required_version ) {

				// Warning when the site doesn't have a required plugin installed or activated.
				$this->message = sprintf( esc_html__( '%1$s requires %2$s to be installed and activated.', 'wc-iikocloud' ),
					'<b>' . $plugin_name . '</b>',
					'<b>' . $required_name . '</b>',
				);

			} else {

				// Warning when the site doesn't have a minimum required plugin or module version.
				$this->message = sprintf( esc_html__( '%1$s requires %2$s version %3$s or greater.', 'wc-iikocloud' ),
					'<b>' . $plugin_name . '</b>',
					'<b>' . $required_name . '</b>',
					'<b>' . $required_version . '</b>',
				);
			}
		}

		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
	}

	/**
	 * Admin notice
	 *
	 * @since 2.5.0
	 */
	public function admin_notice(): void {

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $this->message );
	}
}
