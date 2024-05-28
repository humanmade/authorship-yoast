<?php
/**
 * Schema modifications.
 */

namespace Authorship\Yoast;

use Authorship;
use WP_User_Query;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Generators\Schema;
use Yoast\WP\SEO\Presenters;
use Yoast\WP\SEO\Presentations\Abstract_Presentation;

/**
 * Setup hooks.
 */
function bootstrap(): void {
	if ( ! function_exists( 'Authorship\get_authors' ) ) {
		return;
	}

	add_filter( 'wpseo_frontend_presentation', __NAMESPACE__ . '\\filter_presentation', 10, 2 );
	add_filter( 'wpseo_enhanced_slack_data', __NAMESPACE__ . '\\filter_enhanced_slack_data', 10, 2 );
	add_filter( 'wpseo_frontend_presenters', __NAMESPACE__ . '\\filter_frontend_presenters', 10, 2 );
	add_filter( 'wpseo_schema_graph_pieces', __NAMESPACE__ . '\\filter_schema_graph_pieces', 10, 2 );
	add_filter( 'wpseo_author_archive_post_types', __NAMESPACE__ . '\\filter_author_archive_post_types' );
	add_action( 'pre_get_users', __NAMESPACE__ . '\\action_reference_pre_get_users' );
	add_action( 'pre_user_query', __NAMESPACE__ . '\\action_reference_pre_user_query' );
}

/**
 * Modifies the presentation object for posts to use Authorship.
 *
 * @param Abstract_Presentation $presentation Yoast SEO presentation object.
 * @return Abstract_Presentation
 */
function filter_presentation( Abstract_Presentation $presentation ) : Abstract_Presentation {
	if ( $presentation->context->indexable->object_type !== 'post' ) {
		return $presentation;
	}

	if ( ! post_type_supports( $presentation->context->indexable->object_sub_type, 'author' ) ) {
		return $presentation;
	}

	$authors = Authorship\get_authors( get_post( $presentation->context->indexable->object_id ) );

	// Replace every instance of the author ID with the first author ID.
	// Yoast uses the different objects very inconsistently so need to cover bases.
	$presentation->model->author_id = $authors[0]->ID ?? 0;
	$presentation->context->indexable->author_id = $authors[0]->ID ?? 0;
	$presentation->context->post->post_author = $authors[0]->ID ?? 0;
	$presentation->source->post_author = $authors[0]->ID ?? 0;

	// Add all authors to the model to make it available to presenters.
	$presentation->model->authors = $authors;

	return $presentation;
}

/**
 * Update enhanced share data for Slack and Twitter.
 *
 * @param array $data
 * @param Abstract_Presentation $presentation
 * @return array
 */
function filter_enhanced_slack_data( $data, Abstract_Presentation $presentation ) : array {
	if ( isset( $data[ __( 'Written by', 'wordpress-seo' ) ] ) ) {
		$authors = wp_list_pluck( $presentation->model->authors ?? [], 'display_name' );
		$data[ __( 'Written by', 'wordpress-seo' ) ] = wp_sprintf_l( '%l', $authors );
	}

	return $data;
}

/**
 * Replace default author meta presenters with Authorship output.
 *
 * @param array $presenters
 * @param Meta_Tags_Context $context
 * @return array
 */
function filter_frontend_presenters( array $presenters, Meta_Tags_Context $context ) : array {
	if ( $context->indexable->object_type !== 'post' ) {
		return $presenters;
	}

	if ( ! post_type_supports( $context->indexable->object_sub_type, 'author' ) ) {
		return $presenters;
	}

	// Retrieve the authors for the post.
	$authors = Authorship\get_authors( get_post( $context->indexable->object_id ) );
	if ( empty( $authors ) ) {
		return $presenters;
	}

	// Remove default presenter.
	$presenters = array_filter( $presenters, function ( $presenter ) {
		return ! $presenter instanceof Presenters\Meta_Author_Presenter;
	} );

	// Add custom author presenters.
	foreach ( $authors as $author ) {
		$presenters[] = new Meta_Authors_Presenter( $author->ID );
	}

	return $presenters;
}

/**
 * Filter the schema graph pieces to inject all authors.
 *
 * @param array $pieces
 * @param Meta_Tags_Context $context
 * @return array
 */
function filter_schema_graph_pieces( array $pieces, Meta_Tags_Context $context ) : array {
	if ( $context->indexable->object_type !== 'post' ) {
		return $pieces;
	}

	if ( ! post_type_supports( $context->indexable->object_sub_type, 'author' ) ) {
		return $pieces;
	}

	// Remove default author piece.
	$pieces = array_filter( $pieces, function( $piece ) {
		return ! $piece instanceof Schema\Author;
	} );

	// Retrieve the authors for the post.
	$authors = Authorship\get_authors( get_post( $context->indexable->object_id ) );
	if ( empty( $authors ) ) {
		return $pieces;
	}

	// Add authorship author pieces.
	foreach ( $authors as $author ) {
		$author_piece = new Author( $author->ID );
		// Use a unique identifer so that Yoast SEO doesn't remove all but one.
		$pieces[ 'author_' . $author->ID ] = $author_piece;
	}

	// Replace Article piece with one that references all authors.
	$pieces = array_map( function ( $piece ) {
		if ( $piece instanceof Schema\Article ) {
			return new Article();
		}
		return $piece;
	}, $pieces );

	return $pieces;
}

