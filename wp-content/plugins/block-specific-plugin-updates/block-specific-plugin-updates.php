<?php
/* 
Plugin Name: Block Specific Plugin Updates
Plugin URI: https://dineshkarki.com.np/block-specific-plugin-updates
Description: This plugin blocks the updates for specific plugins. You can select the plugins from plugin setting page.
Author: Dinesh Karki
Version: 3.3.3
Author URI: https://www.dineshkarki.com.np
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*  Copyright 2012  Dinesh Karki  (email : dnesskarki@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_filter( 'http_request_args', 'bpu_prevent_update_check', 10, 2 );
function bpu_prevent_update_check( $r, $url ) {
	if ( 0 === strpos( $url, 'https://api.wordpress.org/plugins/update-check/1.1/' ) ) {
		$bpu_update_blocked_plugins = get_option( 'bpu_update_blocked_plugins' );
		if ( ! empty( $bpu_update_blocked_plugins ) ) {
			$bpu_update_blocked_plugins_array = explode( '###', $bpu_update_blocked_plugins );
			if ( is_array( $bpu_update_blocked_plugins_array ) && isset( $r['body']['plugins'] ) ) {
				$plugins = json_decode( $r['body']['plugins'], true );
				if ( is_array( $plugins ) && isset( $plugins['plugins'] ) && is_array( $plugins['plugins'] ) ) {
					foreach ( $bpu_update_blocked_plugins_array as $my_plugin ) {
						if ( ! empty( $my_plugin ) && isset( $plugins['plugins'][ $my_plugin ] ) ) {
							unset( $plugins['plugins'][ $my_plugin ] );
						}
					}
					$r['body']['plugins'] = json_encode( $plugins );
				}
			}
		}
	}
	return $r;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bpu_plugin_action_links' );
function bpu_plugin_action_links( $links ) {
	$settings_page = plugin_basename( dirname( __FILE__ ) . '/plugin_interface.php' );
	$links[] = '<a href="' . esc_url( get_admin_url( null, 'options-general.php?page=' . $settings_page ) ) . '">Settings</a>';
	return $links;
}

include( 'plugin_interface.php' );
?>