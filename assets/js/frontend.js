(function() {
	'use strict';

	function splitTerms(value) {
		return String(value || '').split(/\s+/).filter(Boolean);
	}

	function hasTerm(card, key, value) {
		if (!value) {
			return true;
		}

		return splitTerms(card.getAttribute('data-' + key)).indexOf(value) !== -1;
	}

	function getPerPage(library) {
		var perPage = parseInt(library.getAttribute('data-ctd-per-page'), 10);

		return perPage > 0 ? perPage : 20;
	}

	function getCurrentPage(library) {
		var currentPage = parseInt(library.getAttribute('data-ctd-current-page'), 10);

		return currentPage > 0 ? currentPage : 1;
	}

	function setCurrentPage(library, page) {
		library.setAttribute('data-ctd-current-page', String(Math.max(1, page || 1)));
	}

	function cardMatchesFilters(card, category, range, language) {
		return hasTerm(card, 'category', category)
			&& hasTerm(card, 'range', range)
			&& hasTerm(card, 'language', language);
	}

	function getPaginationItems(currentPage, totalPages) {
		var pages = [];
		var page;

		if (totalPages <= 7) {
			for (page = 1; page <= totalPages; page += 1) {
				pages.push(page);
			}

			return pages;
		}

		pages.push(1);

		if (currentPage > 3) {
			pages.push('start-ellipsis');
		}

		for (page = Math.max(2, currentPage - 1); page <= Math.min(totalPages - 1, currentPage + 1); page += 1) {
			pages.push(page);
		}

		if (currentPage < totalPages - 2) {
			pages.push('end-ellipsis');
		}

		pages.push(totalPages);

		return pages;
	}

	function renderPagination(library, visibleCount, totalPages, currentPage) {
		var pagination = library.querySelector('[data-ctd-pagination]');
		var pagesContainer;
		var previousButton;
		var nextButton;
		var fragment;

		if (!pagination) {
			return;
		}

		pagesContainer = pagination.querySelector('[data-ctd-pagination-pages]');
		previousButton = pagination.querySelector('[data-ctd-pagination-prev]');
		nextButton = pagination.querySelector('[data-ctd-pagination-next]');
		pagination.hidden = visibleCount === 0 || totalPages <= 1;

		if (previousButton) {
			previousButton.disabled = currentPage <= 1;
		}

		if (nextButton) {
			nextButton.disabled = currentPage >= totalPages;
		}

		if (!pagesContainer) {
			return;
		}

		pagesContainer.innerHTML = '';
		fragment = document.createDocumentFragment();

		getPaginationItems(currentPage, totalPages).forEach(function(item) {
			var element;

			if (typeof item === 'string') {
				element = document.createElement('span');
				element.className = 'ctd-front-pagination-ellipsis';
				element.textContent = '...';
				fragment.appendChild(element);
				return;
			}

			element = document.createElement('button');
			element.type = 'button';
			element.className = 'ctd-front-pagination-page';
			element.setAttribute('data-ctd-pagination-page', String(item));
			element.setAttribute('aria-label', 'Page ' + item);
			element.textContent = String(item);

			if (item === currentPage) {
				element.classList.add('is-active');
				element.setAttribute('aria-current', 'page');
			}

			fragment.appendChild(element);
		});

		pagesContainer.appendChild(fragment);
	}

	function refreshLibrary(library, resetPage) {
		var category = getFilterValue(library, 'category');
		var range = getFilterValue(library, 'range');
		var language = getFilterValue(library, 'language');
		var cards = library.querySelectorAll('[data-ctd-document]');
		var empty = library.querySelector('[data-ctd-empty]');
		var matchingCards = [];
		var perPage = getPerPage(library);
		var currentPage;
		var totalPages;
		var startIndex;
		var endIndex;

		if (resetPage) {
			setCurrentPage(library, 1);
		}

		Array.prototype.forEach.call(cards, function(card) {
			var isMatching = cardMatchesFilters(card, category, range, language);

			if (isMatching) {
				matchingCards.push(card);
			}
		});

		totalPages = Math.max(1, Math.ceil(matchingCards.length / perPage));
		currentPage = Math.min(getCurrentPage(library), totalPages);
		setCurrentPage(library, currentPage);
		startIndex = (currentPage - 1) * perPage;
		endIndex = startIndex + perPage;

		Array.prototype.forEach.call(cards, function(card) {
			card.hidden = true;
		});

		matchingCards.forEach(function(card, index) {
			if (index >= startIndex && index < endIndex) {
				card.hidden = false;
			}
		});

		if (empty) {
			empty.classList.toggle('is-visible', matchingCards.length === 0);
		}

		renderPagination(library, matchingCards.length, totalPages, currentPage);
	}

	function getFilterValue(library, key) {
		var field = library.querySelector('[data-ctd-filter="' + key + '"]');

		return field ? field.value : '';
	}

	function getFilterControl(library, key) {
		return library.querySelector('[data-ctd-filter-control="' + key + '"]');
	}

	function getRelationships(library) {
		var raw = library.getAttribute('data-ctd-filter-relationships') || '{}';

		try {
			return JSON.parse(raw);
		} catch (error) {
			return {};
		}
	}

	function isRelatedOptionAllowed(value, allowedValues) {
		if (!value) {
			return true;
		}

		if (!Array.isArray(allowedValues) || allowedValues.length === 0) {
			return true;
		}

		return allowedValues.indexOf(value) !== -1;
	}

	function setFilterFromOption(control, option) {
		var field = control.querySelector('[data-ctd-filter]');
		var current = control.querySelector('[data-ctd-filter-current]');
		var content = option.querySelector('[data-ctd-filter-option-content]');

		if (field) {
			field.value = option.getAttribute('data-value') || '';
		}

		if (current && content) {
			current.innerHTML = content.innerHTML;
		}

		Array.prototype.forEach.call(control.querySelectorAll('[data-ctd-filter-option]'), function(item) {
			var isSelected = item === option;

			item.classList.toggle('is-selected', isSelected);
			item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
		});
	}

	function resetFilterControl(control) {
		var defaultOption;

		if (!control) {
			return;
		}

		defaultOption = control.querySelector('[data-ctd-filter-option][data-value=""]');

		if (defaultOption) {
			setFilterFromOption(control, defaultOption);
		}
	}

	function refreshRelatedFilterOptions(library) {
		var category = getFilterValue(library, 'category');
		var relationships = getRelationships(library);
		var relationship = category && relationships[category] ? relationships[category] : null;

		updateRelatedFilterControl(library, 'range', relationship ? relationship.ranges : []);
		updateRelatedFilterControl(library, 'language', relationship ? relationship.languages : []);
	}

	function updateRelatedFilterControl(library, key, allowedValues) {
		var control = getFilterControl(library, key);
		var selectedIsAllowed = true;
		var selectedOption;

		if (!control) {
			return;
		}

		selectedOption = control.querySelector('[data-ctd-filter-option].is-selected');

		Array.prototype.forEach.call(control.querySelectorAll('[data-ctd-filter-option]'), function(option) {
			var value = option.getAttribute('data-value') || '';
			var isAllowed = isRelatedOptionAllowed(value, allowedValues);

			option.hidden = !isAllowed;
			option.disabled = !isAllowed;
			option.setAttribute('aria-hidden', isAllowed ? 'false' : 'true');

			if (option === selectedOption && !isAllowed) {
				selectedIsAllowed = false;
			}
		});

		if (!selectedIsAllowed) {
			resetFilterControl(control);
		}
	}

	function closeFilter(control) {
		var button = control.querySelector('[data-ctd-filter-button]');

		control.classList.remove('is-open');

		if (button) {
			button.setAttribute('aria-expanded', 'false');
		}
	}

	function closeFilters(library, exceptControl) {
		Array.prototype.forEach.call(library.querySelectorAll('[data-ctd-filter-control]'), function(control) {
			if (control !== exceptControl) {
				closeFilter(control);
			}
		});
	}

	function toggleFilter(library, control) {
		var button = control.querySelector('[data-ctd-filter-button]');
		var isOpen = control.classList.contains('is-open');

		closeFilters(library, control);
		control.classList.toggle('is-open', !isOpen);

		if (button) {
			button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
		}
	}

	function selectFilterOption(library, control, option) {
		setFilterFromOption(control, option);

		if (control.getAttribute('data-ctd-filter-control') === 'category') {
			refreshRelatedFilterOptions(library);
		}

		closeFilter(control);
		refreshLibrary(library, true);
	}

	function initFilterControl(library, control) {
		var button = control.querySelector('[data-ctd-filter-button]');

		if (button) {
			button.addEventListener('click', function(event) {
				event.preventDefault();
				toggleFilter(library, control);
			});
		}

		Array.prototype.forEach.call(control.querySelectorAll('[data-ctd-filter-option]'), function(option) {
			option.addEventListener('click', function(event) {
				event.preventDefault();
				selectFilterOption(library, control, option);
			});
		});
	}

	function initPagination(library) {
		var pagination = library.querySelector('[data-ctd-pagination]');

		if (!pagination) {
			return;
		}

		pagination.addEventListener('click', function(event) {
			var button = event.target.closest('[data-ctd-pagination-page], [data-ctd-pagination-prev], [data-ctd-pagination-next]');
			var currentPage;
			var targetPage;

			if (!button || button.disabled) {
				return;
			}

			event.preventDefault();
			currentPage = getCurrentPage(library);

			if (button.hasAttribute('data-ctd-pagination-prev')) {
				targetPage = currentPage - 1;
			} else if (button.hasAttribute('data-ctd-pagination-next')) {
				targetPage = currentPage + 1;
			} else {
				targetPage = parseInt(button.getAttribute('data-ctd-pagination-page'), 10);
			}

			setCurrentPage(library, targetPage);
			refreshLibrary(library, false);
		});
	}

	function initLibrary(library) {
		Array.prototype.forEach.call(library.querySelectorAll('[data-ctd-filter-control]'), function(control) {
			initFilterControl(library, control);
		});

		initPagination(library);
		setCurrentPage(library, 1);
		refreshRelatedFilterOptions(library);
		refreshLibrary(library, false);
	}

	function getModal(id) {
		return id ? document.getElementById(id) : null;
	}

	function openModal(modal, trigger) {
		if (!modal) {
			return;
		}

		modal.hidden = false;
		modal.classList.add('is-open');

		if (trigger) {
			modal._ctdTrigger = trigger;
		}

		window.setTimeout(function() {
			var focusable = modal.querySelector('input, button, select, textarea, a[href]');

			if (focusable) {
				focusable.focus();
			}
		}, 40);
	}

	function closeModal(modal) {
		var trigger;

		if (!modal) {
			return;
		}

		trigger = modal._ctdTrigger;
		modal.classList.remove('is-open');
		modal.hidden = true;
		modal._ctdTrigger = null;

		if (trigger && typeof trigger.focus === 'function') {
			trigger.focus();
		}
	}

	function closeModals() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-modal].is-open'), closeModal);
	}

	function selectModalTab(modal, tabKey) {
		if (!modal || !tabKey) {
			return;
		}

		Array.prototype.forEach.call(modal.querySelectorAll('[data-ctd-tab-button]'), function(button) {
			var isActive = button.getAttribute('data-ctd-tab-button') === tabKey;

			button.classList.toggle('is-active', isActive);
			button.setAttribute('aria-selected', isActive ? 'true' : 'false');
		});

		Array.prototype.forEach.call(modal.querySelectorAll('[data-ctd-tab-panel]'), function(panel) {
			var isActive = panel.getAttribute('data-ctd-tab-panel') === tabKey;

			panel.classList.toggle('is-active', isActive);
			panel.hidden = !isActive;
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-library]'), initLibrary);
	});

	document.addEventListener('click', function(event) {
		var modalOpen = event.target.closest('[data-ctd-modal-open]');
		var modalClose = event.target.closest('[data-ctd-modal-close]');
		var tabButton = event.target.closest('[data-ctd-tab-button]');
		var modal;

		if (modalOpen) {
			event.preventDefault();
			openModal(getModal(modalOpen.getAttribute('data-ctd-modal-open')), modalOpen);
			return;
		}

		if (modalClose) {
			event.preventDefault();
			closeModal(modalClose.closest('[data-ctd-modal]'));
			return;
		}

		if (tabButton) {
			event.preventDefault();
			modal = tabButton.closest('[data-ctd-modal]');
			selectModalTab(modal, tabButton.getAttribute('data-ctd-tab-button'));
			return;
		}

		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-library]'), function(library) {
			if (!library.contains(event.target)) {
				closeFilters(library, null);
				return;
			}

			Array.prototype.forEach.call(library.querySelectorAll('[data-ctd-filter-control]'), function(control) {
				if (!control.contains(event.target)) {
					closeFilter(control);
				}
			});
		});
	});

	document.addEventListener('keydown', function(event) {
		if (event.key !== 'Escape') {
			return;
		}

		closeModals();

		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-library]'), function(library) {
			closeFilters(library, null);
		});
	});
})();
