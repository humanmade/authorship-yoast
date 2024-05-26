<?php
/**
 * Schema modifications.
 */

namespace Authorship\Yoast;

use Authorship;
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
