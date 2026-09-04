<?php
/**
 * Fault-tolerant payment add-on and WooCommerce gateway manager.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a unified Payments hub without allowing a third-party gateway failure
 * to take down the Membexa administration screen.
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
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Membexa Payments', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Manage Membexa payment add-ons and WooCommerce payment gateways from one place. Manually installed add-ons are detected automatically.', 'membexa' ); ?></p>
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
		?>
		<h2><?php esc_html_e( 'Membexa gateway add-ons', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Standalone Membexa memberships use these add-ons. Uploaded add-ons are detected here even before WooCommerce gateways are loaded.', 'membexa' ); ?></p>
		<table class="widefat striped membexa-payment-table">
			<thead><tr><th><?php esc_html_e( 'Add-on', 'membexa' ); ?></th><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $membexa_plugin_rows as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row['label'] ); ?></strong><?php echo $row['version'] ? '<br><small>' . esc_html( 'v' . $row['version'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( $row['gateway_label'] ); ?></td>
					<td><?php echo esc_html( $row['status'] ); ?></td>
					<td><?php echo wp_kses_post( $row['action'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Installed WooCommerce payment add-ons', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Compatible payment plugins installed manually or through Discover are shown here from plugin metadata, so this list does not need to initialize third-party gateway classes.', 'membexa' ); ?></p>
		<?php if ( empty( $installed_plugins ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No separate WooCommerce payment add-on is currently detected. Use Discover or upload a gateway ZIP manually.', 'membexa' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped membexa-payment-table">
				<thead><tr><th><?php esc_html_e( 'Plugin', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $installed_plugins as $plugin_file => $plugin ) : ?>
					<tr>
						<td><strong><?php echo esc_html( isset( $plugin['Name'] ) ? $plugin['Name'] : $plugin_file ); ?></strong><?php echo ! empty( $plugin['Version'] ) ? '<br><small>' . esc_html( 'v' . $plugin['Version'] ) . '</small>' : ''; ?></td>
						<td><?php echo esc_html( is_plugin_active( $plugin_file ) ? __( 'Active', 'membexa' ) : __( 'Installed', 'membexa' ) ); ?></td>
						<td><?php echo wp_kses_post( $this->plugin_action_html( $plugin_file ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-payments&tab=discover' ) ); ?>"><?php esc_html_e( 'Discover payment add-ons', 'membexa' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><?php esc_html_e( 'Upload add-on ZIP', 'membexa' ); ?></a></p>
		<?php
	}

	/** Render WooCommerce gateways with defensive integration boundaries. */
	private function woocommerce_gateways_tab() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC() ) {
			?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is not active. Activate WooCommerce to view its payment gateways.', 'membexa' ); ?></p></div>
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
		?>
		<h2><?php esc_html_e( 'WooCommerce payment gateways', 'membexa' ); ?></h2>
		<p><?php esc_html_e( 'Every gateway successfully registered by WooCommerce is shown here, including disabled methods and gateways supplied by active plugins.', 'membexa' ); ?></p>
		<?php if ( empty( $gateways ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'WooCommerce did not return any registered payment gateways.', 'membexa' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped membexa-payment-table">
				<thead><tr><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'ID', 'membexa' ); ?></th><th><?php esc_html_e( 'Source', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $gateways as $gateway ) : ?>
					<?php $row = $this->gateway_row( $gateway ); ?>
					<?php if ( ! $row ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<tr>
						<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
						<td><code><?php echo esc_html( $row['id'] ); ?></code></td>
						<td><?php echo esc_html( $row['source'] ); ?></td>
						<td><?php echo esc_html( $row['enabled'] ? __( 'Enabled', 'membexa' ) : __( 'Disabled', 'membexa' ) ); ?></td>
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
			$id      = isset( $gateway->id ) ? sanitize_key( (string) $gateway->id ) : '';
			$title   = method_exists( $gateway, 'get_method_title' ) ? (string) $gateway->get_method_title() : $id;
			$enabled = isset( $gateway->enabled ) && 'yes' === (string) $gateway->enabled;
			$source  = $this->gateway_source_name( $gateway );
		} catch ( \Throwable $error ) {
			return false;
		}

		if ( ! $id ) {
			return false;
		}

		return array(
			'id'      => $id,
			'title'   => $title ? $title : $id,
			'enabled' => $enabled,
			'source'  => $source,
		);
	}

	/** Render WordPress.org payment gateway discovery. */
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
		$query  = $search ? 'woocommerce payment gateway ' . $search : 'woocommerce payment gateway';
		$api    = plugins_api(
			'query_plugins',
			array(
				'search'   => $query,
				'page'     => 1,
				'per_page' => 18,
				'fields'   => array(
					'short_description' => true,
					'sections'          => false,
					'screenshots'       => false,
				),
			)
		);
		?>
		<h2><?php esc_html_e( 'Discover WooCommerce payment add-ons', 'membexa' ); ?></h2>
		<form method="get" class="membexa-payment-search">
			<input type="hidden" name="page" value="membexa-payments">
			<input type="hidden" name="tab" value="discover">
			<label class="screen-reader-text" for="membexa-payment-search"><?php esc_html_e( 'Search payment gateways', 'membexa' ); ?></label>
			<input id="membexa-payment-search" type="search" class="regular-text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Stripe, PayPal, bKash, bank…', 'membexa' ); ?>">
			<?php submit_button( __( 'Search gateways', 'membexa' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( is_wp_error( $api ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $api->get_error_message() ); ?></p></div>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped membexa-payment-table">
			<thead><tr><th><?php esc_html_e( 'Plugin', 'membexa' ); ?></th><th><?php esc_html_e( 'Description', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $api->plugins as $plugin ) : ?>
				<?php $plugin_file = $this->plugin_file_for_slug( $plugin->slug, get_plugins() ); ?>
				<tr>
					<td><strong><?php echo esc_html( $plugin->name ); ?></strong></td>
					<td><?php echo wp_kses_post( wp_trim_words( $plugin->short_description, 24 ) ); ?></td>
					<td><?php echo wp_kses_post( $this->repository_plugin_action_html( $plugin, $plugin_file ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
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

		$api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );
		if ( is_wp_error( $api ) || empty( $api->download_link ) || ! $this->is_repository_payment_candidate( $api ) ) {
			$this->redirect_with_result( 'not-payment-addon', 'discover' );
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
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
		$this->redirect_with_result( 'activated', 'addons' );
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

			$rows[] = array(
				'label'         => $installed && ! empty( $plugins[ $plugin_file ]['Name'] ) ? $plugins[ $plugin_file ]['Name'] : $item['label'],
				'gateway_label' => ! empty( $gateway['label'] ) ? $gateway['label'] : ucfirst( $item['gateway'] ),
				'version'       => $installed && ! empty( $plugins[ $plugin_file ]['Version'] ) ? $plugins[ $plugin_file ]['Version'] : '',
				'status'        => $enabled ? __( 'Configured', 'membexa' ) : ( $active ? __( 'Needs setup', 'membexa' ) : ( $installed ? __( 'Installed', 'membexa' ) : __( 'Not installed', 'membexa' ) ) ),
				'action'        => $action,
			);
		}
		return $rows;
	}

	/** Detect installed WooCommerce payment add-ons from metadata only. */
	private function installed_payment_plugins_from_metadata( $plugins ) {
		$result = array();
		foreach ( $plugins as $plugin_file => $plugin ) {
			if ( 'woocommerce/woocommerce.php' === $plugin_file || 0 === strpos( $plugin_file, 'membexa-' ) ) {
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

	/** Determine whether plugin metadata describes a WooCommerce payment add-on. */
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
		$terms = array( 'payment', 'gateway', 'stripe', 'paypal', 'paystack', 'razorpay', 'mollie', 'braintree', 'authorize', 'bkash', 'nagad', 'sslcommerz', 'cashfree', 'flutterwave', 'mercado pago', 'mercadopago', 'payu', 'adyen', 'worldpay', 'klarna', 'afterpay', 'square', 'woopayments' );
		$payment = false;
		foreach ( $terms as $term ) {
			if ( false !== strpos( $haystack, $term ) ) {
				$payment = true;
				break;
			}
		}
		$woocommerce = false !== strpos( $haystack, 'woocommerce' ) || false !== strpos( $haystack, 'woo commerce' );
		return $payment && $woocommerce;
	}

	/** Check WordPress.org metadata before one-click installation. */
	private function is_repository_payment_candidate( $plugin ) {
		$requires = isset( $plugin->requires_plugins ) ? implode( ' ', (array) $plugin->requires_plugins ) : '';
		$haystack = strtolower(
			( isset( $plugin->name ) ? $plugin->name : '' ) . ' ' .
			( isset( $plugin->slug ) ? $plugin->slug : '' ) . ' ' .
			( isset( $plugin->short_description ) ? wp_strip_all_tags( $plugin->short_description ) : '' ) . ' ' .
			$requires
		);
		$woocommerce = false !== strpos( $haystack, 'woocommerce' ) || false !== strpos( $haystack, 'woopayments' );
		$payment     = false !== strpos( $haystack, 'payment' ) || false !== strpos( $haystack, 'gateway' ) || false !== strpos( $haystack, 'stripe' ) || false !== strpos( $haystack, 'paypal' );
		return $woocommerce && $payment;
	}

	/** Find an installed plugin main file by slug. */
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

	/** Build an installed-plugin action button. */
	private function plugin_action_html( $plugin_file ) {
		if ( is_plugin_active( $plugin_file ) ) {
			return '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=membexa-payments&tab=woocommerce' ) ) . '">' . esc_html__( 'View gateways', 'membexa' ) . '</a>';
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
		return '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'membexa' ) . '</a>';
	}

	/** Build install/activate action for a WordPress.org search result. */
	private function repository_plugin_action_html( $plugin, $plugin_file ) {
		if ( $plugin_file ) {
			return $this->plugin_action_html( $plugin_file );
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
			'installed'            => array( 'success', __( 'Payment add-on installed and activated.', 'membexa' ) ),
			'installed-not-active' => array( 'warning', __( 'Payment add-on installed, but automatic activation failed.', 'membexa' ) ),
			'activated'            => array( 'success', __( 'Payment add-on activated.', 'membexa' ) ),
			'invalid-plugin'       => array( 'error', __( 'The requested plugin could not be validated.', 'membexa' ) ),
			'not-payment-addon'    => array( 'error', __( 'WordPress.org did not return a compatible WooCommerce payment gateway package.', 'membexa' ) ),
			'install-failed'       => array( 'error', __( 'WordPress could not install the payment add-on.', 'membexa' ) ),
			'activate-failed'      => array( 'error', __( 'WordPress could not activate the payment add-on.', 'membexa' ) ),
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
