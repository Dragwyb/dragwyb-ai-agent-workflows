<?php
/**
 * Webhook application service.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

use InvalidArgumentException;
use RuntimeException;
use AIAWA\Plugin\Core\Encryption;
use AIAWA\Plugin\Domain\Webhook;
use AIAWA\Plugin\Domain\Workflow;
use AIAWA\Plugin\Persistence\WebhookRepository;
use WP_Error;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the full inbound-webhook lifecycle: admin CRUD, public-URL
 * generation, and the security checks on the public ingress path
 * (roadmap item 13). Only this class ever calls
 * `Core\Encryption::decrypt()` for a webhook signing secret — same
 * "one place decides when to decrypt" rule `ConnectionService` already
 * established for connection credentials.
 *
 * Signature scheme (documented in `docs/integrations.md`): callers send
 * `X-aiawa-Signature: sha256=<hex>` where `<hex>` is
 * `hash_hmac( 'sha256', <raw request body>, <signing secret> )`.
 */
class WebhookService {

	public const SIGNATURE_HEADER = 'X-aiawa-Signature';

	private WebhookRepository $webhooks;

	private WorkflowService $workflows;

	private WorkflowExecutionService $executor;

	private SettingsService $settings;

	public function __construct(
		WebhookRepository $webhooks,
		WorkflowService $workflows,
		WorkflowExecutionService $executor,
		SettingsService $settings
	) {
		$this->webhooks  = $webhooks;
		$this->workflows = $workflows;
		$this->executor  = $executor;
		$this->settings  = $settings;
	}

	/**
	 * Creates a new webhook endpoint for a workflow.
	 *
	 * @param int         $workflow_id    Target workflow id.
	 * @param string|null $signing_secret Plaintext secret, or null/'' for none. Required when site settings demand signing.
	 * @param string[]    $ip_allow_list  Exact IPs and/or CIDR ranges; empty = any IP.
	 *
	 * @throws InvalidArgumentException When input is invalid.
	 * @throws RuntimeException         When the underlying insert fails.
	 *
	 * @return Webhook
	 */
	public function create( int $workflow_id, ?string $signing_secret, array $ip_allow_list ): Webhook {
		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			throw new InvalidArgumentException( esc_html__( 'The specified workflow does not exist.', 'ai-agent-workflow-automation' ) );
		}

		$ip_allow_list  = $this->normalizeIpAllowList( $ip_allow_list );
		$signing_secret = null === $signing_secret ? '' : trim( $signing_secret );

		// Site-wide "require signing" means every webhook must have a
		// secret the caller can HMAC with. We deliberately do not
		// auto-generate one here: the admin needs the plaintext once to
		// configure the caller, and this plugin has no one-time flash
		// channel to hand it back after create (masked display only ever
		// shows the last 4 characters). The form documents that the
		// field is required when the setting is on.
		if ( '' === $signing_secret && $this->settings->requireWebhookSigning() ) {
			throw new InvalidArgumentException( esc_html__( 'A signing secret is required by site settings.', 'ai-agent-workflow-automation' ) );
		}

		$webhook = $this->webhooks->insert(
			array(
				'workflow_id'    => $workflow_id,
				'public_id'      => wp_generate_uuid4(),
				'signing_secret' => '' === $signing_secret ? '' : Encryption::encrypt( $signing_secret ),
				'ip_allow_list'  => $ip_allow_list,
			)
		);

		if ( null === $webhook ) {
			throw new RuntimeException( esc_html__( 'Failed to create the webhook.', 'ai-agent-workflow-automation' ) );
		}

