import { useState, useMemo } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	getTriggerApps,
	getAgentApps,
	getToolApps,
	getActionApps,
} from '../nodeCatalog';
import { getNodeMeta } from '../nodeMeta';

/**
 * Left palette: app folders open the right-side picker.
 *
 * @param {Object}   props
 * @param {Array}    props.triggers
 * @param {Array}    props.actions
 * @param {Function} props.onOpenPicker ( kind, appId ) => void
 */
export default function Palette({ triggers, actions, onOpenPicker }) {
	const [query, setQuery] = useState('');

	const triggerApps = useMemo(
		() => getTriggerApps(triggers, query),
		[triggers, query]
	);
	const agentApps = useMemo(
		() => getAgentApps(actions, query),
		[actions, query]
	);
	const toolApps = useMemo(
		() => getToolApps(actions, query),
		[actions, query]
	);
	const actionApps = useMemo(
		() => getActionApps(actions, query),
		[actions, query]
	);

	return (
		<nav
			className="aiawa-builder-palette"
			aria-label={__('Node palette', 'ai-agent-workflow-automation')}
		>
			<div className="aiawa-builder-palette__search">
				<TextControl
					label={__('Search nodes', 'ai-agent-workflow-automation')}
					hideLabelFromVision
					placeholder={__('Search nodes…', 'ai-agent-workflow-automation')}
					value={query}
					onChange={setQuery}
				/>
			</div>
			<PaletteSection
				title={__('Triggers', 'ai-agent-workflow-automation')}
				apps={triggerApps}
				kind="trigger"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No triggers match your search.', 'ai-agent-workflow-automation')
						: __('No triggers are registered.', 'ai-agent-workflow-automation')
				}
			/>
			<PaletteSection
				title={__('Agents', 'ai-agent-workflow-automation')}
				apps={agentApps}
				kind="agent"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No agents match your search.', 'ai-agent-workflow-automation')
						: __('No agents are registered.', 'ai-agent-workflow-automation')
				}
			/>
			<PaletteSection
				title={__('Tools', 'ai-agent-workflow-automation')}
				apps={toolApps}
				kind="tool"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No tools match your search.', 'ai-agent-workflow-automation')
						: __('No tools are registered.', 'ai-agent-workflow-automation')
				}
			/>
			<PaletteSection
				title={__('Actions', 'ai-agent-workflow-automation')}
				apps={actionApps}
				kind="action"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No actions match your search.', 'ai-agent-workflow-automation')
						: __('No actions are registered.', 'ai-agent-workflow-automation')
				}
			/>
		</nav>
	);
}

function PaletteSection({ title, apps, kind, onOpenPicker, emptyMessage }) {
	return (
		<div className="aiawa-builder-palette__section">
			<h2 className="aiawa-builder-palette__heading">{title}</h2>
			{apps.length === 0 && (
				<p className="aiawa-builder-palette__empty">{emptyMessage}</p>
			)}
			<ul className="aiawa-builder-palette__list">
				{apps.map((app) => {
					const meta = getNodeMeta(app.id, kind === 'trigger' ? 'trigger' : 'action');
					const isDisabled = app.available === false;
					const disabledMessage = isDisabled
						? sprintf(
							/* translators: %s: plugin name, e.g. WooCommerce */
							__(
								'Activate %s to use this trigger.',
								'ai-agent-workflow-automation'
							),
							app.requiresPlugin || __('this plugin', 'ai-agent-workflow-automation')
						)
						: '';

					return (
						<li key={app.id}>
							<button
								type="button"
								className={
									isDisabled
										? 'aiawa-builder-palette__item aiawa-builder-palette__item--disabled'
										: 'aiawa-builder-palette__item'
								}
								onClick={() => onOpenPicker(kind, app.id)}
								aria-label={app.label}
								title={isDisabled ? disabledMessage : app.label}
							>
								<span
									className="aiawa-builder-palette__item-icon"
									style={{
										backgroundColor: meta.bg,
										color: meta.accent,
									}}
									aria-hidden="true"
								>
									{meta.icon}
								</span>
								<span className="aiawa-builder-palette__item-content">
									<span
										className="aiawa-builder-palette__item-label"
										aria-hidden="true"
									>
										{app.label}
									</span>
									{isDisabled && (
										<span className="aiawa-builder-palette__item-hint">
											{disabledMessage}
										</span>
									)}
								</span>
							</button>
						</li>
					);
				})}
			</ul>
		</div>
	);
}
