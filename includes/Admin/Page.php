<?php

namespace WPWC\iikoCloud\Admin;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\Traits\ImportTrait;
use WPWC\iikoCloud\Traits\PageElementsTrait;

class Page {

	use ImportTrait;
	use PageElementsTrait;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_submenu_page' ] );
	}

	/**
	 * Register submenu page.
	 */
	public function register_submenu_page() {
		add_submenu_page(
			'woocommerce',
			'iikoCloud',
			'iikoCloud',
			'manage_woocommerce',
			'wc_iikocloud',
			[ $this, 'submenu_page_content' ],
			10
		);
	}

	/**
	 * Submenu page content.
	 */
	public function submenu_page_content() {

		ob_start();

		static::preloader();
		static::plugin_header();
		echo static::plugin_subheader();
		static::admin_links();
		?>

        <div class="wpwc_page_wrapper">
            <div class="wpwc_page_column_left">
                <form class="wpwc_form" method="POST" action="#">

                    <h2><?php esc_html_e( 'Get general information', 'wc-iikocloud' ); ?></h2>
                    <p class="wpwc_header_desc">
						<?php esc_html_e( 'Organizations, terminals, categories and products from iiko.', 'wc-iikocloud' ); ?>
                    </p>

                    <fieldset>
						<?php
						static::print_button(
							'organizations',
							'get',
							esc_html_x( 'Organizations', 'Get', 'wc-iikocloud' ),
							' button-primary',
							false
						);
						?>

                        <div id="wpwcIikoOrganizationsWrap" class="hidden">
							<?php
							static::print_select(
								'organizations',
								esc_html__( 'Organizations', 'wc-iikocloud' )
							);

							static::print_button(
								'organization_import',
								'save',
								esc_html_x( 'Organization for Import', 'Save', 'wc-iikocloud' ),
								''
							);
							?>
                        </div>
                    </fieldset>

                    <fieldset>
						<?php
						static::print_button(
							'terminals',
							'get',
							esc_html_x( 'Terminals', 'Get', 'wc-iikocloud' )
						);
						?>

                        <div id="wpwcIikoTerminalsWrap" class="hidden">
							<?php
							static::print_select(
								'terminals',
								esc_html__( 'Terminals', 'wc-iikocloud' ),
								true,
								4
							);

							static::print_button(
								'organization_terminals_export',
								'save',
								esc_html_x( 'Organization and Terminal for Export', 'Save', 'wc-iikocloud' ),
								''
							);
							?>
                        </div>
                    </fieldset>

                    <h2><?php esc_html_e( 'Get nomenclature (menu)', 'wc-iikocloud' ); ?></h2>

                    <fieldset>

                        <p>
							<?php
							if (
								isset( self::get_import_settings()['method'] )
								&& 'external_menu' === self::get_import_settings()['method']
							) {

								echo '<h3>' . esc_html__( 'External menus', 'wc-iikocloud' ) . '</h3>';
								echo '<p class="wpwc_header_desc">' . esc_html__( 'New method.', 'wc-iikocloud' ) . '</p>';

								static::print_button(
									'menus',
									'get',
									esc_html_x( 'Menus', 'Get', 'wc-iikocloud' )
								);

								echo '<div id="wpwcIikoMenusWrap" class="hidden">';
								static::print_select(
									'menus',
									esc_html__( 'Menus', 'wc-iikocloud' )
								);

								static::print_select(
									'price_categories',
									esc_html__( 'Price categories', 'wc-iikocloud' )
								);

								static::print_button(
									'menu_nomenclature',
									'get',
									esc_html_x( 'Menu nomenclature', 'Get', 'wc-iikocloud' )
								);
								echo '</div>';

							} else {

								echo '<h3>' . esc_html__( 'Menu from iikoOffice', 'wc-iikocloud' ) . '</h3>';
								echo '<p class="wpwc_header_desc">' . esc_html__( 'Previous method.', 'wc-iikocloud' ) . '</p>';

								static::print_button(
									'nomenclature',
									'get',
									esc_html_x( 'Nomenclature', 'Get', 'wc-iikocloud' )
								);
							}

							printf( esc_html__( 'You can change the import method in the %splugin settings%s.', 'wc-iikocloud' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings&section=import' ) ) . '">',
								'</a>'
							);
							?>
                        </p>

						<?php static::print_nomenclature_info(); ?>
                    </fieldset>

                    <fieldset>
                        <div id="wpwcIikoNomenclatureGroupsWrap" class="hidden">

                            <h2><?php esc_html_e( 'Import', 'wc-iikocloud' ); ?></h2>
                            <p class="wpwc_header_desc">
								<?php esc_html_e( 'Categories and products to WooCommerce.', 'wc-iikocloud' ); ?>
                            </p>

							<?php
							static::print_select(
								'groups',
								esc_html__( 'Groups', 'wc-iikocloud' ),
								true,
								20
							);

							echo '<small>';
							printf( esc_html__( '%sStrikethrough%s items are deleted groups.', 'wc-iikocloud' ),
								'<s><b>',
								'</b></s>'
							);
							echo '</small>';

							static::print_button(
								'groups',
								'save',
								esc_html_x( 'selected groups for auto import', 'Save', 'wc-iikocloud' ),
								''
							);

							static::print_button(
								'groups_products',
								'import',
								esc_html_x( 'products of selected groups', 'Get', 'wc-iikocloud' )
							);
							?>
                        </div>

						<?php static::print_imported_nomenclature_info(); ?>
                    </fieldset>

                    <h2><?php esc_html_e( 'Get additional information', 'wc-iikocloud' ); ?></h2>
                    <p class="wpwc_header_desc">
						<?php esc_html_e( 'Cities, streets and payment methods from iiko to prepare the export of orders from WooCommerce to iiko.', 'wc-iikocloud' ); ?>
                    </p>

                    <fieldset>
						<?php
						static::print_button(
							'cities',
							'get',
							esc_html_x( 'Cities', 'Get', 'wc-iikocloud' )
						);
						?>

                        <div id="wpwcIikoCitiesWrap" class="hidden">

							<?php
							static::print_select(
								'cities',
								esc_html__( 'Cities', 'wc-iikocloud' ),
								true,
								10
							);

							static::print_button(
								'streets',
								'get',
								esc_html_x( 'Streets', 'Get', 'wc-iikocloud' )
							);
							?>

                            <small>
								<?php esc_html_e( 'And save chosen city and streets to the plugin options.', 'wc-iikocloud' ) ?>
                            </small>
                        </div>
                    </fieldset>

					<?php do_action( WC_IIKOCLOUD_PREFIX . 'add_plugin_page_fieldsets' ); ?>

					<?php wp_nonce_field( WC_IIKOCLOUD_PREFIX . 'action', WC_IIKOCLOUD_PREFIX . 'nonce' ); ?>
                </form>
            </div>

            <div class="wpwc_terminal_wrapper wpwc_page_column_right">
                <div class="wpwc_terminal_fixed_wrapper">
                    <div class="wpwc_terminal_header">
                        <h2><?php esc_html_e( 'Terminal:', 'wc-iikocloud' ); ?></h2>

                        <p>
                            <button id="wpwcIikoRemoveAccessToken" class="button button-warning"><?php esc_html_e( 'Remove access token', 'wc-iikocloud' ); ?></button>
                            <button id="wpwcIikoClearTerminal" class="button"><?php esc_html_e( 'Clear', 'wc-iikocloud' ); ?></button>
                        </p>
                    </div>

                    <div id="wpwcIikoTerminal" class="wpwc_terminal code"></div>
                </div>
            </div>
        </div>

		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<div id="wpwcIikoPage" class="wpwc_page wrap">%s</div>';

		echo wp_kses( sprintf( $wrapper, $html ), WC_IIKOCLOUD_ALLOWED_HTML );
	}
}
