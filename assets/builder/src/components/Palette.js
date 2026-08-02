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
			aria-label={__('Node palette', 'dragwyb-agentflow')}
		>
			<div className="aiawa-builder-palette__search">
				<TextControl
					label={__('Search nodes', 'dragwyb-agentflow')}
					hideLabelFromVision
					placeholder={__('Search nodes…', 'dragwyb-agentflow')}
					value={query}
					onChange={setQuery}
				/>
			</div>
			<PaletteSection
				title={__('Triggers', 'dragwyb-agentflow')}
				apps={triggerApps}
				kind="trigger"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No triggers match your search.', 'dragwyb-agentflow')
						: __('No triggers are registered.', 'dragwyb-agentflow')
				}
			/>
			<PaletteSection
				title={__('Agents', 'dragwyb-agentflow')}
				apps={agentApps}
				kind="agent"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No agents match your search.', 'dragwyb-agentflow')
						: __('No agents are registered.', 'dragwyb-agentflow')
				}
			/>
			<PaletteSection
				title={__('Tools', 'dragwyb-agentflow')}
				apps={toolApps}
				kind="tool"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No tools match your search.', 'dragwyb-agentflow')
						: __('No tools are registered.', 'dragwyb-agentflow')
				}
			/>
			<PaletteSection
				title={__('Actions', 'dragwyb-agentflow')}
				apps={actionApps}
				kind="action"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No actions match your search.', 'dragwyb-agentflow')
						: __('No actions are registered.', 'dragwyb-agentflow')
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
								'dragwyb-agentflow'
							),
							app.requiresPlugin || __('this plugin', 'dragwyb-agentflow')
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
