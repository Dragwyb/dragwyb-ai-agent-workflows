<?php
/**
 * Connection create/edit admin page.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Admin\Pages;

use AIAWAB\Plugin\Admin\AdminPage;
use AIAWAB\Plugin\Core\Capabilities;
use AIAWAB\Plugin\Domain\Connection;
use AIAWAB\Plugin\Service\ConnectionAuthTypes;
use AIAWAB\Plugin\Service\ConnectionService;
use AIAWAB\Plugin\Service\GoogleOAuthService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu-hidden create/edit screen (same "reachable only via a link" pattern
 * as BuilderPage/RunDetailPage), backed by ConnectionActionsController.
 *
 * Deliberately plain server-rendered HTML rather than the React builder's
 * approach: a credential form has no canvas/drag-and-drop need, the same
 * reasoning RunsPage/RunDetailPage (item 9) and SettingsPage (item 10)
 * already used to justify a classic wp-admin screen over a second SPA.
 *
 * Creating a new connection is a two-step GET-then-POST flow rather than
 * one form with a JS-driven "change these fields based on the selected
 * auth type" script: this plugin's admin screens are otherwise entirely
 * JS-free (see architecture.md's "REST only where something actually
 * consumes it" discipline, applied here to client-side JS instead) and a
 * plain HTTP round trip accomplishes the same "show the right fields for
 * the chosen type" result without introducing a first bit of admin-side
 * JavaScript for one screen. Step 1 collects integration/label/auth type
 * via a GET form (safe: nothing is written yet); step 2 is the real,
 * nonce-protected POST that creates the row.
 */
class ConnectionFormPage implements AdminPage {

	public const SLUG = 'wfa-connection-form';

	private ConnectionService $connections;

	private GoogleOAuthService $google_oauth;

