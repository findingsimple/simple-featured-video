<?php
/*
Plugin Name: Simple Featured Video
Plugin URI: http://plugins.findingsimple.com
Description: Adds a meta box for setting a featured video.
Version: 1.0
Author: Finding Simple
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2012  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Simple_Featured_Video' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Simple_Featured_Video
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple Featured Video
 * @since 1.0
 */
function initialize_featured_video(){
	Simple_Featured_Video::init();
}
add_action( 'init', 'initialize_featured_video', -1 );

/**
 * Plugin Main Class.
 *
 * @package Simple Featured Video
 * @author Jason Conroy <jason@findingsimple.com>
 * @since 1.0
 */
class Simple_Featured_Video {

	static $text_domain;

	/**
	 * Initialise
	 */
	public static function init() {
	
		global $wp_version;

		self::$text_domain = apply_filters( 'simple_featured_video_text_domain', 'Simple_Featured_Video' );
		
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( __CLASS__, 'simple_featured_video_add_meta_box' ) );

		/* Save the meta boxes data on the 'save_post' hook. */
		add_action( 'save_post', array( __CLASS__, 'simple_featured_video_save' ) , 10, 2 );
		
	}

	/* Adds custom meta boxes to the theme settings page. */
	public static function simple_mlmb_add_meta_box() {

		/* Add a custom meta box. */
		add_meta_box(
			'simple-featured-video',			
			__( 'Featured Video', self::$text_domain  ),
			array( __CLASS__, 'simple_featured_video_display' ),			
			'post',
			'normal',
			'low'
		);
		
	}


	/**
	 * Displays the featured video meta box.
	 *
	 */
	public static function simple_featured_video_display( $object, $box ) {
		
		wp_nonce_field( basename( __FILE__ ), 'simple-featured-video-nonce' );
								
		$featured_video_link = esc_attr( get_post_meta( $object->ID, '_simple_featured_video_link' , true) ); 
																					
	?>		

		<p>
			<label for="featured-video-link"><?php _e( 'Video Link:', self::$text_domain ); ?></label>
			<br />
			<input name='featured-video-link' id='featured-video-link' value='<?php echo $featured_video_link ?>' class="widefat" />	
			<br />
			<span style="color:#aaa;">Supports Youtube and Vimeo</span>
		</p>
						
	<?php
		}	


	/**
	 * Saves the featured video meta box settings as post metadata.
	 *
	 */
	public static function simple_mlmb_save( $post_id, $post ) {

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['simple-featured-video-nonce'] ) || !wp_verify_nonce( $_POST['simple-featured-video-nonce'], basename( __FILE__ ) ) )
			return $post_id;

		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;

		$meta = array(
			'_simple_featured_video_link' => strip_tags( $_POST['featured-video-link'] )
		);
		
		foreach ( $meta as $meta_key => $new_meta_value ) {

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, $meta_key, true );

			/* If a new meta value was added and there was no previous value, add it. */
			if ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, $meta_key, $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, $meta_key, $new_meta_value );

			/* If there is no new meta value but an old value exists, delete it. */
			elseif ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, $meta_key, $meta_value );
		}
		
	}
	
}

endif;