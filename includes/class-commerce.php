<?php
/**
 * WooCommerce product, category, order, and subscription integration.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects WooCommerce commerce objects to Membexa membership entitlements.
 */
final class Commerce {
	const META_GRANT_PLANS       = '_membexa_grant_plans';
	const META_REQUIRE_PLANS     = '_membexa_require_plans';
	const META_RESTRICT_VIEW     = '_membexa_restrict_view';
	const META_RESTRICT_PURCHASE = '_membexa_restrict_purchase';
	const ORDER_GRANTED_META     = '_membexa_granted_subscription_ids';

	/** Register integration hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 25 );

		if ( ! Account::woocommerce_active() ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product_rules' ) );
		add_action( 'product_cat_add_form_fields', array( $this, 'category_add_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'category_edit_fields' ) );
		add_action( 'created_product_cat', array( $this, 'save_category_rules' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_rules' ) );

		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'checkout_registration_enabled' ) );
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'checkout_registration_required' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'grant_order_memberships' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'grant_order_memberships' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'revoke_order_memberships' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'revoke_order_memberships' ) );
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'sync_woocommerce_subscription' ), 20, 3 );

		add_filter( 'woocommerce_product_is_visible', array( $this, 'product_is_visible' ), 20, 2 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'product_is_purchasable' ), 20, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'product_is_purchasable' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 20, 5 );
		add_action( 'template_redirect', array( $this, 'protect_catalog_views' ), 20 );
	}

	/** Register the Commerce submenu. */
	public function menu() {
		add_submenu_page(
			'membexa',
			__( 'Membexa Commerce', 'membexa' ),
			__( 'Commerce', 'membexa' ),
			'manage_options',
			'membexa-commerce',
			array( $this, 'page' )
		);
	}

	/** Render the integration overview. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Membexa Commerce.', 'membexa' ) );
		}
		$woo_active = Account::woocommerce_active();
		$wcs_active = Account::subscriptions_active();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Membexa Commerce', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Connect WooCommerce products and product categories to Membexa plans without duplicating WooCommerce cart, order, tax, inventory, or download systems.', 'membexa' ); ?></p>
			<div class="membexa-stat-grid">
				<?php $this->status_card( $woo_active, __( 'WooCommerce', 'membexa' ), $woo_active ? __( 'Connected', 'membexa' ) : __( 'Not active', 'membexa' ) ); ?>
				<?php $this->status_card( $wcs_active, __( 'Woo Subscriptions', 'membexa' ), $wcs_active ? __( 'Lifecycle sync enabled', 'membexa' ) : __( 'Optional', 'membexa' ) ); ?>
				<?php $this->status_card( true, __( 'Account mode', 'membexa' ), ucfirst( Account::mode() ) ); ?>
			</div>

			<?php if ( ! $woo_active ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'WooCommerce is not active. Membexa standalone memberships continue to work normally. Activate WooCommerce only when you need store products, orders, downloads, or WooCommerce subscription products.', 'membexa' ); ?></p></div>
			<?php else : ?>
				<div class="card membexa-card">
					<h2><?php esc_html_e( 'How product entitlements work', 'membexa' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Edit a WooCommerce product and open the Membexa Membership Entitlements box.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Choose plans to grant when an order becomes paid/processing or completed.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Optionally require one or more plans to view or purchase that product.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'Apply the same rules to an entire product category from Products → Categories.', 'membexa' ); ?></li>
						<li><?php esc_html_e( 'If WooCommerce Subscriptions is active, Membexa mirrors active, on-hold, pending-cancel, cancelled, and expired subscription states into membership access.', 'membexa' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'WooCommerce remains the source of truth for products, cart, checkout, orders, taxes, inventory, downloadable-product permissions, and the payment gateways used by WooCommerce checkout. Membexa remains the source of truth for membership access. Membexa’s built-in Stripe, PayPal, and bKash gateways continue to serve standalone Membexa plan checkout.', 'membexa' ); ?></p>
				</div>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Open WooCommerce Products', 'membexa' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ); ?>"><?php esc_html_e( 'Open Product Categories', 'membexa' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Render a Commerce status card. */
	private function status_card( $healthy, $label, $value ) {
		?>
		<div class="membexa-stat">
			<strong><?php echo esc_html( $healthy ? '✓' : '—' ); ?></strong>
			<span><?php echo esc_html( $label . ': ' . $value ); ?></span>
		</div>
		<?php
	}