	public function __construct( ConnectionService $connections, GoogleOAuthService $google_oauth ) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return self::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pageTitle(): string {
		return __( 'Connection', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Connection', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_CONNECTIONS;
	}

	/**
	 * {@inheritDoc}
	 */
	public function showInMenu(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueueAssets(): void {
		wp_enqueue_style(
			'wfa-admin',
			WFA_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			WFA_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'workflow-automate' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which connection to load/create; the admin-post controller this feeds still re-checks capability and nonce on every write.
		$id = isset( $_GET['connection'] ) ? absint( wp_unslash( $_GET['connection'] ) ) : 0;

		echo '<div class="wrap wfa-admin-page">';
		$this->renderNotice();

		if ( $id > 0 ) {
			$connection = $this->connections->find( $id );

			if ( null === $connection ) {
				echo '<h1>' . esc_html( $this->pageTitle() ) . '</h1>';
				echo '<p>' . esc_html__( 'That connection no longer exists.', 'workflow-automate' ) . '</p>';
				$this->renderBackLink();
				echo '</div>';

				return;
			}

			echo '<h1>' . esc_html__( 'Edit Connection', 'workflow-automate' ) . '</h1>';
			$this->renderBackLink();
			$this->renderEditForm( $connection );
			echo '</div>';

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only step-1-to-step-2 selector for this GET-only "choose a type" screen; nothing is written until the real POST form in renderCreateForm() below.
		$auth_type = isset( $_GET['auth_type'] ) ? sanitize_key( wp_unslash( $_GET['auth_type'] ) ) : '';

		echo '<h1>' . esc_html__( 'Add New Connection', 'workflow-automate' ) . '</h1>';
		$this->renderBackLink();

		if ( in_array( $auth_type, ConnectionAuthTypes::VALID, true ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill from the step-1 GET form; re-validated/sanitized again on the real POST submit.
			$integration_slug = isset( $_GET['integration_slug'] ) ? sanitize_key( wp_unslash( $_GET['integration_slug'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill from the step-1 GET form; re-validated/sanitized again on the real POST submit.
			$label = isset( $_GET['label'] ) ? sanitize_text_field( wp_unslash( $_GET['label'] ) ) : '';

			if ( ConnectionAuthTypes::OAUTH2 === $auth_type ) {
				$this->renderOAuthSetupHelp();
			}

			$this->renderCreateForm( $auth_type, $integration_slug, $label );
		} else {
			$this->renderChooseTypeForm();
		}

		echo '</div>';
	}

	/**
	 * @return void
	 */
	private function renderBackLink(): void {
		printf(
			'<p><a href="%1$s">&larr; %2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . ConnectionsPage::SLUG ) ),
			esc_html__( 'Back to Connections', 'workflow-automate' )
		);
	}

	/**
	 * Step 1: collect integration/label/auth type, then reload (GET) into
	 * step 2 with the actual credential fields for the chosen type.
	 *
	 * @return void
	 */
	private function renderChooseTypeForm(): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wfa-settings-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::SLUG ) );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="wfa-connection-integration">' . esc_html__( 'Integration', 'workflow-automate' ) . '</label></th><td>';
		echo '<input type="text" id="wfa-connection-integration" name="integration_slug" class="regular-text" required="required" />';
		echo '<p class="description">' . esc_html__( 'A short identifier for what this connection is for, e.g. "my_email_provider".', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="wfa-connection-label">' . esc_html__( 'Label', 'workflow-automate' ) . '</label></th><td>';
		echo '<input type="text" id="wfa-connection-label" name="label" class="regular-text" required="required" />';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="wfa-connection-auth-type">' . esc_html__( 'Authentication type', 'workflow-automate' ) . '</label></th><td>';
		echo '<select id="wfa-connection-auth-type" name="auth_type">';
		foreach ( ConnectionAuthTypes::VALID as $auth_type ) {
			printf(
				'<option value="%1$s">%2$s</option>',
				esc_attr( $auth_type ),
				esc_html( ConnectionAuthTypes::label( $auth_type ) )
			);
		}
		echo '</select>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Continue', 'workflow-automate' ) );
		echo '</form>';
	}

	/**
	 * Step 2: the real, nonce-protected creation form.
	 *
	 * @param string $auth_type        One of ConnectionAuthTypes::VALID.
	 * @param string $integration_slug Prefilled from step 1.
	 * @param string $label            Prefilled from step 1.
	 *
	 * @return void
	 */
	private function renderCreateForm( string $auth_type, string $integration_slug, string $label ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wfa-settings-form">';
		echo '<input type="hidden" name="action" value="wfa_connection_action" />';
		echo '<input type="hidden" name="op" value="create" />';
		echo '<input type="hidden" name="auth_type" value="' . esc_attr( $auth_type ) . '" />';
		wp_nonce_field( 'wfa_connection_action_create' );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="wfa-connection-integration">' . esc_html__( 'Integration', 'workflow-automate' ) . '</label></th><td>';
		printf(
			'<input type="text" id="wfa-connection-integration" name="integration_slug" class="regular-text" value="%s" required="required" />',
			esc_attr( $integration_slug )
		);
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="wfa-connection-label">' . esc_html__( 'Label', 'workflow-automate' ) . '</label></th><td>';
		printf(
			'<input type="text" id="wfa-connection-label" name="label" class="regular-text" value="%s" required="required" />',
			esc_attr( $label )
		);
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Authentication type', 'workflow-automate' ) . '</th><td>';
		echo '<p>' . esc_html( ConnectionAuthTypes::label( $auth_type ) ) . '</p>';
		echo '</td></tr>';

		foreach ( ConnectionAuthTypes::editableFields( $auth_type ) as $field => $meta ) {
			$this->renderFieldRow( $field, $meta['label'], ! empty( $meta['secret'] ), '', false );
		}

		if ( ConnectionAuthTypes::OAUTH2 === $auth_type ) {
			$this->renderOAuthCallbackRow();
		}

		echo '</tbody></table>';
		submit_button( __( 'Create Connection', 'workflow-automate' ) );
		echo '</form>';
	}

	/**
	 * @param Connection $connection Connection being edited.
	 *
	 * @return void
	 */
	private function renderEditForm( Connection $connection ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wfa-settings-form">';
		echo '<input type="hidden" name="action" value="wfa_connection_action" />';
		echo '<input type="hidden" name="op" value="update" />';
		printf( '<input type="hidden" name="connection_id" value="%d" />', $connection->id() );
		wp_nonce_field( 'wfa_connection_action_update_' . $connection->id() );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Integration', 'workflow-automate' ) . '</th><td>';
		echo '<p>' . esc_html( $connection->integrationSlug() ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Authentication type', 'workflow-automate' ) . '</th><td>';
		echo '<p>' . esc_html( ConnectionAuthTypes::label( $connection->authType() ) ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="wfa-connection-label">' . esc_html__( 'Label', 'workflow-automate' ) . '</label></th><td>';
		printf(
			'<input type="text" id="wfa-connection-label" name="label" class="regular-text" value="%s" required="required" />',
			esc_attr( $connection->label() )
		);
		echo '</td></tr>';

		foreach ( $this->connections->displayCredentials( $connection ) as $field => $info ) {
			if ( ConnectionAuthTypes::OAUTH2 === $connection->authType() && in_array( $field, array( 'access_token', 'refresh_token', 'expires_at' ), true ) ) {
				continue;
			}

			$this->renderFieldRow( $field, $info['label'], $info['secret'], $info['display'], $info['configured'] );
		}

		if ( ConnectionAuthTypes::OAUTH2 === $connection->authType() ) {
			$this->renderOAuthCallbackRow();
			$this->renderOAuthStatusRow( $connection );
			$this->renderOAuthConnectButton( $connection );
		}

		echo '</tbody></table>';
		submit_button( __( 'Save Connection', 'workflow-automate' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Delete Connection', 'workflow-automate' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wfa-settings-form wfa-settings-danger-zone">';
		echo '<input type="hidden" name="action" value="wfa_connection_action" />';
		echo '<input type="hidden" name="op" value="delete" />';
		printf( '<input type="hidden" name="connection_id" value="%d" />', $connection->id() );
		wp_nonce_field( 'wfa_connection_action_delete_' . $connection->id() );
		echo '<p>' . esc_html__( 'Permanently deletes this connection. Anything using it will stop working.', 'workflow-automate' ) . '</p>';
		submit_button( __( 'Delete Connection', 'workflow-automate' ), 'delete' );
		echo '</form>';
	}

	/**
	 * Renders one `<tr>` for a single credential field, shared by both the
	 * create and edit forms.
	 *
	 * @param string $field      Field name (matches ConnectionAuthTypes::fields() keys).
	 * @param string $label      Field label.
	 * @param bool   $secret     Whether to render a password input instead of a text input.
	 * @param string $current    Current masked/plain display value (edit form only; '' on create).
	 * @param bool   $configured Whether a value is already stored for this field (edit form only).
	 *
	 * @return void
	 */
	private function renderFieldRow( string $field, string $label, bool $secret, string $current, bool $configured ): void {
		$input_type = $secret ? 'password' : 'text';
		$input_id   = 'wfa-connection-field-' . $field;

		echo '<tr><th scope="row"><label for="' . esc_attr( $input_id ) . '">' . esc_html( $label ) . '</label></th><td>';

		if ( $configured ) {
			echo '<p class="description wfa-connection-current-value">' . sprintf(
				/* translators: %s: masked or otherwise safe-to-display current value. */
				esc_html__( 'Currently set: %s', 'workflow-automate' ),
				'<code>' . esc_html( $current ) . '</code>'
			) . '</p>';
		}

		printf(
			'<input type="%1$s" id="%2$s" name="credential[%3$s]" class="regular-text" autocomplete="off" %4$s />',
			esc_attr( $input_type ),
			esc_attr( $input_id ),
			esc_attr( $field ),
			$configured ? '' : 'required="required"'
		);

		if ( $configured ) {
			echo '<p class="description">' . esc_html__( 'Leave blank to keep the current value.', 'workflow-automate' ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Google Cloud setup instructions shown when creating an OAuth connection.
	 *
	 * @return void
	 */
	private function renderOAuthSetupHelp(): void {
		echo '<div class="notice notice-info inline"><p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: URL to Google Cloud Console credentials page */
				__( 'Create OAuth credentials in <a href="%s" target="_blank" rel="noopener noreferrer">Google Cloud Console</a> (APIs &amp; Services → Credentials → Create OAuth client ID → Web application). Enable the Google Sheets API and Google Drive API for your project.', 'workflow-automate' ),
				esc_url( GoogleOAuthService::GOOGLE_CREDENTIALS_URL )
			),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		);
		echo '</p></div>';
	}

	/**
	 * Shows the callback URL the user must register in Google Cloud Console.
	 *
	 * @return void
	 */
	private function renderOAuthCallbackRow(): void {
		$callback = $this->google_oauth->callbackUrl();

		echo '<tr><th scope="row">' . esc_html__( 'Callback URL', 'workflow-automate' ) . '</th><td>';
		printf(
			'<input type="text" class="large-text code" readonly="readonly" value="%s" onclick="this.select();" />',
			esc_attr( $callback )
		);
		echo '<p class="description">' . esc_html__( 'Add this exact URL as an Authorized redirect URI in your Google OAuth client.', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param Connection $connection Connection being edited.
	 *
	 * @return void
	 */
	private function renderOAuthStatusRow( Connection $connection ): void {
		$connected = $this->google_oauth->isConnected( $connection );

		echo '<tr><th scope="row">' . esc_html__( 'Google account', 'workflow-automate' ) . '</th><td>';

		if ( $connected ) {
			echo '<p><span class="wfa-connection-status wfa-connection-status--verified">' . esc_html__( 'Connected', 'workflow-automate' ) . '</span></p>';
		} else {
			echo '<p><span class="wfa-connection-status wfa-connection-status--pending">' . esc_html__( 'Not connected — click Connect with Google below.', 'workflow-automate' ) . '</span></p>';
		}

		echo '</td></tr>';
	}

	/**
	 * @param Connection $connection Connection being edited.
	 *
	 * @return void
	 */
	private function renderOAuthConnectButton( Connection $connection ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'        => 'wfa_google_oauth_authorize',
					'connection_id' => $connection->id(),
				),
				admin_url( 'admin-post.php' )
			),
			'wfa_google_oauth_authorize_' . $connection->id()
		);

		echo '<tr><th scope="row">' . esc_html__( 'Authorize', 'workflow-automate' ) . '</th><td>';
		printf(
			'<a href="%1$s" class="button button-primary">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Connect with Google', 'workflow-automate' )
		);
		echo '<p class="description">' . esc_html__( 'Save Client ID and Client Secret first, then connect your Google account.', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector.
		$key = isset( $_GET['wfa_notice'] ) ? sanitize_key( wp_unslash( $_GET['wfa_notice'] ) ) : '';

		$notices = array(
			'created_oauth'   => array(
				'message' => __( 'Connection saved. Connect your Google account using the button below.', 'workflow-automate' ),
				'type'    => 'success',
			),
			'oauth_connected' => array(
				'message' => __( 'Google account connected successfully.', 'workflow-automate' ),
				'type'    => 'success',
			),
			'error'           => array(
				'message' => __( 'That connection action could not be completed.', 'workflow-automate' ),
				'type'    => 'error',
			),
		);

		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display detail.
		$detail = isset( $_GET['wfa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wfa_error'] ) ) : '';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p>%3$s</div>',
			esc_attr( $notices[ $key ]['type'] ),
			esc_html( $notices[ $key ]['message'] ),
			'' !== $detail ? sprintf( '<p>%s</p>', esc_html( $detail ) ) : ''
		);
	}
}
