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

	function initLibrary(library) {
		Array.prototype.forEach.call(library.querySelectorAll('[data-ctd-filter]'), function(field) {
			field.addEventListener('change', function() {
				refreshLibrary(library);
			});
		});

		refreshLibrary(library);
	}

	document.addEventListener('DOMContentLoaded', function() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ctd-library]'), initLibrary);
	});
})();
