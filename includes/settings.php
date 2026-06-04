<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'ctd_register_settings_page' );
add_action( 'admin_init', 'ctd_register_frontend_settings' );
add_action( 'admin_enqueue_scripts', 'ctd_enqueue_settings_assets' );

function ctd_register_settings_page() {
	add_submenu_page(
		'edit.php?post_type=' . CTD_POST_TYPE,
		__( 'Paramètres', 'centre-telechargement' ),
		__( 'Paramètres', 'centre-telechargement' ),
		'manage_options',
		'ctd-settings',
		'ctd_render_settings_page'
	);
}

function ctd_register_frontend_settings() {
	register_setting(
		'ctd_frontend_settings',
		CTD_FRONTEND_SETTINGS_OPTION,
		array(
			'sanitize_callback' => 'ctd_sanitize_frontend_settings',
			'default'           => ctd_get_frontend_settings_defaults(),
		)
	);

	register_setting(
		'ctd_frontend_settings',
		CTD_REPORT_SETTINGS_OPTION,
		array(
			'sanitize_callback' => 'ctd_sanitize_report_settings',
			'default'           => ctd_get_report_settings_defaults(),
		)
	);
}

/**
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function ctd_enqueue_settings_assets( $hook_suffix ) {
	if ( 'download_document_page_ctd-settings' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'ctd-admin',
		CTD_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		CTD_VERSION
	);

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		"jQuery(function($) { $('.ctd-color-field').wpColorPicker(); });"
	);
	wp_add_inline_script( 'wp-color-picker', ctd_get_report_modal_script() );
	wp_add_inline_script( 'wp-color-picker', ctd_get_settings_language_tabs_script() );

	ctd_enqueue_admin_tour_assets( 'settings' );
}

/**
 * @return string
 */
function ctd_get_report_modal_script() {
	$config = wp_json_encode(
		array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'action'         => 'ctd_send_stats_report',
			'settingsOption' => CTD_REPORT_SETTINGS_OPTION,
			'sendingTitle'   => __( 'Envoi du rapport en cours', 'centre-telechargement' ),
			'sendingMessage' => __( 'Génération du fichier de statistiques et envoi de l’email. Gardez cette fenêtre ouverte quelques instants.', 'centre-telechargement' ),
			'successTitle'   => __( 'Rapport envoyé', 'centre-telechargement' ),
			'errorTitle'     => __( 'Envoi impossible', 'centre-telechargement' ),
			'elapsedLabel'   => __( 'Temps écoulé : %s s', 'centre-telechargement' ),
			'totalLabel'     => __( 'Temps total : %s s', 'centre-telechargement' ),
			'genericError'   => __( 'Une erreur est survenue pendant l’envoi du rapport.', 'centre-telechargement' ),
		)
	);

	$config = $config ? $config : '{}';

	return <<<JS
(function() {
	'use strict';

	var config = $config;
	var requestRunning = false;

	function formatSeconds(value) {
		var seconds = Number(value || 0);

		if (window.Intl && window.Intl.NumberFormat) {
			return new Intl.NumberFormat(undefined, {
				maximumFractionDigits: 1,
				minimumFractionDigits: 1
			}).format(seconds);
		}

		return seconds.toFixed(1);
	}

	function getNow() {
		return window.performance && window.performance.now ? window.performance.now() : Date.now();
	}

	function getElapsed(startTime) {
		return (getNow() - startTime) / 1000;
	}

	function setText(element, text) {
		if (element) {
			element.textContent = text;
		}
	}

	function setModalState(modal, state) {
		modal.classList.remove('is-loading', 'is-success', 'is-error');
		modal.classList.add('is-' + state);
	}

	function closeModal(modal, shouldReload) {
		modal.hidden = true;
		document.body.classList.remove('ctd-report-modal-open');

		if (shouldReload) {
			window.location.reload();
		}
	}

	function appendCurrentReportSettings(formData) {
		var fields = document.querySelectorAll('[name^="' + config.settingsOption + '["]');

		fields.forEach(function(field) {
			var type = field.type ? String(field.type).toLowerCase() : '';

			if (!field.name || field.disabled) {
				return;
			}

			if ((type === 'checkbox' || type === 'radio') && !field.checked) {
				return;
			}

			formData.append(field.name, field.value);
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		var sendButton = document.querySelector('[data-ctd-report-send]');
		var modal = document.querySelector('[data-ctd-report-modal]');

		if (!sendButton || !modal) {
			return;
		}

		var title = modal.querySelector('[data-ctd-report-modal-title]');
		var message = modal.querySelector('[data-ctd-report-modal-message]');
		var timer = modal.querySelector('[data-ctd-report-modal-time]');
		var closeButtons = modal.querySelectorAll('[data-ctd-report-modal-close]');
		var reloadOnClose = false;

		closeButtons.forEach(function(closeButton) {
			closeButton.addEventListener('click', function() {
				closeModal(modal, reloadOnClose);
			});
		});

		sendButton.addEventListener('click', function(event) {
			var startTime;
			var interval;
			var formData;

			if (!window.fetch || !window.URLSearchParams) {
				return;
			}

			event.preventDefault();

			if (requestRunning) {
				return;
			}

			requestRunning = true;
			reloadOnClose = false;
			startTime = getNow();

			sendButton.classList.add('is-busy');
			sendButton.setAttribute('aria-disabled', 'true');
			modal.hidden = false;
			document.body.classList.add('ctd-report-modal-open');
			setModalState(modal, 'loading');
			setText(title, config.sendingTitle);
			setText(message, config.sendingMessage);
			setText(timer, config.elapsedLabel.replace('%s', formatSeconds(0)));

			closeButtons.forEach(function(closeButton) {
				closeButton.hidden = true;
			});

			interval = window.setInterval(function() {
				setText(timer, config.elapsedLabel.replace('%s', formatSeconds(getElapsed(startTime))));
			}, 120);

			formData = new URLSearchParams();
			formData.append('action', config.action);
			formData.append('_ajax_nonce', sendButton.getAttribute('data-nonce') || '');
			appendCurrentReportSettings(formData);

			fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: formData.toString()
			})
				.then(function(response) {
					return response.json().then(function(payload) {
						return {
							ok: response.ok,
							payload: payload
						};
					});
				})
				.then(function(result) {
					var data = result.payload && result.payload.data ? result.payload.data : {};
					var serverElapsed = data.elapsed ? Number(data.elapsed) : 0;
					var elapsed = Math.max(serverElapsed, getElapsed(startTime));

					if (!result.ok || !result.payload || !result.payload.success) {
						throw new Error(data.message || config.genericError);
					}

					reloadOnClose = true;
					setModalState(modal, 'success');
					setText(title, config.successTitle);
					setText(message, data.message || config.successTitle);
					setText(timer, config.totalLabel.replace('%s', formatSeconds(elapsed)));
				})
				.catch(function(error) {
					reloadOnClose = false;
					setModalState(modal, 'error');
					setText(title, config.errorTitle);
					setText(message, error && error.message ? error.message : config.genericError);
					setText(timer, config.totalLabel.replace('%s', formatSeconds(getElapsed(startTime))));
				})
				.finally(function() {
					window.clearInterval(interval);
					requestRunning = false;
					sendButton.classList.remove('is-busy');
					sendButton.removeAttribute('aria-disabled');

					closeButtons.forEach(function(closeButton) {
						closeButton.hidden = false;
					});
				});
		});
	});
})();
JS;
}

