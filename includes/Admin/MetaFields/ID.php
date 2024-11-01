<?php

namespace WPWC\iikoCloud\Admin\MetaFields;

defined( 'ABSPATH' ) || exit;

class ID {

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Product categories.
		// {post_type}_add_form_fields
		add_action( 'product_cat_add_form_fields', [ $this, 'product_cat_add_iiko_id' ] );
		// {post_type}_edit_form_fields
		add_action( 'product_cat_edit_form_fields', [ $this, 'product_cat_edit_iiko_id' ] );
		add_action( 'edited_product_cat', [ $this, 'product_cat_save_iiko_id' ] );
		add_action( 'create_product_cat', [ $this, 'product_cat_save_iiko_id' ] );
		// manage_edit-{post_type}_columns
		add_filter( 'manage_edit-product_cat_columns', [ $this, 'product_cat_iiko_id_list_title' ] );
		// manage_{post_type}_custom_column
		add_action( 'manage_product_cat_custom_column', [ $this, 'product_cat_iiko_id_list_column' ], 10, 3 );

		// Products.
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'product_add_iiko_ids' ], 99 );
		add_action( 'woocommerce_process_product_meta', [ $this, 'product_add_iiko_id_save' ] );
		// manage_edit-{post_type}_columns
		add_filter( 'manage_edit-product_columns', [ $this, 'product_iiko_id_list_title' ] );
		// manage_{post_type}_posts_custom_column
		add_action( 'manage_product_posts_custom_column', [ $this, 'product_iiko_id_list_column' ], 10, 2 );
	}

	/**
	 * iiko ID shorter.
	 *
	 * @param  string  $id
	 *
	 * @return string
	 */
	protected function iiko_id_shorter( string $id ): string {

		$num_chars = 9;

		if ( ! empty( $id ) ) {

			$postfix = strlen( $id ) > $num_chars ? '...' : '';
			$id      = mb_substr( $id, 0, $num_chars ) . $postfix;

		} else {
			$id = '–';
		}

		return $id;
	}

	/**
	 * Add iiko group ID field on product category add block.
	 */
	public function product_cat_add_iiko_id() {

		ob_start();
		?>

        <label for="wpwcIikoGroupId">
			<?php
			esc_html_e( 'iiko group ID', 'wc-iikocloud' ); ?>
        </label>

        <input type="text" name="wpwcIikoGroupId" id="wpwcIikoGroupId" value=""/>

        <p class="description">
			<?php
			esc_html_e( 'Uniqe iiko group ID for the product category.', 'wc-iikocloud' ); ?>
        </p>

		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<div class="form-field">%s</div>';

		echo wp_kses( sprintf( $wrapper, $html ), WC_IIKOCLOUD_ALLOWED_HTML );
	}

	/**
	 * Add iiko group ID field on product category edit page.
	 */
	public function product_cat_edit_iiko_id( $term ) {

		ob_start();
		?>

        <th scope="row">
            <label for="wpwcIikoGroupId">
				<?php
				esc_html_e( 'iiko group ID', 'wc-iikocloud' ); ?>
            </label>
        </th>

        <td>
            <input type="text" name="wpwcIikoGroupId" id="wpwcIikoGroupId"
                   value="<?php
			       echo sanitize_key( get_term_meta( $term->term_id, WC_IIKOCLOUD_PREFIX . 'group_id', true ) ); ?>"/>

            <p class="description">
				<?php
				esc_html_e( 'Uniqe iiko group ID for the product category.', 'wc-iikocloud' ); ?>
            </p>
        </td>


		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<tr class="form-field">%s</tr>';

		echo wp_kses( sprintf( $wrapper, $html ), WC_IIKOCLOUD_ALLOWED_HTML );
	}

	/**
	 * Save iiko group ID field on product category add and edit pages.
	 */
	public function product_cat_save_iiko_id( $term_id ) {
		if ( isset( $_POST[ WC_IIKOCLOUD_PREFIX . 'group_id' ] ) ) {
			update_term_meta( $term_id, WC_IIKOCLOUD_PREFIX . 'group_id', sanitize_key( $_POST[ WC_IIKOCLOUD_PREFIX . 'group_id' ] ) );
		}
	}

	/**
	 * Display iiko group ID in product categories list.
	 */
	// Title.
	public function product_cat_iiko_id_list_title( $columns ) {

		$columns[ WC_IIKOCLOUD_PREFIX . 'group_id' ] = ( is_array( $columns ) ) ? esc_html__( 'iiko ID', 'wc-iikocloud' ) : [];

		return $columns;
	}

	// Column.
	public function product_cat_iiko_id_list_column( $columns, $column, $id ) {

		if ( WC_IIKOCLOUD_PREFIX . 'group_id' === $column ) {
			$iiko_group_id = sanitize_key( get_term_meta( $id, WC_IIKOCLOUD_PREFIX . 'group_id', true ) );
			$columns       = esc_attr( $this->iiko_id_shorter( $iiko_group_id ) );
		}

		return $columns;
	}

	/**
	 * Add iiko product ID field on product page.
	 */
	public function product_add_iiko_ids() {

		echo '<div class="options_group">';

		woocommerce_wp_text_input(
			[
				'id'          => WC_IIKOCLOUD_PREFIX . 'product_id',
				'name'        => WC_IIKOCLOUD_PREFIX . 'product_id',
				'label'       => esc_html__( 'iiko ID', 'wc-iikocloud' ),
				'description' => esc_html__( 'Uniqe iiko product ID.', 'wc-iikocloud' ),
				'desc_tip'    => 'true',
				'type'        => 'text',
			]
		);

		woocommerce_wp_text_input(
			[
				'id'    => WC_IIKOCLOUD_PREFIX . 'product_category_id',
				'name'  => WC_IIKOCLOUD_PREFIX . 'product_category_id',
				'label' => esc_html__( 'iiko product category ID', 'wc-iikocloud' ),
				'type'  => 'text',
			]
		);

		echo '</div>';
	}

	/**
	 * Save iiko product ID field on product page.
	 */
	public function product_add_iiko_id_save( $product_id ) {

		if ( $product = wc_get_product( $product_id ) ) {
			$product_iiko_id = isset( $_POST[ WC_IIKOCLOUD_PREFIX . 'product_id' ] ) ? sanitize_key( $_POST[ WC_IIKOCLOUD_PREFIX . 'product_id' ] ) : '';

			$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_id', $product_iiko_id );
			$product->save();
		}
	}

	/**
	 * Display iiko product ID in products list.
	 */
	// Title.
	public function product_iiko_id_list_title( $columns ) {

		$columns[ WC_IIKOCLOUD_PREFIX . 'product_id' ] = ( is_array( $columns ) ) ? esc_html__( 'iiko ID', 'wc-iikocloud' ) : [];

		return $columns;
	}

	// Column.
	public function product_iiko_id_list_column( $column_name, $post_id ) {

		if ( WC_IIKOCLOUD_PREFIX . 'product_id' === $column_name ) {

			if ( $product = wc_get_product( $post_id ) ) {
				echo $this->iiko_id_shorter( esc_attr( $product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_id' ) ) );
			}
		}
	}
}
