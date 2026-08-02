<?php
/**
 * Registers the plugin's own built-in node types.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration;

use DragwybAgentFlow\Plugin\Integration\Actions\AiAgentAction;
use DragwybAgentFlow\Plugin\Integration\Actions\ClaudeMessagesAction;
use DragwybAgentFlow\Plugin\Integration\Actions\ConditionAction;
use DragwybAgentFlow\Plugin\Integration\Actions\GeminiGenerateContentAction;
use DragwybAgentFlow\Plugin\Integration\Actions\HttpRequestAction;
use DragwybAgentFlow\Plugin\Integration\Actions\DeepSeekChatAction;
use DragwybAgentFlow\Plugin\Integration\Actions\GroqChatAction;
use DragwybAgentFlow\Plugin\Integration\Actions\OpenAiChatAction;
use DragwybAgentFlow\Plugin\Integration\Actions\OpenRouterChatAction;
use DragwybAgentFlow\Plugin\Integration\Actions\RouterAction;
use DragwybAgentFlow\Plugin\Integration\Actions\SendEmailAction;
use DragwybAgentFlow\Plugin\Integration\Actions\SlackIncomingWebhookAction;
use DragwybAgentFlow\Plugin\Integration\Actions\StructuredOutputParserAction;
use DragwybAgentFlow\Plugin\Integration\Actions\TelegramSendMessageAction;
use DragwybAgentFlow\Plugin\Integration\Actions\WhatsAppCloudSendMessageAction;
use DragwybAgentFlow\Plugin\Integration\GoogleSheet\GoogleSheetsActionRegistrar;
use DragwybAgentFlow\Plugin\Integration\Triggers\CatalogHookTrigger;
use DragwybAgentFlow\Plugin\Integration\Triggers\ChatMessageReceivedTrigger;
use DragwybAgentFlow\Plugin\Integration\WordPress\WordPressActionRegistrar;
use DragwybAgentFlow\Plugin\Integration\Triggers\WooCommerceCatalogTrigger;
use DragwybAgentFlow\Plugin\Service\Agent\AgentAiClient;
use DragwybAgentFlow\Plugin\Service\Agent\AgentService;
use DragwybAgentFlow\Plugin\Service\ConnectionService;
use DragwybAgentFlow\Plugin\Service\GoogleOAuthService;
use DragwybAgentFlow\Plugin\Service\NodeTypeRegistry;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens on the public `dragwyb_af/nodes/register` action to add this plugin's
 * own built-in trigger/action node types.
 *
 * Optional co-plugin integrations are registered only when that plugin is
 * active so the palette stays relevant to the site.
 */
class BuiltInNodeTypes {

	private ConnectionService $connections;

	private GoogleOAuthService $google_oauth;

	private AgentService $agent;

	private AgentAiClient $ai_client;

	public function __construct(
		ConnectionService $connections,
		GoogleOAuthService $google_oauth,
		AgentService $agent,
		AgentAiClient $ai_client
	) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
		$this->agent        = $agent;
		$this->ai_client    = $ai_client;
	}

	/**
	 * @param NodeTypeRegistry $registry The registry being populated.
	 *
	 * @return void
	 */
	public function register( NodeTypeRegistry $registry ): void {
		foreach ( WordPressHookCatalog::definitions() as $definition ) {
			$registry->registerTrigger( new CatalogHookTrigger( $definition ) );
		}

		$registry->registerTrigger( new ChatMessageReceivedTrigger() );

		$registry->registerAction( new HttpRequestAction( $this->connections ) );
		$registry->registerAction( new SendEmailAction() );
		$registry->registerAction( new SlackIncomingWebhookAction() );
		$registry->registerAction( new OpenAiChatAction( $this->ai_client ) );
		$registry->registerAction( new TelegramSendMessageAction( $this->connections ) );
		$registry->registerAction( new WhatsAppCloudSendMessageAction( $this->connections ) );
		$registry->registerAction( new GeminiGenerateContentAction( $this->ai_client ) );
		$registry->registerAction( new ClaudeMessagesAction( $this->ai_client ) );
		$registry->registerAction( new OpenRouterChatAction( $this->ai_client ) );
		$registry->registerAction( new GroqChatAction( $this->ai_client ) );
		$registry->registerAction( new DeepSeekChatAction( $this->ai_client ) );
		$registry->registerAction( new AiAgentAction( $this->agent ) );
		$registry->registerAction( new StructuredOutputParserAction() );
		$registry->registerAction( new RouterAction() );
		$registry->registerAction( new ConditionAction() );

		foreach ( GoogleSheetsActionRegistrar::all( $this->connections, $this->google_oauth ) as $action ) {
			$registry->registerAction( $action );
		}

		foreach ( WordPressActionRegistrar::all() as $action ) {
			$registry->registerAction( $action );
		}

		foreach ( IntegrationTriggerCatalog::definitions() as $definition ) {
			if ( ! $definition['active'] ) {
				continue;
			}

			if (
				WooCommerceCatalogTrigger::class === $definition['class']
				&& isset( $definition['definition'] )
				&& is_array( $definition['definition'] )
			) {
				$registry->registerTrigger( new WooCommerceCatalogTrigger( $definition['definition'] ) );
				continue;
			}

			$class = $definition['class'];
			$registry->registerTrigger( new $class() );
		}
	}
}