/**
 * @return string
 */
function ctd_get_settings_language_tabs_script() {
	return <<<'JS'
(function() {
	'use strict';

	function getLanguageButtons(tabs) {
		return tabs.querySelectorAll('[data-ctd-settings-language-tab]');
	}

	function getLanguagePanels(tabs) {
		return tabs.querySelectorAll('[data-ctd-settings-language-panel]');
	}

	function selectLanguage(tabs, language) {
		getLanguageButtons(tabs).forEach(function(button) {
			var isActive = !button.hidden && button.getAttribute('data-ctd-settings-language-tab') === language;

			button.classList.toggle('is-active', isActive);
			button.setAttribute('aria-selected', isActive ? 'true' : 'false');
		});

		getLanguagePanels(tabs).forEach(function(panel) {
			panel.hidden = panel.getAttribute('data-ctd-settings-language-panel') !== language;
		});
	}

	function setLanguageEnabled(tabs, language, isEnabled) {
		var input = tabs.querySelector('[data-ctd-settings-enabled-language="' + language + '"]');
		var button = tabs.querySelector('[data-ctd-settings-language-tab="' + language + '"]');
		var addOption = tabs.querySelector('[data-ctd-settings-language-add="' + language + '"]');

		if (input) {
			input.disabled = !isEnabled;
		}

		if (button) {
			button.hidden = !isEnabled;
		}

		if (addOption) {
			addOption.hidden = isEnabled;
		}
	}

	function getFirstVisibleLanguage(tabs) {
		var button = Array.prototype.find.call(getLanguageButtons(tabs), function(item) {
			return !item.hidden;
		});

		return button ? button.getAttribute('data-ctd-settings-language-tab') : '';
	}

	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('[data-ctd-settings-language-tabs]').forEach(function(tabs) {
			tabs.addEventListener('click', function(event) {
				var tabButton = event.target.closest('[data-ctd-settings-language-tab]');
				var addToggle = event.target.closest('[data-ctd-settings-language-add-toggle]');
				var addButton = event.target.closest('[data-ctd-settings-language-add]');
				var removeButton = event.target.closest('[data-ctd-settings-language-remove]');
				var addPanel = tabs.querySelector('[data-ctd-settings-language-add-panel]');
				var addToggleButton = tabs.querySelector('[data-ctd-settings-language-add-toggle]');
				var language;

				if (tabButton) {
					event.preventDefault();
					selectLanguage(tabs, tabButton.getAttribute('data-ctd-settings-language-tab') || '');
					return;
				}

				if (addToggle && addPanel) {
					event.preventDefault();
					addPanel.hidden = !addPanel.hidden;
					addToggle.setAttribute('aria-expanded', addPanel.hidden ? 'false' : 'true');
					return;
				}

				if (addButton) {
					event.preventDefault();
					language = addButton.getAttribute('data-ctd-settings-language-add') || '';
					setLanguageEnabled(tabs, language, true);
					selectLanguage(tabs, language);

					if (addPanel) {
						addPanel.hidden = true;
					}

					if (addToggleButton) {
						addToggleButton.setAttribute('aria-expanded', 'false');
					}
					return;
				}

				if (removeButton) {
					event.preventDefault();
					language = removeButton.getAttribute('data-ctd-settings-language-remove') || '';
					setLanguageEnabled(tabs, language, false);
					selectLanguage(tabs, getFirstVisibleLanguage(tabs));
				}
			});
		});
	});
})();
JS;
}

/**
 * @param WP_Screen|null $screen Current screen.
 * @param string         $hook_suffix Current admin hook.
 * @return string
 */
function ctd_get_admin_tour_current_page_key( $screen = null, $hook_suffix = '' ) {
	if ( 'download_document_page_ctd-settings' === $hook_suffix ) {
		return 'settings';
	}

	if ( ! $screen instanceof WP_Screen ) {
		return '';
	}

	if ( isset( $screen->taxonomy ) ) {
		if ( CTD_RANGE_TAXONOMY === $screen->taxonomy ) {
			return 'range';
		}

		if ( CTD_TAXONOMY === $screen->taxonomy ) {
			return 'category';
		}
	}

	if (
		isset( $screen->post_type )
		&& CTD_POST_TYPE === $screen->post_type
		&& in_array( $screen->base, array( 'post', 'post-new' ), true )
	) {
		return 'document';
	}

	return '';
}

/**
 * @param string $current_page Current tour page key.
 * @return void
 */
function ctd_enqueue_admin_tour_assets( $current_page = '' ) {
	if ( ! current_user_can( 'manage_options' ) || wp_script_is( 'ctd-admin-tour', 'enqueued' ) ) {
		return;
	}

	ctd_enqueue_font_awesome();

	wp_enqueue_script(
		'ctd-admin-tour',
		CTD_PLUGIN_URL . 'assets/js/admin-tour.js',
		array(),
		CTD_VERSION,
		true
	);

	wp_localize_script( 'ctd-admin-tour', 'ctdAdminTour', ctd_get_admin_tour_config( $current_page ) );
}

/**
 * @param string $current_page Current tour page key.
 * @return array<string, mixed>
 */
function ctd_get_admin_tour_config( $current_page ) {
	return array(
		'currentPage' => $current_page,
		'storageKey'  => 'ctd_admin_tour_state',
		'pages'       => array(
			'settings' => admin_url( 'edit.php?post_type=' . CTD_POST_TYPE . '&page=ctd-settings' ),
			'range'    => admin_url( 'edit-tags.php?taxonomy=' . CTD_RANGE_TAXONOMY . '&post_type=' . CTD_POST_TYPE ),
			'category' => admin_url( 'edit-tags.php?taxonomy=' . CTD_TAXONOMY . '&post_type=' . CTD_POST_TYPE ),
			'document' => admin_url( 'post-new.php?post_type=' . CTD_POST_TYPE ),
		),
		'i18n'        => array(
			'next'     => __( 'Suivant', 'centre-telechargement' ),
			'previous' => __( 'Précédent', 'centre-telechargement' ),
			'finish'   => __( 'Terminer', 'centre-telechargement' ),
			'close'    => __( 'Fermer', 'centre-telechargement' ),
			'step'     => __( 'Étape', 'centre-telechargement' ),
			'of'       => __( 'sur', 'centre-telechargement' ),
			'required' => __( 'Action requise avant de continuer.', 'centre-telechargement' ),
			'ready'    => __( 'Étape validée, vous pouvez continuer.', 'centre-telechargement' ),
			'todo'     => __( 'À faire', 'centre-telechargement' ),
			'valid'    => __( 'Validé', 'centre-telechargement' ),
		),
		'steps'       => ctd_get_admin_tour_steps(),
	);
}

