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
				className="aiawa-builder-config__test-result aiawa-builder-config__test-result--error"
				role="alert"
			>
				<h3>{__('Response', 'dragwyb-agentflow')}</h3>
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
		<div className="aiawa-builder-config__test-result">
			<div className="aiawa-builder-config__test-result-header">
				<h3>{__('Response', 'dragwyb-agentflow')}</h3>
				<span
					className={
						success
							? 'aiawa-builder-config__test-badge aiawa-builder-config__test-badge--success'
							: 'aiawa-builder-config__test-badge aiawa-builder-config__test-badge--failed'
					}
				>
					{success
						? __('Success', 'dragwyb-agentflow')
						: __('Failed', 'dragwyb-agentflow')}
				</span>
			</div>

			<div className="aiawa-test-io aiawa-test-io--tabs">
				<div className="aiawa-test-io__tabs" role="tablist">
					<button
						type="button"
						id="aiawa-test-tab-input"
						role="tab"
						className={
							showInput
								? 'aiawa-test-io__tab aiawa-test-io__tab--active'
								: 'aiawa-test-io__tab'
						}
						aria-selected={showInput}
						aria-controls="aiawa-test-tabpanel"
						onClick={() => setActiveTab('input')}
					>
						{__('Input', 'dragwyb-agentflow')}
					</button>
					<button
						type="button"
						id="aiawa-test-tab-output"
						role="tab"
						className={
							!showInput
								? 'aiawa-test-io__tab aiawa-test-io__tab--active'
								: 'aiawa-test-io__tab'
						}
						aria-selected={!showInput}
						aria-controls="aiawa-test-tabpanel"
						onClick={() => setActiveTab('output')}
					>
						{__('Output', 'dragwyb-agentflow')}
					</button>
				</div>

				<div
					id="aiawa-test-tabpanel"
					className="aiawa-test-io__body"
					role="tabpanel"
					aria-labelledby={
						showInput ? 'aiawa-test-tab-input' : 'aiawa-test-tab-output'
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
