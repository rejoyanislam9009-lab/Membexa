<?php
/**
 * Setup automation and payment onboarding helpers.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates required pages safely and provides payment-provider setup shortcuts.
 */
final class Setup {
	const PAGE_META = '_membexa_system_page';

	/** Register setup hooks. */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'maybe_auto_setup' ), 20 );
		add_action( 'admin_menu', array( $this, 'menu' ), 22 );
		add_action( 'admin_post_membexa_setup_pages', array( $this, 'handle_setup_pages' ) );
		add_action( 'admin_footer', array( $this, 'payment_screen_extras' ) );
		add_action( 'admin_notices', array( $this, 'help_version_notice' ) );
	}

	/** Register setup screens. */
	public function menu() {
		add_submenu_page(
			'membexa',
			__( 'Membexa Setup', 'membexa' ),
			__( 'Setup', 'membexa' ),
			'manage_options',
			'membexa-setup',
			array( $this, 'page' )
		);

		add_submenu_page(
			null,
			__( 'PayPal Setup', 'membexa' ),
			__( 'PayPal Setup', 'membexa' ),
			'manage_options',
			'membexa-paypal-setup',
			array( $this, 'paypal_page' )
		);
	}

	/** Create pages automatically after activation or a qualifying upgrade. */
	public function maybe_auto_setup() {
		if ( ! get_option( 'membexa_setup_pages_pending', false ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->create_or_repair_pages();
		delete_option( 'membexa_setup_pages_pending' );
	}

	/** Handle the administrator one-click page setup action. */
	public function handle_setup_pages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to configure Membexa.', 'membexa' ) );
		}
		check_admin_referer( 'membexa_setup_pages' );

		$result = $this->create_or_repair_pages();
		$url    = add_query_arg(
			array(
				'page'            => 'membexa-setup',
				'membexa_setup'   => empty( $result['errors'] ) ? 'success' : 'partial',
				'membexa_created' => count( $result['created'] ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Create missing Membexa pages and connect them to plugin settings.
	 * Existing selected pages are never overwritten.
	 *
	 * @return array{created:array,reused:array,errors:array}
	 */
	private function create_or_repair_pages() {
		$general      = Settings::general();
		$integrations = Settings::integrations();
		$result       = array(
			'created' => array(),
			'reused'  => array(),
			'errors'  => array(),
		);

		foreach ( self::page_definitions() as $key => $definition ) {
			$current_id = $this->configured_page_id( $key, $general, $integrations );
			$page_id    = $this->valid_page_id( $current_id ) ? $current_id : 0;

			if ( ! $page_id ) {
				$page_id = $this->find_system_page( $key );
			}

			if ( $page_id ) {
				$this->repair_owned_page_shortcode( $page_id, $key, $definition['shortcode'] );
				$result['reused'][ $key ] = $page_id;
			} else {
				$page_id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $definition['title'],
						'post_name'    => $definition['slug'],
						'post_content' => $definition['shortcode'],
					),
					true
				);

				if ( is_wp_error( $page_id ) ) {
					$result['errors'][ $key ] = $page_id->get_error_message();
					continue;
				}

				update_post_meta( $page_id, self::PAGE_META, $key );
				$result['created'][ $key ] = $page_id;
			}

			$this->assign_page_id( $key, $page_id, $general, $integrations );
		}

		update_option( 'membexa_general', $general, false );
		update_option( 'membexa_integrations', $integrations, false );
		update_option( 'membexa_flush_rewrite_rules', 1, false );

		return $result;
	}

	/** Get required page definitions. */
	private static function page_definitions() {
		return array(
			'pricing' => array(
				'title'     => __( 'Membership Plans', 'membexa' ),
				'slug'      => 'membership-plans',
				'shortcode' => '[membexa_pricing]',
			),
			'join'    => array(
				'title'     => __( 'Join Membership', 'membexa' ),
				'slug'      => 'join-membership',
				'shortcode' => '[membexa_register]',
			),
			'login'   => array(
				'title'     => __( 'Member Login', 'membexa' ),
				'slug'      => 'member-login',
				'shortcode' => '[membexa_login]',
			),
			'account' => array(
				'title'     => __( 'My Membership', 'membexa' ),
				'slug'      => 'my-membership',
				'shortcode' => '[membexa_account]',
			),
		);
	}

	/** Resolve the current configured page ID. */
	private function configured_page_id( $key, $general, $integrations ) {
		if ( 'pricing' === $key ) {
			return absint( $general['pricing_page_id'] );
		}
		if ( 'account' === $key ) {
			return absint( $general['account_page_id'] );
		}
		if ( 'join' === $key ) {
			return absint( $integrations['join_page_id'] );
		}
		return absint( $integrations['login_page_id'] );
	}

	/** Assign a page ID to the relevant settings array. */
	private function assign_page_id( $key, $page_id, &$general, &$integrations ) {
		if ( 'pricing' === $key ) {
			$general['pricing_page_id'] = absint( $page_id );
		} elseif ( 'account' === $key ) {
			$general['account_page_id'] = absint( $page_id );
		} elseif ( 'join' === $key ) {
			$integrations['join_page_id'] = absint( $page_id );
		} else {
			$integrations['login_page_id'] = absint( $page_id );
		}
	}

	/** Validate an existing WordPress page ID. */
	private function valid_page_id( $page_id ) {
		$page = $page_id ? get_post( $page_id ) : null;
		return $page && 'page' === $page->post_type && 'trash' !== $page->post_status;
	}

	/** Find a page previously created and owned by Membexa. */
	private function find_system_page( $key ) {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::PAGE_META,
				'meta_value'     => $key,
			)
		);
		return empty( $pages ) ? 0 : absint( $pages[0] );
	}

	/** Append a missing shortcode only to pages explicitly created by Membexa. */
	private function repair_owned_page_shortcode( $page_id, $key, $shortcode ) {
		if ( get_post_meta( $page_id, self::PAGE_META, true ) !== $key ) {
			return;
		}
		$page = get_post( $page_id );
		if ( ! $page || has_shortcode( $page->post_content, trim( $shortcode, '[]' ) ) ) {
			return;
		}
		$content = trim( (string) $page->post_content );
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $content ? $content . "\n\n" . $shortcode : $shortcode,
			)
		);
	}

	/** Render the main setup page. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to configure Membexa.', 'membexa' ) );
		}

		$general      = Settings::general();
		$integrations = Settings::integrations();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Membexa Setup', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Create the required member pages automatically, repair missing Membexa pages, and jump directly to official payment-provider setup resources.', 'membexa' ); ?></p>

			<?php $this->setup_notice(); ?>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'Required pages', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Membexa creates missing pages and connects them to the correct settings automatically. Existing selected pages are preserved and never overwritten.', 'membexa' ); ?></p>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Page', 'membexa' ); ?></th><th><?php esc_html_e( 'Shortcode', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( self::page_definitions() as $key => $definition ) : ?>
						<?php $page_id = $this->configured_page_id( $key, $general, $integrations ); ?>
						<?php $this->page_status_row( $definition, $page_id ); ?>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
					<input type="hidden" name="action" value="membexa_setup_pages">
					<?php wp_nonce_field( 'membexa_setup_pages' ); ?>
					<?php submit_button( __( 'Create / Repair Membexa Pages', 'membexa' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'Payment provider setup', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Use only official provider websites for credentials and merchant onboarding.', 'membexa' ); ?></p>
				<p><strong>Stripe:</strong> <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Stripe API keys', 'membexa' ); ?></a></p>
				<p><strong>PayPal:</strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-paypal-setup' ) ); ?>"><?php esc_html_e( 'Open PayPal Setup Assistant', 'membexa' ); ?></a> · <a href="https://developer.paypal.com/dashboard/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PayPal Developer Dashboard', 'membexa' ); ?></a></p>
				<p><strong>bKash:</strong> <a href="https://www.bkash.com/en/business" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open bKash Business', 'membexa' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	/** Render one page status row. */
	private function page_status_row( $definition, $page_id ) {
		$page      = $this->valid_page_id( $page_id ) ? get_post( $page_id ) : null;
		$shortcode = trim( $definition['shortcode'], '[]' );
		$has_code  = $page && has_shortcode( $page->post_content, $shortcode );
		$status    = ! $page ? __( 'Missing', 'membexa' ) : ( $has_code ? __( 'Ready', 'membexa' ) : __( 'Selected page needs shortcode', 'membexa' ) );
		?>
		<tr>
			<td><?php echo esc_html( $definition['title'] ); ?></td>
			<td><code><?php echo esc_html( $definition['shortcode'] ); ?></code></td>
			<td><?php echo esc_html( $status ); ?></td>
			<td>
				<?php if ( $page ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php esc_html_e( 'Edit', 'membexa' ); ?></a>
					<?php if ( 'publish' === $page->post_status ) : ?>
						· <a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'membexa' ); ?></a>
					<?php endif; ?>
				<?php else : ?>
					—
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/** Render setup action feedback. */
	private function setup_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin feedback after a nonce-protected action.
		$status = isset( $_GET['membexa_setup'] ) ? sanitize_key( wp_unslash( $_GET['membexa_setup'] ) ) : '';
		if ( 'success' === $status ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Membexa pages are connected and ready.', 'membexa' ) . '</p></div>';
		} elseif ( 'partial' === $status ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Some pages could not be created. Check your WordPress permissions and try again.', 'membexa' ) . '</p></div>';
		}
	}

	/** Show an upgrade note on the older visual Help Center page. */
	public function help_version_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'membexa-help' ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Membexa 1.4 setup update:', 'membexa' ); ?></strong> <?php esc_html_e( 'Required member pages are now created and assigned automatically. Use Membexa → Setup to create or repair them in one click; manual shortcode page creation is now optional for custom layouts.', 'membexa' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-setup' ) ); ?>"><?php esc_html_e( 'Open Setup', 'membexa' ); ?></a></p>
		</div>
		<?php
	}

	/** Render the PayPal setup assistant. */
	public function paypal_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to configure PayPal.', 'membexa' ) );
		}

		$payments    = Settings::payments();
		$connect_url = $this->paypal_partner_connect_url();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'PayPal Setup Assistant', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Connect PayPal using an approved partner onboarding flow when available, or use the official PayPal Developer Dashboard for standard credential setup.', 'membexa' ); ?></p>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'Automatic PayPal connection', 'membexa' ); ?></h2>
				<?php if ( $connect_url ) : ?>
					<p><?php esc_html_e( 'Automatic onboarding is available for this installation. PayPal will ask the merchant to log in and consent before sharing approved integration credentials.', 'membexa' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( $connect_url ); ?>"><?php esc_html_e( 'Connect PayPal Automatically', 'membexa' ); ?></a></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Automatic credential sharing requires the Membexa distributor to be approved by PayPal for Partner Referrals / software onboarding and to provide a secure partner connection service. It is intentionally disabled until that approval and secure service are configured.', 'membexa' ); ?></p>
					<p><span class="button disabled" aria-disabled="true"><?php esc_html_e( 'Automatic Connect — Partner approval required', 'membexa' ); ?></span></p>
				<?php endif; ?>
			</div>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'Standard setup', 'membexa' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Open the PayPal Developer Dashboard and sign in with your PayPal account.', 'membexa' ); ?></li>
					<li><?php esc_html_e( 'Create or open a REST application and copy its Client ID and Client Secret.', 'membexa' ); ?></li>
					<li><?php esc_html_e( 'Paste the credentials into Membexa → Settings → Payments and save.', 'membexa' ); ?></li>
					<li><?php esc_html_e( 'Create a webhook using the PayPal webhook URL shown by Membexa and save its Webhook ID.', 'membexa' ); ?></li>
				</ol>
				<p><a class="button button-primary" href="https://developer.paypal.com/dashboard/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open PayPal Developer Dashboard', 'membexa' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=payments' ) ); ?>"><?php esc_html_e( 'Open Membexa Payment Settings', 'membexa' ); ?></a></p>
			</div>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'Current PayPal status', 'membexa' ); ?></h2>
				<p><?php echo esc_html( Settings::paypal_client_id() ? '✓ ' . __( 'Client ID configured', 'membexa' ) : '— ' . __( 'Client ID missing', 'membexa' ) ); ?></p>
				<p><?php echo esc_html( Settings::paypal_client_secret() ? '✓ ' . __( 'Client Secret configured', 'membexa' ) : '— ' . __( 'Client Secret missing', 'membexa' ) ); ?></p>
				<p><?php echo esc_html( Settings::paypal_webhook_id() ? '✓ ' . __( 'Webhook ID configured', 'membexa' ) : '— ' . __( 'Webhook ID missing', 'membexa' ) ); ?></p>
				<p><?php echo esc_html( ! empty( $payments['paypal_sandbox'] ) ? __( 'Mode: Sandbox', 'membexa' ) : __( 'Mode: Live', 'membexa' ) ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Resolve a PayPal-approved partner onboarding URL when one is configured.
	 *
	 * The URL must point to a secure service that owns the partner credentials.
	 * PayPal partner secrets must never be shipped in this downloadable plugin.
	 *
	 * @return string
	 */
	private function paypal_partner_connect_url() {
		$url = defined( 'MEMBEXA_PAYPAL_PARTNER_CONNECT_URL' ) ? (string) MEMBEXA_PAYPAL_PARTNER_CONNECT_URL : '';
		/**
		 * Filter the approved PayPal partner onboarding URL.
		 *
		 * @param string $url        Current onboarding URL.
		 * @param string $return_url Membexa payment settings return URL.
		 */
		$url = apply_filters( 'membexa_paypal_partner_connect_url', $url, admin_url( 'admin.php?page=membexa-settings&tab=payments' ) );
		return esc_url_raw( $url );
	}

	/** Add official provider links and the PayPal setup button on Payments settings. */
	public function payment_screen_extras() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'membexa-settings' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings tab selection.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( 'payments' !== $tab ) {
			return;
		}

		$data = array(
			array(
				'url'   => 'https://dashboard.stripe.com/apikeys',
				'label' => __( 'Official Stripe API keys', 'membexa' ),
			),
			array(
				'url'        => 'https://developer.paypal.com/dashboard/',
				'label'      => __( 'Official PayPal Developer Dashboard', 'membexa' ),
				'setupUrl'   => admin_url( 'admin.php?page=membexa-paypal-setup' ),
				'setupLabel' => __( 'Setup PayPal', 'membexa' ),
			),
			array(
				'url'   => 'https://www.bkash.com/en/business',
				'label' => __( 'Official bKash Business', 'membexa' ),
			),
		);
		?>
		<script>
		(function () {
			'use strict';
			var sections = document.querySelectorAll('.membexa-admin form[action="options.php"] h2');
			var providers = <?php echo wp_json_encode( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is generated from fixed, escaped admin data. ?>;
			providers.forEach(function (provider, index) {
				if (!sections[index] || sections[index].nextElementSibling && sections[index].nextElementSibling.classList.contains('membexa-provider-links')) {
					return;
				}
				var row = document.createElement('p');
				row.className = 'description membexa-provider-links';
				var link = document.createElement('a');
				link.href = provider.url;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = provider.label;
				row.appendChild(link);
				if (provider.setupUrl) {
					row.appendChild(document.createTextNode(' · '));
					var setup = document.createElement('a');
					setup.href = provider.setupUrl;
					setup.className = 'button button-small';
					setup.textContent = provider.setupLabel;
					row.appendChild(setup);
				}
				sections[index].insertAdjacentElement('afterend', row);
			});
		}());
		</script>
		<?php
	}
}
