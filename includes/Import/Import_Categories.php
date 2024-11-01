<?php

namespace WPWC\iikoCloud\Import;

defined( 'ABSPATH' ) || exit;

use WPSEO_Taxonomy_Meta;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ImportTrait;

class Import_Categories {

	use ImportTrait;

	/**
	 * Import product category.
	 *
	 * @param  array  $group_data  Checked iiko group data.
	 *
	 * @return false|array False if it cannot import the product category or array with keys 'iiko_id' and 'term_id' otherwise.
	 */
	public static function import_product_category( array $group_data ) {

		// Get term ID and the term taxonomy ID if the product category already exists and 0 or null otherwise.
		$term = term_exists( $group_data['name'], 'product_cat' );

		// Update the product category if it exists.
		if ( $term !== 0 && $term !== null ) {

			$product_category_id = absint( $term['term_id'] );

			$updated_product_cat = wp_update_term(
				$product_category_id,
				'product_cat',
				[
					'name' => $group_data['name'],
					'description' => self::get_product_category_desc( $group_data['desc'], $product_category_id ),
				]
			);

			// Check the product category updating.
			$is_import_successful = self::check_import(
				$group_data['name'],
				$product_category_id,
				'update',
				'Product category',
				$updated_product_cat
			);

			// Insert the product category if it doesn't exist.
		} else {

			$inserted_product_cat = wp_insert_term(
				$group_data['name'],
				'product_cat',
				[
					'description' => self::get_product_category_desc( $group_data['desc'] ),
					'parent'      => 0,
				]
			);

			$product_category_id = ! is_wp_error( $inserted_product_cat ) ? absint( $inserted_product_cat['term_id'] ) : 0;

			// Check the product category insertion.
			$is_import_successful = self::check_import(
				$group_data['name'],
				$product_category_id,
				'insert',
				'Product category',
				$inserted_product_cat
			);
		}

		if ( false === $is_import_successful ) {
			return false;
		}

		update_term_meta( $product_category_id, WC_IIKOCLOUD_PREFIX . 'group_id', $group_data['id'] );
		self::import_product_category_image( $product_category_id, $group_data['name'], $group_data['thumb_urls'] );
		self::import_product_category_seo_data( $product_category_id, $group_data['seo_title'], $group_data['seo_desc'] );

		return [
			'iiko_id' => $group_data['id'],
			'term_id' => $product_category_id,
		];
	}

	/**
	 * Get the current category description.
	 *
	 * @param  string  $desc  New category description.
	 * @param  int|null  $id  Category ID. Default is null.
	 *
	 * @return string Description if import option is switched on. An empty string if it is a category insertion,
	 *  or previous description if it is a category updating.
	 */
	private static function get_product_category_desc(
		string $desc,
		?int $id = null
	): string {

		// If import of descriptions is turned ON.
		if ( isset( self::get_import_settings()['descriptions'] ) && 'yes' === self::get_import_settings()['descriptions'] ) {
			return $desc;
		}

		// If a category insertion.
		if ( null === $id ) {
			return '';
		}

		// If a category updating.
		$product_cat = get_term( $id, 'product_cat' );

		return $product_cat->description;
	}

	/**
	 * Import product category image.
	 *
	 * @param  int  $product_category_id  Product category ID
	 * @param  string  $thumb_desc
	 * @param $thumb_urls
	 *
	 * @return true|false|void True on successful, false on failure.
	 */
	private
	static function import_product_category_image(
		int $product_category_id,
		string $thumb_desc,
		$thumb_urls
	) {

		if ( ! isset( self::get_import_settings()['images'] ) || 'yes' !== self::get_import_settings()['images'] ) {
			return;
		}

		if ( ! is_array( $thumb_urls ) || empty( $thumb_urls ) ) {
			$message = sprintf( esc_html__( 'There is no image for product category %s', 'wc-iikocloud' ), $thumb_desc );

			Logs::add_notice( $message );
			Logs::add_wc_log( $message, 'import', 'notice' );

			return false;
		}

		// Delete old attachments.
		if ( isset( self::get_import_settings()['delete_product_cat_imgs'] ) && 'yes' === self::get_import_settings()['delete_product_cat_imgs'] ) {
			self::delete_product_category_image( $product_category_id );
		}

		$first_thumb = esc_url( array_shift( $thumb_urls ) );

		// Load media_sideload_image function and dependencies in front for CRON jobs.
		if ( ! is_admin() ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		// Downloads the image from the specified URL, saves it as an attachment.
		$thumb_id = media_sideload_image( $first_thumb, $product_category_id, $thumb_desc, 'id' );

		// Return WP_Error when fail to import product thumbnail.
		if ( is_wp_error( $thumb_id ) ) {
			Logs::log_wp_error( $thumb_id, "Error while import product category attachment for '$thumb_desc'" );

			return false;

		} else {

			$related_thumb = add_term_meta( $product_category_id, 'thumbnail_id', $thumb_id, true );

			$error_message = sprintf( esc_html__( 'Error while relate product category thumbnail for %s', 'wc-iikocloud' ), $thumb_desc );

			if ( is_wp_error( $related_thumb ) ) {
				Logs::log_wp_error( $related_thumb, $error_message );

				return false;
			}

			if ( false === $related_thumb ) {
				Logs::add_error( $error_message );
				Logs::add_wc_log( $error_message, 'import', 'error' );

				return false;
			}
		}

		return true;
	}

	/**
	 * Delete product category image.
	 *
	 * @param  int  $product_category_id
	 *
	 * @return void
	 */
	private static function delete_product_category_image( int $product_category_id ): void {

		$thumbnail_id = get_term_meta( $product_category_id, 'thumbnail_id', true );

		if ( empty( $thumbnail_id ) ) {
			return;
		}

		delete_term_meta( $product_category_id, 'thumbnail_id', $thumbnail_id );
		wp_delete_attachment( $thumbnail_id, true );
	}

	/**
	 * Import product category SEO data.
	 *
	 * Work only with Yoast SEO plugin.
	 * $seo_title and $seo_desc should be already sanitized.
	 *
	 * @param  int  $product_category_id  Object ID (product category, product)
	 * @param  string|null  $seo_title
	 * @param  string|null  $seo_desc
	 */
	private static function import_product_category_seo_data(
		int $product_category_id,
		?string $seo_title,
		?string $seo_desc
	) {

		if (
			! is_plugin_active( 'wordpress-seo/wp-seo.php' )
			|| ! isset( self::get_import_settings()['seo'] )
			|| 'yes' !== self::get_import_settings()['seo']
		) {
			return;
		}

		if ( ! empty( $seo_title ) ) {
			$meta_values['wpseo_title'] = $seo_title;
		}

		if ( ! empty( $seo_desc ) ) {
			$meta_values['wpseo_desc'] = $seo_desc;
		}

		if ( ! empty( $meta_values ) && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			WPSEO_Taxonomy_Meta::set_values( $product_category_id, 'product_cat', $meta_values );
		}
	}
}