<?php
/**
 * Payment add-on and WooCommerce gateway manager.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a unified Payments hub for Membexa and WooCommerce gateway add-ons.
 */
final class Payment_Addons_Admin {
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
		add_submenu_page(
			'membexa',
			__( 'Payments', 'membexa' ),
			__( 'Payments', 'membexa' ),
			'manage_options',
			'membexa-payments',
			array( $this, 'page' )
		);
	}

	/** Redirect legacy payment screens to the new Payments hub. */
	public function redirect_legacy_payment_screens() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation-only redirect.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation-only redirect.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( in_array( $page, array( 'membexa-paypal-setup', 'membexa-payment-addons' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=membexa-payments&tab=addons' ) );
			exit;
		}

		if ( 'membexa-settings' === $page && 'payments' === $tab ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect from PayPal verification result.
			$paypal_verified = isset( $_GET['membexa_paypal_verified'] );
			$target          = $paypal_verified && Gateways::is_registered( 'paypal' )
				? admin_url( 'admin.php?page=membexa-paypal-gateway' )
				: admin_url( 'admin.php?page=membexa-payments&tab=addons' );
			wp_safe_redirect( $target );
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
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Membexa Payments', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Manage Membexa payment add-ons and WooCommerce payment gateways from one place. Add-ons installed manually are detected automatically after WordPress loads them.', 'membexa' ); ?></p>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Payment sections', 'membexa' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php $this->render_result_notice(); ?>
			<?php
			if ( 'woocommerce' === $tab ) {
				$this->woocommerce_gateways_tab();
			} elseif ( 'discover' === $tab ) {
				$this->discover_tab();
			} else {
				$this->addons_tab();
			}
			?>
		</div>
		<?php
	}

	/** Render installed payment add-ons. */
	private function addons_tab() {
		$gateways            = Gateways::all();
		$installed_plugins   = $this->installed_payment_plugins();
		$membexa_plugin_rows = $this->membexa_addon_rows();
		?>
		<h2><?php esc_html_e( 'Membexa gateway add-ons', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Standalone Membexa memberships use these add-ons. If an add-on is uploaded through Plugins > Add New, it is detected here automatically.', 'membexa' ); ?></p>
		<table class="widefat striped membexa-payment-table">
			<thead><tr><th><?php esc_html_e( 'Add-on', 'membexa' ); ?></th><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $membexa_plugin_rows as $row ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $row['label'] ); ?></strong>
						<?php if ( $row['version'] ) : ?>
							<br><small><?php echo esc_html( 'v' . $row['version'] ); ?></small>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row['gateway_label'] ); ?></td>
					<td><?php echo esc_html( $row['status'] ); ?></td>
					<td><?php echo wp_kses_post( $row['action'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Installed WooCommerce payment add-ons', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Payment plugins installed from this screen or installed manually are listed here when they are detected as WooCommerce payment add-ons.', 'membexa' ); ?></p>
		<?php if ( empty( $installed_plugins ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No separate WooCommerce payment add-on plugin is currently detected. Open Discover to install one from WordPress.org, or upload a gateway plugin manually.', 'membexa' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped membexa-payment-table">
				<thead><tr><th><?php esc_html_e( 'Plugin', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $installed_plugins as $plugin_file => $plugin ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $plugin['Name'] ); ?></strong>
							<?php if ( ! empty( $plugin['Version'] ) ) : ?>
								<br><small><?php echo esc_html( 'v' . $plugin['Version'] ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( is_plugin_active( $plugin_file ) ? __( 'Active', 'membexa' ) : __( 'Installed', 'membexa' ) ); ?></td>
						<td><?php echo wp_kses_post( $this->plugin_action_html( $plugin_file, $plugin ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( empty( $gateways ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No standalone Membexa gateway is active. Free memberships and WooCommerce-linked memberships can still work normally.', 'membexa' ); ?></p></div>
		<?php endif; ?>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=discover' ) ); ?>"><?php esc_html_e( 'Discover payment add-ons', 'membexa' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><?php esc_html_e( 'Upload add-on ZIP', 'membexa' ); ?></a></p>
		<?php
	}

	/** Render all WooCommerce payment gateway methods currently registered on the site. */
	private function woocommerce_gateways_tab() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) {
			?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is not active. Activate WooCommerce to see its registered payment gateways here.', 'membexa' ); ?></p></div>
			<?php
			return;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		?>
		<h2><?php esc_html_e( 'WooCommerce payment gateways', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Every gateway currently registered with WooCommerce is shown here, including built-in methods and gateways added by active plugins.', 'membexa' ); ?></p>
		<?php if ( empty( $gateways ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'WooCommerce did not register any payment gateways.', 'membexa' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped membexa-payment-table">
				<thead><tr><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'ID', 'membexa' ); ?></th><th><?php esc_html_e( 'Source', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $gateways as $gateway ) : ?>
					<?php
					$title  = method_exists( $gateway, 'get_method_title' ) ? $gateway->get_method_title() : ( isset( $gateway->method_title ) ? $gateway->method_title : $gateway->id );
					$source = $this->gateway_source_name( $gateway );
					?>
					<tr>
						<td><strong><?php echo esc_html( $title ); ?></strong></td>
						<td><code><?php echo esc_html( $gateway->id ); ?></code></td>
						<td><?php echo esc_html( $source ); ?></td>
						<td><?php echo esc_html( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ? __( 'Enabled', 'membexa' ) : __( 'Disabled', 'membexa' ) ); ?></td>
						<td><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $gateway->id ) ) ); ?>"><?php esc_html_e( 'Configure', 'membexa' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'Open WooCommerce payment settings', 'membexa' ); ?></a></p>
		<?php
	}

	/** Render WordPress.org payment gateway discovery and one-click installation. */
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
		$query = $search ? 'woocommerce payment gateway ' . $search : 'woocommerce payment gateway';
		$api   = plugins_api(
			'query_plugins',
			array(
				'search'   => $query,
				'page'     => $page,
				'per_page' => 12,
				'fields'   => array(
					'short_description' => true,
					'icons'             => false,
					'sections'          => false,
					'screenshots'       => false,
					'active_installs'   => true,
					'last_updated'      => true,
				),
			)
		);
		?>
		<h2><?php esc_html_e( 'Discover WooCommerce payment add-ons', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Search WordPress.org from inside Membexa, then install and activate compatible WooCommerce payment gateway plugins without leaving this screen. WordPress still performs the normal plugin installation and permission checks.', 'membexa' ); ?></p>
		<form method="get" class="membexa-payment-search">
			<input type="hidden" name="page" value="membexa-payments">
			<input type="hidden" name="tab" value="discover">
			<label class="screen-reader-text" for="membexa-payment-search"><?php esc_html_e( 'Search payment gateways', 'membexa' ); ?></label>
			<input id="membexa-payment-search" type="search" class="regular-text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Stripe, PayPal, local bank, country…', 'membexa' ); ?>">
			<?php submit_button( __( 'Search gateways', 'membexa' ), 'secondary', '', false ); ?>
		</form>
		<?php
		if ( is_wp_error( $api ) ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $api->get_error_message() ); ?></p></div>
			<?php
			return;
		}

		$plugins = get_plugins();
		if ( empty( $api->plugins ) ) {
			?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No WordPress.org gateway plugins matched this search.', 'membexa' ); ?></p></div>
			<?php
			return;
		}
		?>
		<table class="widefat striped membexa-payment-table">
			<thead><tr><th><?php esc_html_e( 'Plugin', 'membexa' ); ?></th><th><?php esc_html_e( 'Description', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $api->plugins as $plugin ) : ?>
				<?php
				$plugin_file = $this->plugin_file_for_slug( $plugin->slug, $plugins );
				$status      = $plugin_file ? ( is_plugin_active( $plugin_file ) ? __( 'Active', 'membexa' ) : __( 'Installed', 'membexa' ) ) : __( 'Available', 'membexa' );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $plugin->name ); ?></strong>
						<?php if ( ! empty( $plugin->version ) ) : ?>
							<br><small><?php echo esc_html( 'v' . $plugin->version ); ?></small>
						<?php endif; ?>
					</td>
					<td><?php echo wp_kses_post( wp_trim_words( $plugin->short_description, 24 ) ); ?></td>
					<td><?php echo esc_html( $status ); ?></td>
					<td><?php echo wp_kses_post( $this->repository_plugin_action_html( $plugin, $plugin_file ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php $this->discover_pagination( $api, $page, $search ); ?>
		<?php
	}

	/** Install and activate a WordPress.org WooCommerce payment add-on. */
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
				'fields' => array(
					'sections'    => false,
					'screenshots' => false,
				),
			)
		);
		if ( is_wp_error( $api ) || empty( $api->download_link ) || ! $this->is_repository_payment_candidate( $api ) ) {
			$this->redirect_with_result( 'not-payment-addon', 'discover' );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) || false === $result ) {
			$this->redirect_with_result( 'install-failed', 'discover' );
		}

		$plugin_file = $upgrader->plugin_info();
		if ( ! $plugin_file ) {
			$plugin_file = $this->plugin_file_for_slug( $slug, get_plugins() );
		}

		if ( $plugin_file && current_user_can( 'activate_plugins' ) ) {
			$activation = activate_plugin( $plugin_file );
			if ( is_wp_error( $activation ) ) {
				$this->redirect_with_result( 'installed-not-active', 'addons' );
			}
		}

		$this->redirect_with_result( 'installed', 'addons' );
	}

	/** Activate a manually installed payment add-on. */
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
		$this->redirect_with_result( 'activated', 'addons' );
	}

	/** Build rows for the known Membexa gateway add-ons, including manually installed inactive copies. */
	private function membexa_addon_rows() {
		$plugins = get_plugins();
		$catalog = array(
			'membexa-stripe/membexa-stripe.php' => array(
				'gateway' => 'stripe',
				'label'   => __( 'Membexa Stripe Gateway', 'membexa' ),
			),
			'membexa-paypal/membexa-paypal.php' => array(
				'gateway' => 'paypal',
				'label'   => __( 'Membexa PayPal Gateway', 'membexa' ),
			),
			'membexa-bkash/membexa-bkash.php'   => array(
				'gateway' => 'bkash',
				'label'   => __( 'Membexa bKash Gateway', 'membexa' ),
			),
		);
		$rows = array();

		foreach ( $catalog as $plugin_file => $item ) {
			$installed = isset( $plugins[ $plugin_file ] );
			$active    = $installed && is_plugin_active( $plugin_file );
			$gateway   = Gateways::get( $item['gateway'] );
			$enabled   = $gateway && is_callable( $gateway['enabled_callback'] ) ? (bool) call_user_func( $gateway['enabled_callback'] ) : false;
			$action    = '';
			if ( $active && $gateway && ! empty( $gateway['settings_url'] ) ) {
				$action = '<a class="button button-secondary" href="' . esc_url( $gateway['settings_url'] ) . '">' . esc_html__( 'Open settings', 'membexa' ) . '</a>';
			} elseif ( $installed && ! $active ) {
				$action = $this->activate_button_html( $plugin_file );
			} elseif ( ! $installed ) {
				$action = '<a class="button" href="' . esc_url( admin_url( 'plugin-install.php?tab=upload' ) ) . '">' . esc_html__( 'Upload ZIP', 'membexa' ) . '</a>';
			}

			$rows[] = array(
				'label'         => $installed && ! empty( $plugins[ $plugin_file ]['Name'] ) ? $plugins[ $plugin_file ]['Name'] : $item['label'],
				'gateway_label' => $gateway && ! empty( $gateway['label'] ) ? $gateway['label'] : ucfirst( $item['gateway'] ),
				'version'       => $installed && ! empty( $plugins[ $plugin_file ]['Version'] ) ? $plugins[ $plugin_file ]['Version'] : '',
				'status'        => $enabled ? __( 'Configured', 'membexa' ) : ( $active ? __( 'Needs setup', 'membexa' ) : ( $installed ? __( 'Installed', 'membexa' ) : __( 'Not installed', 'membexa' ) ) ),
				'action'        => $action,
			);
		}

		return $rows;
	}

	/** Return installed WooCommerce payment add-on plugins, including sources of registered gateways. */
	private function installed_payment_plugins() {
		$plugins = get_plugins();
		$sources = $this->registered_gateway_plugin_files();
		$result  = array();
		foreach ( $plugins as $plugin_file => $plugin ) {
			if ( in_array( $plugin_file, $sources, true ) || $this->is_payment_plugin_data( $plugin_file, $plugin ) ) {
				if ( 0 !== strpos( $plugin_file, 'membexa-' ) && 'woocommerce/woocommerce.php' !== $plugin_file ) {
					$result[ $plugin_file ] = $plugin;
				}
			}
		}
		return $result;
	}

	/** Return main plugin files that currently provide registered WooCommerce gateway classes. */
	private function registered_gateway_plugin_files() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) {
			return array();
		}
		$files = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			$file = $this->gateway_source_plugin_file( $gateway );
			if ( $file && 'woocommerce/woocommerce.php' !== $file ) {
				$files[] = $file;
			}
		}
		return array_values( array_unique( $files ) );
	}

	/** Identify the installed plugin that provides a WooCommerce gateway class. */
	private function gateway_source_plugin_file( $gateway ) {
		try {
			$reflection = new \ReflectionClass( $gateway );
			$class_file = $reflection->getFileName();
		} catch ( \ReflectionException $exception ) {
			return '';
		}
		if ( ! $class_file ) {
			return '';
		}

		$relative = plugin_basename( $class_file );
		$root     = strtok( $relative, '/' );
		$plugins  = get_plugins();
		foreach ( $plugins as $plugin_file => $plugin ) {
			unset( $plugin );
			if ( strtok( $plugin_file, '/' ) === $root ) {
				return $plugin_file;
			}
		}
		return '';
	}

	/** Return a human-readable plugin source for a WooCommerce gateway object. */
	private function gateway_source_name( $gateway ) {
		$plugin_file = $this->gateway_source_plugin_file( $gateway );
		if ( ! $plugin_file ) {
			return __( 'Unknown source', 'membexa' );
		}
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ]['Name'] ) ? $plugins[ $plugin_file ]['Name'] : $plugin_file;
	}

	/** Determine whether installed plugin metadata looks like a WooCommerce payment add-on. */
	private function is_payment_plugin_data( $plugin_file, $plugin ) {
		if ( 0 === strpos( $plugin_file, 'membexa-' ) ) {
			return true;
		}
		$haystack = strtolower(
			$plugin_file . ' ' .
			( isset( $plugin['Name'] ) ? $plugin['Name'] : '' ) . ' ' .
			( isset( $plugin['Description'] ) ? wp_strip_all_tags( $plugin['Description'] ) : '' ) . ' ' .
			( isset( $plugin['TextDomain'] ) ? $plugin['TextDomain'] : '' ) . ' ' .
			( isset( $plugin['RequiresPlugins'] ) ? $plugin['RequiresPlugins'] : '' )
		);
		$payment_terms = array( 'payment', 'gateway', 'stripe', 'paypal', 'paystack', 'razorpay', 'mollie', 'braintree', 'authorize', 'bkash', 'nagad', 'sslcommerz', 'cashfree', 'flutterwave', 'mercado pago', 'mercadopago', 'payu', 'adyen', 'worldpay', 'klarna', 'afterpay', 'square', 'woopayments' );
		$has_payment   = false;
		foreach ( $payment_terms as $term ) {
			if ( false !== strpos( $haystack, $term ) ) {
				$has_payment = true;
				break;
			}
		}
		$has_woocommerce = false !== strpos( $haystack, 'woocommerce' ) || false !== strpos( $haystack, 'woo commerce' ) || false !== strpos( $haystack, 'requires plugins: woocommerce' );
		return $has_payment && $has_woocommerce;
	}

	/** Check WordPress.org metadata before allowing one-click installation. */
	private function is_repository_payment_candidate( $plugin ) {
		$requires = isset( $plugin->requires_plugins ) ? implode( ' ', (array) $plugin->requires_plugins ) : '';
		$haystack = strtolower(
			( isset( $plugin->name ) ? $plugin->name : '' ) . ' ' .
			( isset( $plugin->slug ) ? $plugin->slug : '' ) . ' ' .
			( isset( $plugin->short_description ) ? wp_strip_all_tags( $plugin->short_description ) : '' ) . ' ' .
			$requires
		);
		$has_woocommerce = false !== strpos( $haystack, 'woocommerce' ) || false !== strpos( $haystack, 'woopayments' );
		$has_payment     = false !== strpos( $haystack, 'payment' ) || false !== strpos( $haystack, 'gateway' ) || false !== strpos( $haystack, 'stripe' ) || false !== strpos( $haystack, 'paypal' );
		return $has_woocommerce && $has_payment;
	}

	/** Find an installed plugin main file by WordPress.org slug. */
	private function plugin_file_for_slug( $slug, $plugins ) {
		foreach ( $plugins as $plugin_file => $plugin ) {
			$directory   = dirname( $plugin_file );
			$text_domain = isset( $plugin['TextDomain'] ) ? sanitize_key( $plugin['TextDomain'] ) : '';
			if ( $slug === $directory || $slug === $text_domain || 0 === strpos( $plugin_file, $slug . '/' ) ) {
				return $plugin_file;
			}
		}
		return '';
	}

	/** Build an action button for an installed plugin. */
	private function plugin_action_html( $plugin_file, $plugin ) {
		unset( $plugin );
		if ( is_plugin_active( $plugin_file ) ) {
			return '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=membexa-payments&tab=woocommerce' ) ) . '">' . esc_html__( 'View gateways', 'membexa' ) . '</a>';
		}
		return $this->activate_button_html( $plugin_file );
	}

	/** Build an activation button. */
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
		return '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'membexa' ) . '</a>';
	}

	/** Build install/activate action for a WordPress.org search result. */
	private function repository_plugin_action_html( $plugin, $plugin_file ) {
		if ( $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				return '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=membexa-payments&tab=woocommerce' ) ) . '">' . esc_html__( 'View gateways', 'membexa' ) . '</a>';
			}
			return $this->activate_button_html( $plugin_file );
		}
		$slug = sanitize_key( $plugin->slug );
		$url  = wp_nonce_url(
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

	/** Render WordPress.org search pagination. */
	private function discover_pagination( $api, $page, $search ) {
		$total_pages = isset( $api->info['pages'] ) ? absint( $api->info['pages'] ) : 1;
		if ( $total_pages <= 1 ) {
			return;
		}
		$base = admin_url( 'admin.php?page=membexa-payments&tab=discover' );
		if ( $search ) {
			$base = add_query_arg( 's', rawurlencode( $search ), $base );
		}
		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%', $base ),
				'format'  => '',
				'current' => $page,
				'total'   => $total_pages,
			)
		);
		if ( $links ) {
			?>
			<div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( $links ); ?></div></div>
			<?php
		}
	}

	/** Render a redirect result notice. */
	private function render_result_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result code after protected admin-post actions.
		$notice = isset( $_GET['membexa_notice'] ) ? sanitize_key( wp_unslash( $_GET['membexa_notice'] ) ) : '';
		if ( ! $notice ) {
			return;
		}
		$messages = array(
			'installed'            => array( 'success', __( 'Payment add-on installed and activated. It is now connected to WordPress and will appear here automatically.', 'membexa' ) ),
			'installed-not-active' => array( 'warning', __( 'Payment add-on was installed, but WordPress could not activate it automatically. Activate it from the Add-ons tab.', 'membexa' ) ),
			'activated'            => array( 'success', __( 'Payment add-on activated. Registered WooCommerce gateways will now appear in the WooCommerce Gateways tab.', 'membexa' ) ),
			'invalid-plugin'       => array( 'error', __( 'The requested plugin could not be validated.', 'membexa' ) ),
			'not-payment-addon'    => array( 'error', __( 'WordPress.org did not return a compatible WooCommerce payment gateway package for that request.', 'membexa' ) ),
			'install-failed'       => array( 'error', __( 'WordPress could not install the payment add-on.', 'membexa' ) ),
			'activate-failed'      => array( 'error', __( 'WordPress could not activate the payment add-on.', 'membexa' ) ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		$type = $messages[ $notice ][0];
		$text = $messages[ $notice ][1];
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> inline"><p><?php echo esc_html( $text ); ?></p></div>
		<?php
	}

	/** Redirect back to the Payments hub with a result code. */
	private function redirect_with_result( $notice, $tab ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'membexa-payments',
					'tab'            => $tab,
					'membexa_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
