<?php
/**
 * Article schema piece with multiple author support.
 */

namespace Authorship\Yoast;

use Authorship;
use WP_User;
use Yoast\WP\SEO\Generators\Schema;

class Article extends Schema\Article {

	function generate() {
		$data = parent::generate();

		$authors = Authorship\get_authors( $this->context->post );
		if ( empty( $authors ) ) {
			unset( $data['author'] );
			return $data;
		}

		$data['author'] = array_map( function ( $author ) {
			return [
				'name' => ( $author instanceof WP_User ) ? $this->helpers->schema->html->smart_strip_tags( $author->display_name ) : '',
				'@id'  => $this->helpers->schema->id->get_user_schema_id( $author->ID, $this->context ),
			];
		}, $authors );

		return $data;
	}

}
