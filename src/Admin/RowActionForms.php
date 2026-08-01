<?php
/**
 * Collects row-action POST forms outside list-table bulk forms.
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
 * WP_List_Table bulk actions require a wrapping POST form, but this
 * plugin's row actions also POST (never GET). Nesting those forms is
 * invalid HTML and breaks submits. This helper renders each row action
 * as a detached <form> plus a <button form="…"> trigger in the table.
 */
final class RowActionForms {

	/**
	 * @var array<string, string> form id => markup.
	 */
	private array $forms = array();

	/**
	 * @param string      $form_id  Unique DOM id for the detached form.
	 * @param string      $markup   Full <form>…</form> HTML.
	 * @param string      $label    Button label.
	 * @param string      $class    Button CSS class(es).
	 * @param string|null $confirm  Optional browser confirm() message.
	 *
	 * @return string Submit button markup for use inside the table.
	 */
	public function registerButton( string $form_id, string $markup, string $label, string $class = 'aiawa-row-action-button', ?string $confirm = null ): string {
		$this->forms[ $form_id ] = $markup;

		$confirm_attr = null !== $confirm
			? sprintf(
				' onclick="return confirm(%s);"',
				wp_json_encode( $confirm )
			)
			: '';

		return sprintf(
			'<button type="submit" class="%1$s" form="%2$s"%3$s>%4$s</button>',
			esc_attr( $class ),
			esc_attr( $form_id ),
			$confirm_attr,
			esc_html( $label )
		);
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( array() === $this->forms ) {
			return;
		}

		echo '<div class="aiawa-detached-row-action-forms" hidden aria-hidden="true">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each stored form was built with escaping at registration time.
		echo implode( '', $this->forms );
		echo '</div>';
	}
}
