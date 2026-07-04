import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STATUS_LABELS = {
	idle: '',
	dirty: __('Unsaved changes', 'workflow-automate'),
	saving: __('Saving…', 'workflow-automate'),
	saved: __('Saved', 'workflow-automate'),
	error: __(
		'Save failed — check your connection and try again.',
		'workflow-automate'
	),
};

/**
 * Top bar: back link, editable title, save status, manual save button.
 *
 * @param {Object}   props
 * @param {string}   props.title
 * @param {Function} props.onTitleChange
 * @param {string}   props.status
 * @param {Function} props.onSave
 * @param {string}   props.listUrl
 * @param {boolean}  props.saveDisabled
 */
export default function Header({
	title,
	onTitleChange,
	status,
	onSave,
	listUrl,
	saveDisabled,
}) {
	return (
		<header className="wfa-builder-header">
			<div className="wfa-builder-header__left">
				{listUrl && (
					<a className="wfa-builder-header__back" href={listUrl}>
						{__('← Workflows', 'workflow-automate')}
					</a>
				)}
				<input
					type="text"
					className="wfa-builder-header__title"
					value={title}
					placeholder={__('Untitled workflow', 'workflow-automate')}
					aria-label={__('Workflow title', 'workflow-automate')}
					onChange={(event) => onTitleChange(event.target.value)}
				/>
			</div>
			<div className="wfa-builder-header__right">
				<span
					className={`wfa-builder-header__status wfa-builder-header__status--${status}`}
					role="status"
				>
					{STATUS_LABELS[status] || ''}
				</span>
				<Button isPrimary onClick={onSave} disabled={saveDisabled}>
					{__('Save', 'workflow-automate')}
				</Button>
			</div>
		</header>
	);
}