	/** Add product membership rules. */
	public function add_product_meta_box() {
		add_meta_box(
			'membexa_product_entitlements',
			__( 'Membexa Membership Entitlements', 'membexa' ),
			array( $this, 'render_product_meta_box' ),
			'product',
			'normal',
			'default'
		);
	}

	/** Render product membership rules. */
	public function render_product_meta_box( $post ) {
		wp_nonce_field( 'membexa_save_product_rules', 'membexa_product_rules_nonce' );
		$grant    = self::clean_plan_ids( get_post_meta( $post->ID, self::META_GRANT_PLANS, true ) );
		$required = self::clean_plan_ids( get_post_meta( $post->ID, self::META_REQUIRE_PLANS, true ) );
		$view     = (bool) get_post_meta( $post->ID, self::META_RESTRICT_VIEW, true );
		$purchase = (bool) get_post_meta( $post->ID, self::META_RESTRICT_PURCHASE, true );
		?>
		<p><strong><?php esc_html_e( 'Grant membership after purchase', 'membexa' ); ?></strong></p>
		<?php $this->plan_checkboxes( 'membexa_product_grant_plans', $grant ); ?>
		<p class="description"><?php esc_html_e( 'When this product is purchased by a registered customer, selected plans are granted. WooCommerce subscription products are synchronized to the subscription lifecycle when WooCommerce Subscriptions is available.', 'membexa' ); ?></p>
		<hr>
		<p><strong><?php esc_html_e( 'Required membership plans', 'membexa' ); ?></strong></p>
		<?php $this->plan_checkboxes( 'membexa_product_require_plans', $required ); ?>
		<p><label><input type="checkbox" name="membexa_product_restrict_view" value="1" <?php checked( $view ); ?>> <?php esc_html_e( 'Hide/restrict this product from visitors without an eligible plan', 'membexa' ); ?></label></p>
		<p><label><input type="checkbox" name="membexa_product_restrict_purchase" value="1" <?php checked( $purchase ); ?>> <?php esc_html_e( 'Block purchase unless the customer has an eligible plan', 'membexa' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Rules also inherit from assigned product categories. Product and category plan lists are combined.', 'membexa' ); ?></p>
		<?php
	}

	/** Render plan checkboxes. */
	private function plan_checkboxes( $name, $selected ) {
		$plans = Plan::all();
		if ( empty( $plans ) ) {
			echo '<p>' . esc_html__( 'Create a Membexa plan first.', 'membexa' ) . '</p>';
			return;
		}
		foreach ( $plans as $plan ) {
			?>
			<label style="display:block;margin:4px 0;"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $plan['id'] ); ?>" <?php checked( in_array( (int) $plan['id'], $selected, true ) ); ?>> <?php echo esc_html( $plan['name'] ); ?></label>
			<?php
		}
	}

	/** Save product membership rules. */
	public function save_product_rules( $post_id ) {
		if ( ! isset( $_POST['membexa_product_rules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_product_rules_nonce'] ) ), 'membexa_save_product_rules' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$grant    = isset( $_POST['membexa_product_grant_plans'] ) ? self::clean_plan_ids( wp_unslash( $_POST['membexa_product_grant_plans'] ) ) : array();
		$required = isset( $_POST['membexa_product_require_plans'] ) ? self::clean_plan_ids( wp_unslash( $_POST['membexa_product_require_plans'] ) ) : array();
		update_post_meta( $post_id, self::META_GRANT_PLANS, $grant );
		update_post_meta( $post_id, self::META_REQUIRE_PLANS, $required );
		update_post_meta( $post_id, self::META_RESTRICT_VIEW, empty( $_POST['membexa_product_restrict_view'] ) ? 0 : 1 );
		update_post_meta( $post_id, self::META_RESTRICT_PURCHASE, empty( $_POST['membexa_product_restrict_purchase'] ) ? 0 : 1 );
	}

