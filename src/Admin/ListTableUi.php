<?php
/**
 * Shared list-table UI helpers.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small helpers reused across admin list tables.
 */
final class ListTableUi {

	/**
	 * Renders hidden fields that preserve current GET filters on bulk POST.
	 *
	 * @param array<string, scalar|null> $filters Query args to preserve.
	 *
	 * @return void
	 */
	public static function renderPreservedFilters( array $filters ): void {
		foreach ( $filters as $name => $value ) {
			if ( null === $value || '' === $value || 0 === $value ) {
				continue;
			}

			printf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( (string) $name ),
				esc_attr( (string) $value )
			);
		}
	}

	/**
	 * @param string               $which   `top` or `bottom`.
	 * @param array<string, mixed> $filters Current GET filter values.
	 *
	 * @return void
	 */
	public static function renderFilterBar( string $which, array $filters ): void {
		if ( 'top' !== $which ) {
			return;
		}

		echo '<div class="alignleft actions aiawa-list-table-filters">';

		foreach ( $filters as $filter ) {
			if ( ! is_array( $filter ) || empty( $filter['name'] ) ) {
				continue;
			}

			$name  = (string) $filter['name'];
			$type  = (string) ( $filter['type'] ?? 'select' );
			$label = (string) ( $filter['label'] ?? '' );
			$value = $filter['value'] ?? '';

			if ( 'search' === $type ) {
				printf(
					'<label class="screen-reader-text" for="aiawa-filter-%1$s">%2$s</label>'
						. '<input type="search" id="aiawa-filter-%1$s" name="%1$s" value="%3$s" placeholder="%4$s" />',
					esc_attr( $name ),
					esc_html( $label ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $filter['placeholder'] ?? $label ) )
				);
				continue;
			}

			$options = is_array( $filter['options'] ?? null ) ? $filter['options'] : array();

			printf(
				'<label class="screen-reader-text" for="aiawa-filter-%1$s">%2$s</label>'
					. '<select id="aiawa-filter-%1$s" name="%1$s">',
				esc_attr( $name ),
				esc_html( $label )
			);

			foreach ( $options as $option_value => $option_label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( (string) $option_value ),
					selected( (string) $option_value, (string) $value, false ),
					esc_html( (string) $option_label )
				);
			}

			echo '</select>';
		}

		submit_button( __( 'Filter', 'workflow-automate' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Opens a bulk-action POST form with an explicit admin URL and a
	 * dedicated nonce field (avoids clashing with WP_List_Table's
	 * `action` / `_wpnonce` fields).
	 *
	 * @param string $page_slug    Admin page slug (`$_GET['page']`).
	 * @param string $nonce_action Nonce action string.
	 * @param string $bulk_flag    Hidden POST flag that marks bulk submits.
	 *
	 * @return void
	 */
	public static function openBulkForm( string $page_slug, string $nonce_action, string $bulk_flag ): void {
		printf(
			'<form method="post" action="%s" class="aiawa-list-table-bulk-form">',
			esc_url( admin_url( 'admin.php?page=' . $page_slug ) )
		);
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $page_slug ) );
		wp_nonce_field( $nonce_action, 'aiawa_bulk_nonce' );
		printf( '<input type="hidden" name="%s" value="1" />', esc_attr( $bulk_flag ) );
	}

	/**
	 * @param string $nonce_action Nonce action used in openBulkForm().
	 *
	 * @return bool
	 */
	public static function verifyBulkNonce( string $nonce_action ): bool {
		if ( ! isset( $_POST['aiawa_bulk_nonce'] ) ) {
			return false;
		}

		return (bool) wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['aiawa_bulk_nonce'] ) ),
			$nonce_action
		);
	}

	/**
	 * @return void
	 */
	public static function closeBulkForm(): void {
		echo '</form>';
	}
}
