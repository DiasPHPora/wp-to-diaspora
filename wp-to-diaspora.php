<?php
/**
 * Plugin Name:       WP to diaspora*
 * Plugin URI:        https://github.com/DiasPHPora/wp-to-diaspora
 * Description:       Automatically shares WordPress posts on diaspora*
 * Version:           4.0.0
 * Requires at least: 4.6
 * Tested up to:      6.3.1
 * Requires PHP:      8.0
 * Author:            Augusto Bennemann, Armando Lüscher
 * Author URI:        https://github.com/DiasPHPora/wp-to-diaspora/graphs/contributors
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-to-diaspora
 * GitHub Plugin URI: DiasPHPora/wp-to-diaspora
 * GitHub Branch:     master
 *
 * Copyright 2014-2023 Augusto Bennemann (gutobenn at gmail.com), Armando Lüscher (armando at noplanman.ch)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write
 * to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package   WP_To_Diaspora
 * @link      https://github.com/DiasPHPora/wp-to-diaspora
 * @author    Augusto Bennemann <gutobenn@gmail.com>
 * @copyright Copyright (c) 2023, Augusto Bennemann, Armando Lüscher
 * @version   4.0.0
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

define( 'WP2D_VERSION', '4.0.0' );
define( 'WP2D_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

// Get the party started!
WP2D::instance();
