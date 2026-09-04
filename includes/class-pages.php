<?php
/**
 * Automatic front-end page provisioning.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Creates, repairs, and assigns the standard Membexa pages. */
final class Pages {
	/** Page definitions. */
	public static function definitions() {
		return array(
			'pricing' => array( 'title' => __( 'Membership Plans', 'membexa' ), 'slug' => 'membership-plans', 'content' => '[membexa_pricing]' ),
			'join'    => array( 'title' => __( 'Join Membership', 'membexa' ), 'slug' => 'join-membership', 'content' => '[membexa_register]' ),
			'login'   => array( 'title' => __( 'Member Login', 'membexa' ), 'slug' => 'member-login', 'content' => '[membexa_login]' ),
			'account' => array( 'title' => __( 'My Membership', 'membexa' ), 'slug' => 'my-membership', 'content' => '[membexa_account]' ),
		);
	}

	/** Create missing pages and connect all page settings. */
	public static function ensure() {
		$ids = array();
		foreach ( self::definitions() as $key => $definition ) {
			$stored = absint( get_option( 'membexa_page_' . $key, 0 ) );
			if ( $stored && 'trash' !== get_post_status( $stored ) && get_post( $stored ) ) {
				$ids[ $key ] = $stored;
				continue;
			}

			$existing = get_page_by_path( $definition['slug'], OBJECT, 'page' );
			if ( $existing && 'trash' !== get_post_status( $existing->ID ) ) {
				$page_id = (int) $existing->ID;
			} else {
				$page_id = wp_insert_post(
					array(
						'post_title'   => $definition['title'],
						'post_name'    => $definition['slug'],
						'post_content' => $definition['content'],
						'post_status'  => 'publish',
						'post_type'    => 'page',
					),
					true
				);
				if ( is_wp_error( $page_id ) ) {
					continue;
				}
			}
			$ids[ $key ] = (int) $page_id;
			update_option( 'membexa_page_' . $key, (int) $page_id, false );
		}

		$general = Settings::general();
		if ( ! empty( $ids['pricing'] ) ) {
			$general['pricing_page_id'] = $ids['pricing'];
		}
		if ( ! empty( $ids['account'] ) ) {
			$general['account_page_id'] = $ids['account'];
		}
		update_option( 'membexa_general', $general, false );

		$integrations = Settings::integrations();
		if ( ! empty( $ids['join'] ) ) {
			$integrations['join_page_id'] = $ids['join'];
		}
		if ( ! empty( $ids['login'] ) ) {
			$integrations['login_page_id'] = $ids['login'];
		}
		update_option( 'membexa_integrations', $integrations, false );
		update_option( 'membexa_flush_rewrite_rules', 1, false );

		return $ids;
	}

	/** Return setup status for every standard page. */
	public static function status() {
		$status = array();
		foreach ( self::definitions() as $key => $definition ) {
			$page_id        = absint( get_option( 'membexa_page_' . $key, 0 ) );
			$status[ $key ] = array(
				'id'      => $page_id,
				'title'   => $definition['title'],
				'content' => $definition['content'],
				'healthy' => $page_id && 'publish' === get_post_status( $page_id ) && false !== strpos( (string) get_post_field( 'post_content', $page_id ), $definition['content'] ),
			);
		}
		return $status;
	}
}
