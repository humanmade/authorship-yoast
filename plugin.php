<?php
/**
 * Plugin Name: Authorship for Yoast SEO
 * Plugin URI: https://github.com/humanmade/authorship-yoast
 * Description: Yoast SEO Compatibility for the Authorship plugin
 * Version: 1.1.0
 * Author: Human Made Limited
 * Author URI: https://humanmade.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: authorship-yoast
 * Domain Path: /languages
 */

namespace Authorship\Yoast;

require_once __DIR__ . '/inc/class-article.php';
require_once __DIR__ . '/inc/class-author.php';
require_once __DIR__ . '/inc/class-meta-authors-presenter.php';
require_once __DIR__ . '/inc/namespace.php';

bootstrap();
