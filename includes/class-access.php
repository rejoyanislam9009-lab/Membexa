<?php
/**
 * Content access rules.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Protects post content according to membership plans.
 */
final class Access {
	/**
	 * Register access-control hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'init', array( $this, 'register_rest_filters' ), 100 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_excerpt' ), 20 );
	}

	/**
	 * Protect REST responses for public REST-enabled post types.
	 *
	 * @return void
	 */
	public function register_rest_filters() {
		$post_types = get_post_types(
			array(
				'public'       => true,
				'show_in_rest' => true,
			),
			'names'
		);
		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			add_filter( 'rest_prepare_' . $post_type, array( $this, 'filter_rest' ), 20, 3 );
		}
	}

	/**
	 * Add the access panel to public content types.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);
		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			add_meta_box( 'membexa_access', __( 'Membexa Access', 'membexa' ), array( $this, 'render_meta_box' ), $post_type, 'side', 'default' );
		}
	}

	/**
	 * Render content access controls.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'membexa_save_access', 'membexa_access_nonce' );
		$restricted = (bool) get_post_meta( $post->ID, '_membexa_restricted', true );
		$selected   = array_map( 'absint', (array) get_post_meta( $post->ID, '_membexa_plan_ids', true ) );
		?>
		<p>
			<label><input type="checkbox" name="membexa_restricted" value="1" <?php checked( $restricted ); ?>> <?php esc_html_e( 'Restrict this content to members', 'membexa' ); ?></label>
		</p>
		<p><strong><?php esc_html_e( 'Allowed plans', 'membexa' ); ?></strong></p>
		<div class="membexa-access-plans">
			<?php foreach ( Plan::all() as $plan ) : ?>
				<label class="membexa-access-plan">
					<input type="checkbox" name="membexa_plan_ids[]" value="<?php echo esc_attr( $plan['id'] ); ?>" <?php checked( in_array( (int) $plan['id'], $selected, true ) ); ?>>
					<?php echo esc_html( $plan['name'] ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e( 'If no plan is selected, restricted content is hidden from every visitor who cannot edit the post.', 'membexa' ); ?></p>
		<?php
	}

	/**
	 * Save post access rules.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( $post_id ) {
		if ( Plan::POST_TYPE === get_post_type( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['membexa_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_access_nonce'] ) ), 'membexa_save_access' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_membexa_restricted', empty( $_POST['membexa_restricted'] ) ? 0 : 1 );
		$plan_ids = isset( $_POST['membexa_plan_ids'] ) && is_array( $_POST['membexa_plan_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['membexa_plan_ids'] ) ) : array();
		update_post_meta( $post_id, '_membexa_plan_ids', array_values( array_filter( $plan_ids ) ) );
	}

	/**
	 * Determine whether a user can view a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	public static function can_view( $post_id, $user_id = 0 ) {
		if ( ! get_post_meta( $post_id, '_membexa_restricted', true ) ) {
			return true;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( $user_id && user_can( $user_id, 'edit_post', $post_id ) ) {
			return true;
		}

		$plans = array_map( 'absint', (array) get_post_meta( $post_id, '_membexa_plan_ids', true ) );
		return $user_id && ! empty( $plans ) && Subscriptions::user_has_plan( $user_id, $plans );
	}

	/**
	 * Replace restricted content in standard main-loop views, including feeds
	 * and archive pages that choose to render full post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || self::can_view( $post_id ) ) {
			return $content;
		}
		return $this->restricted_message();
	}

	/**
	 * Hide restricted excerpts.
	 *
	 * @param string $excerpt Post excerpt.
	 * @return string
	 */
	public function filter_excerpt( $excerpt ) {
		if ( is_admin() ) {
			return $excerpt;
		}

		$post_id = get_the_ID();
		return $post_id && ! self::can_view( $post_id ) ? wp_strip_all_tags( $this->restricted_message() ) : $excerpt;
	}

	/**
	 * Remove protected content from REST responses when access is denied.
	 *
	 * @param \WP_REST_Response $response REST response.
	 * @param \WP_Post          $post     Post object.
	 * @return \WP_REST_Response
	 */
	public function filter_rest( $response, $post ) {
		if ( ! $post || self::can_view( $post->ID ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( isset( $data['content'] ) ) {
			$data['content']['rendered']  = wp_kses_post( $this->restricted_message() );
			$data['content']['protected'] = true;
		}
		if ( isset( $data['excerpt'] ) ) {
			$data['excerpt']['rendered'] = '';
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Build the restricted-content notice.
	 *
	 * @return string
	 */
	private function restricted_message() {
		$settings = Settings::general();
		$message  = $settings['access_message'] ? $settings['access_message'] : __( 'This content is available to members with an eligible plan.', 'membexa' );
		$link     = $settings['pricing_page_id'] ? get_permalink( $settings['pricing_page_id'] ) : '';
		$html     = '<div class="membexa-access-message"><p>' . esc_html( $message ) . '</p>';
		if ( $link ) {
			$html .= '<p><a class="button" href="' . esc_url( $link ) . '">' . esc_html__( 'View membership plans', 'membexa' ) . '</a></p>';
		}
		return $html . '</div>';
	}
}
