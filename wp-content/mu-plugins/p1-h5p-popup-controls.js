(function () {
	'use strict';

	const contentId = '3';
	let fullscreenRefillCount = 0;

	function isVisible(element) {
		return Boolean(
			element &&
			element.offsetParent !== null &&
			!element.classList.contains('h5p-hidden')
		);
	}

	function getInteractiveVideo() {
		return (window.H5P?.instances || []).find(
			(instance) => String(instance.contentId) === contentId
		);
	}

	function getInteractionButtons() {
		return Array.from(document.querySelectorAll('.h5p-interaction[role="button"]'));
	}

	function getInteractionForButton(instance, interactionButton) {
		return (instance.interactions || []).find((interaction) => {
			const element = interaction.getElement?.();

			return element?.get?.(0) === interactionButton || element?.[0] === interactionButton;
		});
	}

	function bindRewindOnClick(instance, interactionButton) {
		if (interactionButton.dataset.p1RewindBound) {
			return;
		}

		const interaction = getInteractionForButton(instance, interactionButton);
		const startTime = interaction?.getDuration?.().from;

		if (!Number.isFinite(startTime)) {
			return;
		}

		interactionButton.dataset.p1RewindBound = 'true';
		interactionButton.addEventListener(
			'click',
			() => {
				interactionButton.dataset.p1Opened = 'true';

				const currentTime = instance.video?.getCurrentTime?.() ?? instance.currentTime;

				if (Number.isFinite(currentTime) && currentTime > startTime + 0.25) {
					instance.pause();
					instance.seek(startTime, { force: true });
				}
			},
			true
		);
	}

	function shouldAutoOpen(instance, interactionButton) {
		const interaction = getInteractionForButton(instance, interactionButton);
		const startTime = interaction?.getDuration?.().from;
		const currentTime = instance.video?.getCurrentTime?.() ?? instance.currentTime;

		return (
			Number.isFinite(startTime) &&
			Number.isFinite(currentTime) &&
			currentTime >= startTime - 0.15 &&
			currentTime <= startTime + 0.75
		);
	}

	function addContinueButton(instance) {
		const dialog = document.querySelector(
			'.h5p-dialog-wrapper:not(.h5p-hidden) .h5p-dialog-interaction'
		);

		if (!dialog || dialog.querySelector('.p1-h5p-continue-button')) {
			return;
		}

		const button = document.createElement('button');
		button.type = 'button';
		button.className =
			'p1-h5p-continue-button h5p-joubelui-button h5p-button';
		button.textContent = 'Continue';
		button.addEventListener('click', () => {
			document
				.querySelector('.h5p-dialog-wrapper:not(.h5p-hidden) .h5p-dialog-close')
				?.click();
			instance.play();
			instance.controls?.$play?.focus();
		});

		dialog.appendChild(button);
	}

	function isInFullscreen() {
		const container = document.querySelector('.h5p-container');

		return Boolean(
			container &&
			(container.classList.contains('h5p-semi-fullscreen') ||
				container.classList.contains('h5p-fullscreen'))
		);
	}

	function refillVideo(instance) {
		// Let H5P recompute its own layout so the video fills the screen and the
		// interaction overlays stay correctly positioned.
		if (typeof instance.resize === 'function') {
			instance.resize();
		} else if (typeof instance.trigger === 'function') {
			instance.trigger('resize');
		}
	}

	function bindFullscreenRefill(instance) {
		if (instance.__p1FullscreenBound || typeof instance.on !== 'function') {
			return;
		}

		instance.__p1FullscreenBound = true;

		const nudge = () => {
			// H5P's initial resize on fullscreen enter runs before the iframe has
			// finished resizing, so the video stays at its small inline scale.
			// Re-run H5P's own resize once the layout has settled.
			[150, 350, 700].forEach((delay) => {
				window.setTimeout(() => refillVideo(instance), delay);
			});
		};

		instance.on('enterFullScreen', nudge);
		instance.on('exitFullScreen', nudge);
	}

	function maybeRefillFullscreen(instance) {
		if (!isInFullscreen()) {
			fullscreenRefillCount = 0;
			return;
		}

		if (fullscreenRefillCount >= 4) {
			return;
		}

		const container = document.querySelector('.h5p-container');
		const wrapper = document.querySelector('.h5p-video-wrapper');

		if (!container || !wrapper) {
			return;
		}

		const rect = wrapper.getBoundingClientRect();
		const underfilled =
			rect.width < container.clientWidth * 0.92 &&
			rect.height < container.clientHeight * 0.92;

		if (underfilled) {
			fullscreenRefillCount += 1;
			refillVideo(instance);
		}
	}

	function syncPopup() {
		const instance = getInteractiveVideo();

		if (!instance) {
			return;
		}

		bindFullscreenRefill(instance);
		maybeRefillFullscreen(instance);

		const interactionButtons = getInteractionButtons();

		if (!interactionButtons.length) {
			return;
		}

		interactionButtons.forEach((interactionButton) => {
			bindRewindOnClick(instance, interactionButton);

			if (!isVisible(interactionButton)) {
				delete interactionButton.dataset.p1Opened;
				return;
			}

			if (
				!interactionButton.dataset.p1Opened &&
				shouldAutoOpen(instance, interactionButton) &&
				!document.querySelector('.h5p-dialog-wrapper:not(.h5p-hidden)')
			) {
				interactionButton.dataset.p1Opened = 'true';
				interactionButton.click();
			}
		});

		addContinueButton(instance);
	}

	window.addEventListener('load', () => {
		if (!window.H5PIntegration?.contents?.['cid-3']) {
			return;
		}

		window.setInterval(syncPopup, 100);
	});
})();
