<?php
/**
 * Configurable author schema piece object.
 */

namespace Authorship\Yoast;

use Yoast\WP\SEO\Generators\Schema;

class Author extends Schema\Author {

	protected $user_id;

	protected $identifer;

	public function __construct( int $user_id ) {
		$this->user_id = $user_id;
		$this->identifier = 'author_' . $user_id;
	}

	protected function determine_user_id() {
		return $this->user_id;
	}

}
