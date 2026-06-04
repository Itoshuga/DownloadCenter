<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ctd_register_taxonomy' );
add_action( 'admin_init', 'ctd_seed_default_languages' );
add_action( CTD_TAXONOMY . '_add_form_fields', 'ctd_render_category_relationship_add_fields' );
add_action( CTD_TAXONOMY . '_edit_form_fields', 'ctd_render_category_relationship_edit_fields' );
add_action( 'created_' . CTD_TAXONOMY, 'ctd_save_category_relationship_term_meta' );
add_action( 'edited_' . CTD_TAXONOMY, 'ctd_save_category_relationship_term_meta' );
add_action( CTD_LANGUAGE_TAXONOMY . '_add_form_fields', 'ctd_render_language_add_fields' );
add_action( CTD_LANGUAGE_TAXONOMY . '_edit_form_fields', 'ctd_render_language_edit_fields' );
add_action( 'created_' . CTD_LANGUAGE_TAXONOMY, 'ctd_save_language_term_meta' );
add_action( 'edited_' . CTD_LANGUAGE_TAXONOMY, 'ctd_save_language_term_meta' );
add_action( CTD_TAXONOMY . '_add_form_fields', 'ctd_render_term_translation_add_field', 30 );
add_action( CTD_TAXONOMY . '_edit_form_fields', 'ctd_render_term_translation_edit_field', 30 );
add_action( CTD_RANGE_TAXONOMY . '_add_form_fields', 'ctd_render_term_translation_add_field', 30 );
add_action( CTD_RANGE_TAXONOMY . '_edit_form_fields', 'ctd_render_term_translation_edit_field', 30 );
add_action( CTD_LANGUAGE_TAXONOMY . '_add_form_fields', 'ctd_render_term_translation_add_field', 30 );
add_action( CTD_LANGUAGE_TAXONOMY . '_edit_form_fields', 'ctd_render_term_translation_edit_field', 30 );
add_action( 'created_' . CTD_TAXONOMY, 'ctd_save_term_translation_meta' );
add_action( 'edited_' . CTD_TAXONOMY, 'ctd_save_term_translation_meta' );
add_action( 'created_' . CTD_RANGE_TAXONOMY, 'ctd_save_term_translation_meta' );
add_action( 'edited_' . CTD_RANGE_TAXONOMY, 'ctd_save_term_translation_meta' );
add_action( 'created_' . CTD_LANGUAGE_TAXONOMY, 'ctd_save_term_translation_meta' );
add_action( 'edited_' . CTD_LANGUAGE_TAXONOMY, 'ctd_save_term_translation_meta' );
add_action( 'save_post_' . CTD_POST_TYPE, 'ctd_sync_document_related_terms_with_categories', 30, 2 );
add_filter( 'manage_edit-' . CTD_TAXONOMY . '_columns', 'ctd_filter_category_term_columns' );
add_filter( 'manage_' . CTD_TAXONOMY . '_custom_column', 'ctd_render_category_term_column', 10, 3 );
add_filter( 'manage_edit-' . CTD_LANGUAGE_TAXONOMY . '_columns', 'ctd_filter_language_term_columns' );
add_filter( 'manage_' . CTD_LANGUAGE_TAXONOMY . '_custom_column', 'ctd_render_language_term_column', 10, 3 );

function ctd_register_taxonomy() {
	ctd_register_category_taxonomy();
	ctd_register_range_taxonomy();
	ctd_register_language_taxonomy();
	ctd_register_category_relationship_meta();
	ctd_register_term_translation_meta();
}

function ctd_register_category_relationship_meta() {
	$args = array(
		'type'              => 'array',
		'single'            => true,
		'sanitize_callback' => 'ctd_sanitize_term_id_list',
		'auth_callback'     => static function () {
			return current_user_can( 'manage_options' );
		},
		'show_in_rest'      => false,
	);

	register_term_meta( CTD_TAXONOMY, CTD_CATEGORY_RANGE_META, $args );
	register_term_meta( CTD_TAXONOMY, CTD_CATEGORY_LANGUAGE_META, $args );
	register_term_meta(
		CTD_TAXONOMY,
		CTD_CATEGORY_PROTECTED_HINT_META,
		array(
			'type'              => 'boolean',
			'single'            => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => static function () {
				return current_user_can( 'manage_options' );
			},
			'show_in_rest'      => false,
		)
	);
}