/**
 * Expose public post types with author support to Yoast.
 *
 * @param array $post_types
 * @return array
 */
function filter_author_archive_post_types( array $post_types ) : array {
	$post_types = array_merge(
		$post_types,
		array_intersect(
			get_post_types_by_support( 'author' ),
			get_post_types( [
				'public' => true,
				'has_archive' => true
			] )
		)
	);

	$post_types = array_unique( $post_types );

	return array_values( $post_types );
}


/**
 * Fires before the WP_User_Query has been parsed.
 *
 * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
 */
function action_reference_pre_get_users( WP_User_Query $query ) : void {
	global $wpdb;

	// Author sitemap queries check for this meta key.
	if ( $query->get( 'meta_key' ) !== '_yoast_wpseo_profile_updated' ) {
		return;
	}

	// If we're checking the capability, we should add the guest-author cap as well.
	if ( $query->get( 'capability' ) === [ 'edit_posts' ] ) {
		$query->set( 'capability', null );
		$query->set( 'capability__in', [ 'edit_posts', 'guest-author' ] );
	}

	// We need to allow for guest authors with user level 0, which Yoast SEO excludes
	// by default, so update that part of the meta query.
	if ( ! $query->get( 'meta_query' ) ) {
		return;
	}

	$meta_query = $query->get( 'meta_query' );

	$user_level_query = array_search( [
		'key'     => $wpdb->get_blog_prefix() . 'user_level',
		'value'   => '0',
		'compare' => '!=',
	], $meta_query );

	if ( $user_level_query === false ) {
		return;
	}

	$meta_query[ $user_level_query ] = [
		'relation' => 'OR',
		$meta_query[ $user_level_query ],
		[
			'key' => $wpdb->get_blog_prefix() . 'capabilities',
			'value' => '"guest-author"',
			'compare' => 'LIKE',
		],
	];

	$query->set( 'meta_query', $meta_query );
}

/**
 * Modifies the has_published_posts where clause to include all guest authors.
 *
 * @param \WP_User_Query $query Current instance of WP_User_Query (passed by reference).
 */
function action_reference_pre_user_query( WP_User_Query $query ) : void {
	global $wpdb;

	// Author sitemap queries check for this meta key.
	if ( ! is_array( $query->get( 'has_published_posts' ) ) ) {
		return;
	}

	$blog_id = $query->get( 'blog_id' ) ?: 0;
	$post_types = $query->get( 'has_published_posts' );
	$supported_post_types = array_intersect(
		Authorship\get_supported_post_types(),
		$post_types
	);
	$unsupported_post_types = array_diff(
		$post_types,
		$supported_post_types
	);

	foreach ( $post_types as &$post_type ) {
		$post_type = $wpdb->prepare( '%s', $post_type );
	}
	foreach ( $supported_post_types as &$post_type ) {
		$post_type = $wpdb->prepare( '%s', $post_type );
	}
	foreach ( $unsupported_post_types as &$post_type ) {
		$post_type = $wpdb->prepare( '%s', $post_type );
	}

	$prefix = $wpdb->get_blog_prefix( $blog_id );
	$posts_table = $prefix . 'posts';
	$term_relationships_table = $prefix . 'term_relationships';
	$term_taxonomy_table = $prefix . 'term_taxonomy';
	$terms_table = $prefix . 'terms';

	// Rebuild the has_published_posts part of the query.
	$sql = " $wpdb->users.ID IN ( SELECT DISTINCT $posts_table.post_author FROM $posts_table WHERE $posts_table.post_status = 'publish' AND $posts_table.post_type IN ( " . implode( ', ', $post_types ) . ' ) )';

	// Non-authorship author query.
	$unsupported_sql = empty( $unsupported_post_types )
		? '1=0'
		: str_replace( implode( ', ', $post_types ), implode( ', ', $unsupported_post_types ), $sql );

	// Attributed author query.
	$supported_sql = empty( $supported_post_types )
		? '1=0'
		: " $wpdb->users.ID IN (
			SELECT DISTINCT CAST( t.slug AS SIGNED )
			FROM {$posts_table} p
				LEFT JOIN {$term_relationships_table} tr ON p.ID = tr.object_id
				LEFT JOIN {$term_taxonomy_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				LEFT JOIN {$terms_table} t ON tt.term_id = t.term_id
			WHERE p.post_type IN ( " . implode( ', ', $supported_post_types ) . " )
				AND p.post_status = 'publish'
		)";

	$query_with_guests = " ( ( $unsupported_sql ) OR ( $supported_sql ) )";

	$query->query_where = str_replace( $sql, $query_with_guests, $query->query_where );
}
