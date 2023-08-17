<?php
/**
 * Plugin Name:         wp-mail
 * Plugin URI:          https://github.com/Jazz-Man/wp-mail
 * Description:         This plugin optimizes and improves the wp_mail function.
 * Author:              Vasyl Sokolyk
 * Author URI:          https://www.linkedin.com/in/sokolyk-vasyl
 * Requires at least:   6.2
 * Requires PHP:        8.1
 * License:             MIT
 * Update URI:          https://github.com/Jazz-Man/wp-mail.
 */

use JazzMan\WPMail\WPMail;

if ( function_exists( 'app_autoload_classes' ) ) {
    app_autoload_classes( [
        WPMail::class,
    ] );
}
