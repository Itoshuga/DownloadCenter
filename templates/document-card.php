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
?>
<article
	class="ctd-front-document"
	data-ctd-document
	data-category="<?php echo esc_attr( $category_slugs ); ?>"
	data-range="<?php echo esc_attr( $range_slugs ); ?>"
	data-language="<?php echo esc_attr( $language_slugs ); ?>"
>
	<div class="ctd-front-document-preview">
		<a
			class="ctd-front-document-open"
			href="<?php echo esc_url( $document['open_url'] ); ?>"
			target="_blank"
			rel="noopener noreferrer"
			aria-label="<?php echo esc_attr( sprintf( __( 'Ouvrir %s', 'centre-telechargement' ), $document['title'] ) ); ?>"
		>
			<?php if ( ! empty( $document['preview_url'] ) ) : ?>
				<img
					src="<?php echo esc_url( $document['preview_url'] ); ?>"
					alt="<?php echo esc_attr( sprintf( __( 'Aperçu de %s', 'centre-telechargement' ), $document['title'] ) ); ?>"
					loading="lazy"
				/>
			<?php else : ?>
				<span class="ctd-front-pdf-fallback" aria-hidden="true">
					<span>PDF</span>
				</span>
			<?php endif; ?>
		</a>

		<a
			class="ctd-front-download"
			href="<?php echo esc_url( $document['download_url'] ); ?>"
			aria-label="<?php echo esc_attr( sprintf( __( 'Télécharger %s', 'centre-telechargement' ), $document['title'] ) ); ?>"
		>
			<i class="fa-solid fa-download" aria-hidden="true"></i>
		</a>
	</div>

	<a
		class="ctd-front-document-title"
		href="<?php echo esc_url( $document['open_url'] ); ?>"
		target="_blank"
		rel="noopener noreferrer"
	>
		<?php echo esc_html( $document['title'] ); ?>
	</a>
</article>
