<?php
/**
 * Collector entrypoint homepage.
 *
 * @package veefun
 */

get_header();

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
 * Keep the editorial feed within the approved category and content boundary.
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

$current_posts = array();

if ( $allowed_category_ids ) {
	$current_cutoff = current_datetime()->modify( '-30 days' )->format( 'Y-m-d H:i:s' );
	$current_query  = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 50,
			'category__in'        => $allowed_category_ids,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'date_query'          => array(
				array(
					'after'     => $current_cutoff,
					'inclusive' => true,
					'column'    => 'post_date',
				),
			),
		)
	);

	$current_posts = array_values( array_filter( $current_query->posts, $is_eligible_post ) );
}

$sticky_ids     = array_map( 'intval', (array) get_option( 'sticky_posts', array() ) );
$featured_post  = null;
$is_spotlight   = false;
$supporting     = array();

foreach ( $current_posts as $candidate ) {
	if ( in_array( (int) $candidate->ID, $sticky_ids, true ) ) {
		$featured_post = $candidate;
		break;
	}
}

if ( ! $featured_post && $current_posts ) {
	$featured_post = $current_posts[0];
}

if ( $featured_post ) {
	foreach ( $current_posts as $candidate ) {
		if ( (int) $candidate->ID !== (int) $featured_post->ID ) {
			$supporting[] = $candidate;
		}
	}

	$supporting = array_slice( $supporting, 0, 3 );
} elseif ( $sticky_ids ) {
	$collector_guides = get_category_by_slug( 'collector-guides' );

	if ( $collector_guides ) {
		$spotlight_query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 50,
				'category__in'        => array( (int) $collector_guides->term_id ),
				'post__in'            => $sticky_ids,
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);

		$eligible_spotlights = array_values( array_filter( $spotlight_query->posts, $is_eligible_post ) );

		if ( $eligible_spotlights ) {
			$featured_post = $eligible_spotlights[0];
			$is_spotlight  = true;
		}
	}
}
?>

<main id="primary" class="vf-entrypoint vf-homepage">
	<section class="vf-hero" aria-labelledby="vf-home-title">
		<div class="vf-shell vf-hero__inner">
			<div class="vf-home-intro">
				<p class="vf-eyebrow">For the joy of collecting</p>
				<h1 id="vf-home-title">Find your next favorite.</h1>
				<p class="vf-hero__summary">Explore Pokémon, look up cards, and follow the stories that make collecting fun.</p>
			</div>
			<div class="vf-home-objects" role="group" aria-label="Explore Mew and Pokémon cards">
				<article class="vf-home-object">
					<p class="vf-kicker">Pokémon <span>#151</span></p>
					<?php // Keep the existing media optional; the subject remains useful without it. ?>
					<?php if ( defined( 'WP_CONTENT_DIR' ) && is_readable( WP_CONTENT_DIR . '/uploads/2026/03/151.png' ) ) : ?>
						<div class="vf-home-object__art">
							<img src="<?php echo esc_url( content_url( '/uploads/2026/03/151.png' ) ); ?>" alt="" width="475" height="475" decoding="async">
						</div>
					<?php endif; ?>
					<h2>Mew</h2>
					<p class="vf-home-object__detail">Meet Pokémon #151.</p>
					<a class="vf-text-link" href="<?php echo esc_url( home_url( '/pokedex/151-mew/' ) ); ?>">Explore Mew <span aria-hidden="true">›</span></a>
				</article>
				<article class="vf-home-object vf-home-object--cards">
					<p class="vf-kicker">From Pokémon to cards</p>
					<h2>Find your next card.</h2>
					<p class="vf-home-object__detail">Explore stored card details and cached prices when available.</p>
					<a class="vf-text-link" href="<?php echo esc_url( home_url( '/price-guide/' ) ); ?>">Browse cards <span aria-hidden="true">›</span></a>
				</article>
			</div>
		</div>
	</section>

	<nav class="vf-resource-nav vf-shell" aria-label="Collector resources">
		<a class="vf-resource-card" href="<?php echo esc_url( home_url( '/pokedex/' ) ); ?>">
			<span class="vf-resource-card__title">Pokédex</span>
			<span class="vf-resource-card__copy">Meet the Pokémon</span>
			<span class="vf-resource-card__arrow" aria-hidden="true">›</span>
		</a>
		<a class="vf-resource-card" href="<?php echo esc_url( home_url( '/price-guide/' ) ); ?>">
			<span class="vf-resource-card__title">Price Guide</span>
			<span class="vf-resource-card__copy">Know your cards</span>
			<span class="vf-resource-card__arrow" aria-hidden="true">›</span>
		</a>
		<a class="vf-resource-card" href="<?php echo esc_url( home_url( '/news/' ) ); ?>">
			<span class="vf-resource-card__title">News &amp; Guides</span>
			<span class="vf-resource-card__copy">Follow the hobby</span>
			<span class="vf-resource-card__arrow" aria-hidden="true">›</span>
		</a>
	</nav>

	<?php if ( $featured_post ) : ?>
		<section class="vf-stories vf-section vf-shell<?php echo $supporting ? '' : ' vf-stories--single'; ?>" aria-labelledby="vf-stories-title">
			<div class="vf-section-heading">
				<p class="vf-eyebrow"><?php echo $is_spotlight ? 'Evergreen guide' : 'From the collector desk'; ?></p>
				<h2 id="vf-stories-title"><?php echo $is_spotlight ? 'Collector Spotlight' : 'Latest stories'; ?></h2>
			</div>

			<div class="vf-story-layout">
				<article class="vf-featured-story">
					<?php if ( has_post_thumbnail( $featured_post ) ) : ?>
						<a class="vf-featured-story__image" href="<?php echo esc_url( get_permalink( $featured_post ) ); ?>" tabindex="-1" aria-labelledby="vf-featured-story-title">
							<?php echo get_the_post_thumbnail( $featured_post, 'large', array( 'loading' => 'eager' ) ); ?>
						</a>
					<?php endif; ?>
					<div class="vf-featured-story__body">
						<p class="vf-kicker"><?php echo esc_html( $get_category_label( $featured_post->ID ) ); ?></p>
						<h3 id="vf-featured-story-title"><a href="<?php echo esc_url( get_permalink( $featured_post ) ); ?>"><?php echo esc_html( get_the_title( $featured_post ) ); ?></a></h3>
						<p><?php echo esc_html( get_the_excerpt( $featured_post ) ); ?></p>
						<p class="vf-story-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $featured_post ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y', $featured_post ) ); ?></time></p>
						<a class="vf-button-link" href="<?php echo esc_url( get_permalink( $featured_post ) ); ?>">Read <?php echo esc_html( get_the_title( $featured_post ) ); ?></a>
					</div>
				</article>

				<?php if ( $supporting ) : ?>
					<div class="vf-supporting-stories" role="group" aria-label="More recent stories">
						<?php foreach ( $supporting as $supporting_post ) : ?>
							<article class="vf-supporting-story">
								<p class="vf-kicker"><?php echo esc_html( $get_category_label( $supporting_post->ID ) ); ?></p>
								<h3><a href="<?php echo esc_url( get_permalink( $supporting_post ) ); ?>"><?php echo esc_html( get_the_title( $supporting_post ) ); ?></a></h3>
								<p class="vf-story-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $supporting_post ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y', $supporting_post ) ); ?></time></p>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();