function ctd_register_term_translation_meta() {
	foreach ( array( CTD_TAXONOMY, CTD_RANGE_TAXONOMY, CTD_LANGUAGE_TAXONOMY ) as $taxonomy ) {
		foreach ( array_keys( ctd_get_predefined_frontend_languages() ) as $language ) {
			if ( 'fr' === $language ) {
				continue;
			}

			register_term_meta(
				$taxonomy,
				ctd_get_term_translation_meta_key( $language ),
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'manage_options' );
					},
					'show_in_rest'      => false,
				)
			);
		}
	}
}

function ctd_register_category_taxonomy() {
	$labels = array(
		'name'              => __( 'Catégories de documents', 'centre-telechargement' ),
		'singular_name'     => __( 'Catégorie de documents', 'centre-telechargement' ),
		'search_items'      => __( 'Rechercher une catégorie', 'centre-telechargement' ),
		'all_items'         => __( 'Toutes les catégories', 'centre-telechargement' ),
		'parent_item'       => __( 'Catégorie parente', 'centre-telechargement' ),
		'parent_item_colon' => __( 'Catégorie parente :', 'centre-telechargement' ),
		'edit_item'         => __( 'Modifier la catégorie', 'centre-telechargement' ),
		'update_item'       => __( 'Mettre à jour la catégorie', 'centre-telechargement' ),
		'add_new_item'      => __( 'Ajouter une catégorie', 'centre-telechargement' ),
		'new_item_name'     => __( 'Nom de la nouvelle catégorie', 'centre-telechargement' ),
		'menu_name'         => __( 'Catégories', 'centre-telechargement' ),
	);

	register_taxonomy(
		CTD_TAXONOMY,
		array( CTD_POST_TYPE ),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => true,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}

function ctd_register_range_taxonomy() {
	$labels = array(
		'name'              => __( 'Gammes', 'centre-telechargement' ),
		'singular_name'     => __( 'Gamme', 'centre-telechargement' ),
		'search_items'      => __( 'Rechercher une gamme', 'centre-telechargement' ),
		'all_items'         => __( 'Toutes les gammes', 'centre-telechargement' ),
		'parent_item'       => __( 'Gamme parente', 'centre-telechargement' ),
		'parent_item_colon' => __( 'Gamme parente :', 'centre-telechargement' ),
		'edit_item'         => __( 'Modifier la gamme', 'centre-telechargement' ),
		'update_item'       => __( 'Mettre à jour la gamme', 'centre-telechargement' ),
		'add_new_item'      => __( 'Ajouter une gamme', 'centre-telechargement' ),
		'new_item_name'     => __( 'Nom de la nouvelle gamme', 'centre-telechargement' ),
		'menu_name'         => __( 'Gammes', 'centre-telechargement' ),
	);

	register_taxonomy(
		CTD_RANGE_TAXONOMY,
		array( CTD_POST_TYPE ),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => true,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}

function ctd_register_language_taxonomy() {
	$labels = array(
		'name'                       => __( 'Langues', 'centre-telechargement' ),
		'singular_name'              => __( 'Langue', 'centre-telechargement' ),
		'search_items'               => __( 'Rechercher une langue', 'centre-telechargement' ),
		'all_items'                  => __( 'Toutes les langues', 'centre-telechargement' ),
		'parent_item'                => __( 'Langue parente', 'centre-telechargement' ),
		'parent_item_colon'          => __( 'Langue parente :', 'centre-telechargement' ),
		'edit_item'                  => __( 'Modifier la langue', 'centre-telechargement' ),
		'update_item'                => __( 'Mettre à jour la langue', 'centre-telechargement' ),
		'add_new_item'               => __( 'Ajouter une langue', 'centre-telechargement' ),
		'new_item_name'              => __( 'Nom de la nouvelle langue', 'centre-telechargement' ),
		'separate_items_with_commas' => __( 'Séparer les langues par des virgules', 'centre-telechargement' ),
		'add_or_remove_items'        => __( 'Ajouter ou retirer des langues', 'centre-telechargement' ),
		'choose_from_most_used'      => __( 'Choisir parmi les langues les plus utilisées', 'centre-telechargement' ),
		'menu_name'                  => __( 'Langues', 'centre-telechargement' ),
	);

	register_taxonomy(
		CTD_LANGUAGE_TAXONOMY,
		array( CTD_POST_TYPE ),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => true,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}

/**
 * @param bool $force Force seeding even if it already ran.
 * @return void
 */
function ctd_seed_default_languages( $force = false ) {
	if ( ! taxonomy_exists( CTD_LANGUAGE_TAXONOMY ) ) {
		return;
	}

	foreach ( ctd_get_default_languages() as $language ) {
		$term    = get_term_by( 'slug', $language['slug'], CTD_LANGUAGE_TAXONOMY );
		$term_id = 0;

		if ( $term instanceof WP_Term ) {
			$term_id = $term->term_id;
		} else {
			$created = wp_insert_term(
				$language['name'],
				CTD_LANGUAGE_TAXONOMY,
				array(
					'slug' => $language['slug'],
				)
			);

			if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
				$term_id = absint( $created['term_id'] );
			}
		}

		if (
			$term_id
			&& ! get_term_meta( $term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META, true )
			&& ( $force || ! get_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, true ) )
		) {
			update_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, $language['flag'] );
		}

		if (
			$term_id
			&& ! get_term_meta( $term_id, CTD_TERM_TRANSLATION_EN_META, true )
			&& ! empty( $language['translation_en'] )
		) {
			update_term_meta( $term_id, CTD_TERM_TRANSLATION_EN_META, sanitize_text_field( $language['translation_en'] ) );
		}

		if ( $term_id && ! empty( $language['translations'] ) && is_array( $language['translations'] ) ) {
			foreach ( $language['translations'] as $translation_language => $translation ) {
				$translation_language = ctd_normalize_language_code( $translation_language );
				$meta_key             = ctd_get_term_translation_meta_key( $translation_language );

				if ( 'fr' === $translation_language || get_term_meta( $term_id, $meta_key, true ) ) {
					continue;
				}

				update_term_meta( $term_id, $meta_key, sanitize_text_field( $translation ) );
			}
		}
	}

	update_option( 'ctd_default_languages_seeded', CTD_VERSION );
}

