const settings = window.p1ReflectionPromptSettings;

function setStatus( element, message, state = '' ) {
	element.textContent = message;
	element.dataset.state = state;
}

async function requestResponse( lessonId, responseId, options = {} ) {
	const url = `${
		settings.restUrl
	}p1-race-academy/v1/reflections/${ lessonId }/${ encodeURIComponent(
		responseId
	) }`;
	const response = await fetch( url, {
		...options,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': settings.nonce,
			...( options.headers || {} ),
		},
	} );
	const body = await response.json();

	if ( ! response.ok ) {
		throw new Error( body.message || 'Unable to save your response.' );
	}

	return body;
}

async function initializePrompt( block ) {
	const responseId = block.dataset.responseId;
	const textarea = block.querySelector( '.p1-reflection-prompt__response' );
	const button = block.querySelector( '.p1-reflection-prompt__save' );
	const status = block.querySelector( '.p1-reflection-prompt__status' );

	if ( ! responseId || ! textarea || ! button || ! status ) {
		return;
	}

	if ( ! settings?.isLoggedIn ) {
		textarea.disabled = true;
		button.disabled = true;
		setStatus(
			status,
			'Log in to write and save your response.',
			'notice'
		);
		return;
	}

	button.disabled = true;

	try {
		const saved = await requestResponse( settings.lessonId, responseId );
		textarea.value = saved.response || '';
		button.disabled = false;
		setStatus(
			status,
			saved.response
				? 'Saved response loaded.'
				: 'No response saved yet.',
			saved.response ? 'success' : 'notice'
		);
	} catch ( error ) {
		textarea.disabled = true;
		setStatus( status, error.message, 'error' );
		return;
	}

	textarea.addEventListener( 'input', () => {
		setStatus( status, 'Unsaved changes.', 'notice' );
	} );

	button.addEventListener( 'click', async () => {
		button.disabled = true;
		setStatus( status, 'Saving…' );

		try {
			await requestResponse( settings.lessonId, responseId, {
				method: 'POST',
				body: JSON.stringify( { response: textarea.value } ),
			} );
			setStatus( status, 'Response saved.', 'success' );
		} catch ( error ) {
			setStatus( status, error.message, 'error' );
		} finally {
			button.disabled = false;
		}
	} );
}

document
	.querySelectorAll( '.wp-block-p1-race-academy-reflection-prompt' )
	.forEach( initializePrompt );
