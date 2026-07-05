/**
 * Helpers for builder test-flow captured trigger samples.
 */

/**
 * @param {string} triggerType
 * @param {*}      payload
 * @return {boolean}
 */
function legacyPayloadMatchesTrigger(triggerType, payload) {
	if (!triggerType || !payload || typeof payload !== 'object') {
		return false;
	}

	const source = typeof payload.source === 'string' ? payload.source : '';

	if (triggerType === 'elementor_form_submitted_trigger') {
		return source === 'elementor';
	}

	if (triggerType === 'woocommerce_order_completed_trigger') {
		return source === 'woocommerce';
	}

	if (triggerType === 'contact_form7_submitted_trigger') {
		return source === 'contact-form-7';
	}

	if (triggerType === 'wpforms_submitted_trigger') {
		return source === 'wpforms';
	}

	if (triggerType.startsWith('wp_') && source === 'wordpress') {
		return true;
	}

	return false;
}

/**
 * @param {Object}      status
 * @param {string|null} triggerType Current trigger node slug.
 * @return {boolean}
 */
export function sampleMatchesTrigger(status, triggerType) {
	if (!status?.has_sample || !status.sample_payload) {
		return false;
	}

	const storedType = status.sample_payload_trigger_type;

	if (triggerType && storedType) {
		return storedType === triggerType;
	}

	if (triggerType && !storedType) {
		return legacyPayloadMatchesTrigger(triggerType, status.sample_payload);
	}

	return true;
}

/**
 * @param {Object}      status
 * @param {string|null} triggerType
 * @return {{ payload: *, capturedAt: string|null }}
 */
export function capturedSampleFromStatus(status, triggerType) {
	if (!sampleMatchesTrigger(status, triggerType)) {
		return { payload: null, capturedAt: null };
	}

	return {
		payload: status.sample_payload,
		capturedAt: status.captured_at || null,
	};
}
