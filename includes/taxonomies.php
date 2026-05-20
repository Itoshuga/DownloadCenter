<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ctd_register_taxonomy' );

function ctd_register_taxonomy() {
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
			'labels'            => $labels,
			'public'            => false,
			'publicly_queryable' => false,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_quick_edit' => true,
			'show_in_rest'      => false,
			'query_var'         => false,
			'rewrite'           => false,
			'capabilities'      => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}
