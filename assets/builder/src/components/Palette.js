import { useState, useMemo } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { getNodeMeta } from '../nodeMeta';

/**
 * Left-hand palette. Purely a source of "add this node type" buttons — it
 * has no opinion on where the node lands or what happens after; the parent
 * owns that behaviour so the palette stays a simple, presentational list.
 *
 * @param {Object}   props
 * @param {Array}    props.triggers
 * @param {Array}    props.actions
 * @param {Function} props.onAdd    ( nodeType, category ) => void
 */
export default function Palette({ triggers, actions, onAdd }) {
	const [query, setQuery] = useState('');

	const filteredTriggers = useMemo(
		() => filterItems(triggers, query),
		[triggers, query]
	);
	const filteredActions = useMemo(
		() => filterItems(actions, query),
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
				items={filteredTriggers}
				category="trigger"
				onAdd={onAdd}
				emptyMessage={
					query
						? __('No triggers match your search.', 'workflow-automate')
						: __('No triggers are registered.', 'workflow-automate')
				}
			/>
			<PaletteSection
				title={__('Actions', 'workflow-automate')}
				items={filteredActions}
				category="action"
				onAdd={onAdd}
				emptyMessage={
					query
						? __('No actions match your search.', 'workflow-automate')
						: __('No actions are registered.', 'workflow-automate')
				}
			/>
		</nav>
	);
}

/**
 * @param {Array}  items
 * @param {string} query
 * @return {Array}
 */
function filterItems(items, query) {
	const needle = query.trim().toLowerCase();
	if (!needle) {
		return items;
	}

	return items.filter((item) => {
		const haystack = `${item.label} ${item.description || ''} ${item.slug}`.toLowerCase();
		return haystack.includes(needle);
	});
}

function PaletteSection({ title, items, category, onAdd, emptyMessage }) {
	return (
		<div className="wfa-builder-palette__section">
			<h2 className="wfa-builder-palette__heading">{title}</h2>
			{items.length === 0 && (
				<p className="wfa-builder-palette__empty">{emptyMessage}</p>
			)}
			<ul className="wfa-builder-palette__list">
				{items.map((item) => {
					const meta = getNodeMeta(item.slug, category);

					return (
						<li key={item.slug}>
							<button
								type="button"
								className="wfa-builder-palette__item"
								onClick={() => onAdd(item, category)}
								title={item.description}
								aria-label={
									category === 'trigger'
										? sprintf(
											/* translators: %s: trigger label */
											__(
												'Add trigger: %s',
												'workflow-automate'
											),
											item.label
										)
										: sprintf(
											/* translators: %s: action label */
											__(
												'Add action: %s',
												'workflow-automate'
											),
											item.label
										)
								}
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
										{item.label}
									</span>
									{item.description && (
										<span
											className="wfa-builder-palette__item-description"
											aria-hidden="true"
										>
											{item.description}
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
