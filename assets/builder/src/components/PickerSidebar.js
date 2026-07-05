import { useState, useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	appUsesGroups,
	appUsesGroupedSections,
	categoryForKind,
	getAppLabel,
	getGroupedItemsForPicker,
	getGroupsForApp,
	getItemsForPicker,
	getPickerItemLabel,
	getSubAppsForPicker,
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
	const [subAppId, setSubAppId] = useState(null);
	const usesGroups = appUsesGroups(kind, appId, triggers);
	const usesGroupedSections = appUsesGroupedSections(kind, appId, subAppId);
	const groups = useMemo(
		() => getGroupsForApp(kind, appId, triggers, actions),
		[kind, appId, triggers, actions]
	);
	const subApps = useMemo(
		() => getSubAppsForPicker(kind, appId, actions),
		[kind, appId, actions]
	);
	const groupedItems = useMemo(
		() => getGroupedItemsForPicker(kind, appId, subAppId, triggers, actions),
		[kind, appId, subAppId, triggers, actions]
	);
	const items = useMemo(
		() => getItemsForPicker(kind, appId, groupId, subAppId, triggers, actions),
		[kind, appId, groupId, subAppId, triggers, actions]
	);
	const showGroups = usesGroups && !groupId;
	const showCommunicationList = kind === 'action' && appId === 'communication' && !subAppId;
	const category = categoryForKind(kind);
	const appLabel = getAppLabel(kind, appId, subAppId);
	const metaAppId = subAppId || appId;
	const title = showGroups
		? __('Choose a group', 'workflow-automate')
		: usesGroupedSections
			? appLabel
			: __('Choose a node', 'workflow-automate');
	const showBack = (usesGroups && groupId) || subAppId;

	const handlePick = (item) => {
		if (item.available === false) {
			return;
		}

		onSelect(item, category);
		onClose();
	};

	const handleBack = () => {
		if (subAppId) {
			setSubAppId(null);
			return;
		}

		setGroupId(null);
	};

	return (
		<aside
			className="wfa-builder-picker"
			aria-label={title}
		>
			<div className="wfa-builder-picker__header">
				{showBack && (
					<Button
						variant="link"
						className="wfa-builder-picker__back"
						onClick={handleBack}
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
			) : usesGroupedSections && groupedItems ? (
				groupedItems.map((group) => (
					<div key={group.id} className="wfa-builder-picker__section">
						<h3 className="wfa-builder-picker__section-heading">
							{group.label}
						</h3>
						<ul className="wfa-builder-picker__list">
							{group.items.map((item) => (
								<PickerItem
									key={item.slug}
									item={item}
									appId={metaAppId}
									category={category}
									onPick={handlePick}
								/>
							))}
						</ul>
					</div>
				))
			) : (
				<ul className="wfa-builder-picker__list">
					{showCommunicationList &&
						subApps.map((subApp) => (
							<PickerSubApp
								key={subApp.id}
								subApp={subApp}
								onOpen={() => setSubAppId(subApp.id)}
							/>
						))}
					{items.map((item) => (
						<PickerItem
							key={item.slug}
							item={item}
							appId={metaAppId}
							category={category}
							onPick={handlePick}
						/>
					))}
				</ul>
			)}
		</aside>
	);
}

function PickerSubApp({ subApp, onOpen }) {
	const meta = getNodeMeta(subApp.id, 'action', subApp.id);

	return (
		<li>
			<button
				type="button"
				className="wfa-builder-picker__item wfa-builder-picker__item--subapp"
				onClick={onOpen}
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
						{subApp.label}
					</span>
				</span>
				<span className="wfa-builder-picker__item-chevron" aria-hidden="true">
					›
				</span>
			</button>
		</li>
	);
}

function PickerItem({ item, appId, category, onPick }) {
	const meta = getNodeMeta(item.slug, category, appId);
	const isDisabled = item.available === false;
	const disabledMessage = isDisabled
		? sprintf(
				/* translators: %s: plugin name, e.g. WooCommerce */
				__('Activate %s to use this trigger.', 'workflow-automate'),
				item.requires_plugin || __('this plugin', 'workflow-automate')
			)
		: '';

	return (
		<li>
			<button
				type="button"
				className={
					isDisabled
						? 'wfa-builder-picker__item wfa-builder-picker__item--disabled'
						: 'wfa-builder-picker__item'
				}
				onClick={() => onPick(item)}
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
						{getPickerItemLabel(item, appId)}
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
}
