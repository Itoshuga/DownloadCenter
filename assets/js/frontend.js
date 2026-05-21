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

	function updateRelatedOptions(library, category) {
		var relationships = window.ctdDocumentsLibrary && window.ctdDocumentsLibrary.relationships
			? window.ctdDocumentsLibrary.relationships
			: {};
		var relationship = category ? relationships[category] || {} : {};
		var rangeSelect = library.querySelector('[data-ctd-filter="range"]');
		var languageSelect = library.querySelector('[data-ctd-filter="language"]');

		updateSelectOptions(rangeSelect, relationship.ranges || []);
		updateSelectOptions(languageSelect, relationship.languages || []);
	}

	function updateSelectOptions(select, allowedSlugs) {
		if (!select) {
			return;
		}

		allowedSlugs = Array.isArray(allowedSlugs) ? allowedSlugs : [];

		Array.prototype.forEach.call(select.options, function(option) {
			var value = option.value || '';
			var isAvailable = !value || allowedSlugs.indexOf(value) !== -1;

			option.hidden = !isAvailable;
			option.disabled = !isAvailable;

			if (!isAvailable && select.value === value) {
				select.value = '';
			}
		});

		select.disabled = allowedSlugs.length === 0;
	}

	function refreshLibrary(library) {
		var category = getFilterValue(library, 'category');
		var range = getFilterValue(library, 'range');
		var language = getFilterValue(library, 'language');
		var cards = library.querySelectorAll('[data-ctd-document]');
		var empty = library.querySelector('[data-ctd-empty]');
		var visibleCount = 0;

		updateRelatedOptions(library, category);
		range = getFilterValue(library, 'range');
		language = getFilterValue(library, 'language');

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
