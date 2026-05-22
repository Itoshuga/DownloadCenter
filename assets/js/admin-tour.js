(function() {
	'use strict';

	var config = window.ctdAdminTour || {};
	var storageKey = config.storageKey || 'ctd_admin_tour_state';
	var steps = Array.isArray(config.steps) ? config.steps : [];
	var labels = config.i18n || {};
	var activeTarget = null;
	var currentIndex = 0;
	var messageBox = null;
	var primaryButton = null;
	var resizeHandler = null;
	var tooltip = null;
	var validationHandler = null;

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
			// The tour needs localStorage to continue between admin pages.
		}
	}

	function updateState(partial) {
		var state = getState() || {};
		var key;

		for (key in partial) {
			if (Object.prototype.hasOwnProperty.call(partial, key)) {
				state[key] = partial[key];
			}
		}

		setState(state);
		return state;
	}

	function clearState() {
		try {
			window.localStorage.removeItem(storageKey);
		} catch (error) {
			// Nothing to clear.
		}
	}

	function getStepKey(step, index) {
		return [step.page || '', index, step.selector || ''].join('|');
	}

	function removeValidationListeners() {
		if (!validationHandler) {
			return;
		}

		document.removeEventListener('input', validationHandler, true);
		document.removeEventListener('change', validationHandler, true);
		document.removeEventListener('click', validationHandler, true);
		validationHandler = null;
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

		removeValidationListeners();
		messageBox = null;
		primaryButton = null;
	}

	function stopTour() {
		removeTourElements();
		clearState();
	}

	function findTarget(selector) {
		if (!selector) {
			return document.querySelector('.wrap') || document.body;
		}

		return document.querySelector(selector) || document.querySelector('.wrap') || document.body;
	}

	function getDockSide(target) {
		var rect = target.getBoundingClientRect();

		return (rect.left + (rect.width / 2)) > (window.innerWidth * 0.58) ? 'left' : 'right';
	}

	function positionTooltip(target) {
		if (!tooltip || !target) {
			return;
		}

		tooltip.classList.toggle('is-left', getDockSide(target) === 'left');
		tooltip.classList.toggle('is-right', getDockSide(target) !== 'left');
	}

	function getRequirements(step) {
		return Array.isArray(step.requirements) ? step.requirements : [];
	}

	function getFirstFocusable(selector) {
		var element = document.querySelector(selector || '');

		if (!element) {
			return null;
		}

		if (typeof element.focus === 'function' && /^(INPUT|TEXTAREA|SELECT|BUTTON|A)$/.test(element.tagName)) {
			return element;
		}

		return element.querySelector('input:not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled]), a[href]');
	}

	function hasInteraction(step, index, selector) {
		var state = getState() || {};
		var interactions = state.interactions || {};
		var stepKey = getStepKey(step, index);

		return !!interactions[stepKey + '|' + selector];
	}

	function markInteraction(step, index, selector) {
		var state = getState() || {};
		var interactions = state.interactions || {};

		interactions[getStepKey(step, index) + '|' + selector] = true;
		updateState({
			interactions: interactions
		});
	}

	function getFirstUnmetRequirement(step, index) {
		var requirements = getRequirements(step);
		var indexRequirement;
		var requirement;
		var element;

		for (indexRequirement = 0; indexRequirement < requirements.length; indexRequirement++) {
			requirement = requirements[indexRequirement];
			element = document.querySelector(requirement.selector || '');

			if (requirement.type === 'interacted') {
				if (!hasInteraction(step, index, requirement.selector || '')) {
					return requirement;
				}
				continue;
			}

			if (!element) {
				return requirement;
			}

			if (requirement.type === 'value' && !String(element.value || '').trim()) {
				return requirement;
			}
		}

		return null;
	}

	function focusRequirement(requirement) {
		var focusable = getFirstFocusable(requirement.selector);

		if (focusable) {
			try {
				focusable.focus({
					preventScroll: false
				});
			} catch (error) {
				focusable.focus();
			}
		}
	}

	function updateValidationState(shouldFocus) {
		var step = steps[currentIndex] || {};
		var requirements = getRequirements(step);
		var unmet = getFirstUnmetRequirement(step, currentIndex);

		if (primaryButton) {
			primaryButton.disabled = !!unmet;
			primaryButton.classList.toggle('ctd-tour-button-disabled', !!unmet);
		}

		if (messageBox) {
			messageBox.hidden = !requirements.length;
			messageBox.classList.toggle('is-ready', requirements.length && !unmet);
			messageBox.innerHTML = unmet
				? '<strong>' + getLabel('todo', 'A faire') + '</strong><span>' + (unmet.message || getLabel('required', 'Action requise avant de continuer.')) + '</span>'
				: '<strong>' + getLabel('valid', 'Valide') + '</strong><span>' + getLabel('ready', 'Etape validee, vous pouvez continuer.') + '</span>';
		}

		if (unmet && shouldFocus) {
			focusRequirement(unmet);
		}

		return !unmet;
	}

	function bindValidationListeners(step, index) {
		validationHandler = function(event) {
			var target = event.target;

			getRequirements(step).forEach(function(requirement) {
				var wrapper;

				if (requirement.type !== 'interacted' || !requirement.selector) {
					return;
				}

				wrapper = target && target.closest ? target.closest(requirement.selector) : null;

				if (wrapper) {
					markInteraction(step, index, requirement.selector);
				}
			});

			window.setTimeout(function() {
				updateValidationState(false);
			}, 40);
		};

		document.addEventListener('input', validationHandler, true);
		document.addEventListener('change', validationHandler, true);
		document.addEventListener('click', validationHandler, true);
	}

	function getSubmitter(form) {
		return form.querySelector('#publish, #submit, input[type="submit"], button[type="submit"]');
	}

	function submitStepForm(step, nextIndex) {
		var form = document.querySelector(step.form || '');
		var submitter;
		var event;

		if (!form) {
			goToStep(nextIndex);
			return;
		}

		if (step.finishOnSubmit) {
			clearState();
		} else {
			updateState({
				active: true,
				index: nextIndex
			});
		}

		removeTourElements();
		submitter = getSubmitter(form);

		if (form.requestSubmit) {
			try {
				form.requestSubmit(submitter || undefined);
			} catch (error) {
				form.requestSubmit();
			}
			return;
		}

		if (submitter) {
			submitter.click();
			return;
		}

		event = document.createEvent('Event');
		event.initEvent('submit', true, true);

		if (form.dispatchEvent(event)) {
			form.submit();
		}
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
		updateState({
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

	function appendTask(step) {
		var task;
		var label;

		if (!step.task || !tooltip) {
			return;
		}

		task = document.createElement('div');
		task.className = 'ctd-tour-task';

		label = document.createElement('strong');
		label.textContent = getLabel('todo', 'A faire');

		task.appendChild(label);
		task.appendChild(document.createTextNode(step.task));
		tooltip.appendChild(task);
	}

	function renderStep(index) {
		var step = steps[index];
		var target = findTarget(step.selector);
		var actions;
		var backdrop;
		var body;
		var closeButton;
		var closeIcon;
		var nextAction = index + 1 >= steps.length ? 'finish' : 'next';
		var progress;
		var title;

		currentIndex = index;
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
			tooltip.className = 'ctd-tour-card ctd-tour-card-docked';
			tooltip.setAttribute('role', 'dialog');
			tooltip.setAttribute('aria-live', 'polite');
			tooltip.setAttribute('aria-modal', 'false');

			progress = document.createElement('span');
			progress.className = 'ctd-tour-progress';
			progress.textContent = getLabel('step', 'Etape') + ' ' + (index + 1) + ' ' + getLabel('of', 'sur') + ' ' + steps.length;

			closeButton = document.createElement('button');
			closeButton.type = 'button';
			closeButton.className = 'ctd-tour-close';
			closeButton.setAttribute('aria-label', getLabel('close', 'Fermer'));
			closeButton.setAttribute('data-ctd-tour-action', 'close');

			closeIcon = document.createElement('i');
			closeIcon.className = 'fa-solid fa-xmark';
			closeIcon.setAttribute('aria-hidden', 'true');
			closeButton.appendChild(closeIcon);

			title = document.createElement('h2');
			title.textContent = step.title || '';

			body = document.createElement('p');
			body.textContent = step.body || '';

			messageBox = document.createElement('div');
			messageBox.className = 'ctd-tour-message';
			messageBox.hidden = true;

			actions = document.createElement('div');
			actions.className = 'ctd-tour-actions';

			if (index > 0) {
				actions.appendChild(createButton(getLabel('previous', 'Precedent'), 'previous', false));
			}

			primaryButton = createButton(
				step.nextLabel || (nextAction === 'finish' ? getLabel('finish', 'Terminer') : getLabel('next', 'Suivant')),
				nextAction,
				true
			);
			actions.appendChild(primaryButton);

			tooltip.appendChild(progress);
			tooltip.appendChild(closeButton);
			tooltip.appendChild(title);
			tooltip.appendChild(body);
			appendTask(step);
			tooltip.appendChild(messageBox);
			tooltip.appendChild(actions);
			document.body.appendChild(tooltip);

			bindValidationListeners(step, index);
			updateValidationState(false);
			positionTooltip(target);

			resizeHandler = function() {
				positionTooltip(target);
			};
			window.addEventListener('resize', resizeHandler);
			window.addEventListener('scroll', resizeHandler, true);
		}, 260);
	}

	function handleTourAction(action) {
		var index = currentIndex;
		var nextIndex = action === 'previous' ? index - 1 : index + 1;
		var step = steps[index] || {};

		if (action === 'close') {
			stopTour();
			return;
		}

		if (action === 'previous') {
			goToStep(nextIndex);
			return;
		}

		if (!updateValidationState(true)) {
			return;
		}

		if (step.action === 'submit') {
			submitStepForm(step, step.finishOnSubmit ? steps.length : nextIndex);
			return;
		}

		if (action === 'finish') {
			stopTour();
			return;
		}

		goToStep(nextIndex);
	}

	function startTour() {
		updateState({
			active: true,
			index: 0,
			interactions: {}
		});
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
