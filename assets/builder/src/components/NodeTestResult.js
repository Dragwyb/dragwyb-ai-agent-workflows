import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import TestDataTree from './TestDataTree';

/**
 * Shows the result of "Test node" — Input / Output tabs with tree data.
 *
 * @param {Object}      props
 * @param {boolean}     props.success
 * @param {string|null} props.error
 * @param {Object|null} props.input
 * @param {Object|null} props.output
 */
export default function NodeTestResult({
	success,
	error,
	input = null,
	output = null,
}) {
	const [activeTab, setActiveTab] = useState('input');

	useEffect(() => {
		if (output && typeof output === 'object' && Object.keys(output).length > 0) {
			setActiveTab('output');
		}
	}, [output]);

	if (error) {
		return (
			<div
				className="wfa-builder-config__test-result wfa-builder-config__test-result--error"
				role="alert"
			>
				<h3>{__('Response', 'workflow-automate')}</h3>
				<p>{error}</p>
			</div>
		);
	}

	const hasInput =
		input && typeof input === 'object' && Object.keys(input).length > 0;
	const hasOutput =
		output && typeof output === 'object' && Object.keys(output).length > 0;

	if (!hasInput && !hasOutput) {
		return null;
	}

	const showInput = activeTab === 'input';

	return (
		<div className="wfa-builder-config__test-result">
			<div className="wfa-builder-config__test-result-header">
				<h3>{__('Response', 'workflow-automate')}</h3>
				<span
					className={
						success
							? 'wfa-builder-config__test-badge wfa-builder-config__test-badge--success'
							: 'wfa-builder-config__test-badge wfa-builder-config__test-badge--failed'
					}
				>
					{success
						? __('Success', 'workflow-automate')
						: __('Failed', 'workflow-automate')}
				</span>
			</div>

			<div className="wfa-test-io wfa-test-io--tabs">
				<div className="wfa-test-io__tabs" role="tablist">
					<button
						type="button"
						id="wfa-test-tab-input"
						role="tab"
						className={
							showInput
								? 'wfa-test-io__tab wfa-test-io__tab--active'
								: 'wfa-test-io__tab'
						}
						aria-selected={showInput}
						aria-controls="wfa-test-tabpanel"
						onClick={() => setActiveTab('input')}
					>
						{__('Input', 'workflow-automate')}
					</button>
					<button
						type="button"
						id="wfa-test-tab-output"
						role="tab"
						className={
							!showInput
								? 'wfa-test-io__tab wfa-test-io__tab--active'
								: 'wfa-test-io__tab'
						}
						aria-selected={!showInput}
						aria-controls="wfa-test-tabpanel"
						onClick={() => setActiveTab('output')}
					>
						{__('Output', 'workflow-automate')}
					</button>
				</div>

				<div
					id="wfa-test-tabpanel"
					className="wfa-test-io__body"
					role="tabpanel"
					aria-labelledby={
						showInput ? 'wfa-test-tab-input' : 'wfa-test-tab-output'
					}
				>
					{showInput ? (
						<TestDataTree data={input} embedded />
					) : (
						<TestDataTree data={output} embedded />
					)}
				</div>
			</div>
		</div>
	);
}
