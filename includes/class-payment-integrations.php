<?php
/**
 * Modular payment integration administration.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Connects Membexa plans to WooCommerce products and external gateway plugins. */
final class Payment_Integrations {
	/** Register hooks. */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_plan_box' ) );
		add_action( 'save_post_' . Plan::POST_TYPE, array( $this, 'save_plan_product' ), 20 );
		add_filter( 'membexa_commerce_grant_plans', array( $this, 'linked_plan_grant' ), 5, 2 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ), 30 );
			add_action( 'admin_init', array( $this, 'redirect_legacy_payments' ) );
			add_action( 'admin_head', array( $this, 'hide_legacy_payment_tab' ) );
		}
	}

	/** Add the modular Payments screen. */
	public function menu() {
		add_submenu_page( 'membexa', __( 'Payment Integrations', 'membexa' ), __( 'Payments', 'membexa' ), 'manage_options', 'membexa-payments', array( $this, 'page' ) );
	}

	/** Redirect the old credential screen to the modular integration screen. */
	public function redirect_legacy_payments() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation redirect.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation redirect.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'membexa-settings' === $page && 'payments' === $tab && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=membexa-payments' ) );
			exit;
		}
	}

	/** Hide the obsolete Payments tab from the legacy settings navigation. */
	public function hide_legacy_payment_tab() {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'membexa-settings' ) ) {
			echo '<style>.nav-tab[href*="tab=payments"]{display:none!important}</style>';
		}
	}

	/** Render payment integration status. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage payment integrations.', 'membexa' ) );
		}
		$woo_active = Account::woocommerce_active();
		$gateways   = $woo_active ? Gateways::enabled() : array();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Payment Integrations', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Payments are modular. Membexa stores no Stripe, PayPal, bKash, card, or wallet credentials. Install payment gateway plugins for WooCommerce and configure them in WooCommerce.', 'membexa' ); ?></p>
			<?php if ( ! $woo_active ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is required for paid Membexa plans. Free memberships continue to work without WooCommerce.', 'membexa' ); ?></p></div>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugin-install.php?s=WooCommerce&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install WooCommerce', 'membexa' ); ?></a></p>
			<?php else : ?>
				<div class="card membexa-card">
					<h2><?php esc_html_e( 'Active checkout methods', 'membexa' ); ?></h2>
					<?php if ( empty( $gateways ) ) : ?>
						<p><?php esc_html_e( 'No enabled WooCommerce payment gateway is currently available.', 'membexa' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $gateways as $label ) : ?><li>✓ <?php echo esc_html( $label ); ?></li><?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'Open WooCommerce Payments', 'membexa' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'plugin-install.php?s=WooCommerce%20payment%20gateway&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Add Payment Plugin', 'membexa' ); ?></a></p>
				</div>
				<div class="card membexa-card">
					<h2><?php esc_html_e( 'How it works', 'membexa' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Install a WooCommerce gateway plugin such as Stripe, PayPal, or a compatible Bangladesh payment gateway.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Configure and enable that gateway in WooCommerce settings.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Create a WooCommerce product for the membership purchase.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Edit the Membexa plan and link that WooCommerce product in Payment Integration.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Members choose the plan in Membexa, then choose the actual payment method on WooCommerce checkout.', 'membexa' ); ?></li>
					</ol>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Add payment product mapping to a Membexa plan. */
	public function add_plan_box() {
		add_meta_box( 'membexa_payment_integration', __( 'Payment Integration', 'membexa' ), array( $this, 'render_plan_box' ), Plan::POST_TYPE, 'side', 'high' );
	}

	/** Render WooCommerce product mapping. */
	public function render_plan_box( $post ) {
		wp_nonce_field( 'membexa_save_payment_product', 'membexa_payment_product_nonce' );
		$product_id = Gateways::product_id_for_plan( $post->ID );
		?>
		<p><?php esc_html_e( 'Link this paid plan to a WooCommerce product. Payment methods are supplied by separate WooCommerce gateway plugins.', 'membexa' ); ?></p>
		<p><label for="membexa-payment-product"><strong><?php esc_html_e( 'WooCommerce product', 'membexa' ); ?></strong></label>
		<select id="membexa-payment-product" name="membexa_payment_product_id" style="width:100%;"><option value="0"><?php esc_html_e( '— Select product —', 'membexa' ); ?></option>
		<?php foreach ( $this->products() as $product ) : ?><option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>><?php echo esc_html( $product->get_name() . ' (#' . $product->get_id() . ')' ); ?></option><?php endforeach; ?>
		</select></p>
		<p class="description"><?php esc_html_e( 'One-time/lifetime: use a normal virtual product. Monthly/yearly: use a subscription product from a compatible WooCommerce subscriptions extension.', 'membexa' ); ?></p>
		<?php
	}

	/** Save linked WooCommerce product. */
	public function save_plan_product( $post_id ) {
		if ( ! isset( $_POST['membexa_payment_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_payment_product_nonce'] ) ), 'membexa_save_payment_product' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$product_id = isset( $_POST['membexa_payment_product_id'] ) ? absint( $_POST['membexa_payment_product_id'] ) : 0;
		if ( $product_id && ( ! Account::woocommerce_active() || ! wc_get_product( $product_id ) ) ) { $product_id = 0; }
		update_post_meta( $post_id, '_membexa_payment_product_id', $product_id );
	}

	/** Automatically grant the plan linked to a purchased product. */
	public function linked_plan_grant( $plans, $product_id ) {
		$product_id = absint( $product_id );
		$parent_id  = wp_get_post_parent_id( $product_id );
		$product_id = $parent_id && 'product' === get_post_type( $parent_id ) ? $parent_id : $product_id;
		foreach ( Plan::all() as $plan ) {
			if ( $product_id === Gateways::product_id_for_plan( $plan['id'] ) ) { $plans[] = $plan['id']; }
		}
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $plans ) ) ) );
	}

	/** Get manageable WooCommerce products. */
	private function products() {
		if ( ! Account::woocommerce_active() || ! function_exists( 'wc_get_products' ) ) { return array(); }
		return wc_get_products( array( 'status' => array( 'publish', 'draft', 'private' ), 'limit' => 200, 'orderby' => 'name', 'order' => 'ASC' ) );
	}
}
