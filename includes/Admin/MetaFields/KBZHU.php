<?php

namespace WPWC\iikoCloud\Admin\MetaFields;

defined( 'ABSPATH' ) || exit;

class KBZHU {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'product_add_iiko_kbzhu' ], 999 );
	}

	/**
	 * Display iiko product KBZHU fields on product page.
	 */
	public function product_add_iiko_kbzhu() {

		if ( ! $product = wc_get_product( absint( $_GET['post'] ) ) ) {
			return;
		}

		$product_kbzhu = $product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_kbzhu' );
		$kbzhu_names   = [
			'energy'        => esc_html__( 'Energy value', 'wc-iikocloud' ),
			'proteins'      => esc_html__( 'Proteins', 'wc-iikocloud' ),
			'fat'           => esc_html__( 'Fats', 'wc-iikocloud' ),
			'carbohydrates' => esc_html__( 'Carbohydrates', 'wc-iikocloud' ),
		];

		if ( ! is_array( $product_kbzhu ) || empty( $product_kbzhu ) ) {
			return;
		}

		foreach ( $product_kbzhu as $kbzhu_type => $kbzhu_values ) {

			if ( empty( $kbzhu_values ) ) {
				continue;
			}

			$is_per_100g = 'per_100g' === $kbzhu_type;
			$full_name   = $is_per_100g ? '' : 'Full';

			echo '<div class="options_group">';
			echo '<h2>';
			echo $is_per_100g
				? esc_html__( 'KBZHU (per 100g)', 'wc-iikocloud' )
				: esc_html__( 'KBZHU (per item)', 'wc-iikocloud' );
			echo '</h2>';

			foreach ( $kbzhu_values as $kbzhu_name => $kbzhu_value ) {
				woocommerce_wp_text_input(
					[
						'id'                => WC_IIKOCLOUD_PREFIX . 'product_' . esc_attr( $kbzhu_name ) . $full_name . 'Amount',
						'name'              => WC_IIKOCLOUD_PREFIX . 'product_' . esc_attr( $kbzhu_name ) . $full_name . 'Amount',
						'label'             => $kbzhu_names[ $kbzhu_name ],
						'type'              => 'number',
						'custom_attributes' => [ 'readonly' => 'readonly' ],
						'value'             => floatval( $kbzhu_value ),
					]
				);
			}

			echo '</div>';
		}
	}
}
