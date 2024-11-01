<?php

namespace WPWC\iikoCloud\Traits;

defined( 'ABSPATH' ) || exit;

trait PageElementsTrait {

	/**
	 * Print preloader.
	 *
	 * @return void
	 */
	protected static function preloader(): void {

		$preloader = '<div id="wpwcPreloader" class="wpwc_preloader hidden">';
		$preloader .= '<img src="' . esc_url( plugin_dir_url( WC_IIKOCLOUD_FILE ) . 'assets/img/preloader.svg' ) . '" alt="Preloader">';
		$preloader .= '</div>';

		echo $preloader;
	}

	/**
	 * Print plugin header.
	 *
	 * @return void
	 */
	protected static function plugin_header(): void {

		$header = sprintf( esc_html__( '%1sIikoCloud Control Panel v%2s%3s', 'wc-iikocloud' ),
			'<h1>',
			WC_IIKOCLOUD_VERSION,
			'</h1>'
		);

		echo $header;
	}

	/**
	 * Print plugin header.
	 *
	 * @param  bool  $close_button  Show close button
	 *
	 * @return mixed|null
	 */
	protected static function plugin_subheader( bool $close_button = true ) {

		$features = [
			0  => esc_html__( '%sExporting orders to the kitchen from tables%s', 'wc-iikocloud' ),
			1  => esc_html__( '%sUpdating order statuses from iiko on the website%s', 'wc-iikocloud' ),
			2  => esc_html__( '%sAdvanced import of dishes%s - with sizes and group modifiers', 'wc-iikocloud' ),
			3  => esc_html__( '%sSimultaneous export of delivery orders and kitchen orders%s', 'wc-iikocloud' ),
			4  => esc_html__( '%sSupport for a bonus system%s - a fixed percentage discount on an order', 'wc-iikocloud' ),
			5  => esc_html__( '%sLinking WooCommerce payment methods used on the site to iiko payment types%s', 'wc-iikocloud' ),
			6  => esc_html__( '%sAuto-import of items%s according to schedule after a specified period of time', 'wc-iikocloud' ),
			7  => esc_html__( '%sUpdating iiko stop lists%s - instant removal from sale of goods added to the iiko stop list', 'wc-iikocloud' ),
			8  => esc_html__( '%sFlexible customization of the ordering page%s - additional fields: guests, time, entrance, floor and many others', 'wc-iikocloud' ),
			9  => esc_html__( '%sDelivery zones on Yandex.Map%s when placing an order. Linking Shipping Zones to WooCommerce Shipping Methods', 'wc-iikocloud' ),
			10 => esc_html__( '%sTwo new delivery methods: pickup and free delivery%s - for manually selecting the terminal to which to send the order', 'wc-iikocloud' ),
			11 => esc_html__( '%sAccess to the latest updates for the regular and premium versions of the plugin%s (the free version on WordPress.org is updated once a year)', 'wc-iikocloud' ),
		];

		ob_start();
		?>

        <div class="wpwc_premium--container">

			<?php if ( $close_button ): ?>
                <span id="wpwcPremiumClose" class="wpwc_premium-button__close"></span>
			<?php endif; ?>

            <h2 class="wpwc_premium--header"><?php esc_html_e( 'Renew iikoCloud for WooCommerce Premium', 'wc-iikocloud' ); ?></h2>

            <ul class="wpwc_premium--motivation">

				<?php foreach ( $features as $feature ): ?>
                    <li>
                        <div class="wpwc_premium--argument">
							<?php printf( $feature, '<strong>', '</strong>' ); ?>
                        </div>
                    </li>
				<?php endforeach; ?>

            </ul>

            <p>
                <a class="wpwc_premium-button" href="https://wpwc.ru/" target="_blank"><?php esc_html_e( 'Get iikoCloud for WooCommerce Premium', 'wc-iikocloud' ); ?> <span class="wpwc_premium-button__caret"></span></a>
            </p>
        </div>

		<?php

		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<div id="wpwcPremium" class="wpwc_premium">%s</div>';

		return apply_filters( WC_IIKOCLOUD_PREFIX . 'plugin_subheader', sprintf( $wrapper, $html ) );
	}