function ctd_get_term_translation_languages() {
	$languages = function_exists( 'ctd_get_enabled_frontend_language_codes' )
		? ctd_get_enabled_frontend_language_codes()
		: array( 'en' );

	return array_values(
		array_filter(
			$languages,
			static function ( $language ) {
				return 'fr' !== $language;
			}
		)
	);
}

/**
 * @param array<int, string> $languages Translation languages.
 * @param int                $term_id Current term ID.
 * @return void
 */
function ctd_render_term_translation_controls( $languages, $term_id = 0 ) {
	$term_id = absint( $term_id );
	$count   = count( $languages );
	?>
	<div class="ctd-term-translation-card">
		<div class="ctd-term-translation-card-header">
			<div>
				<strong><?php esc_html_e( 'Traductions', 'centre-telechargement' ); ?></strong>
				<span><?php esc_html_e( 'Noms utilisés dans les filtres du shortcode selon la langue active.', 'centre-telechargement' ); ?></span>
			</div>
			<span class="ctd-term-translation-count">
				<?php
				printf(
					/* translators: %d: enabled translation language count. */
					esc_html( _n( '%d langue', '%d langues', $count, 'centre-telechargement' ) ),
					absint( $count )
				);
				?>
			</span>
		</div>

		<div class="ctd-term-translation-fields">
			<?php foreach ( $languages as $language ) : ?>
				<?php
				$translation = $term_id ? get_term_meta( $term_id, ctd_get_term_translation_meta_key( $language ), true ) : '';
				$field_id    = 'ctd_term_translation_' . $language;
				?>
				<label class="ctd-term-translation-field" for="<?php echo esc_attr( $field_id ); ?>">
					<span class="ctd-term-translation-field-heading">
						<span class="ctd-term-translation-language"><?php echo esc_html( ctd_get_frontend_language_label( $language ) ); ?></span>
						<span class="ctd-term-translation-code"><?php echo esc_html( strtoupper( $language ) ); ?></span>
					</span>
					<input
						type="text"
						id="<?php echo esc_attr( $field_id ); ?>"
						name="<?php echo esc_attr( 'ctd_term_translations[' . $language . ']' ); ?>"
						value="<?php echo esc_attr( is_string( $translation ) ? $translation : '' ); ?>"
						placeholder="<?php esc_attr_e( 'Nom traduit à afficher', 'centre-telechargement' ); ?>"
					/>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

function ctd_render_term_translation_add_field() {
	$languages = ctd_get_term_translation_languages();

	if ( empty( $languages ) ) {
		return;
	}
	?>
	<div class="form-field term-ctd-translation-wrap">
		<?php wp_nonce_field( 'ctd_save_term_translation', 'ctd_term_translation_nonce' ); ?>
		<?php ctd_render_term_translation_controls( $languages ); ?>
	</div>
	<?php
}

/**
 * @param WP_Term $term Current term.
 * @return void
 */
function ctd_render_term_translation_edit_field( $term ) {
	$languages = ctd_get_term_translation_languages();

	if ( empty( $languages ) ) {
		return;
	}
	?>
	<tr class="form-field term-ctd-translation-wrap">
		<th scope="row">
			<?php esc_html_e( 'Traductions', 'centre-telechargement' ); ?>
		</th>
		<td>
			<?php wp_nonce_field( 'ctd_save_term_translation', 'ctd_term_translation_nonce' ); ?>
			<?php ctd_render_term_translation_controls( $languages, $term->term_id ); ?>
		</td>
	</tr>
	<?php
}

/**
 * @param int $term_id Term ID.
 * @return void
 */
function ctd_save_term_translation_meta( $term_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['ctd_term_translation_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ctd_term_translation_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ctd_save_term_translation' ) ) {
		return;
	}

	$translations = isset( $_POST['ctd_term_translations'] ) && is_array( $_POST['ctd_term_translations'] )
		? wp_unslash( $_POST['ctd_term_translations'] )
		: array();

	if ( isset( $_POST['ctd_term_translation_en'] ) && ! isset( $translations['en'] ) ) {
		$translations['en'] = wp_unslash( $_POST['ctd_term_translation_en'] );
	}

	foreach ( ctd_get_term_translation_languages() as $language ) {
		$translation = isset( $translations[ $language ] )
			? sanitize_text_field( is_scalar( $translations[ $language ] ) ? (string) $translations[ $language ] : '' )
			: '';

		if ( '' === $translation ) {
			delete_term_meta( $term_id, ctd_get_term_translation_meta_key( $language ) );
			continue;
		}

		update_term_meta( $term_id, ctd_get_term_translation_meta_key( $language ), $translation );
	}
}

