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
				className="daiaw-builder-config__test-result daiaw-builder-config__test-result--error"
				role="alert"
			>
				<h3>{__('Response', 'dragwyb-ai-agent-workflows')}</h3>
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
		<div className="daiaw-builder-config__test-result">
			<div className="daiaw-builder-config__test-result-header">
				<h3>{__('Response', 'dragwyb-ai-agent-workflows')}</h3>
				<span
					className={
						success
							? 'daiaw-builder-config__test-badge daiaw-builder-config__test-badge--success'
							: 'daiaw-builder-config__test-badge daiaw-builder-config__test-badge--failed'
					}
				>
					{success
						? __('Success', 'dragwyb-ai-agent-workflows')
						: __('Failed', 'dragwyb-ai-agent-workflows')}
				</span>
			</div>

			<div className="daiaw-test-io daiaw-test-io--tabs">
				<div className="daiaw-test-io__tabs" role="tablist">
					<button
						type="button"
						id="daiaw-test-tab-input"
						role="tab"
						className={
							showInput
								? 'daiaw-test-io__tab daiaw-test-io__tab--active'
								: 'daiaw-test-io__tab'
						}
						aria-selected={showInput}
						aria-controls="daiaw-test-tabpanel"
						onClick={() => setActiveTab('input')}
					>
						{__('Input', 'dragwyb-ai-agent-workflows')}
					</button>
					<button
						type="button"
						id="daiaw-test-tab-output"
						role="tab"
						className={
							!showInput
								? 'daiaw-test-io__tab daiaw-test-io__tab--active'
								: 'daiaw-test-io__tab'
						}
						aria-selected={!showInput}
						aria-controls="daiaw-test-tabpanel"
						onClick={() => setActiveTab('output')}
					>
						{__('Output', 'dragwyb-ai-agent-workflows')}
					</button>
				</div>

				<div
					id="daiaw-test-tabpanel"
					className="daiaw-test-io__body"
					role="tabpanel"
					aria-labelledby={
						showInput ? 'daiaw-test-tab-input' : 'daiaw-test-tab-output'
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
