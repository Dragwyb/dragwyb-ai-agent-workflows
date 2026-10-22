<?php
/**
 * Shared empty-state markup for admin list screens.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Admin;

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
	 * @param string                                                                                                         $title       Heading.
	 * @param string                                                                                                         $description Supporting paragraph.
	 * @param array<int, string>                                                                                             $steps       Optional numbered guidance steps.
	 * @param array<int, array{url: string, label: string, primary?: bool, title?: string, target?: string, rel?: string}> $actions     CTA buttons.
	 *
	 * @return void
	 */
	public static function render( string $title, string $description, array $steps = array(), array $actions = array() ): void {
		echo '<div class="dragaiw-empty-state" role="status">';
		echo '<h2 class="dragaiw-empty-state__title">' . esc_html( $title ) . '</h2>';
		echo '<p class="dragaiw-empty-state__description">' . esc_html( $description ) . '</p>';

		if ( array() !== $steps ) {
			echo '<ol class="dragaiw-empty-state__steps">';
			foreach ( $steps as $step ) {
				echo '<li>' . esc_html( (string) $step ) . '</li>';
			}
			echo '</ol>';
		}

		if ( array() !== $actions ) {
			echo '<p class="dragaiw-empty-state__actions">';
			foreach ( $actions as $action ) {
				$class  = ! empty( $action['primary'] ) ? 'button button-primary' : 'button';
				$title  = ! empty( $action['title'] ) ? (string) $action['title'] : '';
				$target = ! empty( $action['target'] ) ? (string) $action['target'] : '';
				$rel    = ! empty( $action['rel'] ) ? (string) $action['rel'] : ( $target ? 'noopener noreferrer' : '' );

				echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( (string) $action['url'] ) . '"';
				if ( '' !== $title ) {
					echo ' title="' . esc_attr( $title ) . '"';
				}
				if ( '' !== $target ) {
					echo ' target="' . esc_attr( $target ) . '" rel="' . esc_attr( $rel ) . '"';
				}
				echo '>' . esc_html( (string) $action['label'] ) . '</a> ';
			}
			echo '</p>';
		}


		echo '</div>';
	}
}
