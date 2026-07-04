import { useState, useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	appUsesGroups,
	categoryForKind,
	getGroupsForApp,
	getItemsForPicker,
} from '../nodeCatalog';
import { getNodeMeta } from '../nodeMeta';

/**
 * Right sidebar for picking a trigger, agent, or action after choosing an app.
 *
 * @param {Object}        props
 * @param {'trigger'|'agent'|'action'} props.kind
 * @param {string}        props.appId
 * @param {Array<Object>} props.triggers
 * @param {Array<Object>} props.actions
 * @param {Function}      props.onSelect   ( nodeType, category ) => void
 * @param {Function}      props.onClose
 */
export default function PickerSidebar({
	kind,
	appId,
	triggers,
	actions,
	onSelect,
	onClose,
}) {
	const [groupId, setGroupId] = useState(null);
	const usesGroups = appUsesGroups(kind, appId, triggers);
	const groups = useMemo(
		() => getGroupsForApp(kind, appId, triggers, actions),
		[kind, appId, triggers, actions]
	);
	const items = useMemo(
		() => getItemsForPicker(kind, appId, groupId, triggers, actions),
		[kind, appId, groupId, triggers, actions]
	);
	const showGroups = usesGroups && !groupId;
	const category = categoryForKind(kind);
	const title = showGroups
		? __('Choose a group', 'workflow-automate')
		: __('Choose a node', 'workflow-automate');

	const handlePick = (item) => {
		if (item.available === false) {
			return;
		}

		onSelect(item, category);
		onClose();
	};

	return (
		<aside
			className="wfa-builder-picker"
			aria-label={title}
		>
			<div className="wfa-builder-picker__header">
				{usesGroups && groupId && (
					<Button
						variant="link"
						className="wfa-builder-picker__back"
						onClick={() => setGroupId(null)}
					>
						{__('← Back', 'workflow-automate')}
					</Button>
				)}
				<h2 className="wfa-builder-picker__title">{title}</h2>
				<Button
					className="wfa-builder-picker__close"
					icon="no-alt"
					label={__('Close', 'workflow-automate')}
					onClick={onClose}
				/>
			</div>

			{showGroups ? (
				<ul className="wfa-builder-picker__list">
					{groups.map((group) => (
						<li key={group.id}>
							<button
								type="button"
								className="wfa-builder-picker__item"
								onClick={() => setGroupId(group.id)}
							>
								<span className="wfa-builder-picker__item-label">
									{group.label}
								</span>
							</button>
						</li>
					))}
				</ul>
			) : (
				<ul className="wfa-builder-picker__list">
					{items.map((item) => {
						const meta = getNodeMeta(item.slug, category, appId);
						const isDisabled = item.available === false;
						const disabledMessage = isDisabled
							? sprintf(
									/* translators: %s: plugin name, e.g. WooCommerce */
									__(
										'Activate %s to use this trigger.',
										'workflow-automate'
									),
									item.requires_plugin || __('this plugin', 'workflow-automate')
								)
							: '';

						return (
							<li key={item.slug}>
								<button
									type="button"
									className={
										isDisabled
											? 'wfa-builder-picker__item wfa-builder-picker__item--disabled'
											: 'wfa-builder-picker__item'
									}
									onClick={() => handlePick(item)}
									title={isDisabled ? disabledMessage : item.description}
									disabled={isDisabled}
									aria-disabled={isDisabled}
								>
									<span
										className="wfa-builder-picker__item-icon"
										style={{
											backgroundColor: meta.bg,
											color: meta.accent,
										}}
										aria-hidden="true"
									>
										{meta.icon}
									</span>
									<span className="wfa-builder-picker__item-content">
										<span className="wfa-builder-picker__item-label">
											{item.label}
										</span>
										{isDisabled && (
											<span className="wfa-builder-picker__item-hint">
												{disabledMessage}
											</span>
										)}
									</span>
								</button>
							</li>
						);
					})}
				</ul>
			)}
		</aside>
	);
}
