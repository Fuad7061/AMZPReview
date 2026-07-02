<?php
/**
 * Per-category archive: review_category taxonomy.
 *
 * Renders the category landing experience — auto-generated intro, filtered
 * grid of reviews, and CollectionPage / ItemList JSON-LD for SEO.
 *
 * @package ProductReviews
 */

get_header();

$term = get_queried_object();
$term_id      = isset( $term->term_id ) ? (int) $term->term_id : 0;
$term_url     = $term_id ? get_term_link( $term ) : home_url( '/' );
$term_url     = ( is_wp_error( $term_url ) || ! is_string( $term_url ) || '' === $term_url ) ? home_url( '/' ) : $term_url;
$amazon_slug  = $term_id ? (string) get_term_meta( $term_id, PR_CAT_META_SLUG,         true ) : '';
$browse_node  = $term_id ? (string) get_term_meta( $term_id, PR_CAT_META_BROWSE_NODE,  true ) : '';
$landing_id   = $term_id ? (int)    get_term_meta( $term_id, PR_CAT_META_LANDING_PAGE, true ) : 0;
$last_synced  = $term_id ? (string) get_term_meta( $term_id, PR_CAT_META_LAST_SYNCED,  true ) : '';
?>
<div class="yf-container">
	<header class="yf-archive__head">
		<h1><?php single_term_title(); ?></h1>
		<?php
		$desc = term_description();
		if ( $desc ) {
			echo '<div class="yf-archive__desc">' . wp_kses_post( $desc ) . '</div>';
		}
		if ( $last_synced ) {
			printf(
				'<p class="yf-archive__meta"><small>%s</small></p>',
				esc_html( sprintf(
					/* translators: %s = ISO timestamp */
					__( 'Auto-synced from Amazon · last refresh %s UTC', 'product-reviews' ),
					$last_synced
				) )
			);
		}
		?>
	</header>

	<div class="yf-archive__layout">
		<?php if ( function_exists( 'pr_facets_render' ) ) { pr_facets_render(); } ?>
		<div class="yf-archive__main">
	<div class="yf-grid yf-grid--3">
	<?php
	$items = array();
	if ( have_posts() ) :
		$position = 0;
		while ( have_posts() ) : the_post();
			$position++;
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'url'      => get_permalink(),
				'name'     => get_the_title(),
			);
			?>
			<a class="yf-card" href="<?php the_permalink(); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="yf-card__img"><?php the_post_thumbnail( 'pr-card' ); ?></div>
				<?php endif; ?>
				<div class="yf-card__body">
					<h3 class="yf-card__title"><?php the_title(); ?></h3>
					<p class="yf-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
					<span class="yf-card__meta"><?php
						echo esc_html( sprintf(
							/* translators: %s = relative date */
							__( 'Updated %s', 'product-reviews' ),
							function_exists( 'yadfood_last_updated' ) ? yadfood_last_updated() : get_the_modified_date()
						) );
					?></span>
				</div>
			</a>
		<?php endwhile;
	else : ?>
		<p><?php esc_html_e( 'New reviews are being prepared in this category. Check back soon.', 'product-reviews' ); ?></p>
	<?php endif; ?>
	</div>

	<div class="yf-pagination"><?php the_posts_pagination(); ?></div>

	<?php if ( $landing_id ) : ?>
		<aside class="yf-archive__landing">
			<p><a href="<?php echo esc_url( get_permalink( $landing_id ) ); ?>"><?php esc_html_e( 'Read the full buying guide for this category →', 'product-reviews' ); ?></a></p>
		</aside>
	<?php endif; ?>
		</div>
	</div>
</div>

<?php
$jsonld = array(
	'@context'    => 'https://schema.org',
	'@type'       => 'CollectionPage',
	'name'        => single_term_title( '', false ),
	'url'         => $term_url,
	'description' => wp_strip_all_tags( term_description() ),
);
if ( $browse_node || $amazon_slug ) {
	$jsonld['isPartOf'] = array(
		'@type' => 'WebSite',
		'name'  => function_exists( 'pr_brand' ) ? pr_brand() : get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	$jsonld['identifier'] = array_filter( array(
		'amazonBrowseNode' => $browse_node,
		'amazonSlug'       => $amazon_slug,
	) );
}
if ( ! empty( $items ) ) {
	$jsonld['mainEntity'] = array(
		'@type'           => 'ItemList',
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
}
echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";

get_footer();
