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
			className="dragwyb-af-builder-palette"
			aria-label={__('Node palette', 'dragwyb-visual-automation')}
		>
			<div className="dragwyb-af-builder-palette__search">
				<TextControl
					label={__('Search nodes', 'dragwyb-visual-automation')}
					hideLabelFromVision
					placeholder={__('Search nodes…', 'dragwyb-visual-automation')}
					value={query}
					onChange={setQuery}
				/>
			</div>
			<PaletteSection
				title={__('Triggers', 'dragwyb-visual-automation')}
				apps={triggerApps}
				kind="trigger"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No triggers match your search.', 'dragwyb-visual-automation')
						: __('No triggers are registered.', 'dragwyb-visual-automation')
				}
			/>
			<PaletteSection
				title={__('Agents', 'dragwyb-visual-automation')}
				apps={agentApps}
				kind="agent"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No agents match your search.', 'dragwyb-visual-automation')
						: __('No agents are registered.', 'dragwyb-visual-automation')
				}
			/>
			<PaletteSection
				title={__('Tools', 'dragwyb-visual-automation')}
				apps={toolApps}
				kind="tool"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No tools match your search.', 'dragwyb-visual-automation')
						: __('No tools are registered.', 'dragwyb-visual-automation')
				}
			/>
			<PaletteSection
				title={__('Actions', 'dragwyb-visual-automation')}
				apps={actionApps}
				kind="action"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No actions match your search.', 'dragwyb-visual-automation')
						: __('No actions are registered.', 'dragwyb-visual-automation')
				}
			/>
		</nav>
	);
}

function PaletteSection({ title, apps, kind, onOpenPicker, emptyMessage }) {
	return (
		<div className="dragwyb-af-builder-palette__section">
			<h2 className="dragwyb-af-builder-palette__heading">{title}</h2>
			{apps.length === 0 && (
				<p className="dragwyb-af-builder-palette__empty">{emptyMessage}</p>
			)}
			<ul className="dragwyb-af-builder-palette__list">
				{apps.map((app) => {
					const meta = getNodeMeta(app.id, kind === 'trigger' ? 'trigger' : 'action');
					const isDisabled = app.available === false;
					const disabledMessage = isDisabled
						? sprintf(
							/* translators: %s: plugin name, e.g. WooCommerce */
							__(
								'Activate %s to use this trigger.',
								'dragwyb-visual-automation'
							),
							app.requiresPlugin || __('this plugin', 'dragwyb-visual-automation')
						)
						: '';

					return (
						<li key={app.id}>
							<button
								type="button"
								className={
									isDisabled
										? 'dragwyb-af-builder-palette__item dragwyb-af-builder-palette__item--disabled'
										: 'dragwyb-af-builder-palette__item'
								}
								onClick={() => onOpenPicker(kind, app.id)}
								aria-label={app.label}
								title={isDisabled ? disabledMessage : app.label}
							>
								<span
									className="dragwyb-af-builder-palette__item-icon"
									style={{
										backgroundColor: meta.bg,
										color: meta.accent,
									}}
									aria-hidden="true"
								>
									{meta.icon}
								</span>
								<span className="dragwyb-af-builder-palette__item-content">
									<span
										className="dragwyb-af-builder-palette__item-label"
										aria-hidden="true"
									>
										{app.label}
									</span>
									{isDisabled && (
										<span className="dragwyb-af-builder-palette__item-hint">
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
