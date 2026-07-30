import { useState, useMemo } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	appUsesGroups,
	appUsesGroupedSections,
	categoryForKind,
	getAgentToolPickerSections,
	getAppLabel,
	getGroupedItemsForPicker,
	getGroupsForApp,
	getItemsForPicker,
	getPickerItemLabel,
	getSubAppsForPicker,
} from '../nodeCatalog';
import { getNodeMeta } from '../nodeMeta';

/**
 * Right sidebar for picking a trigger, agent, action, or agent attachment.
 *
 * @param {Object}        props
 * @param {'trigger'|'agent'|'action'|'agent-tool'|'agent-chat-model'|'branch-action'} props.kind
 * @param {string}        props.appId
 * @param {Array<Object>} props.triggers
 * @param {Array<Object>} props.actions
 * @param {boolean}       [props.hasExistingTrigger]
 * @param {Function}      props.onSelect   ( nodeType, category ) => void
 * @param {Function}      props.onClose
 */
export default function PickerSidebar({
	kind,
	appId,
	triggers,
	actions,
	hasExistingTrigger = false,
	onSelect,
	onClose,
}) {
	const [groupId, setGroupId] = useState(null);
	const [subAppId, setSubAppId] = useState(null);
	const [toolQuery, setToolQuery] = useState('');
	const [groupedQuery, setGroupedQuery] = useState('');
	const pickerKind =
		kind === 'branch-action' ||
		kind === 'edge-insert' ||
		kind === 'edge-branch-insert'
			? 'action'
			: kind;
	const usesGroups = appUsesGroups(pickerKind, appId);
	const usesGroupedSections = appUsesGroupedSections(pickerKind, appId, subAppId);
	const groups = useMemo(
		() => getGroupsForApp(pickerKind, appId, triggers),
		[pickerKind, appId, triggers]
	);
	const subApps = useMemo(
		() => getSubAppsForPicker(pickerKind, appId, actions),
		[pickerKind, appId, actions]
	);
	const groupedItems = useMemo(
		() =>
			getGroupedItemsForPicker(
				pickerKind,
				appId,
				subAppId,
				triggers,
				actions,
				groupedQuery
			),
		[pickerKind, appId, subAppId, triggers, actions, groupedQuery]
	);
	const items = useMemo(
		() => getItemsForPicker(pickerKind, appId, groupId, subAppId, triggers, actions),
		[pickerKind, appId, groupId, subAppId, triggers, actions]
	);
	const toolSections = useMemo(
		() =>
			kind === 'agent-tool'
				? getAgentToolPickerSections(actions, toolQuery)
				: [],
		[kind, actions, toolQuery]
	);
	const showGroups = usesGroups && !groupId;
	const showCommunicationList =
		pickerKind === 'action' && appId === 'communication' && !subAppId;
	const category = categoryForKind(pickerKind);
	const appLabel = getAppLabel(pickerKind, appId, subAppId);
	const metaAppId = subAppId || appId;
	const title =
		kind === 'agent-chat-model' ||
		kind === 'agent-fallback-chat-model' ||
		kind === 'parser-chat-model'
			? kind === 'agent-fallback-chat-model'
				? __('Select fallback chat model', 'workflow-automate')
				: kind === 'parser-chat-model'
					? __('Select Auto-Fix chat model', 'workflow-automate')
					: __('Select chat model', 'workflow-automate')
			: kind === 'agent-tool'
				? __('Add tool to agent', 'workflow-automate')
				: kind === 'branch-action'
					? __('Add step to branch', 'workflow-automate')
					: kind === 'edge-insert' || kind === 'edge-branch-insert'
						? __('Add step between nodes', 'workflow-automate')
						: showGroups
						? __('Choose a group', 'workflow-automate')
						: usesGroupedSections
							? appLabel
							: __('Choose a node', 'workflow-automate');
	const replaceHint =
		kind === 'trigger' && hasExistingTrigger && !showGroups
			? __('Selecting a trigger replaces your current one.', 'workflow-automate')
			: kind === 'agent-chat-model'
				? __(
					'Pick a provider — configure API key and model on the canvas node.',
					'workflow-automate'
				)
				: '';
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

	const renderItem = (item, itemAppId = metaAppId) => {
		const meta = getNodeMeta(
			item.slug,
			category,
			item.pickerAppId || itemAppId
		);
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
							{getPickerItemLabel(item, itemAppId)}
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

			{replaceHint && (
				<p className="wfa-builder-picker__hint">{replaceHint}</p>
			)}

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
			) : kind === 'agent-tool' ? (
				<>
					<div className="wfa-builder-picker__search">
						<TextControl
							label={__('Search tools', 'workflow-automate')}
							hideLabelFromVision
							placeholder={__('Search tools…', 'workflow-automate')}
							value={toolQuery}
							onChange={setToolQuery}
						/>
					</div>
					{toolSections.length === 0 ? (
						<p className="wfa-builder-picker__empty">
							{__(
								'No tools match your search.',
								'workflow-automate'
							)}
						</p>
					) : (
						toolSections.map((section) => (
							<div
								key={section.id}
								className="wfa-builder-picker__section"
							>
								<h3 className="wfa-builder-picker__section-heading">
									{section.label}
								</h3>
								<ul className="wfa-builder-picker__list">
									{section.items.map((item) =>
										renderItem(item, item.pickerAppId)
									)}
								</ul>
							</div>
						))
					)}
				</>
			) : usesGroupedSections && groupedItems ? (
				<>
					<div className="wfa-builder-picker__search">
						<TextControl
							label={__('Search actions', 'workflow-automate')}
							hideLabelFromVision
							placeholder={__('Search actions…', 'workflow-automate')}
							value={groupedQuery}
							onChange={setGroupedQuery}
						/>
					</div>
					{groupedItems.length === 0 ? (
						<p className="wfa-builder-picker__empty">
							{__(
								'No actions match your search.',
								'workflow-automate'
							)}
						</p>
					) : (
						groupedItems.map((group) => (
							<div
								key={group.id}
								className="wfa-builder-picker__section"
							>
								<h3 className="wfa-builder-picker__section-heading">
									{group.label}
								</h3>
								<ul className="wfa-builder-picker__list">
									{group.items.map((item) =>
										renderItem(item, metaAppId)
									)}
								</ul>
							</div>
						))
					)}
				</>
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
					{items.map((item) => renderItem(item))}
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