	/** Render add-category fields. */
	public function category_add_fields() {
		wp_nonce_field( 'membexa_save_product_cat', 'membexa_product_cat_nonce' );
		?>
		<div class="form-field">
			<label><?php esc_html_e( 'Membexa: grant plans', 'membexa' ); ?></label>
			<?php $this->plan_checkboxes( 'membexa_term_grant_plans', array() ); ?>
			<p><?php esc_html_e( 'Purchasing a product in this category can grant these plans.', 'membexa' ); ?></p>
		</div>
		<div class="form-field">
			<label><?php esc_html_e( 'Membexa: required plans', 'membexa' ); ?></label>
			<?php $this->plan_checkboxes( 'membexa_term_require_plans', array() ); ?>
			<label><input type="checkbox" name="membexa_term_restrict_view" value="1"> <?php esc_html_e( 'Restrict category/product visibility', 'membexa' ); ?></label><br>
			<label><input type="checkbox" name="membexa_term_restrict_purchase" value="1"> <?php esc_html_e( 'Restrict product purchase', 'membexa' ); ?></label>
		</div>
		<?php
	}

	/** Render edit-category fields. */
	public function category_edit_fields( $term ) {
		wp_nonce_field( 'membexa_save_product_cat', 'membexa_product_cat_nonce' );
		$grant    = self::clean_plan_ids( get_term_meta( $term->term_id, self::META_GRANT_PLANS, true ) );
		$required = self::clean_plan_ids( get_term_meta( $term->term_id, self::META_REQUIRE_PLANS, true ) );
		$view     = (bool) get_term_meta( $term->term_id, self::META_RESTRICT_VIEW, true );
		$purchase = (bool) get_term_meta( $term->term_id, self::META_RESTRICT_PURCHASE, true );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Membexa: grant plans', 'membexa' ); ?></th>
			<td><?php $this->plan_checkboxes( 'membexa_term_grant_plans', $grant ); ?><p class="description"><?php esc_html_e( 'Purchasing a product in this category can grant these plans.', 'membexa' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Membexa: access rules', 'membexa' ); ?></th>
			<td>
				<?php $this->plan_checkboxes( 'membexa_term_require_plans', $required ); ?>
				<p><label><input type="checkbox" name="membexa_term_restrict_view" value="1" <?php checked( $view ); ?>> <?php esc_html_e( 'Restrict category/product visibility', 'membexa' ); ?></label></p>
				<p><label><input type="checkbox" name="membexa_term_restrict_purchase" value="1" <?php checked( $purchase ); ?>> <?php esc_html_e( 'Restrict product purchase', 'membexa' ); ?></label></p>
			</td>
		</tr>
		<?php
	}

	/** Save product category membership rules. */
	public function save_category_rules( $term_id ) {
		if ( ! isset( $_POST['membexa_product_cat_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_product_cat_nonce'] ) ), 'membexa_save_product_cat' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		$grant    = isset( $_POST['membexa_term_grant_plans'] ) ? self::clean_plan_ids( wp_unslash( $_POST['membexa_term_grant_plans'] ) ) : array();
		$required = isset( $_POST['membexa_term_require_plans'] ) ? self::clean_plan_ids( wp_unslash( $_POST['membexa_term_require_plans'] ) ) : array();
		update_term_meta( $term_id, self::META_GRANT_PLANS, $grant );
		update_term_meta( $term_id, self::META_REQUIRE_PLANS, $required );
		update_term_meta( $term_id, self::META_RESTRICT_VIEW, empty( $_POST['membexa_term_restrict_view'] ) ? 0 : 1 );
		update_term_meta( $term_id, self::META_RESTRICT_PURCHASE, empty( $_POST['membexa_term_restrict_purchase'] ) ? 0 : 1 );
	}

	/** Ensure checkout account creation is available when a cart item grants membership. */
	public function checkout_registration_enabled( $enabled ) {
		return $this->cart_grants_membership() ? true : $enabled;
	}

	/** Require an account when a cart item grants membership. */
	public function checkout_registration_required( $required ) {
		return $this->cart_grants_membership() ? true : $required;
	}

	/** Determine whether the current WooCommerce cart grants a Membexa plan. */
	private function cart_grants_membership() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product_id = ! empty( $item['variation_id'] ) ? absint( $item['variation_id'] ) : absint( $item['product_id'] );
			if ( self::grant_plans_for_product( $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/** Grant memberships from a paid WooCommerce order. */
	public function grant_order_memberships( $order_id ) {
		$contains_subscription = function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id );
		$contains_renewal      = function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id );
		if ( $contains_subscription || $contains_renewal ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}

		$user_id  = absint( $order->get_user_id() );
		$stored   = self::clean_plan_ids( $order->get_meta( self::ORDER_GRANTED_META, true ) );
		$created  = $stored;
		$plan_ids = $this->plan_ids_from_order( $order );
		foreach ( $plan_ids as $plan_id ) {
			$external_id = 'wc-order:' . absint( $order_id ) . ':plan:' . absint( $plan_id );
			if ( Subscriptions::get_by_external_id( $external_id ) || Subscriptions::user_has_plan( $user_id, array( $plan_id ) ) ) {
				continue;
			}
			$subscription_id = Subscriptions::create( $user_id, $plan_id, 'active', 'woocommerce', $external_id );
			if ( $subscription_id ) {
				$created[] = $subscription_id;
				do_action( 'membexa_woocommerce_membership_granted', $subscription_id, $plan_id, absint( $order_id ), $user_id );
			}
		}

		$created = self::clean_plan_ids( $created );
		if ( $created !== $stored ) {
			$order->update_meta_data( self::ORDER_GRANTED_META, $created );
			$order->save();
		}
	}

	/** Revoke memberships that this exact WooCommerce order granted. */
	public function revoke_order_memberships( $order_id ) {
		$settings = Settings::integrations();
		if ( empty( $settings['revoke_on_refund'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( self::clean_plan_ids( $order->get_meta( self::ORDER_GRANTED_META, true ) ) as $subscription_id ) {
			if ( Subscriptions::cancel_local( $subscription_id ) ) {
				do_action( 'membexa_woocommerce_membership_revoked', $subscription_id, absint( $order_id ) );
			}
		}
	}

	/** Synchronize WooCommerce Subscription lifecycle to Membexa access. */
	public function sync_woocommerce_subscription( $subscription, $new_status, $old_status ) {
		unset( $old_status );
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_id' ) || ! method_exists( $subscription, 'get_user_id' ) ) {
			return;
		}
		$user_id = absint( $subscription->get_user_id() );
		if ( ! $user_id ) {
			return;
		}

		$status = $this->map_woo_subscription_status( $new_status );
		$end    = method_exists( $subscription, 'get_time' ) ? absint( $subscription->get_time( 'end' ) ) : 0;
		foreach ( $this->plan_ids_from_order( $subscription ) as $plan_id ) {
			$external_id = 'wc-sub:' . absint( $subscription->get_id() ) . ':plan:' . absint( $plan_id );
			$local       = Subscriptions::get_by_external_id( $external_id );
			if ( ! $local ) {
				Subscriptions::create( $user_id, $plan_id, $status, 'woocommerce_subscription', $external_id );
				$local = Subscriptions::get_by_external_id( $external_id );
			}
			if ( ! $local ) {
				continue;
			}
			$cancel_at_end = 'pending-cancel' === sanitize_key( $new_status );
			Subscriptions::update_status_by_external_id( $external_id, $status, $end ? $end : null, $cancel_at_end );
			do_action( 'membexa_woocommerce_subscription_synced', (int) $local->id, $subscription, $status );
		}
	}

	/** Map WooCommerce Subscription status to a Membexa status. */
	private function map_woo_subscription_status( $status ) {
		$status = sanitize_key( $status );
		$map    = array(
			'active'         => 'active',
			'pending-cancel' => 'active',
			'on-hold'        => 'past_due',
			'pending'        => 'pending',
			'cancelled'      => 'canceled',
			'expired'        => 'expired',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : 'pending';
	}

	/** Get all grant plans represented by Woo order/subscription items. */
	private function plan_ids_from_order( $order ) {
		$plans = array();
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return $plans;
		}
		foreach ( $order->get_items() as $item ) {
			$product_id = method_exists( $item, 'get_variation_id' ) && $item->get_variation_id() ? absint( $item->get_variation_id() ) : absint( $item->get_product_id() );
			$plans      = array_merge( $plans, self::grant_plans_for_product( $product_id ) );
		}
		return self::clean_plan_ids( $plans );
	}

	/** Hide restricted products from catalogs for unauthorized customers. */
	public function product_is_visible( $visible, $product_id ) {
		if ( ! $visible || ! self::product_flag( $product_id, self::META_RESTRICT_VIEW ) ) {
			return $visible;
		}
		return self::user_can_access_product( $product_id );
	}

	/** Prevent restricted products from being purchasable. */
	public function product_is_purchasable( $purchasable, $product ) {
		if ( ! $purchasable || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $purchasable;
		}
		$product_id = absint( $product->get_id() );
		if ( ! self::product_flag( $product_id, self::META_RESTRICT_PURCHASE ) ) {
			return $purchasable;
		}
		return self::user_can_access_product( $product_id );
	}

	/** Show a useful notice instead of silently rejecting restricted add-to-cart actions. */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		unset( $quantity, $variations );
		$rule_product_id = $variation_id ? absint( $variation_id ) : absint( $product_id );
		if ( $passed && self::product_flag( $rule_product_id, self::META_RESTRICT_PURCHASE ) && ! self::user_can_access_product( $rule_product_id ) ) {
			wc_add_notice( __( 'This product is available for members with an eligible Membexa plan.', 'membexa' ), 'error' );
			return false;
		}
		return $passed;
	}

	/** Redirect unauthorized direct product/category views to the appropriate account or pricing page. */
	public function protect_catalog_views() {
		if ( is_admin() || wp_doing_ajax() || current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$restricted = false;
		$return_url = '';
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_queried_object_id();
			$restricted = self::product_flag( $product_id, self::META_RESTRICT_VIEW ) && ! self::user_can_access_product( $product_id );
			$return_url = get_permalink( $product_id );
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$required   = self::clean_plan_ids( get_term_meta( $term->term_id, self::META_REQUIRE_PLANS, true ) );
				$view       = (bool) get_term_meta( $term->term_id, self::META_RESTRICT_VIEW, true );
				$restricted = $view && ! self::user_has_any_plan( $required );
				$term_link  = get_term_link( $term );
				$return_url = is_wp_error( $term_link ) ? '' : $term_link;
			}
		}

		if ( ! $restricted ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Account::login_url( $return_url ) );
			exit;
		}
		$general = Settings::general();
		$target  = $general['pricing_page_id'] ? get_permalink( $general['pricing_page_id'] ) : Account::account_url();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Cancel a WooCommerce Subscription represented by a local Membexa record.
	 *
	 * @param object $local_subscription Local Membexa subscription record.
	 * @return string|\WP_Error Result code.
	 */
	public static function cancel_woocommerce_subscription( $local_subscription ) {
		if ( ! $local_subscription || 'woocommerce_subscription' !== $local_subscription->gateway || ! function_exists( 'wcs_get_subscription' ) ) {
			return new \WP_Error( 'membexa_woo_subscription_unavailable', __( 'The WooCommerce subscription could not be managed.', 'membexa' ) );
		}
		if ( ! preg_match( '/^wc-sub:(\d+):plan:\d+$/', (string) $local_subscription->gateway_external_id, $matches ) ) {
			return new \WP_Error( 'membexa_woo_subscription_invalid', __( 'The WooCommerce subscription reference is invalid.', 'membexa' ) );
		}

		$subscription = wcs_get_subscription( absint( $matches[1] ) );
		if ( ! $subscription || ! method_exists( $subscription, 'get_status' ) || ! method_exists( $subscription, 'update_status' ) ) {
			return new \WP_Error( 'membexa_woo_subscription_missing', __( 'The WooCommerce subscription could not be found.', 'membexa' ) );
		}
		$status = sanitize_key( $subscription->get_status() );
		if ( 'pending-cancel' === $status ) {
			Subscriptions::set_cancel_at_period_end( $local_subscription->id, true );
			return 'scheduled';
		}
		if ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
			Subscriptions::cancel_local( $local_subscription->id );
			return 'cancelled';
		}

		try {
			$subscription->update_status( 'pending-cancel', __( 'Cancellation requested from the Membexa membership account.', 'membexa' ) );
		} catch ( \Exception $exception ) {
			return new \WP_Error( 'membexa_woo_subscription_cancel_failed', $exception->getMessage() );
		}

		Subscriptions::set_cancel_at_period_end( $local_subscription->id, true );
		return 'scheduled';
	}