		return $webhook;
	}

	/**
	 * Updates a webhook's workflow, signing secret, and/or IP allow-list.
	 *
	 * A blank `$signing_secret` leaves the currently stored secret
	 * untouched (rotation pattern, same as ConnectionService::update()).
	 * Pass `$clear_signing_secret = true` to remove it entirely — refused
	 * when site settings require signing.
	 *
	 * @param int         $id                   Webhook id.
	 * @param int         $workflow_id          Target workflow id.
	 * @param string|null $signing_secret       New plaintext secret, or null/'' to keep existing.
	 * @param bool        $clear_signing_secret When true, removes the stored secret.
	 * @param string[]    $ip_allow_list        Exact IPs and/or CIDR ranges.
	 *
	 * @throws InvalidArgumentException When input is invalid.
	 * @throws RuntimeException         When the underlying update fails.
	 *
	 * @return Webhook
	 */
	public function update( int $id, int $workflow_id, ?string $signing_secret, bool $clear_signing_secret, array $ip_allow_list ): Webhook {
		$webhook = $this->webhooks->find( $id );

		if ( null === $webhook ) {
			throw new InvalidArgumentException( esc_html__( 'The specified webhook does not exist.', 'ai-agent-workflow-automation' ) );
		}

		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			throw new InvalidArgumentException( esc_html__( 'The specified workflow does not exist.', 'ai-agent-workflow-automation' ) );
		}

		$ip_allow_list  = $this->normalizeIpAllowList( $ip_allow_list );
		$signing_secret = null === $signing_secret ? '' : trim( $signing_secret );

		$attributes = array(
			'workflow_id'   => $workflow_id,
			'ip_allow_list' => $ip_allow_list,
		);

		if ( $clear_signing_secret ) {
			if ( $this->settings->requireWebhookSigning() ) {
				throw new InvalidArgumentException( esc_html__( 'A signing secret is required by site settings.', 'ai-agent-workflow-automation' ) );
			}

			$attributes['signing_secret'] = '';
		} elseif ( '' !== $signing_secret ) {
			$attributes['signing_secret'] = Encryption::encrypt( $signing_secret );
		}

		$updated = $this->webhooks->update( $id, $attributes );

		if ( null === $updated ) {
			throw new RuntimeException( esc_html__( 'Failed to update the webhook.', 'ai-agent-workflow-automation' ) );
		}

		return $updated;
	}

	/**
	 * @param int $id Webhook id.
	 *
	 * @return Webhook|null
	 */
	public function find( int $id ): ?Webhook {
		return $this->webhooks->find( $id );
	}

	/**
	 * @param array<string, mixed> $args See WebhookRepository::paginate().
	 *
	 * @return array{items: Webhook[], total: int, page: int, per_page: int}
	 */
	public function list( array $args = array() ): array {
		return $this->webhooks->paginate( $args );
	}

	/**
	 * @param int $id Webhook id.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->webhooks->delete( $id );
	}

	/**
	 * Application-level ON DELETE SET NULL for permanently deleted workflows.
	 *
	 * @param int $workflow_id Workflow id that was hard-deleted.
	 *
	 * @return void
	 */
	public function nullifyWorkflow( int $workflow_id ): void {
		$this->webhooks->nullifyWorkflow( $workflow_id );
	}

	/**
	 * Public ingress URL for a webhook.
	 *
	 * @param Webhook $webhook Webhook to describe.
	 *
	 * @return string
	 */
	public function publicUrl( Webhook $webhook ): string {
		return rest_url( 'aiawa/v1/webhooks/' . $webhook->publicId() );
	}

	/**
	 * Masked view of the signing secret for admin display (last 4 chars),
	 * or an empty string when none is configured.
	 *
	 * @param Webhook $webhook Webhook to describe.
	 *
	 * @return array{configured: bool, display: string}
	 */
	public function displaySigningSecret( Webhook $webhook ): array {
		if ( ! $webhook->hasSigningSecret() ) {
			return array(
				'configured' => false,
				'display'    => '',
			);
		}

		$plaintext = Encryption::decrypt( $webhook->encryptedSigningSecret() );

		if ( null === $plaintext ) {
			return array(
				'configured' => true,
				'display'    => __( '(unable to decrypt — please re-enter this value)', 'ai-agent-workflow-automation' ),
			);
		}

		return array(
			'configured' => true,
			'display'    => self::mask( $plaintext ),
		);
	}

	/**
	 * Handles a public ingress request: validates IP allow-list and
	 * optional HMAC signature, then queues (or synchronously runs) the
	 * linked workflow with the request body as its trigger payload.
	 *
	 * @param string $public_id         UUID from the URL.
	 * @param string $raw_body          Exact request body bytes (needed for HMAC).
	 * @param string $client_ip         Caller IP (typically REMOTE_ADDR).
	 * @param string $signature_header  Value of the X-aiawa-Signature header, or ''.
	 *
	 * @return array{run_id: int, status: string, queued: bool}|WP_Error
	 */
	public function ingest( string $public_id, string $raw_body, string $client_ip, string $signature_header ) {
		$webhook = $this->webhooks->findByPublicId( $public_id );

		if ( null === $webhook ) {
			return new WP_Error(
				'aiawa_webhook_not_found',
				__( 'Webhook not found.', 'ai-agent-workflow-automation' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->isIpAllowed( $client_ip, $webhook->ipAllowList() ) ) {
			return new WP_Error(
				'aiawa_webhook_ip_denied',
				__( 'Request IP is not allowed for this webhook.', 'ai-agent-workflow-automation' ),
				array( 'status' => 403 )
			);
		}

		$requires_signature = $webhook->hasSigningSecret() || $this->settings->requireWebhookSigning();

		if ( $requires_signature ) {
			if ( ! $webhook->hasSigningSecret() ) {
				return new WP_Error(
					'aiawa_webhook_signing_required',
					__( 'This webhook has no signing secret configured, but site settings require one.', 'ai-agent-workflow-automation' ),
					array( 'status' => 403 )
				);
			}

			$secret = Encryption::decrypt( $webhook->encryptedSigningSecret() );

			if ( null === $secret || '' === $secret ) {
				return new WP_Error(
					'aiawa_webhook_signing_unavailable',
					__( 'Unable to verify this webhook\'s signature.', 'ai-agent-workflow-automation' ),
					array( 'status' => 500 )
				);
			}

			if ( ! $this->signatureIsValid( $raw_body, $secret, $signature_header ) ) {
				return new WP_Error(
					'aiawa_webhook_invalid_signature',
					__( 'Invalid webhook signature.', 'ai-agent-workflow-automation' ),
					array( 'status' => 401 )
				);
			}
		}

		$workflow_id = $webhook->workflowId();

		if ( null === $workflow_id ) {
			return new WP_Error(
				'aiawa_webhook_unlinked',
				__( 'This webhook is not linked to a workflow.', 'ai-agent-workflow-automation' ),
				array( 'status' => 409 )
			);
		}

		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow || Workflow::STATUS_ACTIVE !== $workflow->status() ) {
			return new WP_Error(
				'aiawa_webhook_workflow_inactive',
				__( 'The workflow linked to this webhook is not active.', 'ai-agent-workflow-automation' ),
				array( 'status' => 409 )
			);
		}

		$payload = $this->buildPayload( $raw_body, $client_ip );

		try {
			if ( $this->settings->backgroundExecutionEnabled() ) {
				$run = $this->executor->queue( $workflow_id, $payload );

				return array(
					'run_id' => $run->id(),
					'status' => 'queued',
					'queued' => true,
				);
			}

			$run = $this->executor->run( $workflow_id, $payload );

			return array(
				'run_id' => $run->id(),
				'status' => $run->status(),
				'queued' => false,
			);
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			return new WP_Error(
				'aiawa_webhook_run_failed',
				__( 'The workflow could not be started.', 'ai-agent-workflow-automation' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * @param string[] $ip_allow_list Raw entries from the admin form.
	 *
	 * @return string[]
	 */
	private function normalizeIpAllowList( array $ip_allow_list ): array {
		$normalized = array();

		foreach ( $ip_allow_list as $entry ) {
			$entry = trim( (string) $entry );

			if ( '' === $entry ) {
				continue;
			}

			// Exact IPv4/IPv6, or a simple IPv4 CIDR (a.b.c.d/nn).
			if ( false !== filter_var( $entry, FILTER_VALIDATE_IP ) ) {
				$normalized[ $entry ] = true;
				continue;
			}

			if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', $entry, $matches ) ) {
				$ip     = $matches[1];
				$prefix = (int) $matches[2];

				if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && $prefix >= 0 && $prefix <= 32 ) {
					$normalized[ $entry ] = true;
				}
			}
		}

		return array_keys( $normalized );
	}

	/**
	 * @param string   $client_ip    Caller IP.
	 * @param string[] $ip_allow_list Empty = allow any.
	 *
	 * @return bool
	 */
	private function isIpAllowed( string $client_ip, array $ip_allow_list ): bool {
		if ( array() === $ip_allow_list ) {
			return true;
		}

		if ( '' === $client_ip ) {
			return false;
		}

		foreach ( $ip_allow_list as $entry ) {
			if ( $entry === $client_ip ) {
				return true;
			}

			if ( $this->ipMatchesCidr( $client_ip, $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $ip   Caller IP.
	 * @param string $cidr e.g. "192.168.1.0/24".
	 *
	 * @return bool
	 */
	private function ipMatchesCidr( string $ip, string $cidr ): bool {
		if ( ! preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', $cidr, $matches ) ) {
			return false;
		}

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		$prefix       = (int) $matches[2];
		$mask         = $prefix > 0 ? ( ~0 << ( 32 - $prefix ) ) : 0;
		$ip_long      = ip2long( $ip );
		$network_long = ip2long( $matches[1] );

		if ( false === $ip_long || false === $network_long ) {
			return false;
		}

		return ( $ip_long & $mask ) === ( $network_long & $mask );
	}

	/**
	 * @param string $raw_body         Exact request body.
	 * @param string $secret           Plaintext signing secret.
	 * @param string $signature_header Header value, e.g. "sha256=abc…".
	 *
	 * @return bool
	 */
	private function signatureIsValid( string $raw_body, string $secret, string $signature_header ): bool {
		if ( ! preg_match( '/^sha256=([a-fA-F0-9]+)$/', trim( $signature_header ), $matches ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $raw_body, $secret );

		return hash_equals( $expected, strtolower( $matches[1] ) );
	}

	/**
	 * Builds the trigger payload stored on the run. Prefer JSON when the
	 * body parses as an object/array; otherwise wrap the raw string so
	 * actions still have something to read.
	 *
	 * @param string $raw_body  Exact request body.
	 * @param string $client_ip Caller IP (recorded for audit, not secrets).
	 *
	 * @return array<string, mixed>
	 */
	private function buildPayload( string $raw_body, string $client_ip ): array {
		$decoded = json_decode( $raw_body, true );
		$body    = ( JSON_ERROR_NONE === json_last_error() && ( is_array( $decoded ) || is_object( $decoded ) ) )
			? $decoded
			: array( 'raw' => $raw_body );

		return array(
			'source'    => 'webhook',
			'client_ip' => $client_ip,
			'body'      => $body,
		);
	}

	/**
	 * @param string $value Plaintext secret.
	 *
	 * @return string
	 */
	private static function mask( string $value ): string {
		$length = strlen( $value );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 4 ) {
			return str_repeat( '•', $length );
		}

		return str_repeat( '•', $length - 4 ) . substr( $value, -4 );
	}
}
