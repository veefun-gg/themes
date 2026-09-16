<?php
/**
 * News and guides page template.
 *
 * @package veefun
 */

get_header();

$news_page              = get_queried_object();
$allowed_category_slugs = array( 'news', 'collector-guides' );
$excluded_term_fragments = array(
	'uncategorized',
	'techwire',
	'visibility-test',
	'local-game-store',
	'lgs',
	'competitive',
	'scraped',
	'automated',
	'syndicated',
);

$allowed_category_ids = array();
foreach ( $allowed_category_slugs as $category_slug ) {
	$category = get_category_by_slug( $category_slug );

	if ( $category ) {
		$allowed_category_ids[] = (int) $category->term_id;
	}
}

/**
 * Keep the archive within the approved editorial boundary.
 *
 * @param WP_Post $post Post under consideration.
 * @return bool
 */
$is_eligible_post = static function ( $post ) use ( $allowed_category_slugs, $excluded_term_fragments ) {
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}

	$categories = get_the_category( $post->ID );
	$has_allowed_category = false;

	foreach ( $categories as $category ) {
		$term_text = strtolower( $category->slug . ' ' . $category->name );

		if ( in_array( $category->slug, $allowed_category_slugs, true ) ) {
			$has_allowed_category = true;
		}

		foreach ( $excluded_term_fragments as $fragment ) {
			if ( false !== strpos( $term_text, $fragment ) ) {
				return false;
			}
		}
	}

	if ( ! $has_allowed_category ) {
		return false;
	}

	$visibility_text = strtolower( $post->post_title . ' ' . $post->post_excerpt );

	return false === strpos( $visibility_text, 'visibility test' )
		&& false === strpos( $visibility_text, 'visibility-test' );
};

/**
 * Return the first approved category label for a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
$get_category_label = static function ( $post_id ) use ( $allowed_category_slugs ) {
	foreach ( get_the_category( $post_id ) as $category ) {
		if ( in_array( $category->slug, $allowed_category_slugs, true ) ) {
			return $category->name;
		}
	}

	return 'VeeFun';
};

$news_posts = array();

if ( $allowed_category_ids ) {
	$news_query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'category__in'        => $allowed_category_ids,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);

	$news_posts = array_values( array_filter( $news_query->posts, $is_eligible_post ) );
}
?>

<main id="primary" class="vf-entrypoint vf-news-page">
	<header class="vf-page-header">
		<div class="vf-shell">
			<p class="vf-eyebrow">VeeFun editorial</p>
			<h1><?php echo esc_html( get_the_title( $news_page ) ); ?></h1>
			<div class="vf-page-intro">
				<?php echo apply_filters( 'the_content', $news_page->post_content ); ?>
			</div>
		</div>
	</header>

	<section class="vf-news-feed vf-section vf-shell" aria-labelledby="vf-news-feed-title">
		<h2 id="vf-news-feed-title" class="vf-news-feed__title">Published news and guides</h2>
		<?php if ( $news_posts ) : ?>
			<div class="vf-news-list<?php echo 1 === count( $news_posts ) ? ' vf-news-list--single' : ''; ?>">
				<?php foreach ( $news_posts as $news_post ) : ?>
					<?php $has_image = has_post_thumbnail( $news_post ); ?>
					<article class="vf-news-card<?php echo $has_image ? ' has-image' : ''; ?>">
						<div class="vf-news-card__body">
							<p class="vf-kicker"><?php echo esc_html( $get_category_label( $news_post->ID ) ); ?></p>
							<h3 class="vf-news-card__title"><a href="<?php echo esc_url( get_permalink( $news_post ) ); ?>"><?php echo esc_html( get_the_title( $news_post ) ); ?></a></h3>
							<p><?php echo esc_html( get_the_excerpt( $news_post ) ); ?></p>
							<p class="vf-story-meta">Published <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $news_post ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y', $news_post ) ); ?></time></p>
							<a class="vf-news-card__action vf-c-relationship-link" href="<?php echo esc_url( get_permalink( $news_post ) ); ?>">Read <?php echo esc_html( get_the_title( $news_post ) ); ?></a>
						</div>
						<?php if ( $has_image ) : ?>
							<a class="vf-news-card__image" href="<?php echo esc_url( get_permalink( $news_post ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php echo get_the_post_thumbnail( $news_post, 'large' ); ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="vf-c-empty-state">No published news or guides are available here yet.</p>
		<?php endif; ?>
	</section>
</main>

<?php
get_footer();
