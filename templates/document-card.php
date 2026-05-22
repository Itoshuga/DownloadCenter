<?php
/**
 * Frontend document card.
 *
 * @var array<string, mixed> $document Document data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category_slugs = implode( ' ', array_map( 'sanitize_html_class', wp_list_pluck( $document['categories'], 'slug' ) ) );
$range_slugs    = implode( ' ', array_map( 'sanitize_html_class', wp_list_pluck( $document['ranges'], 'slug' ) ) );
$language_slugs = implode( ' ', array_map( 'sanitize_html_class', wp_list_pluck( $document['languages'], 'slug' ) ) );
$can_access     = ! empty( $document['can_access'] );
$preview_label  = sprintf( __( 'Aperçu de %s', 'centre-telechargement' ), $document['title'] );
?>
<article
	class="ctd-front-document<?php echo $can_access ? '' : ' is-locked'; ?>"
	data-ctd-document
	data-category="<?php echo esc_attr( $category_slugs ); ?>"
	data-range="<?php echo esc_attr( $range_slugs ); ?>"
	data-language="<?php echo esc_attr( $language_slugs ); ?>"
	data-access="<?php echo $can_access ? 'allowed' : 'locked'; ?>"
>
	<div class="ctd-front-document-preview">
		<?php if ( $can_access ) : ?>
			<a
				class="ctd-front-document-open"
				href="<?php echo esc_url( $document['open_url'] ); ?>"
				target="_blank"
				rel="noopener noreferrer"
				aria-label="<?php echo esc_attr( sprintf( __( 'Ouvrir %s', 'centre-telechargement' ), $document['title'] ) ); ?>"
			>
		<?php else : ?>
			<div
				class="ctd-front-document-open ctd-front-document-open-disabled"
				aria-label="<?php echo esc_attr( $preview_label ); ?>"
			>
		<?php endif; ?>
			<?php if ( ! empty( $document['preview_url'] ) ) : ?>
				<img
					src="<?php echo esc_url( $document['preview_url'] ); ?>"
					alt="<?php echo esc_attr( $preview_label ); ?>"
					loading="lazy"
				/>
			<?php else : ?>
				<span class="ctd-front-pdf-fallback" aria-hidden="true">
					<span>PDF</span>
				</span>
			<?php endif; ?>
		<?php if ( $can_access ) : ?>
			</a>

			<a
				class="ctd-front-download"
				href="<?php echo esc_url( $document['download_url'] ); ?>"
				aria-label="<?php echo esc_attr( sprintf( __( 'Télécharger %s', 'centre-telechargement' ), $document['title'] ) ); ?>"
			>
				<i class="fa-solid fa-download" aria-hidden="true"></i>
			</a>
		<?php else : ?>
			</div>

			<span class="ctd-front-lock-badge" aria-label="<?php esc_attr_e( 'Accès non autorisé', 'centre-telechargement' ); ?>">
				<i class="fa-solid fa-lock" aria-hidden="true"></i>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $can_access ) : ?>
		<a
			class="ctd-front-document-title"
			href="<?php echo esc_url( $document['open_url'] ); ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php echo esc_html( $document['title'] ); ?>
		</a>
	<?php else : ?>
		<span class="ctd-front-document-title ctd-front-document-title-disabled">
			<?php echo esc_html( $document['title'] ); ?>
		</span>
	<?php endif; ?>
</article>
