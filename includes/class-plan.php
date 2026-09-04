<?php
/**
 * Membership plan post type.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and manages membership plans.
 */
final class Plan {
	const POST_TYPE = 'membexa_plan';

	/** Register hooks. */
	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ) );
	}

	/** Register the internal membership plan post type. */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Plans', 'membexa' ),
					'singular_name' => __( 'Plan', 'membexa' ),
					'add_new_item'  => __( 'Add New Plan', 'membexa' ),
					'edit_item'     => __( 'Edit Plan', 'membexa' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'membexa',
				'supports'            => array( 'title', 'editor', 'page-attributes' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
			)
		);
	}

	/** Register plan meta boxes. */
	public function add_meta_boxes() {
		add_meta_box( 'membexa_plan_details', __( 'Plan Details', 'membexa' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Render plan configuration fields.
	 *
	 * @param \WP_Post $post Plan post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'membexa_save_plan', 'membexa_plan_nonce' );
		$price      = get_post_meta( $post->ID, '_membexa_price', true );
		$currency   = get_post_meta( $post->ID, '_membexa_currency', true );
		$billing    = get_post_meta( $post->ID, '_membexa_billing', true );
		$trial_days = get_post_meta( $post->ID, '_membexa_trial_days', true );
		$features   = get_post_meta( $post->ID, '_membexa_features', true );
		$currency   = $currency ? $currency : Settings::general()['default_currency'];
		$billing    = $billing ? $billing : 'free';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="membexa_price"><?php esc_html_e( 'Price', 'membexa' ); ?></label></th>
				<td><input class="regular-text" type="number" min="0" step="0.01" id="membexa_price" name="membexa_price" value="<?php echo esc_attr( $price ); ?>"></td>
			</tr>
			<tr>
				<th><label for="membexa_currency"><?php esc_html_e( 'Currency', 'membexa' ); ?></label></th>
				<td>
					<input class="small-text" maxlength="3" id="membexa_currency" name="membexa_currency" value="<?php echo esc_attr( $currency ); ?>">
					<p class="description"><?php esc_html_e( 'Three-letter ISO currency code, for example USD, EUR, GBP, SAR, AED or BDT.', 'membexa' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="membexa_billing"><?php esc_html_e( 'Billing', 'membexa' ); ?></label></th>
				<td>
					<select id="membexa_billing" name="membexa_billing">
						<?php foreach ( self::billing_options() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $billing, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="membexa_trial_days"><?php esc_html_e( 'Trial days', 'membexa' ); ?></label></th>
				<td>
					<input class="small-text" type="number" min="0" max="365" id="membexa_trial_days" name="membexa_trial_days" value="<?php echo esc_attr( $trial_days ); ?>">
					<p class="description"><?php esc_html_e( 'Payment gateway add-ons can use this value when their billing API supports trial periods.', 'membexa' ); ?></p>
				</td>
			</tr>
			<?php do_action( 'membexa_plan_payment_fields', $post ); ?>
			<tr>
				<th><label for="membexa_features"><?php esc_html_e( 'Features', 'membexa' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="6" id="membexa_features" name="membexa_features"><?php echo esc_textarea( $features ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One feature per line. Used by the pricing shortcode.', 'membexa' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save plan metadata.
	 *
	 * @param int $post_id Plan post ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['membexa_plan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_plan_nonce'] ) ), 'membexa_save_plan' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$billing_allowed = array_keys( self::billing_options() );
		$billing         = isset( $_POST['membexa_billing'] ) ? sanitize_key( wp_unslash( $_POST['membexa_billing'] ) ) : 'free';
		$billing         = in_array( $billing, $billing_allowed, true ) ? $billing : 'free';
		$currency        = isset( $_POST['membexa_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['membexa_currency'] ) ) ) : 'USD';
		$currency        = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : 'USD';
		$price_raw       = isset( $_POST['membexa_price'] ) ? sanitize_text_field( wp_unslash( $_POST['membexa_price'] ) ) : '0';
		$price           = max( 0, (float) $price_raw );

		update_post_meta( $post_id, '_membexa_price', number_format( $price, 2, '.', '' ) );
		update_post_meta( $post_id, '_membexa_currency', $currency );
		update_post_meta( $post_id, '_membexa_billing', $billing );
		update_post_meta( $post_id, '_membexa_trial_days', isset( $_POST['membexa_trial_days'] ) ? min( 365, absint( $_POST['membexa_trial_days'] ) ) : 0 );
		update_post_meta( $post_id, '_membexa_features', isset( $_POST['membexa_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['membexa_features'] ) ) : '' );
		do_action( 'membexa_save_plan_payment_fields', $post_id );
	}

	/** Return supported billing models. */
	public static function billing_options() {
		return array(
			'free'     => __( 'Free', 'membexa' ),
			'one_time' => __( 'One-time payment', 'membexa' ),
			'monthly'  => __( 'Monthly recurring', 'membexa' ),
			'yearly'   => __( 'Yearly recurring', 'membexa' ),
			'lifetime' => __( 'Lifetime', 'membexa' ),
		);
	}

	/**
	 * Get a published plan.
	 *
	 * @param int $plan_id Plan post ID.
	 * @return array|null
	 */
	public static function get( $plan_id ) {
		$post = get_post( $plan_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		return array(
			'id'              => $post->ID,
			'name'            => $post->post_title,
			'description'     => $post->post_content,
			'price'           => (float) get_post_meta( $post->ID, '_membexa_price', true ),
			'currency'        => (string) get_post_meta( $post->ID, '_membexa_currency', true ),
			'billing'         => (string) get_post_meta( $post->ID, '_membexa_billing', true ),
			'trial_days'      => absint( get_post_meta( $post->ID, '_membexa_trial_days', true ) ),
			'stripe_price_id' => (string) get_post_meta( $post->ID, '_membexa_stripe_price_id', true ),
			'paypal_plan_id'  => (string) get_post_meta( $post->ID, '_membexa_paypal_plan_id', true ),
			'features'        => (string) get_post_meta( $post->ID, '_membexa_features', true ),
		);
	}

	/** Return all published membership plans. */
	public static function all() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		$plans = array();
		foreach ( $posts as $post ) {
			$plan = self::get( $post->ID );
			if ( $plan ) {
				$plans[] = $plan;
			}
		}
		return $plans;
	}
}