	/**
	 * Print links on the main plugin page.
	 *
	 * @return void
	 */
	protected static function admin_links(): void {

		ob_start();

		printf( esc_html__( '%sSettings%s', 'wc-iikocloud' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings' ) ) . '" target="_blank">',
			'</a>'
		);

		printf( esc_html__( '%sLogs%s', 'wc-iikocloud' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '" target="_blank">',
			'</a>'
		);

		printf( esc_html__( '%sPlugin Website%s', 'wc-iikocloud' ),
			'<a href="https://' . WC_IIKOCLOUD_DOMAIN . '" target="_blank">',
			'</a>'
		);

		printf( esc_html__( '%sDocumentation%s', 'wc-iikocloud' ),
			'<a href="https://docs.' . WC_IIKOCLOUD_DOMAIN . '" target="_blank">',
			'</a>'
		);

		printf( esc_html__( '%sSupport%s', 'wc-iikocloud' ),
			'<a href="mailto:hi@' . WC_IIKOCLOUD_DOMAIN . '?subject=iikoCloud plugin v.' . WC_IIKOCLOUD_VERSION . '">',
			'</a>'
		);

		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<div class="wpwc_links">%s</div>';

		echo sprintf( $wrapper, $html );
	}

	/**
	 * Print button with type 'submit'.
	 *
	 * @param  string  $key  Unique key is used for ID, class and 'name' attribute.
	 * @param  string  $type  Button type is used for the button name prefix.
	 * @param  string  $value  Value is used for the button name.
	 * @param  string  $class  Button class.
	 * @param  bool  $disabled  Attribute 'disabled'. Default true.
	 *
	 * @return void
	 */
	protected static function print_button(
		string $key,
		string $type,
		string $value,
		string $class = ' button-primary',
		bool $disabled = true
	): void {

		switch ( $type ) {
			case 'get':
				$name_prefix = esc_html__( 'Get', 'wc-iikocloud' );
				break;
			case 'save':
				$name_prefix = esc_html__( 'Save', 'wc-iikocloud' );
				break;
			case 'import':
				$name_prefix = esc_html_x( 'Import', 'Action', 'wc-iikocloud' );
				break;
			case 'update':
				$name_prefix = esc_html__( 'Update', 'wc-iikocloud' );
				break;
			case 'delete':
				$name_prefix = esc_html__( 'Delete', 'wc-iikocloud' );
				break;
			default:
				$name_prefix = '';
		}

		$class = $class . ' iiko_' . $key;
		$name  = $type . '_iiko_' . $key;
		$value = $name_prefix . ' ' . $value;

		ob_start();
		?>

        <input type="submit"
               name="<?php echo esc_attr( $name ); ?>"
               class="button<?php echo esc_attr( $class ); ?> wpwc_form_submit"
               value="<?php echo esc_attr( $value ); ?>"
			<?php echo $disabled ? 'disabled="disabled"' : ''; ?>
        />

		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<p>%s</p>';

		echo sprintf( $wrapper, $html );
	}

	/**
	 * Print input type 'select' without options.
	 *
	 * @param  string  $key  Unique key is used for ID, class and 'name' attribute.
	 * @param  string  $label  Label for the select.
	 * @param  bool  $multiple  Attribute 'multiple'. Default false.
	 * @param  int  $size  Attribute 'size'. Default 0.
	 *
	 * @return void
	 */
	protected static function print_select(
		string $key,
		string $label,
		bool $multiple = false,
		int $size = 0
	): void {

		$id    = 'wpwcIiko' . ucfirst( $key );
		$class = 'iiko_' . $key;
		$label = $label . ':';

		ob_start();
		?>

        <label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
        </label>
        <select
                name="<?php echo esc_attr( $class ); ?>"
                class="<?php echo esc_attr( $class ); ?>"
                id="<?php echo esc_attr( $id ); ?>"
			<?php echo $multiple ? 'multiple="multiple"' : ''; ?>
			<?php echo ! empty( absint( $size ) ) ? 'size="' . absint( $size ) . '"' : ''; ?>
        >
        </select>

		<?php
		if ( $multiple ) {
			echo '<small>';
			printf( esc_html__( 'Use %smouse or CTRL + click%s to select several values in the list.', 'wc-iikocloud' ),
				'<b>',
				'</b>'
			);
			echo '</small>';
		}
		?>

		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<p>%s</p>';

		echo sprintf( $wrapper, $html );
	}

	/**
	 * Print nomenclature info.
	 *
	 * @return void
	 */
	protected static function print_nomenclature_info(): void {

		ob_start();
		?>

        <h3><?php esc_html_e( 'Nomenclature general info:', 'wc-iikocloud' ); ?></h3>

        <h4><?php esc_html_e( 'Product categories', 'wc-iikocloud' ); ?></h4>

        <p>
            <span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Groups', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureGroups"></span>
        </p>
        <p>
            <span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Product categories', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureProductCategories"></span>
        </p>

        <h4><?php esc_html_e( 'Products', 'wc-iikocloud' ); ?></h4>

        <p>
			<span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Dishes', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureDishes"></span>
        </p>
        <p>
			<span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Goods', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureGoods"></span>
        </p>
        <p>
			<span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Services', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureServices"></span>
        </p>
        <p>
			<span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Modifiers', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureModifiers"></span>
        </p>
        <p>
            <span class="wpwc_nomenclature_name">
                <?php
                esc_html_e( 'Sizes', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureSizes"></span>
        </p>

        <h4>
			<?php
			esc_html_e( 'Revision:', 'wc-iikocloud' ); ?>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureRevision"></span>
        </h4>

		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<div id="wpwcIikoNomenclatureInfoWrap" class="wpwc_nomenclature_info_wrap hidden">%s</div>';

		echo sprintf( $wrapper, $html );
	}

	/**
	 * Print imported nomenclature info.
	 *
	 * @return void
	 */
	protected static function print_imported_nomenclature_info(): void {

		ob_start();
		?>

        <h3>
			<?php
			esc_html_e( 'Imported nomenclature info:', 'wc-iikocloud' ); ?>
        </h3>

        <p>
            <span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Groups', 'wc-iikocloud' ) . ': '; ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureImportedGroups"></span>
        </p>

        <p>
            <span class="wpwc_nomenclature_name">
                <?php esc_html_e( 'Products', 'wc-iikocloud' ); ?>
            </span>
            <span class="wpwc_nomenclature_value" id="wpwcIikoNomenclatureImportedProducts"></span>
        </p>

		<?php
		$html = ob_get_contents();
		ob_end_clean();

		$wrapper = '<div id="wpwcIikoNomenclatureImportedWrap" class="hidden">%s</div>';

		echo sprintf( $wrapper, $html );
	}
}