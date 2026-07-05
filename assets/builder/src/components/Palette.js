import { useState, useMemo } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	getTriggerApps,
	getAgentApps,
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
	const actionApps = useMemo(
		() => getActionApps(actions, query),
		[actions, query]
	);

	return (
		<nav
			className="wfa-builder-palette"
			aria-label={__('Node palette', 'workflow-automate')}
		>
			<div className="wfa-builder-palette__search">
				<TextControl
					label={__('Search nodes', 'workflow-automate')}
					hideLabelFromVision
					placeholder={__('Search nodes…', 'workflow-automate')}
					value={query}
					onChange={setQuery}
				/>
			</div>
			<PaletteSection
				title={__('Triggers', 'workflow-automate')}
				apps={triggerApps}
				kind="trigger"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No triggers match your search.', 'workflow-automate')
						: __('No triggers are registered.', 'workflow-automate')
				}
			/>
			<PaletteSection
				title={__('Agents', 'workflow-automate')}
				apps={agentApps}
				kind="agent"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No agents match your search.', 'workflow-automate')
						: __('No agents are registered.', 'workflow-automate')
				}
			/>
			<PaletteSection
				title={__('Actions', 'workflow-automate')}
				apps={actionApps}
				kind="action"
				onOpenPicker={onOpenPicker}
				emptyMessage={
					query
						? __('No actions match your search.', 'workflow-automate')
						: __('No actions are registered.', 'workflow-automate')
				}
			/>
		</nav>
	);
}

function PaletteSection({ title, apps, kind, onOpenPicker, emptyMessage }) {
	return (
		<div className="wfa-builder-palette__section">
			<h2 className="wfa-builder-palette__heading">{title}</h2>
			{apps.length === 0 && (
				<p className="wfa-builder-palette__empty">{emptyMessage}</p>
			)}
			<ul className="wfa-builder-palette__list">
				{apps.map((app) => {
					const meta = getNodeMeta(app.id, kind === 'trigger' ? 'trigger' : 'action');
					const isDisabled = app.available === false;
					const disabledMessage = isDisabled
						? sprintf(
							/* translators: %s: plugin name, e.g. WooCommerce */
							__(
								'Activate %s to use this trigger.',
								'workflow-automate'
							),
							app.requiresPlugin || __('this plugin', 'workflow-automate')
						)
						: '';

					return (
						<li key={app.id}>
							<button
								type="button"
								className={
									isDisabled
										? 'wfa-builder-palette__item wfa-builder-palette__item--disabled'
										: 'wfa-builder-palette__item'
								}
								onClick={() => onOpenPicker(kind, app.id)}
								aria-label={app.label}
								title={isDisabled ? disabledMessage : app.label}
							>
								<span
									className="wfa-builder-palette__item-icon"
									style={{
										backgroundColor: meta.bg,
										color: meta.accent,
									}}
									aria-hidden="true"
								>
									{meta.icon}
								</span>
								<span className="wfa-builder-palette__item-content">
									<span
										className="wfa-builder-palette__item-label"
										aria-hidden="true"
									>
										{app.label}
									</span>
									{isDisabled && (
										<span className="wfa-builder-palette__item-hint">
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