	/** Get grant plans for a product plus its categories. */
	public static function grant_plans_for_product( $product_id ) {
		$plans = self::plans_for_product_rule( $product_id, self::META_GRANT_PLANS );
		return self::clean_plan_ids( apply_filters( 'membexa_commerce_grant_plans', $plans, absint( $product_id ) ) );
	}

	/** Get required plans for a product plus its categories. */
	public static function required_plans_for_product( $product_id ) {
		$plans = self::plans_for_product_rule( $product_id, self::META_REQUIRE_PLANS );
		return self::clean_plan_ids( apply_filters( 'membexa_commerce_required_plans', $plans, absint( $product_id ) ) );
	}

	/** Determine whether the current user can access a product rule. */
	public static function user_can_access_product( $product_id, $user_id = 0 ) {
		$product_id = self::rule_product_id( $product_id );
		$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( $user_id && user_can( $user_id, 'edit_post', $product_id ) ) {
			return true;
		}
		$required = self::required_plans_for_product( $product_id );
		if ( empty( $required ) ) {
			return false;
		}
		return $user_id && Subscriptions::user_has_plan( $user_id, $required );
	}

	/** Determine whether the current user has any requested plan. */
	private static function user_has_any_plan( $plan_ids ) {
		$plan_ids = self::clean_plan_ids( $plan_ids );
		return ! empty( $plan_ids ) && is_user_logged_in() && Subscriptions::user_has_plan( get_current_user_id(), $plan_ids );
	}

