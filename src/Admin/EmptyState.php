<?php
/**
 * Shared empty-state markup for admin list screens.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a consistent empty-state panel (roadmap item 16) used when a
 * list screen has nothing to show yet — friendlier than WP_List_Table's
 * default "No items found." line, and the place the guided-first-workflow
 * copy lives on the Workflows screen.
 */
class EmptyState {

	/**
	 * @param string               $title       Heading.
	 * @param string               $description Supporting paragraph.
	 * @param array<int, string>   $steps       Optional numbered guidance steps.
	 * @param array<int, array{url: string, label: string, primary?: bool}> $actions CTA buttons.
	 *
	 * @return void
	 */
	public static function render( string $title, string $description, array $steps = array(), array $actions = array() ): void {
		echo '<div class="wfa-empty-state" role="status">';
		echo '<h2 class="wfa-empty-state__title">' . esc_html( $title ) . '</h2>';
		echo '<p class="wfa-empty-state__description">' . esc_html( $description ) . '</p>';

		if ( array() !== $steps ) {
			echo '<ol class="wfa-empty-state__steps">';
			foreach ( $steps as $step ) {
				echo '<li>' . esc_html( (string) $step ) . '</li>';
			}
			echo '</ol>';
		}

		if ( array() !== $actions ) {
			echo '<p class="wfa-empty-state__actions">';
			foreach ( $actions as $action ) {
				$class = ! empty( $action['primary'] ) ? 'button button-primary' : 'button';
				printf(
					'<a class="%1$s" href="%2$s">%3$s</a> ',
					esc_attr( $class ),
					esc_url( (string) $action['url'] ),
					esc_html( (string) $action['label'] )
				);
			}
			echo '</p>';
		}

		echo '</div>';
	}
}
