import { __ } from '@wordpress/i18n';

import VariablePicker from './VariablePicker';

/**
 * Shows captured trigger data using the same tree picker as action fields.
 *
 * @param {Object}      props
 * @param {*}           props.payload
 * @param {string|null} props.capturedAt
 * @param {string}      props.sourceLabel
 */
export default function CapturedResponse({ payload, capturedAt, sourceLabel }) {
	const hasPayload =
		payload !== null &&
		payload !== undefined &&
		(typeof payload !== 'object' || Object.keys(payload).length > 0);

	const handleSelect = (token) => {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(token);
		}
	};

	return (
		<div className="wfa-builder-config__captured">
			<div className="wfa-builder-config__captured-header">
				<h3>{__('Captured response', 'workflow-automate')}</h3>
				{capturedAt && (
					<span className="wfa-builder-config__captured-time">
						{capturedAt}
					</span>
				)}
			</div>
			{hasPayload ? (
				<VariablePicker
					payload={payload}
					sourceLabel={sourceLabel}
					onSelect={handleSelect}
					onClose={() => {}}
					embedded
				/>
			) : (
				<p className="wfa-builder-config__captured-empty">
					{__(
						'No captured data for this trigger yet. Use Test Flow → Listen new response, then fire the trigger.',
						'workflow-automate'
					)}
				</p>
			)}
		</div>
	);
}
