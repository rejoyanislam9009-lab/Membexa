<?php
/**
 * Live payment add-on and WooCommerce gateway manager.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a unified Payments hub backed by the real WordPress.org plugin API
 * and WooCommerce's live payment gateway registry.
 */
final class Payment_Hub_Admin {
	/** Register hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_payment_screens' ) );
		add_action( 'admin_post_membexa_install_payment_addon', array( $this, 'install_payment_addon' ) );
		add_action( 'admin_post_membexa_activate_payment_addon', array( $this, 'activate_payment_addon' ) );
	}

	/** Add the Payments hub under Membexa. */
	public function menu() {
		remove_submenu_page( 'membexa', 'membexa-paypal-setup' );
		remove_submenu_page( 'membexa', 'membexa-payment-addons' );
		add_submenu_page(
			'membexa',
			__( 'Payments', 'membexa' ),
			__( 'Payments', 'membexa' ),
			'manage_options',
			'membexa-payments',
			array( $this, 'page' )
		);
	}

	/** Redirect legacy payment screens to the Payments hub. */
	public function redirect_legacy_payment_screens() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation value.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation value.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( in_array( $page, array( 'membexa-paypal-setup', 'membexa-payment-addons' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=membexa-payments&tab=addons' ) );
			exit;
		}

		if ( 'membexa-settings' === $page && 'payments' === $tab ) {
			wp_safe_redirect( admin_url( 'admin.php?page=membexa-payments&tab=addons' ) );
			exit;
		}
	}

	/** Render the Payments hub. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'addons';
		$tabs = array(
			'addons'      => __( 'Add-ons', 'membexa' ),
			'woocommerce' => __( 'WooCommerce Gateways', 'membexa' ),
			'discover'    => __( 'Discover', 'membexa' ),
		);
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'addons';
		?>
		<div class="wrap membexa-admin membexa-payments-hub">
			<h1><?php esc_html_e( 'Membexa Payments', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'A live payment workspace for Membexa add-ons, WooCommerce payment methods, and real WordPress.org gateway plugins.', 'membexa' ); ?></p>
			<?php $this->woocommerce_connection_card(); ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Payment sections', 'membexa' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php $this->render_result_notice(); ?>
			<?php $this->render_tab_safely( $tab ); ?>
		</div>
		<?php
	}

	/** Show the real WooCommerce connection state without loading gateway classes. */
	private function woocommerce_connection_card() {
		$active  = class_exists( 'WooCommerce' );
		$version = defined( 'WC_VERSION' ) ? WC_VERSION : '';
		?>
		<div class="membexa-woo-connection <?php echo $active ? 'is-connected' : 'is-disconnected'; ?>">
			<div>
				<span class="membexa-live-dot" aria-hidden="true"></span>
				<strong><?php echo esc_html( $active ? __( 'WooCommerce connected', 'membexa' ) : __( 'WooCommerce not active', 'membexa' ) ); ?></strong>
				<?php if ( $active && $version ) : ?>
					<span><?php echo esc_html( sprintf( __( 'Version %s', 'membexa' ), $version ) ); ?></span>
				<?php endif; ?>
			</div>
			<p><?php echo esc_html( $active ? __( 'Gateway status is read directly from this site. Installing or activating a gateway plugin refreshes WooCommerce on the next page load.', 'membexa' ) : __( 'Activate WooCommerce to use WooCommerce gateway discovery and live payment-method status.', 'membexa' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a tab and convert unexpected integration errors into an admin notice.
	 *
	 * @param string $tab Active tab.
	 */
	private function render_tab_safely( $tab ) {
		try {
			if ( 'woocommerce' === $tab ) {
				$this->woocommerce_gateways_tab();
			} elseif ( 'discover' === $tab ) {
				$this->discover_tab();
			} else {
				$this->addons_tab();
			}
		} catch ( \Throwable $error ) {
			$this->integration_error_notice();
		}
	}

	/** Render installed payment add-ons without instantiating WooCommerce gateways. */
	private function addons_tab() {
		$plugins             = get_plugins();
		$installed_plugins   = $this->installed_payment_plugins_from_metadata( $plugins );
		$membexa_plugin_rows = $this->membexa_addon_rows( $plugins );

		if ( $installed_plugins ) {
			uasort(
				$installed_plugins,
				static function ( $left, $right ) {
					return strcasecmp( isset( $left['Name'] ) ? $left['Name'] : '', isset( $right['Name'] ) ? $right['Name'] : '' );
				}
			);
		}
		?>
		<div class="membexa-payment-section-heading">
			<div>
				<h2><?php esc_html_e( 'Membexa gateway add-ons', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Standalone Membexa memberships use these provider-specific add-ons.', 'membexa' ); ?></p>
			</div>
			<a class="button" href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><?php esc_html_e( 'Upload add-on ZIP', 'membexa' ); ?></a>
		</div>
		<table class="widefat striped membexa-payment-table">
			<thead><tr><th><?php esc_html_e( 'Add-on', 'membexa' ); ?></th><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $membexa_plugin_rows as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row['label'] ); ?></strong><?php echo $row['version'] ? '<br><small>' . esc_html( 'v' . $row['version'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( $row['gateway_label'] ); ?></td>
					<td><span class="membexa-payment-status <?php echo esc_attr( $row['status_class'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
					<td><?php echo wp_kses_post( $row['action'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="membexa-payment-section-heading">
			<div>
				<h2><?php esc_html_e( 'Installed WooCommerce payment plugins', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Only plugins whose identity clearly describes a WooCommerce payment gateway are listed here. Membexa Core and unrelated checkout utilities are excluded.', 'membexa' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=discover' ) ); ?>"><?php esc_html_e( 'Find gateways', 'membexa' ); ?></a>
		</div>
		<?php if ( empty( $installed_plugins ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No separate WooCommerce payment gateway plugin is currently detected. Use Discover to search WordPress.org or upload a gateway ZIP manually.', 'membexa' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped membexa-payment-table">
				<thead><tr><th><?php esc_html_e( '#', 'membexa' ); ?></th><th><?php esc_html_e( 'Plugin', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
				<tbody>
				<?php $number = 0; ?>
				<?php foreach ( $installed_plugins as $plugin_file => $plugin ) : ?>
					<?php ++$number; ?>
					<tr>
						<td><?php echo esc_html( $number ); ?></td>
						<td>
							<strong><?php echo esc_html( isset( $plugin['Name'] ) ? $plugin['Name'] : $plugin_file ); ?></strong>
							<?php if ( ! empty( $plugin['Version'] ) ) : ?>
								<br><small><?php echo esc_html( 'v' . $plugin['Version'] ); ?></small>
							<?php endif; ?>
						</td>
						<td><span class="membexa-payment-status <?php echo is_plugin_active( $plugin_file ) ? 'is-live' : 'is-installed'; ?>"><?php echo esc_html( is_plugin_active( $plugin_file ) ? __( 'Active', 'membexa' ) : __( 'Installed', 'membexa' ) ); ?></span></td>
						<td><?php echo wp_kses_post( $this->plugin_action_html( $plugin_file ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/** Render WooCommerce gateways with defensive integration boundaries. */
	private function woocommerce_gateways_tab() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC() ) {
			?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is not active. Activate WooCommerce to view its live payment gateways.', 'membexa' ); ?></p></div>
			<?php
			return;
		}

		try {
			$manager  = WC()->payment_gateways();
			$gateways = $manager ? $manager->payment_gateways() : array();
		} catch ( \Throwable $error ) {
			$this->integration_error_notice();
			return;
		}

		$rows = array();
		foreach ( $gateways as $gateway ) {
			$row = $this->gateway_row( $gateway );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		$enabled_count = count(
			array_filter(
				$rows,
				static function ( $row ) {
					return ! empty( $row['enabled'] );
				}
			)
		);
		?>
		<div class="membexa-gateway-summary">
			<div><strong><?php echo esc_html( count( $rows ) ); ?></strong><span><?php esc_html_e( 'Registered', 'membexa' ); ?></span></div>
			<div><strong><?php echo esc_html( $enabled_count ); ?></strong><span><?php esc_html_e( 'Enabled', 'membexa' ); ?></span></div>
			<div><strong><?php echo esc_html( max( 0, count( $rows ) - $enabled_count ) ); ?></strong><span><?php esc_html_e( 'Disabled', 'membexa' ); ?></span></div>
		</div>
		<div class="membexa-payment-section-heading">
			<div>
				<h2><?php esc_html_e( 'Live WooCommerce payment gateways', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'This list comes directly from WooCommerce on this site. A gateway appears here only after its active plugin actually registers it with WooCommerce.', 'membexa' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=discover' ) ); ?>"><?php esc_html_e( 'Add a gateway', 'membexa' ); ?></a>
		</div>
		<?php if ( empty( $rows ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'WooCommerce did not return any registered payment gateways.', 'membexa' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped membexa-payment-table membexa-live-gateways">
				<thead><tr><th><?php esc_html_e( '#', 'membexa' ); ?></th><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'ID', 'membexa' ); ?></th><th><?php esc_html_e( 'Source', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $index => $row ) : ?>
					<tr>
						<td><?php echo esc_html( $index + 1 ); ?></td>
						<td>
							<strong><?php echo esc_html( $row['title'] ); ?></strong>
							<?php if ( $row['description'] ) : ?>
								<br><small><?php echo esc_html( wp_trim_words( $row['description'], 18 ) ); ?></small>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $row['id'] ); ?></code></td>
						<td><?php echo esc_html( $row['source'] ); ?></td>
						<td><span class="membexa-payment-status <?php echo $row['enabled'] ? 'is-live' : 'is-disabled'; ?>"><?php echo esc_html( $row['enabled'] ? __( 'Enabled', 'membexa' ) : __( 'Disabled', 'membexa' ) ); ?></span></td>
						<td><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $row['id'] ) ) ); ?>"><?php esc_html_e( 'Configure', 'membexa' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'Open WooCommerce payment settings', 'membexa' ); ?></a></p>
		<?php
	}

	/**
	 * Build one safe WooCommerce gateway row.
	 *
	 * @param mixed $gateway Gateway object.
	 * @return array|false
	 */
	private function gateway_row( $gateway ) {
		if ( ! is_object( $gateway ) ) {
			return false;
		}

		try {
			$id          = isset( $gateway->id ) ? sanitize_key( (string) $gateway->id ) : '';
			$title       = method_exists( $gateway, 'get_method_title' ) ? (string) $gateway->get_method_title() : $id;
			$description = isset( $gateway->method_description ) ? wp_strip_all_tags( (string) $gateway->method_description ) : '';
			$enabled     = isset( $gateway->enabled ) && 'yes' === (string) $gateway->enabled;
			$source      = $this->gateway_source_name( $gateway );
		} catch ( \Throwable $error ) {
			return false;
		}

		if ( ! $id ) {
			return false;
		}

		return array(
			'id'          => $id,
			'title'       => $title ? $title : $id,
			'description' => $description,
			'enabled'     => $enabled,
			'source'      => $source,
		);
	}

	/** Render a real WordPress.org WooCommerce payment gateway marketplace. */
	private function discover_tab() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Your account does not have permission to install plugins.', 'membexa' ); ?></p></div>
			<?php
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search query.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$query = $search ? trim( 'woocommerce ' . $search ) : 'woocommerce payment gateway';
		$api   = plugins_api(
			'query_plugins',
			array(
				'search'            => $query,
				'page'              => $page,
				'per_page'          => 12,
				'installed_plugins' => array_keys( get_plugins() ),
				'is_ssl'            => is_ssl(),
				'fields'            => array(
					'short_description' => true,
					'tested'            => true,
					'requires'          => true,
					'requires_php'      => true,
					'rating'            => true,
					'ratings'           => false,
					'downloaded'        => false,
					'downloadlink'      => true,
					'last_updated'      => true,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => true,
					'versions'          => false,
					'donate_link'       => false,
					'reviews'           => false,
					'banners'           => false,
					'icons'             => true,
					'active_installs'   => true,
					'contributors'      => false,
				),
			)
		);
		?>
		<div class="membexa-payment-section-heading">
			<div>
				<h2><?php esc_html_e( 'Discover WooCommerce payment gateways', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Results are loaded from the official WordPress.org Plugins API. Install & activate runs through WordPress core, then the gateway is read live from WooCommerce.', 'membexa' ); ?></p>
			</div>
			<span class="membexa-source-badge"><?php esc_html_e( 'Source: WordPress.org', 'membexa' ); ?></span>
		</div>
		<form method="get" class="membexa-payment-search">
			<input type="hidden" name="page" value="membexa-payments">
			<input type="hidden" name="tab" value="discover">
			<label class="screen-reader-text" for="membexa-payment-search"><?php esc_html_e( 'Search payment gateways', 'membexa' ); ?></label>
			<input id="membexa-payment-search" type="search" class="regular-text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search Stripe, PayPal, bKash, SSLCommerz, Paystack…', 'membexa' ); ?>">
			<?php submit_button( __( 'Search gateways', 'membexa' ), 'primary', '', false ); ?>
			<?php if ( $search ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=discover' ) ); ?>"><?php esc_html_e( 'Clear', 'membexa' ); ?></a>
			<?php endif; ?>
		</form>
		<?php if ( is_wp_error( $api ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $api->get_error_message() ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>
		<?php
		$plugins = $this->api_plugins( $api );
		$plugins = array_values(
			array_filter(
				$plugins,
				array( $this, 'is_repository_payment_candidate' )
			)
		);
		if ( empty( $plugins ) ) :
			?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No clearly identified WooCommerce payment gateway plugins were found for this search. Try the provider name, for example Stripe, PayPal, bKash, SSLCommerz, Paystack, or Razorpay.', 'membexa' ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>
		<div class="membexa-plugin-marketplace">
			<?php foreach ( $plugins as $index => $plugin ) : ?>
				<?php $this->repository_plugin_card( $plugin, $index + 1 ); ?>
			<?php endforeach; ?>
		</div>
		<?php $this->discover_pagination( $api, $page, $search ); ?>
		<?php
	}

	/**
	 * Normalize query_plugins results to a plain array.
	 *
	 * @param mixed $api Plugin API response.
	 * @return array
	 */
	private function api_plugins( $api ) {
		if ( is_object( $api ) && isset( $api->plugins ) && is_array( $api->plugins ) ) {
			return $api->plugins;
		}
		if ( is_array( $api ) && isset( $api['plugins'] ) && is_array( $api['plugins'] ) ) {
			return $api['plugins'];
		}
		return array();
	}

	/** Render one WordPress.org plugin result as a real install card. */
	private function repository_plugin_card( $plugin, $number ) {
		$slug          = sanitize_key( (string) $this->plugin_api_value( $plugin, 'slug', '' ) );
		$name          = (string) $this->plugin_api_value( $plugin, 'name', $slug );
		$description   = wp_strip_all_tags( (string) $this->plugin_api_value( $plugin, 'short_description', '' ) );
		$active        = absint( $this->plugin_api_value( $plugin, 'active_installs', 0 ) );
		$rating        = absint( $this->plugin_api_value( $plugin, 'rating', 0 ) );
		$last_updated  = (string) $this->plugin_api_value( $plugin, 'last_updated', '' );
		$tested        = (string) $this->plugin_api_value( $plugin, 'tested', '' );
		$requires_php  = (string) $this->plugin_api_value( $plugin, 'requires_php', '' );
		$installed     = get_plugins();
		$plugin_file   = $this->plugin_file_for_slug( $slug, $installed );
		$status        = $plugin_file ? ( is_plugin_active( $plugin_file ) ? __( 'Active', 'membexa' ) : __( 'Installed', 'membexa' ) ) : __( 'Available', 'membexa' );
		$status_class  = $plugin_file ? ( is_plugin_active( $plugin_file ) ? 'is-live' : 'is-installed' ) : 'is-available';
		$icon          = $this->plugin_icon_url( $plugin );
		$wordpress_url = 'https://wordpress.org/plugins/' . rawurlencode( $slug ) . '/';
		?>
		<article class="membexa-marketplace-card">
			<div class="membexa-marketplace-card-top">
				<div class="membexa-marketplace-number"><?php echo esc_html( sprintf( '%02d', $number ) ); ?></div>
				<?php if ( $icon ) : ?>
					<img class="membexa-marketplace-icon" src="<?php echo esc_url( $icon ); ?>" alt="" loading="lazy">
				<?php else : ?>
					<div class="membexa-marketplace-icon is-fallback"><span class="dashicons dashicons-money-alt"></span></div>
				<?php endif; ?>
				<div class="membexa-marketplace-title">
					<h3><?php echo esc_html( $name ? $name : $slug ); ?></h3>
					<span class="membexa-payment-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span>
				</div>
			</div>
			<p class="membexa-marketplace-description"><?php echo esc_html( $description ? wp_trim_words( $description, 28 ) : __( 'WooCommerce payment gateway plugin from WordPress.org.', 'membexa' ) ); ?></p>
			<div class="membexa-marketplace-meta">
				<span><strong><?php esc_html_e( 'Active installs:', 'membexa' ); ?></strong> <?php echo esc_html( $this->format_active_installs( $active ) ); ?></span>
				<span><strong><?php esc_html_e( 'Rating:', 'membexa' ); ?></strong> <?php echo esc_html( $rating ? $rating . '%' : '—' ); ?></span>
				<?php if ( $tested ) : ?><span><strong><?php esc_html_e( 'Tested:', 'membexa' ); ?></strong> <?php echo esc_html( $tested ); ?></span><?php endif; ?>
				<?php if ( $requires_php ) : ?><span><strong><?php esc_html_e( 'PHP:', 'membexa' ); ?></strong> <?php echo esc_html( $requires_php . '+' ); ?></span><?php endif; ?>
				<?php if ( $last_updated ) : ?><span><strong><?php esc_html_e( 'Updated:', 'membexa' ); ?></strong> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $last_updated ) ); ?></span><?php endif; ?>
			</div>
			<div class="membexa-marketplace-actions">
				<?php echo wp_kses_post( $this->repository_plugin_action_html( $plugin, $plugin_file ) ); ?>
				<a class="button" href="<?php echo esc_url( $wordpress_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WordPress.org details', 'membexa' ); ?></a>
			</div>
		</article>
		<?php
	}

	/** Render search pagination using WordPress.org response metadata when available. */
	private function discover_pagination( $api, $page, $search ) {
		$pages = 1;
		if ( is_object( $api ) && isset( $api->info ) ) {
			$info = is_object( $api->info ) ? get_object_vars( $api->info ) : (array) $api->info;
			if ( isset( $info['pages'] ) ) {
				$pages = max( 1, absint( $info['pages'] ) );
			}
		}
		if ( $pages <= 1 ) {
			return;
		}

		$base = add_query_arg(
			array(
				'page'  => 'membexa-payments',
				'tab'   => 'discover',
				's'     => $search,
				'paged' => '%#%',
			),
			admin_url( 'admin.php' )
		);
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => $base,
					'format'    => '',
					'current'   => $page,
					'total'     => $pages,
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'membexa' ),
					'next_text' => __( 'Next', 'membexa' ),
				)
			)
		);
	}

	/** Install and activate a WordPress.org payment add-on. */
	public function install_payment_addon() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugins.', 'membexa' ) );
		}

		$slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';
		if ( ! $slug ) {
			$this->redirect_with_result( 'invalid-plugin', 'discover' );
		}
		check_admin_referer( 'membexa_install_payment_addon_' . $slug );

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'is_ssl' => is_ssl(),
				'fields' => array(
					'short_description' => true,
					'downloadlink'      => true,
				),
			)
		);
		$download_link = (string) $this->plugin_api_value( $api, 'download_link', '' );
		if ( is_wp_error( $api ) || ! $download_link || ! $this->is_repository_payment_candidate( $api ) ) {
			$this->redirect_with_result( 'not-payment-addon', 'discover' );
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $download_link );
		if ( is_wp_error( $result ) || false === $result ) {
			$this->redirect_with_result( 'install-failed', 'discover' );
		}

		wp_clean_plugins_cache( true );
		$plugin_file = $upgrader->plugin_info();
		if ( ! $plugin_file ) {
			$plugin_file = $this->plugin_file_for_slug( $slug, get_plugins() );
		}
		if ( ! $plugin_file ) {
			$this->redirect_with_result( 'installed-not-active', 'addons' );
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			$this->redirect_with_result( 'installed-not-active', 'addons' );
		}

		$activation = activate_plugin( $plugin_file );
		if ( is_wp_error( $activation ) ) {
			$this->redirect_with_result( 'installed-not-active', 'addons' );
		}
		wp_clean_plugins_cache( true );
		$this->redirect_with_result( 'installed-live', 'woocommerce' );
	}

	/** Activate a detected payment add-on. */
	public function activate_payment_addon() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to activate plugins.', 'membexa' ) );
		}

		$plugin_file = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
		if ( ! $plugin_file || validate_file( $plugin_file ) ) {
			$this->redirect_with_result( 'invalid-plugin', 'addons' );
		}
		check_admin_referer( 'membexa_activate_payment_addon_' . $plugin_file );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin_file ] ) || ! $this->is_payment_plugin_data( $plugin_file, $plugins[ $plugin_file ] ) ) {
			$this->redirect_with_result( 'invalid-plugin', 'addons' );
		}
		$result = activate_plugin( $plugin_file );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_result( 'activate-failed', 'addons' );
		}
		wp_clean_plugins_cache( true );
		$this->redirect_with_result( 'activated-live', 'woocommerce' );
	}

	/** Build known Membexa add-on rows. */
	private function membexa_addon_rows( $plugins ) {
		$catalog = array(
			'membexa-stripe/membexa-stripe.php' => array(
				'gateway' => 'stripe',
				'label'   => __( 'Membexa Stripe Gateway', 'membexa' ),
			),
			'membexa-paypal/membexa-paypal.php' => array(
				'gateway' => 'paypal',
				'label'   => __( 'Membexa PayPal Gateway', 'membexa' ),
			),
			'membexa-bkash/membexa-bkash.php' => array(
				'gateway' => 'bkash',
				'label'   => __( 'Membexa bKash Gateway', 'membexa' ),
			),
		);
		$rows = array();

		foreach ( $catalog as $plugin_file => $item ) {
			$installed = isset( $plugins[ $plugin_file ] );
			$active    = $installed && is_plugin_active( $plugin_file );
			$gateway   = Gateways::is_registered( $item['gateway'] ) ? Gateways::all()[ $item['gateway'] ] : array();
			$enabled   = false;
			if ( $gateway && is_callable( $gateway['enabled_callback'] ) ) {
				try {
					$enabled = (bool) call_user_func( $gateway['enabled_callback'] );
				} catch ( \Throwable $error ) {
					$enabled = false;
				}
			}

			if ( $active && ! empty( $gateway['settings_url'] ) ) {
				$action = '<a class="button button-secondary" href="' . esc_url( $gateway['settings_url'] ) . '">' . esc_html__( 'Open settings', 'membexa' ) . '</a>';
			} elseif ( $installed && ! $active ) {
				$action = $this->activate_button_html( $plugin_file );
			} else {
				$action = '<a class="button" href="' . esc_url( admin_url( 'plugin-install.php?tab=upload' ) ) . '">' . esc_html__( 'Upload ZIP', 'membexa' ) . '</a>';
			}

			if ( $enabled ) {
				$status       = __( 'Configured', 'membexa' );
				$status_class = 'is-live';
			} elseif ( $active ) {
				$status       = __( 'Needs setup', 'membexa' );
				$status_class = 'is-disabled';
			} elseif ( $installed ) {
				$status       = __( 'Installed', 'membexa' );
				$status_class = 'is-installed';
			} else {
				$status       = __( 'Not installed', 'membexa' );
				$status_class = 'is-available';
			}

			$rows[] = array(
				'label'         => $installed && ! empty( $plugins[ $plugin_file ]['Name'] ) ? $plugins[ $plugin_file ]['Name'] : $item['label'],
				'gateway_label' => ! empty( $gateway['label'] ) ? $gateway['label'] : ucfirst( $item['gateway'] ),
				'version'       => $installed && ! empty( $plugins[ $plugin_file ]['Version'] ) ? $plugins[ $plugin_file ]['Version'] : '',
				'status'        => $status,
				'status_class'  => $status_class,
				'action'        => $action,
			);
		}
		return $rows;
	}

	/** Detect installed WooCommerce payment add-ons from metadata only. */
	private function installed_payment_plugins_from_metadata( $plugins ) {
		$result    = array();
		$core_file = plugin_basename( MEMBEXA_FILE );
		foreach ( $plugins as $plugin_file => $plugin ) {
			if ( $core_file === $plugin_file || 'woocommerce/woocommerce.php' === $plugin_file || 0 === strpos( $plugin_file, 'membexa-' ) ) {
				continue;
			}
			if ( $this->is_payment_plugin_data( $plugin_file, $plugin ) ) {
				$result[ $plugin_file ] = $plugin;
			}
		}
		return $result;
	}

	/** Identify the installed plugin that supplies a gateway class. */
	private function gateway_source_name( $gateway ) {
		try {
			$reflection = new \ReflectionClass( $gateway );
			$class_file = $reflection->getFileName();
		} catch ( \Throwable $error ) {
			return __( 'Unknown source', 'membexa' );
		}
		if ( ! $class_file ) {
			return __( 'Unknown source', 'membexa' );
		}

		$relative = plugin_basename( $class_file );
		$root     = strtok( $relative, '/' );
		foreach ( get_plugins() as $plugin_file => $plugin ) {
			if ( strtok( $plugin_file, '/' ) === $root ) {
				return ! empty( $plugin['Name'] ) ? $plugin['Name'] : $plugin_file;
			}
		}
		return __( 'WooCommerce / WordPress', 'membexa' );
	}

	/** Determine whether plugin metadata clearly describes a WooCommerce payment add-on. */
	private function is_payment_plugin_data( $plugin_file, $plugin ) {
		if ( 0 === strpos( $plugin_file, 'membexa-' ) ) {
			return true;
		}

		$identity = strtolower(
			$plugin_file . ' ' .
			( isset( $plugin['Name'] ) ? $plugin['Name'] : '' ) . ' ' .
			( isset( $plugin['TextDomain'] ) ? $plugin['TextDomain'] : '' )
		);
		$context  = strtolower(
			$identity . ' ' .
			( isset( $plugin['Description'] ) ? wp_strip_all_tags( $plugin['Description'] ) : '' ) . ' ' .
			( isset( $plugin['RequiresPlugins'] ) ? $plugin['RequiresPlugins'] : '' )
		);
		return $this->contains_payment_identity_keyword( $identity ) && ( false !== strpos( $context, 'woocommerce' ) || false !== strpos( $context, 'woo commerce' ) );
	}

	/** Check WordPress.org metadata before display or one-click installation. */
	private function is_repository_payment_candidate( $plugin ) {
		$slug        = (string) $this->plugin_api_value( $plugin, 'slug', '' );
		$name        = (string) $this->plugin_api_value( $plugin, 'name', '' );
		$description = wp_strip_all_tags( (string) $this->plugin_api_value( $plugin, 'short_description', '' ) );
		$requires    = $this->plugin_api_value( $plugin, 'requires_plugins', array() );
		if ( is_array( $requires ) ) {
			$requires = implode( ' ', $requires );
		}
		$identity    = strtolower( $slug . ' ' . $name );
		$context     = strtolower( $identity . ' ' . $description . ' ' . (string) $requires );
		$woocommerce = false !== strpos( $context, 'woocommerce' ) || false !== strpos( $context, 'woo commerce' ) || false !== strpos( $context, 'woopayments' );
		return $woocommerce && $this->contains_payment_identity_keyword( $identity );
	}

	/** Check whether a plugin identity looks like a payment provider/gateway. */
	private function contains_payment_identity_keyword( $identity ) {
		$terms = array(
			'payment',
			'gateway',
			'checkout',
			'stripe',
			'paypal',
			'paystack',
			'razorpay',
			'mollie',
			'braintree',
			'authorize',
			'bkash',
			'nagad',
			'sslcommerz',
			'cashfree',
			'flutterwave',
			'mercado pago',
			'mercadopago',
			'payu',
			'adyen',
			'worldpay',
			'klarna',
			'afterpay',
			'square',
			'woopayments',
			'2checkout',
			'skrill',
			'payoneer',
			'payfast',
			'instamojo',
			'moneris',
			'eway',
		);
		foreach ( $terms as $term ) {
			if ( false !== strpos( $identity, $term ) ) {
				return true;
			}
		}
		return false;
	}

	/** Get one property from either an object or array API response. */
	private function plugin_api_value( $plugin, $key, $default = '' ) {
		if ( is_object( $plugin ) && isset( $plugin->{$key} ) ) {
			return $plugin->{$key};
		}
		if ( is_array( $plugin ) && isset( $plugin[ $key ] ) ) {
			return $plugin[ $key ];
		}
		return $default;
	}

	/** Return the best WordPress.org icon URL for a plugin. */
	private function plugin_icon_url( $plugin ) {
		$icons = $this->plugin_api_value( $plugin, 'icons', array() );
		if ( is_object( $icons ) ) {
			$icons = get_object_vars( $icons );
		}
		if ( ! is_array( $icons ) ) {
			return '';
		}
		foreach ( array( '2x', '1x', 'svg', 'default' ) as $size ) {
			if ( ! empty( $icons[ $size ] ) ) {
				return esc_url_raw( $icons[ $size ] );
			}
		}
		return '';
	}

	/** Format a WordPress.org active-install count. */
	private function format_active_installs( $count ) {
		$count = absint( $count );
		if ( $count >= 1000000 ) {
			return number_format_i18n( floor( $count / 100000 ) / 10, 1 ) . 'M+';
		}
		if ( $count >= 1000 ) {
			return number_format_i18n( floor( $count / 100 ) / 10, 1 ) . 'K+';
		}
		return $count ? number_format_i18n( $count ) . '+' : '—';
	}

	/** Find an installed plugin main file by slug. */
	private function plugin_file_for_slug( $slug, $plugins ) {
		$slug = sanitize_key( $slug );
		foreach ( $plugins as $plugin_file => $plugin ) {
			$directory   = dirname( $plugin_file );
			$text_domain = isset( $plugin['TextDomain'] ) ? sanitize_key( $plugin['TextDomain'] ) : '';
			if ( $slug === $directory || $slug === $text_domain || 0 === strpos( $plugin_file, $slug . '/' ) ) {
				return $plugin_file;
			}
		}
		return '';
	}

	/** Build an installed-plugin action button. */
	private function plugin_action_html( $plugin_file ) {
		if ( is_plugin_active( $plugin_file ) ) {
			return '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=membexa-payments&tab=woocommerce' ) ) . '">' . esc_html__( 'View live gateways', 'membexa' ) . '</a>';
		}
		return $this->activate_button_html( $plugin_file );
	}

	/** Build a protected activation button. */
	private function activate_button_html( $plugin_file ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return esc_html__( 'Activation permission required', 'membexa' );
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'membexa_activate_payment_addon',
					'plugin' => $plugin_file,
				),
				admin_url( 'admin-post.php' )
			),
			'membexa_activate_payment_addon_' . $plugin_file
		);
		return '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Activate in WordPress', 'membexa' ) . '</a>';
	}

	/** Build install/activate action for a WordPress.org search result. */
	private function repository_plugin_action_html( $plugin, $plugin_file ) {
		if ( $plugin_file ) {
			return $this->plugin_action_html( $plugin_file );
		}
		$slug = sanitize_key( (string) $this->plugin_api_value( $plugin, 'slug', '' ) );
		if ( ! $slug ) {
			return esc_html__( 'Unavailable', 'membexa' );
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'membexa_install_payment_addon',
					'slug'   => $slug,
				),
				admin_url( 'admin-post.php' )
			),
			'membexa_install_payment_addon_' . $slug
		);
		return '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Install & activate', 'membexa' ) . '</a>';
	}

	/** Render an integration-safe error notice. */
	private function integration_error_notice() {
		?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'A payment integration could not be loaded safely. Membexa prevented it from crashing this page. Check the related gateway plugin, update it, or temporarily deactivate it, then reload Payments.', 'membexa' ); ?></p></div>
		<?php
	}

	/** Render result notice after an admin action. */
	private function render_result_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result after protected action.
		$notice = isset( $_GET['membexa_notice'] ) ? sanitize_key( wp_unslash( $_GET['membexa_notice'] ) ) : '';
		$messages = array(
			'installed-live'       => array( 'success', __( 'Payment plugin installed and activated. WooCommerce has been refreshed below; configure the new gateway before enabling it for customers.', 'membexa' ) ),
			'activated-live'       => array( 'success', __( 'Payment plugin activated. Its registered WooCommerce gateway methods are shown below.', 'membexa' ) ),
			'installed-not-active' => array( 'warning', __( 'Payment plugin was installed, but WordPress could not activate it automatically. Open Add-ons and activate it manually.', 'membexa' ) ),
			'invalid-plugin'       => array( 'error', __( 'The requested plugin could not be validated.', 'membexa' ) ),
			'not-payment-addon'    => array( 'error', __( 'WordPress.org did not return a clearly identified WooCommerce payment gateway package.', 'membexa' ) ),
			'install-failed'       => array( 'error', __( 'WordPress could not install the payment plugin.', 'membexa' ) ),
			'activate-failed'      => array( 'error', __( 'WordPress could not activate the payment plugin.', 'membexa' ) ),
		);
		if ( ! $notice || ! isset( $messages[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $messages[ $notice ][0] ); ?> inline"><p><?php echo esc_html( $messages[ $notice ][1] ); ?></p></div>
		<?php
	}

	/** Redirect to the Payments hub with a result code. */
	private function redirect_with_result( $notice, $tab ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'membexa-payments',
					'tab'            => sanitize_key( $tab ),
					'membexa_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
