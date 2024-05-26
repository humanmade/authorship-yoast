<?php

namespace Authorship\Yoast;

use WP_User;
use Yoast\WP\SEO\Presenters;

class Meta_Authors_Presenter extends Presenters\Meta_Author_Presenter {

	public $user_id;


	public function __construct( int $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * Get the author's display name.
	 *
	 * @return string The author's display name.
	 */
	public function get() {
		if ( $this->presentation->model->object_sub_type !== 'post' ) {
			return '';
		}

		$user_data = get_userdata( $this->user_id );

		if ( ! $user_data instanceof WP_User ) {
			return '';
		}

		return trim( $this->helpers->schema->html->smart_strip_tags( $user_data->display_name ) );
	}
}
