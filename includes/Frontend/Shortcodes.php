<?php

namespace WPWC\iikoCloud\Frontend;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	/**
	 * Initialization.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );
	}

	/**
	 * Register shortcodes.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'iiko_kbzhu', [ __CLASS__, 'render' ] );
	}

	/**
	 * Render shortcode.
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public static function render( $atts ): string {

		$atts = shortcode_atts( [
			'id'        => '',
			'mode'      => 'per_100g', // per_item
			'names'     => 'short',
			'precision' => 0,
			'delimiter' => '&nbsp;&mdash;&nbsp;',
			'style'     => 'on',
		], $atts, 'iiko_kbzhu' );

		if ( empty( $atts['id'] ) ) {
			return false;
		}

		if ( ! $product = wc_get_product( absint( $atts['id'] ) ) ) {
			return false;
		}

		if ( empty( $product_kbzhu = $product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_kbzhu' ) ) ) {
			return false;
		}

		$kbzhu_names = 'short' === $atts['names']
			? [
				'energy'        => esc_html__( 'E', 'wc-iikocloud' ),
				'proteins'      => esc_html__( 'P', 'wc-iikocloud' ),
				'fat'           => esc_html__( 'F', 'wc-iikocloud' ),
				'carbohydrates' => esc_html__( 'C', 'wc-iikocloud' ),
			]
			: [
				'energy'        => esc_html__( 'Energy value', 'wc-iikocloud' ),
				'proteins'      => esc_html__( 'Proteins', 'wc-iikocloud' ),
				'fat'           => esc_html__( 'Fats', 'wc-iikocloud' ),
				'carbohydrates' => esc_html__( 'Carbohydrates', 'wc-iikocloud' ),
			];

		$kbzhu_names['energy'] = 'per_100g' === $atts['mode']
			? $kbzhu_names['energy'] . ' ' . esc_html__( 'per 100 g', 'wc-iikocloud' )
			: $kbzhu_names['energy'] . ' ' . esc_html__( 'per item', 'wc-iikocloud' );

		$kbzhu_units = [
			'energy'        => esc_html__( 'kcal', 'wc-iikocloud' ),
			'proteins'      => esc_html__( 'g', 'wc-iikocloud' ),
			'fat'           => esc_html__( 'g', 'wc-iikocloud' ),
			'carbohydrates' => esc_html__( 'g', 'wc-iikocloud' ),
		];

		ob_start();

		foreach ( $product_kbzhu as $kbzhu_type => $kbzhu_values ) {

			if (
				$atts['mode'] !== $kbzhu_type
				|| empty( $kbzhu_values )
			) {
				continue;
			}

			echo '<dl class="iiko_kbzhu_group">';

			foreach ( $kbzhu_values as $kbzhu_name => $kbzhu_value ) {

				if ( empty( $kbzhu_value ) ) {
					continue;
				}

				echo '<dt class="iiko_kbzhu_term">';
				echo esc_html( $kbzhu_names[ $kbzhu_name ] );
				if ( ! empty( $atts['delimiter'] ) ) {
					echo '<span>';
					echo esc_attr( $atts['delimiter'] );
					echo '</span>';
				}
				echo '</dt>';

				echo '<dd class="iiko_kbzhu_value">';
				echo round( floatval( $kbzhu_value ), intval( $atts['precision'] ) );
				echo '&nbsp;' . esc_html( $kbzhu_units[ $kbzhu_name ] );
				echo '</dd>';
			}

			echo '</dl>';
		}

		$html = ob_get_contents();
		ob_end_clean();

		if ( empty( $html ) ) {
			return false;
		}

		$style = 'on' === $atts['style']
			? '<style>
				dl.iiko_kbzhu_group {
				  display: grid;
				  grid-template-columns: max-content auto;
				}
				
				dl.iiko_kbzhu_group dt {
				  grid-column-start: 1;
				  min-width: 280px;
				}
				
				dl.iiko_kbzhu_group dd {
				  grid-column-start: 2;
				}
			</style>'
			: '';

		$wrapper = '%1s<div class="iiko_kbzhu">%2s</div>';
		$html    = sprintf( $wrapper, $style, $html );

		return apply_filters( WC_IIKOCLOUD_PREFIX . 'kbzhu', $html, $product_kbzhu );
	}
}