function ctd_render_category_relationship_add_fields() {
	?>
	<div class="form-field term-ctd-category-protected-wrap">
		<label class="ctd-taxonomy-field-label"><?php esc_html_e( 'Protection', 'centre-telechargement' ); ?></label>
		<?php wp_nonce_field( 'ctd_save_category_relationships', 'ctd_category_relationships_nonce' ); ?>
		<label class="ctd-category-protected-option">
			<input type="checkbox" name="ctd_category_protected_hint" value="1" />
			<span class="ctd-category-protected-content">
				<strong><?php esc_html_e( 'Catégorie protégée', 'centre-telechargement' ); ?></strong>
				<span><?php esc_html_e( 'Indiquer que cette catégorie contient des documents protégés.', 'centre-telechargement' ); ?></span>
			</span>
		</label>
		<p>
			<?php esc_html_e( 'Information visuelle uniquement, sans impact sur les accès.', 'centre-telechargement' ); ?>
		</p>
	</div>

	<div class="form-field term-ctd-category-relations-wrap">
		<label class="ctd-taxonomy-field-label"><?php esc_html_e( 'Filtres liés', 'centre-telechargement' ); ?></label>
		<?php ctd_render_category_relationship_controls(); ?>
		<p>
			<?php esc_html_e( 'Choisissez les gammes et langues disponibles lorsque cette catégorie est sélectionnée.', 'centre-telechargement' ); ?>
		</p>
	</div>
	<?php
}

