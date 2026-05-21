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

	function refreshLibrary(library) {
		var category = getFilterValue(library, 'category');
		var range = getFilterValue(library, 'range');
		var language = getFilterValue(library, 'language');
		var cards = library.querySelectorAll('[data-ctd-document]');
		var empty = library.querySelector('[data-ctd-empty]');
		var visibleCount = 0;

		Array.prototype.forEach.call(cards, function(card) {
			var isVisible = hasTerm(card, 'category', category)
				&& hasTerm(card, 'range', range)
				&& hasTerm(card, 'language', language);

			card.hidden = !isVisible;

			if (isVisible) {
				visibleCount += 1;
			}
		});

		if (empty) {
			empty.classList.toggle('is-visible', visibleCount === 0);
		}
	}

	function getFilterValue(library, key) {
		var field = library.querySelector('[data-ctd-filter="' + key + '"]');

		return field ? field.value : '';
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

		closeFilter(control);
		refreshLibrary(library);
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

	function initLibrary(library) {
		Array.prototype.forEach.call(library.querySelectorAll('[data-ctd-filter-control]'), function(control) {
			initFilterControl(library, control);
		});

		refreshLibrary(library);
	}

	document.addEventListener('DOMContentLoaded', function() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-library]'), initLibrary);
	});

	document.addEventListener('click', function(event) {
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

		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-library]'), function(library) {
			closeFilters(library, null);
		});
	});
})();
