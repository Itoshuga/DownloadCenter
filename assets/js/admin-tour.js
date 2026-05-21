(function() {
	'use strict';

	var config = window.ctdAdminTour || {};
	var storageKey = config.storageKey || 'ctd_admin_tour_state';
	var steps = Array.isArray(config.steps) ? config.steps : [];
	var labels = config.i18n || {};
	var activeTarget = null;
	var tooltip = null;
	var resizeHandler = null;

	function getLabel(key, fallback) {
		return labels[key] || fallback;
	}

	function getState() {
		var rawState;

		try {
			rawState = window.localStorage.getItem(storageKey);
		} catch (error) {
			return null;
		}

		if (!rawState) {
			return null;
		}

		try {
			return JSON.parse(rawState);
		} catch (error) {
			return null;
		}
	}

	function setState(state) {
		try {
			window.localStorage.setItem(storageKey, JSON.stringify(state));
		} catch (error) {
			// The tour can continue only when localStorage is available.
		}
	}

	function clearState() {
		try {
			window.localStorage.removeItem(storageKey);
		} catch (error) {
			// Nothing to clear.
		}
	}

	function removeTourElements() {
		var backdrop = document.querySelector('[data-ctd-tour-backdrop]');

		if (activeTarget) {
			activeTarget.classList.remove('ctd-tour-highlight');
			activeTarget = null;
		}

		if (tooltip && tooltip.parentNode) {
			tooltip.parentNode.removeChild(tooltip);
			tooltip = null;
		}

		if (backdrop && backdrop.parentNode) {
			backdrop.parentNode.removeChild(backdrop);
		}

		if (resizeHandler) {
			window.removeEventListener('resize', resizeHandler);
			window.removeEventListener('scroll', resizeHandler, true);
			resizeHandler = null;
		}
	}

	function stopTour() {
		removeTourElements();
		clearState();
	}

	function clamp(value, min, max) {
		return Math.max(min, Math.min(max, value));
	}

	function findTarget(selector) {
		if (!selector) {
			return document.querySelector('.wrap') || document.body;
		}

		return document.querySelector(selector) || document.querySelector('.wrap') || document.body;
	}

	function positionTooltip(target) {
		var rect;
		var width;
		var top;
		var left;

		if (!tooltip || !target) {
			return;
		}

		rect = target.getBoundingClientRect();
		width = Math.min(390, window.innerWidth - 32);
		tooltip.style.width = width + 'px';

		left = clamp(rect.left + (rect.width / 2) - (width / 2), 16, window.innerWidth - width - 16);
		top = rect.bottom + 14;

		if (top + tooltip.offsetHeight > window.innerHeight - 16) {
			top = Math.max(16, rect.top - tooltip.offsetHeight - 14);
		}

		tooltip.style.left = left + 'px';
		tooltip.style.top = top + 'px';
	}

	function goToStep(index) {
		var step;
		var targetUrl;

		if (index < 0) {
			index = 0;
		}

		if (index >= steps.length) {
			stopTour();
			return;
		}

		step = steps[index];
		setState({
			active: true,
			index: index
		});

		if (step.page && step.page !== config.currentPage) {
			targetUrl = config.pages && config.pages[step.page] ? config.pages[step.page] : '';

			if (targetUrl) {
				window.location.href = targetUrl;
				return;
			}
		}

		renderStep(index);
	}

	function createButton(text, action, isPrimary) {
		var button = document.createElement('button');

		button.type = 'button';
		button.className = isPrimary ? 'button button-primary' : 'button';
		button.textContent = text;
		button.setAttribute('data-ctd-tour-action', action);

		return button;
	}

	function renderStep(index) {
		var step = steps[index];
		var target = findTarget(step.selector);
		var backdrop;
		var progress;
		var closeButton;
		var title;
		var body;
		var actions;
		var nextAction = index + 1 >= steps.length ? 'finish' : 'next';

		removeTourElements();

		target.scrollIntoView({
			block: 'center',
			inline: 'nearest'
		});

		window.setTimeout(function() {
			backdrop = document.createElement('div');
			backdrop.className = 'ctd-tour-backdrop';
			backdrop.setAttribute('data-ctd-tour-backdrop', '1');
			document.body.appendChild(backdrop);

			target.classList.add('ctd-tour-highlight');
			activeTarget = target;

			tooltip = document.createElement('div');
			tooltip.className = 'ctd-tour-card';
			tooltip.setAttribute('role', 'dialog');
			tooltip.setAttribute('aria-live', 'polite');
			tooltip.setAttribute('aria-modal', 'false');

			progress = document.createElement('span');
			progress.className = 'ctd-tour-progress';
			progress.textContent = getLabel('step', 'Etape') + ' ' + (index + 1) + ' ' + getLabel('of', 'sur') + ' ' + steps.length;

			closeButton = document.createElement('button');
			closeButton.type = 'button';
			closeButton.className = 'ctd-tour-close';
			closeButton.textContent = '×';
			closeButton.setAttribute('aria-label', getLabel('close', 'Fermer'));
			closeButton.setAttribute('data-ctd-tour-action', 'close');

			title = document.createElement('h2');
			title.textContent = step.title || '';

			body = document.createElement('p');
			body.textContent = step.body || '';

			actions = document.createElement('div');
			actions.className = 'ctd-tour-actions';

			if (index > 0) {
				actions.appendChild(createButton(getLabel('previous', 'Precedent'), 'previous', false));
			}

			actions.appendChild(createButton(
				nextAction === 'finish' ? getLabel('finish', 'Terminer') : getLabel('next', 'Suivant'),
				nextAction,
				true
			));

			tooltip.appendChild(progress);
			tooltip.appendChild(closeButton);
			tooltip.appendChild(title);
			tooltip.appendChild(body);
			tooltip.appendChild(actions);
			document.body.appendChild(tooltip);

			positionTooltip(target);

			resizeHandler = function() {
				positionTooltip(target);
			};
			window.addEventListener('resize', resizeHandler);
			window.addEventListener('scroll', resizeHandler, true);
		}, 260);
	}

	function handleTourAction(action) {
		var state = getState() || {
			index: 0
		};
		var index = Number(state.index) || 0;

		if (action === 'close' || action === 'finish') {
			stopTour();
			return;
		}

		if (action === 'previous') {
			goToStep(index - 1);
			return;
		}

		if (action === 'next') {
			goToStep(index + 1);
		}
	}

	function startTour() {
		goToStep(0);
	}

	document.addEventListener('click', function(event) {
		var startButton = event.target.closest('[data-ctd-tour-start]');
		var actionButton = event.target.closest('[data-ctd-tour-action]');

		if (startButton) {
			event.preventDefault();
			startTour();
			return;
		}

		if (actionButton) {
			event.preventDefault();
			handleTourAction(actionButton.getAttribute('data-ctd-tour-action'));
		}
	});

	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape') {
			stopTour();
		}
	});

	document.addEventListener('DOMContentLoaded', function() {
		var state = getState();
		var index;

		if (!state || !state.active) {
			return;
		}

		index = Number(state.index) || 0;
		window.setTimeout(function() {
			goToStep(index);
		}, 300);
	});
})();