/**
 * @param WP_Term $term Current term.
 * @return void
 */
function ctd_render_category_relationship_edit_fields( $term ) {
	?>
	<tr class="form-field term-ctd-category-protected-wrap">
		<th scope="row">
			<?php esc_html_e( 'Protection', 'centre-telechargement' ); ?>
		</th>
		<td>
			<?php wp_nonce_field( 'ctd_save_category_relationships', 'ctd_category_relationships_nonce' ); ?>
			<label class="ctd-category-protected-option">
				<input
					type="checkbox"
					name="ctd_category_protected_hint"
					value="1"
					<?php checked( ctd_category_has_protected_hint( $term ) ); ?>
				/>
				<span class="ctd-category-protected-content">
					<strong><?php esc_html_e( 'Catégorie protégée', 'centre-telechargement' ); ?></strong>
					<span><?php esc_html_e( 'Indiquer que cette catégorie contient des documents protégés.', 'centre-telechargement' ); ?></span>
				</span>
			</label>
			<p class="description">
				<?php esc_html_e( 'Information visuelle uniquement, sans impact sur les accès.', 'centre-telechargement' ); ?>
			</p>
		</td>
	</tr>

	<tr class="form-field term-ctd-category-relations-wrap">
		<th scope="row">
			<?php esc_html_e( 'Filtres liés', 'centre-telechargement' ); ?>
		</th>
		<td>
			<?php
			ctd_render_category_relationship_controls(
				ctd_get_category_linked_range_ids( $term ),
				ctd_get_category_linked_language_ids( $term )
			);
			?>
			<p class="description">
				<?php esc_html_e( 'Si rien n’est coché, toutes les gammes et langues restent disponibles pour cette catégorie.', 'centre-telechargement' ); ?>
			</p>
		</td>
	</tr>
	<?php
}

/**
 * @param array<int> $selected_range_ids Selected range IDs.
 * @param array<int> $selected_language_ids Selected language IDs.
 * @return void
 */
