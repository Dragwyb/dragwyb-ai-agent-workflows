import { __ } from '@wordpress/i18n';

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
	return (
		<nav
			className="wfa-builder-palette"
			aria-label={__('Node palette', 'workflow-automate')}
		>
			<PaletteSection
				title={__('Triggers', 'workflow-automate')}
				items={triggers}
				category="trigger"
				onAdd={onAdd}
				emptyMessage={__(
					'No triggers are registered.',
					'workflow-automate'
				)}
			/>
			<PaletteSection
				title={__('Actions', 'workflow-automate')}
				items={actions}
				category="action"
				onAdd={onAdd}
				emptyMessage={__(
					'No actions are registered.',
					'workflow-automate'
				)}
			/>
		</nav>
	);
}

function PaletteSection({ title, items, category, onAdd, emptyMessage }) {
	return (
		<div className="wfa-builder-palette__section">
			<h2 className="wfa-builder-palette__heading">{title}</h2>
			{items.length === 0 && (
				<p className="wfa-builder-palette__empty">{emptyMessage}</p>
			)}
			<ul className="wfa-builder-palette__list">
				{items.map((item) => (
					<li key={item.slug}>
						<button
							type="button"
							className="wfa-builder-palette__item"
							onClick={() => onAdd(item, category)}
							title={item.description}
						>
							<span className="wfa-builder-palette__item-label">
								{item.label}
							</span>
							{item.description && (
								<span className="wfa-builder-palette__item-description">
									{item.description}
								</span>
							)}
						</button>
					</li>
				))}
			</ul>
		</div>
	);
}
