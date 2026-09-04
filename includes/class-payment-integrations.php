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
	}

	/** Add payment product mapping to a Membexa plan. */
	public function add_plan_box() {
		add_meta_box(
			'membexa_payment_integration',
			__( 'Payment Integration', 'membexa' ),
			array( $this, 'render_plan_box' ),
			Plan::POST_TYPE,
			'side',
			'high'
		);
	}

	/** Render WooCommerce product mapping. */
	public function render_plan_box( $post ) {
		wp_nonce_field( 'membexa_save_payment_product', 'membexa_payment_product_nonce' );
		$product_id = Gateways::product_id_for_plan( $post->ID );
		?>
		<p><?php esc_html_e( 'Membexa does not store payment credentials. Link this paid plan to a WooCommerce product; payment methods come from separate WooCommerce gateway plugins.', 'membexa' ); ?></p>
		<p>
			<label for="membexa-payment-product"><strong><?php esc_html_e( 'WooCommerce product', 'membexa' ); ?></strong></label>
			<select id="membexa-payment-product" name="membexa_payment_product_id" style="width:100%;">
				<option value="0"><?php esc_html_e( '— Select product —', 'membexa' ); ?></option>
				<?php foreach ( $this->products() as $product ) : ?>
					<option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>><?php echo esc_html( $product->get_name() . ' (#' . $product->get_id() . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php if ( ! Account::woocommerce_active() ) : ?>
			<p class="description"><?php esc_html_e( 'Install and activate WooCommerce to link paid plans.', 'membexa' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'One-time/lifetime: use a normal virtual product. Monthly/yearly: use a subscription product from a compatible WooCommerce subscriptions extension.', 'membexa' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/** Save linked WooCommerce product. */
	public function save_plan_product( $post_id ) {
		if ( ! isset( $_POST['membexa_payment_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_payment_product_nonce'] ) ), 'membexa_save_payment_product' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$product_id = isset( $_POST['membexa_payment_product_id'] ) ? absint( $_POST['membexa_payment_product_id'] ) : 0;
		if ( $product_id && ( ! Account::woocommerce_active() || ! wc_get_product( $product_id ) ) ) {
			$product_id = 0;
		}
		update_post_meta( $post_id, '_membexa_payment_product_id', $product_id );
	}

	/** Automatically grant the plan linked to a purchased product. */
	public function linked_plan_grant( $plans, $product_id ) {
		$product_id = absint( $product_id );
		$parent_id  = wp_get_post_parent_id( $product_id );
		$product_id = $parent_id && 'product' === get_post_type( $parent_id ) ? $parent_id : $product_id;
		foreach ( Plan::all() as $plan ) {
			if ( $product_id === Gateways::product_id_for_plan( $plan['id'] ) ) {
				$plans[] = $plan['id'];
			}
		}
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $plans ) ) ) );
	}

	/** Get manageable WooCommerce products. */
	private function products() {
		if ( ! Account::woocommerce_active() || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		return wc_get_products(
			array(
				'status'  => array( 'publish', 'draft', 'private' ),
				'limit'   => 200,
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
	}
}