/**
 * @return array<int, array<string, mixed>>
 */
function ctd_get_admin_tour_steps() {
	return array(
		array(
			'page'      => 'settings',
			'selector'  => '.ctd-tour-launch-card',
			'title'     => __( 'Bienvenue dans le guide', 'centre-telechargement' ),
			'body'      => __( 'On va créer les filtres dans le bon ordre, puis ajouter un document PDF. La visite vous demandera de manipuler chaque zone avant de continuer.', 'centre-telechargement' ),
			'task'      => __( 'Lisez cette introduction, puis lancez la première action.', 'centre-telechargement' ),
			'nextLabel' => __( 'Commencer', 'centre-telechargement' ),
		),
		array(
			'page'         => 'range',
			'selector'     => '#addtag',
			'title'        => __( 'Créer une gamme', 'centre-telechargement' ),
			'body'         => __( 'Une gamme correspond à une famille de documents. Elle servira ensuite à affiner les documents visibles sur le front.', 'centre-telechargement' ),
			'task'         => __( 'Saisissez le nom de la gamme, puis validez le formulaire.', 'centre-telechargement' ),
			'action'       => 'submit',
			'form'         => '#addtag',
			'nextLabel'    => __( 'Valider la gamme', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#tag-name',
					'type'     => 'value',
					'message'  => __( 'Renseignez un nom de gamme avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'category',
			'selector'     => '#addtag',
			'title'        => __( 'Nommer la catégorie', 'centre-telechargement' ),
			'body'         => __( 'La catégorie est le premier filtre utilisé par le visiteur. Choisissez un nom simple et lisible.', 'centre-telechargement' ),
			'task'         => __( 'Renseignez le nom de la catégorie. Ne validez pas encore, on configure les liaisons juste après.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#tag-name',
					'type'     => 'value',
					'message'  => __( 'Renseignez le nom de la catégorie avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'category',
			'selector'     => '.term-ctd-category-protected-wrap',
			'title'        => __( 'Indiquer la protection', 'centre-telechargement' ),
			'body'         => __( 'Cette indication affiche un cadenas dans le filtre front. Elle est informative et ne remplace pas les droits du document.', 'centre-telechargement' ),
			'task'         => __( 'Cliquez dans cette zone pour choisir si la catégorie doit être signalée comme protégée ou non.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '.term-ctd-category-protected-wrap',
					'type'     => 'interacted',
					'message'  => __( 'Cliquez dans la zone Protection pour confirmer votre choix.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'category',
			'selector'     => '.term-ctd-category-relations-wrap',
			'title'        => __( 'Lier gammes et langues', 'centre-telechargement' ),
			'body'         => __( 'Ces liaisons pilotent les filtres dépendants. Quand cette catégorie sera choisie, seules les gammes et langues liées seront proposées.', 'centre-telechargement' ),
			'task'         => __( 'Cochez au moins une gamme et une langue, puis validez la catégorie.', 'centre-telechargement' ),
			'action'       => 'submit',
			'form'         => '#addtag',
			'nextLabel'    => __( 'Valider la catégorie', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#tag-name',
					'type'     => 'value',
					'message'  => __( 'Le nom de la catégorie est obligatoire avant validation.', 'centre-telechargement' ),
				),
				array(
					'selector' => 'input[name="ctd_category_range_ids[]"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Cochez au moins une gamme liée.', 'centre-telechargement' ),
				),
				array(
					'selector' => 'input[name="ctd_category_language_ids[]"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Cochez au moins une langue liée.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'document',
			'selector'     => '#titlediv',
			'title'        => __( 'Nommer le document', 'centre-telechargement' ),
			'body'         => __( 'Le titre WordPress devient le nom affiché sous la vignette PDF sur le front-office.', 'centre-telechargement' ),
			'task'         => __( 'Renseignez un titre clair pour le document.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#title',
					'type'     => 'value',
					'message'  => __( 'Renseignez le nom du document avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'document',
			'selector'     => '#ctd_document_file',
			'title'        => __( 'Choisir le PDF', 'centre-telechargement' ),
			'body'         => __( 'Le fichier vient de la médiathèque WordPress. Le plugin contrôle qu’il s’agit bien d’un PDF.', 'centre-telechargement' ),
			'task'         => __( 'Cliquez sur Choisir un PDF et sélectionnez le fichier à associer.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#ctd_pdf_file_id',
					'type'     => 'value',
					'message'  => __( 'Choisissez un PDF avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'document',
			'selector'     => '#' . CTD_TAXONOMY . 'div',
			'title'        => __( 'Associer une catégorie', 'centre-telechargement' ),
			'body'         => __( 'La catégorie détermine les gammes et langues disponibles ensuite.', 'centre-telechargement' ),
			'task'         => __( 'Cochez une catégorie pour faire apparaître les choix compatibles.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#' . CTD_TAXONOMY . 'div input[type="checkbox"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Cochez une catégorie avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'document',
			'selector'     => '#' . CTD_RANGE_TAXONOMY . 'div',
			'title'        => __( 'Associer une gamme', 'centre-telechargement' ),
			'body'         => __( 'Les gammes affichées ici dépendent de la catégorie cochée.', 'centre-telechargement' ),
			'task'         => __( 'Cochez la gamme correspondant au document.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#' . CTD_RANGE_TAXONOMY . 'div input[type="checkbox"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Cochez une gamme avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'document',
			'selector'     => '#' . CTD_LANGUAGE_TAXONOMY . 'div',
			'title'        => __( 'Associer une langue', 'centre-telechargement' ),
			'body'         => __( 'La langue permet au visiteur de filtrer les documents dans la bibliothèque.', 'centre-telechargement' ),
			'task'         => __( 'Cochez la langue du PDF.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#' . CTD_LANGUAGE_TAXONOMY . 'div input[type="checkbox"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Cochez une langue avant de continuer.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'         => 'document',
			'selector'     => '#ctd_document_access',
			'title'        => __( 'Définir les accès', 'centre-telechargement' ),
			'body'         => __( 'Un document public est visible librement. Un document protégé demande une connexion, soit pour tous les utilisateurs, soit pour une liste précise.', 'centre-telechargement' ),
			'task'         => __( 'Cliquez sur Public ou Protégé, puis ajustez le mode d’accès si besoin.', 'centre-telechargement' ),
			'requirements' => array(
				array(
					'selector' => '#ctd_document_access',
					'type'     => 'interacted',
					'message'  => __( 'Cliquez dans la zone Accès pour confirmer le choix de protection.', 'centre-telechargement' ),
				),
			),
		),
		array(
			'page'           => 'document',
			'selector'       => '#submitdiv',
			'title'          => __( 'Enregistrer le document', 'centre-telechargement' ),
			'body'           => __( 'Dernière vérification : le document doit avoir un titre, un PDF, une catégorie, une gamme, une langue et une règle d’accès.', 'centre-telechargement' ),
			'task'           => __( 'Publiez ou enregistrez le document pour terminer la procédure.', 'centre-telechargement' ),
			'action'         => 'submit',
			'form'           => '#post',
			'finishOnSubmit' => true,
			'nextLabel'      => __( 'Publier / Enregistrer', 'centre-telechargement' ),
			'requirements'   => array(
				array(
					'selector' => '#title',
					'type'     => 'value',
					'message'  => __( 'Le document doit avoir un nom avant publication.', 'centre-telechargement' ),
				),
				array(
					'selector' => '#ctd_pdf_file_id',
					'type'     => 'value',
					'message'  => __( 'Un PDF doit être choisi avant publication.', 'centre-telechargement' ),
				),
				array(
					'selector' => '#' . CTD_TAXONOMY . 'div input[type="checkbox"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Une catégorie doit être cochée avant publication.', 'centre-telechargement' ),
				),
				array(
					'selector' => '#' . CTD_RANGE_TAXONOMY . 'div input[type="checkbox"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Une gamme doit être cochée avant publication.', 'centre-telechargement' ),
				),
				array(
					'selector' => '#' . CTD_LANGUAGE_TAXONOMY . 'div input[type="checkbox"]:checked',
					'type'     => 'exists',
					'message'  => __( 'Une langue doit être cochée avant publication.', 'centre-telechargement' ),
				),
			),
		),
	);
}

/**
 * @return array<string, string>
 */
function ctd_get_frontend_settings_defaults() {
	$language_catalog = ctd_get_predefined_frontend_languages();
	$fallback_strings = $language_catalog['fr']['strings'];
	$defaults         = array(
		'primary_color'          => '#10233f',
		'accent_color'           => '#11a9cf',
		'accent_hover_color'     => '#0b88ad',
		'border_color'           => '#d7e4ec',
		'soft_border_color'      => '#e0ebf1',
		'surface_color'          => '#ffffff',
		'muted_surface_color'    => '#f8fbfd',
		'filter_hover_bg_color'  => '#edf8fb',
		'empty_text_color'       => '#5b6d7d',
		'filter_category_width'  => '1fr',
		'filter_range_width'     => '1fr',
		'filter_language_width'  => '1fr',
		'filter_gap'             => '14px',
		'document_min_width'     => '150px',
		'document_gap'           => '26px',
		'enabled_languages'      => ctd_get_default_frontend_language_codes(),
		'empty_message'          => $fallback_strings['empty_message'],
		'login_notice_text'      => $fallback_strings['login_notice_text'],
		'login_button_text'      => $fallback_strings['login_button_text'],
		'password_request_shortcode' => '',
	);

	foreach ( $language_catalog as $language => $language_data ) {
		$strings = isset( $language_data['strings'] ) && is_array( $language_data['strings'] )
			? $language_data['strings']
			: array();

		foreach ( ctd_get_frontend_localized_setting_keys() as $localized_key ) {
			$key = $localized_key . '_' . $language;

			if ( 'password_request_shortcode' === $localized_key ) {
				$defaults[ $key ] = '';
				continue;
			}

			$defaults[ $key ] = isset( $strings[ $localized_key ] )
				? (string) $strings[ $localized_key ]
				: (string) ( $fallback_strings[ $localized_key ] ?? '' );
		}
	}

	return $defaults;
}

/**
 * @return array<string, string>
 */
function ctd_get_frontend_settings() {
	$settings = get_option( CTD_FRONTEND_SETTINGS_OPTION, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return ctd_sanitize_frontend_settings( $settings );
}

/**
 * @param mixed $settings Settings candidate.
 * @return array<string, string>
 */
function ctd_sanitize_frontend_settings( $settings ) {
	$defaults = ctd_get_frontend_settings_defaults();

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$language_catalog  = ctd_get_predefined_frontend_languages();
	$enabled_languages = ctd_sanitize_frontend_language_codes( $settings['enabled_languages'] ?? $defaults['enabled_languages'] );

	$sanitized = array(
		'primary_color'          => ctd_sanitize_hex_setting( $settings['primary_color'] ?? '', $defaults['primary_color'] ),
		'accent_color'           => ctd_sanitize_hex_setting( $settings['accent_color'] ?? '', $defaults['accent_color'] ),
		'accent_hover_color'     => ctd_sanitize_hex_setting( $settings['accent_hover_color'] ?? '', $defaults['accent_hover_color'] ),
		'border_color'           => ctd_sanitize_hex_setting( $settings['border_color'] ?? '', $defaults['border_color'] ),
		'soft_border_color'      => ctd_sanitize_hex_setting( $settings['soft_border_color'] ?? '', $defaults['soft_border_color'] ),
		'surface_color'          => ctd_sanitize_hex_setting( $settings['surface_color'] ?? '', $defaults['surface_color'] ),
		'muted_surface_color'    => ctd_sanitize_hex_setting( $settings['muted_surface_color'] ?? '', $defaults['muted_surface_color'] ),
		'filter_hover_bg_color'  => ctd_sanitize_hex_setting( $settings['filter_hover_bg_color'] ?? '', $defaults['filter_hover_bg_color'] ),
		'empty_text_color'       => ctd_sanitize_hex_setting( $settings['empty_text_color'] ?? '', $defaults['empty_text_color'] ),
		'filter_category_width'  => ctd_sanitize_css_track_setting( $settings['filter_category_width'] ?? '', $defaults['filter_category_width'] ),
		'filter_range_width'     => ctd_sanitize_css_track_setting( $settings['filter_range_width'] ?? '', $defaults['filter_range_width'] ),
		'filter_language_width'  => ctd_sanitize_css_track_setting( $settings['filter_language_width'] ?? '', $defaults['filter_language_width'] ),
		'filter_gap'             => ctd_sanitize_css_length_setting( $settings['filter_gap'] ?? '', $defaults['filter_gap'] ),
		'document_min_width'     => ctd_sanitize_css_length_setting( $settings['document_min_width'] ?? '', $defaults['document_min_width'] ),
		'document_gap'           => ctd_sanitize_css_length_setting( $settings['document_gap'] ?? '', $defaults['document_gap'] ),
		'enabled_languages'      => $enabled_languages,
		'empty_message'          => ctd_sanitize_plain_text_setting( $settings['empty_message'] ?? '', $defaults['empty_message'] ),
		'login_notice_text'      => ctd_sanitize_plain_text_setting( $settings['login_notice_text'] ?? '', $defaults['login_notice_text'] ),
		'login_button_text'      => ctd_sanitize_plain_text_setting( $settings['login_button_text'] ?? '', $defaults['login_button_text'] ),
		'password_request_shortcode' => ctd_sanitize_shortcode_setting( $settings['password_request_shortcode'] ?? '' ),
	);

	foreach ( ctd_get_frontend_localized_setting_keys() as $localized_key ) {
		foreach ( array_keys( $language_catalog ) as $language ) {
			$key = $localized_key . '_' . $language;

			if ( 'password_request_shortcode' === $localized_key ) {
				$value = $settings[ $key ] ?? '';

				if ( 'fr' === $language && '' === (string) $value && isset( $settings[ $localized_key ] ) ) {
					$value = $settings[ $localized_key ];
				}

				$sanitized[ $key ] = ctd_sanitize_shortcode_setting( $value );
				continue;
			}

			$fallback_key = $key;

			if ( 'fr' === $language && empty( $settings[ $key ] ) && isset( $settings[ $localized_key ] ) ) {
				$sanitized[ $key ] = ctd_sanitize_plain_text_setting( $settings[ $localized_key ], $defaults[ $fallback_key ] );
				continue;
			}

			$sanitized[ $key ] = ctd_sanitize_plain_text_setting( $settings[ $key ] ?? '', $defaults[ $fallback_key ] );
		}
	}

	return $sanitized;
}

/**
 * @param mixed $languages Language codes candidate.
 * @return array<int, string>
 */
function ctd_sanitize_frontend_language_codes( $languages ) {
	$catalog = ctd_get_predefined_frontend_languages();

	if ( ! is_array( $languages ) ) {
		$languages = array( $languages );
	}

	$languages = array_map( 'ctd_normalize_language_code', $languages );
	$languages = array_values( array_unique( array_filter( $languages ) ) );
	$languages = array_values(
		array_filter(
			$languages,
			static function ( $language ) use ( $catalog ) {
				return isset( $catalog[ $language ] );
			}
		)
	);

	foreach ( ctd_get_default_frontend_language_codes() as $required_language ) {
		if ( ! in_array( $required_language, $languages, true ) ) {
			$languages[] = $required_language;
		}
	}

	usort(
		$languages,
		static function ( $a, $b ) use ( $catalog ) {
			$keys = array_keys( $catalog );

			return array_search( $a, $keys, true ) <=> array_search( $b, $keys, true );
		}
	);

	return $languages;
}

/**
 * @param array<string, mixed>|null $settings Optional frontend settings.
 * @return array<int, string>
 */
function ctd_get_enabled_frontend_language_codes( $settings = null ) {
	if ( ! is_array( $settings ) ) {
		$settings = ctd_get_frontend_settings();
	}

	return ctd_sanitize_frontend_language_codes( $settings['enabled_languages'] ?? ctd_get_default_frontend_language_codes() );
}

/**
 * @param string $language Language code.
 * @return string
 */
function ctd_get_frontend_language_label( $language ) {
	$language = ctd_normalize_frontend_language( $language );
	$catalog  = ctd_get_predefined_frontend_languages();

	return isset( $catalog[ $language ]['label'] ) ? (string) $catalog[ $language ]['label'] : strtoupper( $language );
}

/**
 * @return array<int, string>
 */
function ctd_get_frontend_localized_setting_keys() {
	return array(
		'login_notice_text',
		'login_button_text',
		'login_modal_title',
		'login_tab_label',
		'login_username_label',
		'login_password_label',
		'password_tab_label',
		'password_request_shortcode',
		'password_shortcode_empty_message',
		'filter_category_label',
		'filter_category_empty_label',
		'filter_range_label',
		'filter_range_empty_label',
		'filter_language_label',
		'filter_language_empty_label',
		'empty_message',
	);
}

/**
 * @param string $language Language candidate.
 * @return string
 */
function ctd_normalize_frontend_language( $language ) {
	$language = ctd_normalize_language_code( $language );
	$catalog  = ctd_get_predefined_frontend_languages();

	return isset( $catalog[ $language ] ) ? $language : 'fr';
}

/**
 * @return string
 */
function ctd_get_current_frontend_language() {
	$language = '';

	if ( function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
	}

	if ( ! $language ) {
		$language = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	}

	$language = ctd_normalize_frontend_language( $language );
	$settings = ctd_get_frontend_settings();
	$enabled  = ctd_get_enabled_frontend_language_codes( $settings );

	return in_array( $language, $enabled, true ) ? $language : 'fr';
}

/**
 * @param string                     $language Language code.
 * @param array<string, mixed>|null  $settings Optional settings.
 * @return array<string, string>
 */
function ctd_get_frontend_i18n_strings( $language = '', $settings = null ) {
	$language = ctd_normalize_frontend_language( $language ? $language : ctd_get_current_frontend_language() );
	$settings = is_array( $settings ) ? $settings : ctd_get_frontend_settings();
	$strings  = array();
	$enabled  = ctd_get_enabled_frontend_language_codes( $settings );

	if ( ! in_array( $language, $enabled, true ) ) {
		$language = 'fr';
	}

	foreach ( ctd_get_frontend_localized_setting_keys() as $localized_key ) {
		$key = $localized_key . '_' . $language;
		$fallback_key = $localized_key . '_fr';

		$strings[ $localized_key ] = isset( $settings[ $key ] ) && '' !== (string) $settings[ $key ]
			? (string) $settings[ $key ]
			: (string) ( $settings[ $fallback_key ] ?? '' );
	}

	return $strings;
}

/**
 * @param mixed  $value Candidate text.
 * @param string $fallback Fallback text.
 * @return string
 */
function ctd_sanitize_plain_text_setting( $value, $fallback ) {
	$value = sanitize_text_field( (string) $value );

	return '' !== $value ? $value : $fallback;
}

/**
 * @param mixed $value Candidate shortcode.
 * @return string
 */
function ctd_sanitize_shortcode_setting( $value ) {
	return sanitize_text_field( (string) $value );
}

/**
 * @param mixed  $value Candidate color.
 * @param string $fallback Fallback color.
 * @return string
 */
function ctd_sanitize_hex_setting( $value, $fallback ) {
	$value = sanitize_hex_color( (string) $value );

	return $value ? $value : $fallback;
}

/**
 * @param mixed  $value Candidate CSS grid track value.
 * @param string $fallback Fallback track value.
 * @return string
 */
function ctd_sanitize_css_track_setting( $value, $fallback ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );

	if ( '' === $value || 80 < strlen( $value ) ) {
		return $fallback;
	}

	if ( preg_match( '/(?:url|expression|var|;|:|{|})/i', $value ) ) {
		return $fallback;
	}

	if ( ! preg_match( '/^[0-9a-z\s.,()%_\-\/]+$/i', $value ) ) {
		return $fallback;
	}

	return $value;
}

/**
 * @param mixed  $value Candidate CSS length.
 * @param string $fallback Fallback length.
 * @return string
 */
function ctd_sanitize_css_length_setting( $value, $fallback ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );

	if ( preg_match( '/^(?:0|[0-9]+(?:\.[0-9]+)?(?:px|rem|em|%))$/', $value ) ) {
		return $value;
	}

	return $fallback;
}

/**
 * @return string
 */
function ctd_get_frontend_settings_css() {
	$settings = ctd_get_frontend_settings();

	return sprintf(
		".ctd-front-library {\n\t--ctd-color-primary: %1\$s;\n\t--ctd-color-accent: %2\$s;\n\t--ctd-color-accent-hover: %3\$s;\n\t--ctd-color-border: %4\$s;\n\t--ctd-color-border-soft: %5\$s;\n\t--ctd-color-surface: %6\$s;\n\t--ctd-color-muted-surface: %7\$s;\n\t--ctd-color-filter-hover: %8\$s;\n\t--ctd-color-empty-text: %9\$s;\n\t--ctd-color-accent-focus: %10\$s;\n\t--ctd-color-accent-shadow: %11\$s;\n\t--ctd-filter-category-width: %12\$s;\n\t--ctd-filter-range-width: %13\$s;\n\t--ctd-filter-language-width: %14\$s;\n\t--ctd-filter-gap: %15\$s;\n\t--ctd-document-min-width: %16\$s;\n\t--ctd-document-gap: %17\$s;\n}\n",
		$settings['primary_color'],
		$settings['accent_color'],
		$settings['accent_hover_color'],
		$settings['border_color'],
		$settings['soft_border_color'],
		$settings['surface_color'],
		$settings['muted_surface_color'],
		$settings['filter_hover_bg_color'],
		$settings['empty_text_color'],
		ctd_hex_to_rgba( $settings['accent_color'], 0.16 ),
		ctd_hex_to_rgba( $settings['accent_color'], 0.25 ),
		$settings['filter_category_width'],
		$settings['filter_range_width'],
		$settings['filter_language_width'],
		$settings['filter_gap'],
		$settings['document_min_width'],
		$settings['document_gap']
	);
}

/**
 * @param string $hex Hex color.
 * @param float  $alpha Alpha value.
 * @return string
 */
function ctd_hex_to_rgba( $hex, $alpha ) {
	$hex = sanitize_hex_color( $hex );

	if ( ! $hex ) {
		return 'rgba(17, 169, 207, ' . (float) $alpha . ')';
	}

	$hex = ltrim( $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) ) {
		return 'rgba(17, 169, 207, ' . (float) $alpha . ')';
	}

	return sprintf(
		'rgba(%1$d, %2$d, %3$d, %4$s)',
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
		rtrim( rtrim( number_format( max( 0, min( 1, $alpha ) ), 2, '.', '' ), '0' ), '.' )
	);
}

function ctd_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = ctd_get_frontend_settings();
	$frontend_language_catalog = ctd_get_predefined_frontend_languages();
	$enabled_frontend_languages = ctd_get_enabled_frontend_language_codes( $settings );
	$required_frontend_languages = ctd_get_default_frontend_language_codes();
	$active_frontend_language = $enabled_frontend_languages[0] ?? 'fr';
	$report_settings = ctd_get_report_settings();
	$manual_report_nonce = wp_create_nonce( 'ctd_send_stats_report' );
	$manual_report_url   = add_query_arg(
		array(
			'action'   => 'ctd_send_stats_report',
			'_wpnonce' => $manual_report_nonce,
		),
		admin_url( 'admin-post.php' )
	);
	$next_report_timestamp = ctd_get_next_scheduled_stats_report_timestamp();
	$last_report_run       = ctd_get_stats_report_last_run();
	?>
	<div class="wrap ctd-settings-page">
		<h1><?php esc_html_e( 'Paramètres', 'centre-telechargement' ); ?></h1>
		<?php ctd_render_stats_report_admin_notice(); ?>

		<section class="ctd-tour-launch-card">
			<div>
				<h2><?php esc_html_e( 'Guide d’utilisation', 'centre-telechargement' ); ?></h2>
				<p><?php esc_html_e( 'Lancez une procédure pas à pas pour apprendre à créer une gamme, une catégorie liée, puis un document PDF protégé ou public.', 'centre-telechargement' ); ?></p>
			</div>
			<button type="button" class="button button-primary" data-ctd-tour-start>
				<?php esc_html_e( 'Lancer la procédure pas à pas', 'centre-telechargement' ); ?>
			</button>
		</section>

		<form method="post" action="options.php">
			<?php settings_fields( 'ctd_frontend_settings' ); ?>

			<div class="ctd-settings-layout">
				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Rapports statistiques par email', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Envoyez un récapitulatif HTML des ouvertures et téléchargements de tous les documents.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-inline">
						<?php
						ctd_render_text_setting_field( 'sender_name', __( 'Nom de l’expéditeur', 'centre-telechargement' ), $report_settings['sender_name'], CTD_REPORT_SETTINGS_OPTION );
						ctd_render_text_setting_field( 'sender_email', __( 'Email expéditeur', 'centre-telechargement' ), $report_settings['sender_email'], CTD_REPORT_SETTINGS_OPTION, 'email' );
						?>
					</div>

					<div class="ctd-settings-fields ctd-settings-fields-inline">
						<?php
						ctd_render_text_setting_field( 'recipient_email', __( 'Email destinataire', 'centre-telechargement' ), $report_settings['recipient_email'], CTD_REPORT_SETTINGS_OPTION, 'email' );
						ctd_render_select_setting_field( 'frequency', __( 'Fréquence d’envoi', 'centre-telechargement' ), $report_settings['frequency'], ctd_get_report_frequencies(), CTD_REPORT_SETTINGS_OPTION );
						?>
					</div>

					<div class="ctd-settings-actions">
						<a
							class="button button-secondary ctd-report-send-button"
							href="<?php echo esc_url( $manual_report_url ); ?>"
							data-ctd-report-send
							data-nonce="<?php echo esc_attr( $manual_report_nonce ); ?>"
						>
							<?php esc_html_e( 'Envoyer le rapport maintenant', 'centre-telechargement' ); ?>
						</a>

						<div class="ctd-settings-status">
							<?php if ( 'manual' === $report_settings['frequency'] ) : ?>
								<span><?php esc_html_e( 'Aucun envoi automatique programmé.', 'centre-telechargement' ); ?></span>
							<?php elseif ( $next_report_timestamp ) : ?>
								<span>
									<?php
									printf(
										/* translators: %s: next report date. */
										esc_html__( 'Prochain envoi : %s.', 'centre-telechargement' ),
										esc_html( ctd_format_report_timestamp( $next_report_timestamp ) )
									);
									?>
								</span>
							<?php endif; ?>

							<?php if ( ! empty( $last_report_run['occurred_at'] ) ) : ?>
								<span>
									<?php
									printf(
										/* translators: %s: last report date. */
										esc_html__( 'Dernier envoi : %s.', 'centre-telechargement' ),
										esc_html( ctd_format_report_mysql_datetime( $last_report_run['occurred_at'] ) )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</section>

				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Connexion front', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Ces textes sont utilisés par le shortcode selon la langue courante, notamment avec Polylang.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-language-tabs" data-ctd-settings-language-tabs>
						<div class="ctd-settings-enabled-language-fields" data-ctd-settings-enabled-language-fields>
							<?php foreach ( array_keys( $frontend_language_catalog ) as $language ) : ?>
								<input
									type="hidden"
									name="<?php echo esc_attr( CTD_FRONTEND_SETTINGS_OPTION . '[enabled_languages][]' ); ?>"
									value="<?php echo esc_attr( $language ); ?>"
									data-ctd-settings-enabled-language="<?php echo esc_attr( $language ); ?>"
									<?php disabled( ! in_array( $language, $enabled_frontend_languages, true ) ); ?>
								/>
							<?php endforeach; ?>
						</div>

						<div class="ctd-settings-language-tablist" role="tablist" aria-label="<?php esc_attr_e( 'Langues des textes front', 'centre-telechargement' ); ?>">
							<?php foreach ( $frontend_language_catalog as $language => $language_data ) : ?>
								<?php
								$is_enabled = in_array( $language, $enabled_frontend_languages, true );
								$is_active  = $language === $active_frontend_language;
								?>
								<button
									type="button"
									class="ctd-settings-language-tab<?php echo $is_active ? ' is-active' : ''; ?>"
									role="tab"
									aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
									data-ctd-settings-language-tab="<?php echo esc_attr( $language ); ?>"
									<?php echo $is_enabled ? '' : 'hidden'; ?>
								>
									<?php echo esc_html( ctd_get_frontend_language_label( $language ) ); ?>
								</button>
							<?php endforeach; ?>

							<button type="button" class="ctd-settings-language-add-toggle" data-ctd-settings-language-add-toggle aria-expanded="false">
								<span aria-hidden="true">+</span>
								<?php esc_html_e( 'Ajouter une langue', 'centre-telechargement' ); ?>
							</button>
						</div>

						<div class="ctd-settings-language-add-panel" data-ctd-settings-language-add-panel hidden>
							<strong><?php esc_html_e( 'Langues prédéfinies disponibles', 'centre-telechargement' ); ?></strong>
							<div class="ctd-settings-language-add-options">
								<?php foreach ( $frontend_language_catalog as $language => $language_data ) : ?>
									<button
										type="button"
										class="ctd-settings-language-add-option"
										data-ctd-settings-language-add="<?php echo esc_attr( $language ); ?>"
										<?php echo in_array( $language, $enabled_frontend_languages, true ) ? 'hidden' : ''; ?>
									>
										<?php echo esc_html( ctd_get_frontend_language_label( $language ) ); ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>

						<?php foreach ( $frontend_language_catalog as $language => $language_data ) : ?>
							<?php
							ctd_render_frontend_language_settings_panel(
								$language,
								ctd_get_frontend_language_label( $language ),
								$settings,
								$language !== $active_frontend_language,
								! in_array( $language, $required_frontend_languages, true )
							);
							?>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Couleurs du front', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Ces couleurs pilotent le shortcode de bibliothèque de documents.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-colors">
						<?php
						ctd_render_color_setting_field( 'primary_color', __( 'Bleu marine / texte', 'centre-telechargement' ), $settings['primary_color'] );
						ctd_render_color_setting_field( 'accent_color', __( 'Accent cyan', 'centre-telechargement' ), $settings['accent_color'] );
						ctd_render_color_setting_field( 'accent_hover_color', __( 'Accent au survol', 'centre-telechargement' ), $settings['accent_hover_color'] );
						ctd_render_color_setting_field( 'border_color', __( 'Bordures principales', 'centre-telechargement' ), $settings['border_color'] );
						ctd_render_color_setting_field( 'soft_border_color', __( 'Bordures des cartes', 'centre-telechargement' ), $settings['soft_border_color'] );
						ctd_render_color_setting_field( 'surface_color', __( 'Fond des filtres/cartes', 'centre-telechargement' ), $settings['surface_color'] );
						ctd_render_color_setting_field( 'muted_surface_color', __( 'Fond clair', 'centre-telechargement' ), $settings['muted_surface_color'] );
						ctd_render_color_setting_field( 'filter_hover_bg_color', __( 'Fond actif des filtres', 'centre-telechargement' ), $settings['filter_hover_bg_color'] );
						ctd_render_color_setting_field( 'empty_text_color', __( 'Texte vide', 'centre-telechargement' ), $settings['empty_text_color'] );
						?>
					</div>
				</section>

				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Disposition des filtres', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Utilise des valeurs CSS comme 1fr, 0.7fr, 180px ou minmax(240px, 1.5fr).', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-tracks">
						<?php
						ctd_render_text_setting_field( 'filter_category_width', __( 'Largeur Catégorie', 'centre-telechargement' ), $settings['filter_category_width'] );
						ctd_render_text_setting_field( 'filter_range_width', __( 'Largeur Gamme', 'centre-telechargement' ), $settings['filter_range_width'] );
						ctd_render_text_setting_field( 'filter_language_width', __( 'Largeur Langue', 'centre-telechargement' ), $settings['filter_language_width'] );
						ctd_render_text_setting_field( 'filter_gap', __( 'Espace entre filtres', 'centre-telechargement' ), $settings['filter_gap'] );
						?>
					</div>
				</section>

				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Grille des documents', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Ces réglages pilotent la taille minimale des vignettes et leur espacement.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-inline">
						<?php
						ctd_render_text_setting_field( 'document_min_width', __( 'Largeur minimale d’une vignette', 'centre-telechargement' ), $settings['document_min_width'] );
						ctd_render_text_setting_field( 'document_gap', __( 'Espace entre les vignettes', 'centre-telechargement' ), $settings['document_gap'] );
						?>
					</div>
				</section>
			</div>

			<?php submit_button( __( 'Enregistrer les paramètres', 'centre-telechargement' ) ); ?>
		</form>
		<?php ctd_render_report_sending_modal(); ?>
	</div>
	<?php
}

/**
 * @param string                $language Language code.
 * @param string                $label Panel label.
 * @param array<string, string> $settings Frontend settings.
 * @param bool                  $hidden Whether the panel is initially hidden.
 * @param bool                  $removable Whether the language can be removed.
 * @return void
 */
function ctd_render_frontend_language_settings_panel( $language, $label, $settings, $hidden = false, $removable = false ) {
	$language = ctd_normalize_frontend_language( $language );
	?>
	<div
		class="ctd-settings-language-panel"
		data-ctd-settings-language-panel="<?php echo esc_attr( $language ); ?>"
		<?php echo $hidden ? 'hidden' : ''; ?>
	>
		<div class="ctd-settings-language-panel-heading">
			<h3><?php echo esc_html( $label ); ?></h3>

			<?php if ( $removable ) : ?>
				<button type="button" class="ctd-settings-language-remove" data-ctd-settings-language-remove="<?php echo esc_attr( $language ); ?>">
					<?php esc_html_e( 'Retirer cette langue', 'centre-telechargement' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<div class="ctd-settings-fields ctd-settings-fields-inline">
			<?php
			ctd_render_text_setting_field( 'login_notice_text_' . $language, __( 'Texte au-dessus du bouton', 'centre-telechargement' ), $settings[ 'login_notice_text_' . $language ] );
			ctd_render_text_setting_field( 'login_button_text_' . $language, __( 'Texte du bouton', 'centre-telechargement' ), $settings[ 'login_button_text_' . $language ] );
			ctd_render_text_setting_field( 'login_modal_title_' . $language, __( 'Titre de la popup', 'centre-telechargement' ), $settings[ 'login_modal_title_' . $language ] );
			ctd_render_text_setting_field( 'login_tab_label_' . $language, __( 'Onglet connexion', 'centre-telechargement' ), $settings[ 'login_tab_label_' . $language ] );
			ctd_render_text_setting_field( 'login_username_label_' . $language, __( 'Libellé identifiant / email', 'centre-telechargement' ), $settings[ 'login_username_label_' . $language ] );
			ctd_render_text_setting_field( 'login_password_label_' . $language, __( 'Libellé mot de passe', 'centre-telechargement' ), $settings[ 'login_password_label_' . $language ] );
			ctd_render_text_setting_field( 'password_tab_label_' . $language, __( 'Onglet demande de mot de passe', 'centre-telechargement' ), $settings[ 'password_tab_label_' . $language ] );
			?>
		</div>

		<div class="ctd-settings-fields ctd-settings-fields-single">
			<?php
			ctd_render_text_setting_field( 'password_request_shortcode_' . $language, __( 'Shortcode du formulaire de demande de mot de passe', 'centre-telechargement' ), $settings[ 'password_request_shortcode_' . $language ] );
			ctd_render_text_setting_field( 'password_shortcode_empty_message_' . $language, __( 'Message si aucun shortcode de formulaire n’est renseigné', 'centre-telechargement' ), $settings[ 'password_shortcode_empty_message_' . $language ] );
			ctd_render_text_setting_field( 'empty_message_' . $language, __( 'Message si aucun document ne correspond aux filtres', 'centre-telechargement' ), $settings[ 'empty_message_' . $language ] );
			?>
		</div>

		<div class="ctd-settings-subpanel">
			<h4><?php esc_html_e( 'Libellés des filtres', 'centre-telechargement' ); ?></h4>
			<div class="ctd-settings-fields ctd-settings-fields-inline">
				<?php
				ctd_render_text_setting_field( 'filter_category_label_' . $language, __( 'Libellé Catégorie', 'centre-telechargement' ), $settings[ 'filter_category_label_' . $language ] );
				ctd_render_text_setting_field( 'filter_category_empty_label_' . $language, __( 'Option Toutes les catégories', 'centre-telechargement' ), $settings[ 'filter_category_empty_label_' . $language ] );
				ctd_render_text_setting_field( 'filter_range_label_' . $language, __( 'Libellé Gamme', 'centre-telechargement' ), $settings[ 'filter_range_label_' . $language ] );
				ctd_render_text_setting_field( 'filter_range_empty_label_' . $language, __( 'Option Toutes les gammes', 'centre-telechargement' ), $settings[ 'filter_range_empty_label_' . $language ] );
				ctd_render_text_setting_field( 'filter_language_label_' . $language, __( 'Libellé Langue', 'centre-telechargement' ), $settings[ 'filter_language_label_' . $language ] );
				ctd_render_text_setting_field( 'filter_language_empty_label_' . $language, __( 'Option Toutes les langues', 'centre-telechargement' ), $settings[ 'filter_language_empty_label_' . $language ] );
				?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * @return void
 */
function ctd_render_report_sending_modal() {
	?>
	<div class="ctd-report-modal" data-ctd-report-modal hidden>
		<div class="ctd-report-modal-backdrop" aria-hidden="true"></div>
		<div class="ctd-report-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ctd-report-modal-title">
			<button type="button" class="ctd-report-modal-close" data-ctd-report-modal-close aria-label="<?php esc_attr_e( 'Fermer', 'centre-telechargement' ); ?>" hidden>
				<span class="ctd-report-modal-fa ctd-report-modal-fa-close" aria-hidden="true"></span>
			</button>

			<div class="ctd-report-modal-visual" aria-hidden="true">
				<span class="ctd-report-spinner"></span>
				<span class="ctd-report-modal-state-icon ctd-report-modal-state-success"></span>
				<span class="ctd-report-modal-state-icon ctd-report-modal-state-error"></span>
			</div>

			<h2 id="ctd-report-modal-title" data-ctd-report-modal-title><?php esc_html_e( 'Envoi du rapport en cours', 'centre-telechargement' ); ?></h2>
			<p data-ctd-report-modal-message><?php esc_html_e( 'Génération du fichier de statistiques et envoi de l’email. Gardez cette fenêtre ouverte quelques instants.', 'centre-telechargement' ); ?></p>
			<p class="ctd-report-modal-note"><?php esc_html_e( 'Le mail peut mettre plusieurs minutes avant d’apparaître dans la boîte de réception du destinataire.', 'centre-telechargement' ); ?></p>
			<p class="ctd-report-modal-time" data-ctd-report-modal-time><?php esc_html_e( 'Temps écoulé : 0,0 s', 'centre-telechargement' ); ?></p>

			<div class="ctd-report-modal-actions">
				<button type="button" class="button button-primary" data-ctd-report-modal-close hidden>
					<?php esc_html_e( 'Fermer', 'centre-telechargement' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * @param string $key Field key.
 * @param string $label Field label.
 * @param string $value Field value.
 * @return void
 */
function ctd_render_color_setting_field( $key, $label, $value ) {
	?>
	<label class="ctd-settings-field">
		<span><?php echo esc_html( $label ); ?></span>
		<input
			type="text"
			class="ctd-color-field"
			name="<?php echo esc_attr( CTD_FRONTEND_SETTINGS_OPTION . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			data-default-color="<?php echo esc_attr( ctd_get_frontend_settings_defaults()[ $key ] ); ?>"
		/>
	</label>
	<?php
}

/**
 * @param string $key Field key.
 * @param string $label Field label.
 * @param string $value Field value.
 * @param string $option_name Option name.
 * @param string $type Input type.
 * @return void
 */
function ctd_render_text_setting_field( $key, $label, $value, $option_name = CTD_FRONTEND_SETTINGS_OPTION, $type = 'text' ) {
	?>
	<label class="ctd-settings-field">
		<span><?php echo esc_html( $label ); ?></span>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			name="<?php echo esc_attr( $option_name . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
		/>
	</label>
	<?php
}

/**
 * @param string                $key Field key.
 * @param string                $label Field label.
 * @param string                $value Field value.
 * @param array<string, string> $choices Select choices.
 * @param string                $option_name Option name.
 * @return void
 */
function ctd_render_select_setting_field( $key, $label, $value, $choices, $option_name = CTD_FRONTEND_SETTINGS_OPTION ) {
	?>
	<label class="ctd-settings-field">
		<span><?php echo esc_html( $label ); ?></span>
		<select name="<?php echo esc_attr( $option_name . '[' . $key . ']' ); ?>">
			<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
				<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $value, $choice_value ); ?>>
					<?php echo esc_html( $choice_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}