function ctd_render_category_relationship_controls( $selected_range_ids = array(), $selected_language_ids = array() ) {
	$ranges    = get_terms(
		array(
			'taxonomy'   => CTD_RANGE_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	$languages = get_terms(
		array(
			'taxonomy'   => CTD_LANGUAGE_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	$ranges    = is_wp_error( $ranges ) ? array() : $ranges;
	$languages = is_wp_error( $languages ) ? array() : $languages;
	?>
	<div class="ctd-term-relationship-grid">
		<div class="ctd-term-relationship-panel ctd-term-relationship-panel-ranges">
			<strong><?php esc_html_e( 'Gammes liées', 'centre-telechargement' ); ?></strong>

			<?php if ( empty( $ranges ) ) : ?>
				<p class="ctd-empty-value"><?php esc_html_e( 'Aucune gamme disponible.', 'centre-telechargement' ); ?></p>
			<?php else : ?>
				<div class="ctd-term-relationship-list ctd-term-relationship-list-ranges">
					<?php foreach ( $ranges as $range ) : ?>
						<label class="ctd-term-relationship-option">
							<input
								type="checkbox"
								name="ctd_category_range_ids[]"
								value="<?php echo esc_attr( $range->term_id ); ?>"
								<?php checked( in_array( $range->term_id, $selected_range_ids, true ) ); ?>
							/>
							<span class="ctd-term-relationship-label"><?php echo esc_html( $range->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="ctd-term-relationship-panel ctd-term-relationship-panel-languages">
			<strong><?php esc_html_e( 'Langues liées', 'centre-telechargement' ); ?></strong>

			<?php if ( empty( $languages ) ) : ?>
				<p class="ctd-empty-value"><?php esc_html_e( 'Aucune langue disponible.', 'centre-telechargement' ); ?></p>
			<?php else : ?>
				<div class="ctd-term-relationship-list ctd-term-relationship-list-languages">
					<?php foreach ( $languages as $language ) : ?>
						<label class="ctd-term-relationship-option">
							<input
								type="checkbox"
								name="ctd_category_language_ids[]"
								value="<?php echo esc_attr( $language->term_id ); ?>"
								<?php checked( in_array( $language->term_id, $selected_language_ids, true ) ); ?>
							/>
							<?php echo ctd_get_language_badge_html( $language ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * @param int $term_id Term ID.
 * @return void
 */
function ctd_save_category_relationship_term_meta( $term_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['ctd_category_relationships_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ctd_category_relationships_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ctd_save_category_relationships' ) ) {
		return;
	}

	$range_ids = isset( $_POST['ctd_category_range_ids'] )
		? ctd_sanitize_term_id_list( wp_unslash( $_POST['ctd_category_range_ids'] ) )
		: array();
	$language_ids = isset( $_POST['ctd_category_language_ids'] )
		? ctd_sanitize_term_id_list( wp_unslash( $_POST['ctd_category_language_ids'] ) )
		: array();
	$protected_hint = isset( $_POST['ctd_category_protected_hint'] ) ? 1 : 0;

	ctd_save_category_relationship_ids( $term_id, CTD_CATEGORY_RANGE_META, $range_ids, CTD_RANGE_TAXONOMY );
	ctd_save_category_relationship_ids( $term_id, CTD_CATEGORY_LANGUAGE_META, $language_ids, CTD_LANGUAGE_TAXONOMY );

	if ( $protected_hint ) {
		update_term_meta( $term_id, CTD_CATEGORY_PROTECTED_HINT_META, 1 );
	} else {
		delete_term_meta( $term_id, CTD_CATEGORY_PROTECTED_HINT_META );
	}
}

/**
 * @param int        $term_id Target category ID.
 * @param string     $meta_key Relationship meta key.
 * @param array<int> $term_ids Related term IDs.
 * @param string     $taxonomy Related taxonomy.
 * @return void
 */
function ctd_save_category_relationship_ids( $term_id, $meta_key, $term_ids, $taxonomy ) {
	$term_ids = ctd_sanitize_term_id_list( $term_ids );
	$term_ids = array_filter(
		$term_ids,
		static function ( $related_term_id ) use ( $taxonomy ) {
			$term = get_term( $related_term_id, $taxonomy );

			return $term instanceof WP_Term && ! is_wp_error( $term );
		}
	);
	$term_ids = array_values( $term_ids );

	if ( empty( $term_ids ) ) {
		delete_term_meta( $term_id, $meta_key );
		return;
	}

	update_term_meta( $term_id, $meta_key, $term_ids );
}

/**
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function ctd_filter_category_term_columns( $columns ) {
	$new_columns = array();

	foreach ( $columns as $column_key => $column_label ) {
		if ( 'description' === $column_key ) {
			continue;
		}

		$new_columns[ $column_key ] = $column_label;

		if ( 'name' === $column_key ) {
			$new_columns['ctd_protected_hint'] = __( 'Protection', 'centre-telechargement' );
			$new_columns['ctd_ranges']    = __( 'Gammes liées', 'centre-telechargement' );
			$new_columns['ctd_languages'] = __( 'Langues liées', 'centre-telechargement' );
		}
	}

	return $new_columns;
}

/**
 * @param string $content Existing column content.
 * @param string $column_name Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function ctd_render_category_term_column( $content, $column_name, $term_id ) {
	if ( 'ctd_protected_hint' === $column_name ) {
		return ctd_get_category_protected_hint_column_html( $term_id );
	}

	if ( 'ctd_ranges' === $column_name ) {
		return ctd_get_linked_terms_column_html( $term_id, CTD_RANGE_TAXONOMY );
	}

	if ( 'ctd_languages' === $column_name ) {
		return ctd_get_linked_terms_column_html( $term_id, CTD_LANGUAGE_TAXONOMY );
	}

	return $content;
}

/**
 * @param int $term_id Category term ID.
 * @return string
 */
function ctd_get_category_protected_hint_column_html( $term_id ) {
	if ( ctd_category_has_protected_hint( $term_id ) ) {
		return '<span class="ctd-category-protection-badge ctd-category-protection-yes">' . esc_html__( 'Protégée', 'centre-telechargement' ) . '</span>';
	}

	return '<span class="ctd-category-protection-badge ctd-category-protection-no">' . esc_html__( 'Non Protégé', 'centre-telechargement' ) . '</span>';
}

/**
 * @param int    $term_id Category term ID.
 * @param string $taxonomy Linked taxonomy.
 * @return string
 */
function ctd_get_linked_terms_column_html( $term_id, $taxonomy ) {
	$terms = ctd_get_category_linked_terms( $term_id, $taxonomy );

	if ( empty( $terms ) ) {
		return '<span class="ctd-empty-value">' . esc_html__( 'Toutes', 'centre-telechargement' ) . '</span>';
	}

	$items = array();
	$total = count( $terms );
	$terms = array_slice( $terms, 0, 2 );

	foreach ( $terms as $term ) {
		if ( CTD_LANGUAGE_TAXONOMY === $taxonomy ) {
			$items[] = ctd_get_language_badge_html( $term );
			continue;
		}

		$items[] = sprintf(
			'<span class="ctd-category-chip">%s</span>',
			esc_html( $term->name )
		);
	}

	if ( $total > 2 ) {
		$items[] = sprintf(
			'<span class="ctd-more-chip">+%d</span>',
			$total - 2
		);
	}

	$list_class = CTD_LANGUAGE_TAXONOMY === $taxonomy ? 'ctd-language-list' : 'ctd-category-list';

	return sprintf(
		'<div class="%1$s">%2$s</div>',
		esc_attr( $list_class ),
		implode( '', $items )
	);
}

/**
 * @param int     $post_id Post ID.
 * @param WP_Post $post Current post.
 * @return void
 */
function ctd_sync_document_related_terms_with_categories( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$category_ids = wp_get_object_terms(
		$post_id,
		CTD_TAXONOMY,
		array(
			'fields' => 'ids',
		)
	);

	if ( is_wp_error( $category_ids ) ) {
		return;
	}

	ctd_restrict_document_terms_for_categories( $post_id, CTD_RANGE_TAXONOMY, $category_ids );
	ctd_restrict_document_terms_for_categories( $post_id, CTD_LANGUAGE_TAXONOMY, $category_ids );
}

/**
 * @param int        $post_id Post ID.
 * @param string     $taxonomy Related taxonomy.
 * @param array<int> $category_ids Selected category IDs.
 * @return void
 */
function ctd_restrict_document_terms_for_categories( $post_id, $taxonomy, $category_ids ) {
	$availability = ctd_get_allowed_related_term_ids_for_categories( $category_ids, $taxonomy );

	if ( empty( $availability['restricted'] ) ) {
		return;
	}

	$current_ids = wp_get_object_terms(
		$post_id,
		$taxonomy,
		array(
			'fields' => 'ids',
		)
	);

	if ( is_wp_error( $current_ids ) || empty( $current_ids ) ) {
		return;
	}

	$current_ids = ctd_sanitize_term_id_list( $current_ids );
	$allowed_ids = ctd_sanitize_term_id_list( $availability['ids'] );
	$valid_ids   = array_values( array_intersect( $current_ids, $allowed_ids ) );

	if ( count( $valid_ids ) !== count( $current_ids ) ) {
		wp_set_object_terms( $post_id, $valid_ids, $taxonomy );
	}
}

function ctd_render_language_add_fields() {
	?>
	<div class="form-field term-ctd-language-flag-wrap">
		<label for="ctd_language_flag_attachment_id"><?php esc_html_e( 'Drapeau', 'centre-telechargement' ); ?></label>
		<?php wp_nonce_field( 'ctd_save_language_meta', 'ctd_language_meta_nonce' ); ?>
		<?php ctd_render_language_flag_media_controls(); ?>
		<p><?php esc_html_e( 'Choisissez une image depuis la médiathèque WordPress.', 'centre-telechargement' ); ?></p>
	</div>
	<?php
}

/**
 * @param WP_Term $term Current term.
 * @return void
 */
function ctd_render_language_edit_fields( $term ) {
	$attachment_id = ctd_get_language_flag_attachment_id( $term );
	?>
	<tr class="form-field term-ctd-language-flag-wrap">
		<th scope="row">
			<label for="ctd_language_flag_attachment_id"><?php esc_html_e( 'Drapeau', 'centre-telechargement' ); ?></label>
		</th>
		<td>
			<?php wp_nonce_field( 'ctd_save_language_meta', 'ctd_language_meta_nonce' ); ?>
			<?php ctd_render_language_flag_media_controls( $attachment_id ); ?>
			<p class="description"><?php esc_html_e( 'Choisissez une image depuis la médiathèque WordPress.', 'centre-telechargement' ); ?></p>
			<div class="ctd-language-preview">
				<?php echo ctd_get_language_badge_html( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * @param int $attachment_id Selected attachment ID.
 * @return void
 */
function ctd_render_language_flag_media_controls( $attachment_id = 0 ) {
	$attachment_id = absint( $attachment_id );
	$image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
	$file_name     = $attachment_id ? get_the_title( $attachment_id ) : '';

	if ( $attachment_id && ! $image_url ) {
		$image_url = wp_get_attachment_url( $attachment_id );
	}

	if ( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( $file_path ) {
			$file_name = wp_basename( $file_path );
		}
	}
	?>
	<input
		type="hidden"
		id="ctd_language_flag_attachment_id"
		name="ctd_language_flag_attachment_id"
		value="<?php echo esc_attr( $attachment_id ); ?>"
	/>
	<div class="ctd-language-media-control">
		<div class="ctd-language-media-preview">
			<img
				id="ctd-language-flag-media-preview"
				class="<?php echo $image_url ? '' : 'hidden'; ?>"
				src="<?php echo esc_url( $image_url ); ?>"
				alt=""
			/>
			<span
				id="ctd-language-flag-media-placeholder"
				class="dashicons dashicons-format-image<?php echo $image_url ? ' hidden' : ''; ?>"
				aria-hidden="true"
			></span>
			<span id="ctd-language-flag-media-filename">
				<?php echo $file_name ? esc_html( $file_name ) : esc_html__( 'Aucune image sélectionnée', 'centre-telechargement' ); ?>
			</span>
		</div>
		<div class="ctd-language-media-actions">
			<button type="button" class="button ctd-select-language-flag-media">
				<?php esc_html_e( 'Choisir depuis la médiathèque', 'centre-telechargement' ); ?>
			</button>
			<button
				type="button"
				id="ctd-remove-language-flag-media"
				class="button ctd-remove-language-flag-media<?php echo $attachment_id ? '' : ' hidden'; ?>"
			>
				<?php esc_html_e( 'Retirer', 'centre-telechargement' ); ?>
			</button>
		</div>
	</div>
	<?php
}

/**
 * @param int $term_id Term ID.
 * @return void
 */
function ctd_save_language_term_meta( $term_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['ctd_language_meta_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ctd_language_meta_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ctd_save_language_meta' ) ) {
		return;
	}

	$attachment_id = isset( $_POST['ctd_language_flag_attachment_id'] )
		? absint( wp_unslash( $_POST['ctd_language_flag_attachment_id'] ) )
		: 0;

	if ( $attachment_id && ctd_attachment_is_language_flag_image( $attachment_id ) ) {
		update_term_meta( $term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META, $attachment_id );
		return;
	}

	delete_term_meta( $term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META );
}

/**
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function ctd_filter_language_term_columns( $columns ) {
	$new_columns = array();

	foreach ( $columns as $column_key => $column_label ) {
		$new_columns[ $column_key ] = $column_label;

		if ( 'name' === $column_key ) {
			$new_columns['ctd_flag'] = __( 'Drapeau', 'centre-telechargement' );
		}
	}

	return $new_columns;
}

/**
 * @param string $content Existing column content.
 * @param string $column_name Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function ctd_render_language_term_column( $content, $column_name, $term_id ) {
	if ( 'ctd_flag' !== $column_name ) {
		return $content;
	}

	return ctd_get_language_badge_html( $term_id );
}