	/** Resolve a product boolean flag, including product-category inheritance. */
	private static function product_flag( $product_id, $meta_key ) {
		$product_id = self::rule_product_id( $product_id );
		if ( ! $product_id ) {
			return false;
		}
		if ( get_post_meta( $product_id, $meta_key, true ) ) {
			return true;
		}
		foreach ( self::product_term_ids( $product_id ) as $term_id ) {
			if ( get_term_meta( $term_id, $meta_key, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** Resolve product/category plan rules. */
	private static function plans_for_product_rule( $product_id, $meta_key ) {
		$product_id = self::rule_product_id( $product_id );
		if ( ! $product_id ) {
			return array();
		}
		$plans = self::clean_plan_ids( get_post_meta( $product_id, $meta_key, true ) );
		foreach ( self::product_term_ids( $product_id ) as $term_id ) {
			$plans = array_merge( $plans, self::clean_plan_ids( get_term_meta( $term_id, $meta_key, true ) ) );
		}
		return self::clean_plan_ids( $plans );
	}

	/** Return product category term IDs safely. */
	private static function product_term_ids( $product_id ) {
		$terms = wp_get_post_terms( absint( $product_id ), 'product_cat', array( 'fields' => 'ids' ) );
		return is_wp_error( $terms ) ? array() : array_map( 'absint', $terms );
	}

	/** Use the parent product for variation-level rule evaluation. */
	private static function rule_product_id( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return 0;
		}
		$parent = wp_get_post_parent_id( $product_id );
		return $parent && 'product' === get_post_type( $parent ) ? $parent : $product_id;
	}

	/** Clean a list of plan/subscription IDs. */
	private static function clean_plan_ids( $ids ) {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	}
}
