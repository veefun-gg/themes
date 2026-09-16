<?php

declare(strict_types=1);

// Offline output fixtures only: no WordPress bootstrap or external resources.
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class WP_Post {
	public int $ID;
	public string $post_type = 'post';
	public string $post_status = 'publish';
	public string $post_title;
	public string $post_excerpt = 'A collector guide.';
	public string $post_content = '';
	public string $post_date = '2026-09-14T12:30:00-05:00';
	public string $permalink;
	public bool $thumbnail = false;
	public array $categories;

	public function __construct( int $id, array $overrides = array() ) {
		$this->ID         = $id;
		$this->post_title = 'Story ' . $id;
		$this->permalink  = 'https://fixture.invalid/story-' . $id . '/';
		$this->categories = array( news_term( 'news', 'News', 7 ) );
		foreach ( $overrides as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_Query {
	public array $posts;
	public function __construct( array $args ) {
		$GLOBALS['news_fixture']['queries'][] = $args;
		// Supply query results directly; the test does not emulate WordPress filtering.
		$this->posts = $GLOBALS['news_fixture']['posts'];
	}
}

function news_term( string $slug, string $name, int $id = 90 ): object {
	return (object) array( 'slug' => $slug, 'name' => $name, 'term_id' => $id );
}
function get_header(): void {}
function get_footer(): void {}
function get_queried_object(): WP_Post { return $GLOBALS['news_fixture']['page']; }
function get_category_by_slug( $slug ) { return $GLOBALS['news_fixture']['terms'][ $slug ] ?? false; }
function get_the_category( $id ): array { return $GLOBALS['news_fixture']['categories'][ $id ] ?? array(); }
function get_the_title( $post ): string { return $post->post_title; }
function get_the_excerpt( $post ): string { return $post->post_excerpt; }
function get_permalink( $post ): string { return $post->permalink; }
function has_post_thumbnail( $post ): bool { return $post->thumbnail; }
function get_the_date( $format, $post ): string { return ( new DateTimeImmutable( $post->post_date ) )->format( $format ); }
function esc_html( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $value ): string { return esc_html( $value ); }
function esc_url( $value ): string { return esc_html( $value ); }
function apply_filters( $hook, $value ) {
	$GLOBALS['news_fixture']['filters'][] = array( $hook, $value );
	return $value;
}
function get_the_post_thumbnail( $post, $size ): string {
	$GLOBALS['news_fixture']['thumbnails'][] = array( $post->ID, $size );
	return '<img src="https://fixture.invalid/image-' . $post->ID . '.jpg" alt="Fixture artwork">';
}

function news_render( array $posts, bool $has_categories = true ): string {
	$GLOBALS['news_fixture'] = array(
		'posts' => $posts,
		'page' => new WP_Post( 1524, array(
			'post_title' => 'News & guides',
			'post_content' => '<p>Owner-authored <em>editorial introduction</em>.</p>',
		) ),
		'terms' => $has_categories ? array(
			'news' => news_term( 'news', 'News', 7 ),
			'collector-guides' => news_term( 'collector-guides', 'Collector Guides', 12 ),
		) : array(),
		'categories' => array(), 'queries' => array(), 'filters' => array(), 'thumbnails' => array(),
	);
	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post ) {
			$GLOBALS['news_fixture']['categories'][ $post->ID ] = $post->categories;
		}
	}
	ob_start();
	try {
		include dirname( __DIR__ ) . '/page-news.php';
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
}

$assertions = 0;
function news_assert( bool $condition, string $message ): void {
	$GLOBALS['assertions']++;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}
function news_contains( string $needle, string $html, string $message ): void {
	news_assert( false !== strpos( $html, $needle ), $message );
}
function news_articles( string $html ): array {
	preg_match_all( '/<article\b[^>]*class="[^"]*\bvf-news-card\b[^"]*"[^>]*>.*?<\/article>/s', $html, $matches );
	return $matches[0];
}

$expected_query = array(
	'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1,
	'category__in' => array( 7, 12 ), 'ignore_sticky_posts' => true,
	'orderby' => 'date', 'order' => 'DESC',
);
$empty_message = 'No published news or guides are available here yet.';

$html = news_render( array() );
news_assert( array( $expected_query ) === $GLOBALS['news_fixture']['queries'], 'approved query arguments remain exact' );
news_assert( 0 === count( news_articles( $html ) ), 'zero results render no article' );
news_contains( $empty_message, $html, 'zero results explain the empty feed' );
news_contains( '<h1>News &amp; guides</h1>', $html, 'page title is preserved and escaped' );
news_contains( '<p>Owner-authored <em>editorial introduction</em>.</p>', $html, 'owner-authored introduction markup is preserved' );
news_assert( array( array( 'the_content', $GLOBALS['news_fixture']['page']->post_content ) ) === $GLOBALS['news_fixture']['filters'], 'introduction retains its content filter' );
news_assert( 1 === preg_match( '/<h2\b[^>]*id="vf-news-feed-title"[^>]*>Published news and guides<\/h2>/', $html ), 'empty feed retains its section heading' );

$html = news_render( array( new WP_Post( 1 ) ), false );
news_assert( array() === $GLOBALS['news_fixture']['queries'], 'absent approved categories do not query all posts' );
news_assert( array() === news_articles( $html ), 'absent categories do not leak supplied posts' );
news_contains( $empty_message, $html, 'absent categories use the honest empty state' );

$long_title = 'Machop & "collecting" <script>fixture</script> ' . str_repeat( 'A long collector story ', 18 );
$single = new WP_Post( 21, array(
	'post_title' => $long_title,
	'post_excerpt' => '<b>Stored details</b> & careful context ' . str_repeat( 'without unsupported claims ', 14 ),
	'permalink' => 'https://fixture.invalid/story-21/?edition=base&view=full',
	'categories' => array( news_term( 'collector-guides', 'Collector Guides', 12 ) ),
) );
$html = news_render( array( $single ) );
$articles = news_articles( $html );
news_assert( 1 === count( $articles ), 'single eligible post renders once' );
news_contains( 'vf-news-list--single', $html, 'single-post state remains available' );
news_assert( false === strpos( $articles[0], 'has-image' ) && false === strpos( $articles[0], 'vf-news-card__image' ), 'missing thumbnail has no image state or empty image link' );
news_assert( array() === $GLOBALS['news_fixture']['thumbnails'], 'missing thumbnail is never requested' );
news_contains( esc_html( $long_title ), $articles[0], 'long title is preserved and escaped' );
news_contains( esc_html( $single->post_excerpt ), $articles[0], 'long excerpt is preserved and escaped' );
news_assert( false === strpos( $articles[0], '<script>' ) && false === strpos( $articles[0], '<b>' ), 'title and excerpt cannot inject fixture markup' );
news_contains( 'href="https://fixture.invalid/story-21/?edition=base&amp;view=full"', $articles[0], 'article URL is preserved and escaped' );
news_contains( '<p class="vf-kicker">Collector Guides</p>', $articles[0], 'approved category label is preserved' );
news_contains( 'datetime="2026-09-14T12:30:00-05:00"', $articles[0], 'machine-readable publication date is preserved' );
news_contains( 'September 14, 2026', $articles[0], 'human-readable publication date is preserved' );
news_contains( 'Read ' . esc_html( $long_title ), $articles[0], 'article action retains the contextual Read label' );
news_assert( 1 === preg_match( '/<h3\b[^>]*><a\b[^>]*>.*?<\/a><\/h3>/s', $articles[0] ), 'article title is subordinate to the feed heading' );
news_assert( false === strpos( $html, $empty_message ), 'populated feed does not show an empty message' );

$newer = new WP_Post( 31, array( 'thumbnail' => true ) );
$older = new WP_Post( 32, array( 'post_date' => '2026-09-01T08:00:00-05:00' ) );
$excluded = array(
	new WP_Post( 40, array( 'post_status' => 'draft' ) ),
	new WP_Post( 41, array( 'post_type' => 'page' ) ),
	new WP_Post( 42, array( 'categories' => array( news_term( 'other', 'Other' ) ) ) ),
	new WP_Post( 43, array( 'post_title' => 'Visibility test excluded title' ) ),
	new WP_Post( 44, array( 'post_excerpt' => 'VISIBILITY-TEST excluded excerpt' ) ),
);
foreach ( array( 'uncategorized', 'techwire', 'visibility-test', 'local-game-store', 'lgs', 'competitive', 'scraped', 'automated', 'syndicated' ) as $offset => $fragment ) {
	$excluded[] = new WP_Post( 50 + $offset, array(
		'categories' => array( news_term( 'news', 'News', 7 ), news_term( 'blocked-' . $fragment, 'Blocked ' . $fragment ) ),
	) );
}
$html = news_render( array_merge( array( $newer, $older ), $excluded, array( new stdClass(), null ) ) );
$articles = news_articles( $html );
news_assert( 2 === count( $articles ), 'only two eligible articles survive mixed query results' );
news_assert( array( $expected_query ) === $GLOBALS['news_fixture']['queries'], 'multiple results retain date-descending query contract' );
news_contains( 'Story 31', $articles[0], 'first query result remains first' );
news_contains( 'Story 32', $articles[1], 'second query result remains second' );
news_contains( 'September 1, 2026', $articles[1], 'older article retains its own publication date' );
news_contains( 'has-image', $articles[0], 'thumbnail presence exposes the image layout state' );
news_contains( 'vf-news-card__image', $articles[0], 'thumbnail retains its existing image hook' );
news_assert( array( array( 31, 'large' ) ) === $GLOBALS['news_fixture']['thumbnails'], 'only existing large thumbnail is requested' );
news_contains( 'src="https://fixture.invalid/image-31.jpg"', $articles[0], 'thumbnail output is preserved' );
news_assert( false === strpos( $articles[1], 'has-image' ), 'image state does not leak into another article' );
news_assert( false === strpos( $html, 'vf-news-list--single' ), 'multiple results do not retain single-post state' );
foreach ( $excluded as $post ) {
	news_assert( false === strpos( $html, esc_url( $post->permalink ) ), 'excluded post ' . $post->ID . ' has no destination in output' );
}

$html = news_render( $excluded );
news_assert( 0 === count( news_articles( $html ) ), 'all-excluded results render no articles' );
news_contains( $empty_message, $html, 'all-excluded results use the honest empty state' );

fwrite( STDOUT, 'PASS assertions=' . $assertions . PHP_EOL );
