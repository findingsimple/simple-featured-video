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
		
		/* Override thumbnail id */
		add_filter( 'get_post_metadata', array( __CLASS__, 'override_thumbnail' ) , 10, 4 );
		
		/* Link thumbnail to the video */
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'link_thumbnail' ), 10, 5 );

	}

	/* Adds custom meta boxes to the theme settings page. */
	public static function simple_featured_video_add_meta_box() {

		/* Add a custom meta box. */
		add_meta_box(
			'simple-featured-video',			
			__( 'Featured Video', self::$text_domain  ),
			array( __CLASS__, 'simple_featured_video_display' ),			
			'post',
			'side',
			'default'
		);
		
	}


	/**
	 * Displays the featured video meta box.
	 *
	 */
	public static function simple_featured_video_display( $object, $box ) {
		
		wp_nonce_field( basename( __FILE__ ), 'simple-featured-video-nonce' );
								
		$featured_video_url = esc_attr( get_post_meta( $object->ID, '_simple_featured_video_url' , true) ); 
		
		$featured_video_thumbnail_url = esc_attr( get_post_meta( $object->ID , '_simple_featured_video_thumbnail_url' , true ) );
		
		$override_featured_image = esc_attr( get_post_meta( $object->ID , '_simple_featured_video_override' , true ) );
		
		$featured_video_link_to_video = esc_attr( get_post_meta( $object->ID , '_simple_featured_video_link_to_video' , true ) );
																					
	?>		
		
		<?php if ( !empty( $featured_video_thumbnail_url ) ) { ?>
		<a href="<?php echo $featured_video_url; ?>" title="" target="_blank" style="display:block;padding-top:4px;" ><img src='<?php echo $featured_video_thumbnail_url; ?>' alt='' style="display:block;width:100%;" /></a>
		<?php } ?>
		
		<p>
			<label for="featured-video-url"><?php _e( 'Video URI:', self::$text_domain ); ?></label>
			<br />
			<input name='featured-video-url' id='featured-video-url' value='<?php echo $featured_video_url ?>' class="widefat" />	
			<br />
			<span style="color:#aaa;">Supports Youtube and Vimeo (non vanity urls)</span>
		</p>
		<p>
			<label for="override-featured-image" class="selectit">
			<input name="override-featured-image" type="checkbox" id="override-featured-image" value="yes" <?php checked( $override_featured_image, 'yes', true ); ?>/>
			<?php _e( 'Override Featured Image', self::$text_domain ); ?></label>
		</p>
		<p>
			<label for="featured-video-link-to-video" class="selectit">
			<input name="featured-video-link-to-video" type="checkbox" id="featured-video-link-to-video" value="yes" <?php checked( $featured_video_link_to_video, 'yes', true ); ?>/>
			<?php _e( 'Link to featured video', self::$text_domain ); ?></label>
		</p>
						
	<?php
		}	


	/**
	 * Saves the featured video meta box settings as post metadata.
	 *
	 */
	public static function simple_featured_video_save( $post_id, $post ) {
		
		$post_type = $post->post_type;
		
	    /* don't run if this is an auto save */
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;
				
		/* don't run if the function is called for saving revision - default wp doesn't save meta with revisions */
		if ( $post_type == 'revision' )
			return;

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['simple-featured-video-nonce'] ) || !wp_verify_nonce( $_POST['simple-featured-video-nonce'], basename( __FILE__ ) ) )
			return $post_id;
			
		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;

		$meta = array();
		
		$meta['_simple_featured_video_url'] = strip_tags( $_POST['featured-video-url'] );
		$meta['_simple_featured_video_thumbnail_url'] = self::maybe_get_video_thumbnail_uri( $meta['_simple_featured_video_url'] , $post_id );
		$meta['_simple_featured_video_attachment_id'] = self::maybe_attach_video_thumbnail( $meta['_simple_featured_video_url'], $meta['_simple_featured_video_thumbnail_url'] , $post_id );
		$meta['_simple_featured_video_override'] = strip_tags( $_POST['override-featured-image'] );
		$meta['_simple_featured_video_link_to_video'] = strip_tags( $_POST['featured-video-link-to-video'] );

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
	

	/**
	 * X
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */
	public static function maybe_get_video_thumbnail_uri( $video_uri, $post_id ) {
	
		$current_video_uri = get_post_meta( $post_id , '_simple_featured_video_url' , true );
		
		// if the video hasn't changed don't re-determine the thumbnail url
		if ( $current_video_uri == $video_uri ) {
			return get_post_meta( $post_id , '_simple_featured_video_thumbnail_url' , true );
		} else {		
			return self::get_video_thumbnail_uri( $video_uri ); 
		}
				
	}
	
	/**
	 * X
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */
	public static function maybe_attach_video_thumbnail( $video_uri, $thumbnail_uri, $post_id ) {
	
		$current_video_uri = get_post_meta( $post_id , '_simple_featured_video_url' , true );
		
		// if the video hasn't changed don't re-attach the thumbnail
		if ( $current_video_uri == $video_uri ) {
			return get_post_meta( $post_id , '_simple_featured_video_attachment_id' , true );
		} else {		
			return self::attach_video_thumbnail( $video_uri, $thumbnail_uri, $post_id ); 
		}
				
	}

	/**
	 * XXX
	 * 
	 * @since 1.0
	 * @author Jason COnroy
	 */
	public static function get_video_thumbnail_uri( $video_uri ) {
	
		$thumbnail_uri = '';
		
		// determine the type of video and the video id
		$video = self::parse_video_uri( $video_uri );
		
		// get youtube thumbnail
		if ( $video['type'] == 'youtube' )
			$thumbnail_uri = 'http://img.youtube.com/vi/' . $video['id'] . '/hqdefault.jpg';
		
		// get vimeo thumbnail
		if( $video['type'] == 'vimeo' )
			$thumbnail_uri = self::get_vimeo_thumbnail_uri( $video['id'] );
		
		// get default/placeholder thumbnail
		if( empty( $thumbnail_uri ) || is_wp_error( $thumbnail_uri ) )
			$thumbnail_uri = ''; 
		
		//return thumbnail uri
		return $thumbnail_uri;
		
	}

	/**
	 * XXX
	 * 
	 * @since 1.0
	 * @author Jason COnroy
	 */
	public static function parse_video_uri( $url ) {
		
		// Parse the url 
		$parse = parse_url( $url );
		
		// Set blank variables
		$video_type = '';
		$video_id = '';
		
		// Url is http://youtu.be/xxxx
		if ( $parse['host'] == 'youtu.be' ) {
		
			$video_type = 'youtube';
			
			$video_id = ltrim( $urls['path'],'/' );	
			
		}
		
		// Url is http://www.youtube.com/watch?v=xxxx 
		// or http://www.youtube.com/watch?feature=player_embedded&v=xxx
		// or http://www.youtube.com/embed/xxxx
		if ( ( $parse['host'] == 'youtube.com' ) || ( $parse['host'] == 'www.youtube.com' ) ) {
		
			$video_type = 'youtube';
			
			parse_str( $parse['query'] );
			
			$video_id = $v;	
			
			if ( !empty( $feature ) )
				$video_id = end( explode( 'v=', $parse['query'] ) );
				
			if ( strpos( $parse['path'], 'embed' ) == 1 )
				$video_id = end( explode( '/', $parse['path'] ) );
			
		}
		
		// Url is http://www.vimeo.com
		if ( ( $parse['host'] == 'vimeo.com' ) || ( $parse['host'] == 'www.vimeo.com' ) ) {
		
			$video_type = 'vimeo';
			
			$video_id = ltrim( $parse['path'],'/' );	
						
		}
		
		// If recognised type return video array
		if ( !empty( $video_type ) ) {
		
			$video_array = array(
				'type' => $video_type,
				'id' => $video_id
			);
		
			return $video_array;
			
		} else {
		
			return false;
			
		}
		
	}	


	/**
	 * Takes a Vimeo video/clip ID and calls the Vimeo API v2 to get the large thumbnail URL.
	 * 
	 * @since 1.0
	 * @author Brent Shepherd
	 */
	public static function get_vimeo_thumbnail_uri( $clip_id ) {

		$vimeo_api_uri = 'http://vimeo.com/api/v2/video/' . $clip_id . '.php';

		$vimeo_response = wp_remote_get( $vimeo_api_uri );

		if( is_wp_error( $vimeo_response ) ) {
			return $vimeo_response;
		} else {
			$vimeo_response = unserialize( $vimeo_response['body'] );
			return $vimeo_response[0]['thumbnail_large'];
		}
		
	}

	/**
	 * Attach the thumbnail image to the post and return attachment id
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */
	public static function attach_video_thumbnail( $video_uri, $thumbnail_uri, $post_id ) {

		$image = media_sideload_image( $thumbnail_uri, $post_id );

		// Remove unwanted HTML, keep just a plain URL (or whatever is in the image src="..." )
		$image = preg_replace("/.*(?<=src=[\"'])([^\"']*)(?=[\"']).*/", '$1', $image);
				
		$attachment_id = self::get_attachment_id_from_src( $image );
		
		if ( !empty( $attachment_id ) )
			return $attachment_id;
		else
			return false;
		
	}	

	/**
	 * Get attachment id from image src.
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */	
    public static function get_attachment_id_from_src( $image_src ) {
    
		global $wpdb;
		
		$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid = %s", $image_src );
		
		$id = $wpdb->get_var($query);
		
		if ( !empty( $id ) )
			return $id;
		else
			return false;
		
    }
 

	/**
	 * Filter to override the thumbnail
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */	    
	public static function override_thumbnail( $meta_value, $post_id, $meta_key, $single ) {
		
		if ( is_admin() )
			return $meta_value;
				
		if ( !isset( $meta_key ) || ( $meta_key != '_thumbnail_id' ) )
			return $meta_value;

		$override = esc_attr( get_post_meta( $post_id , '_simple_featured_video_override' , true ) );
							
		if ( $override != 'yes' )
		 	return $meta_value;
		 	
		$meta_value = get_post_meta( $post_id , '_simple_featured_video_attachment_id' , true );
		
		return $meta_value;

	}

	/**
	 * Filter to wrap thumbnail in anchor tag/s to link to video
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */	 	
	public static function link_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		
		$override = esc_attr( get_post_meta( $post_id , '_simple_featured_video_override' , true ) );
		
		$featured_video_link_to_video = esc_attr( get_post_meta( $post_id , '_simple_featured_video_link_to_video' , true ) );

		if ( ( $override != 'yes' ) || ( $featured_video_link_to_video != 'yes' )  )
		 	return $html;	
		
		$video = self::parse_video_uri( get_post_meta( $post_id , '_simple_featured_video_url' , true ) );		
		
		if ( $video['type'] == 'youtube' )
			$url = 'http://www.youtube.com/embed/'. $video['id'] .'?rel=0';
			
		if ( $video['type'] == 'vimeo' )
			$url = 'http://player.vimeo.com/video/'. $video['id'] ;
		
		/* Check for existing <a></a> tags - e.g. embedded by Justin Tadlock's "Get The Image" plugin/extension */
		if ( strpos( $html , '<a href=' ) === false ) {
						
			$html = '<a class="feature-video" href="' . $url . '" >' . $html . $tail;
		
		} else {

			$pattern = "/(?<=href=(\"|'))[^\"']+(?=(\"|'))/";
			$html = preg_replace( $pattern, $url, $html );	
			$html = str_replace( '<a href=', '<a class="feature-video" href=', $html );	
		
		}

		return $html;
		
	}

	/**
	 * Check if post has a video
	 *
	 * Works same as http://codex.wordpress.org/Function_Reference/has_post_thumbnail
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 */	 
	public function has_post_video( $post_id = null ) {
	
		return ( bool ) self::get_post_video_thumbnail_id( $post_id );
		
	}	

	/**
	 * Retrieve Post Featured Video ID.
	 *
	 * @param int $post_id Optional. Post ID.
	 * @return int
	 * @since 1.0
	 */
	public function get_post_video_thumbnail_id( $post_id = null ) {
	
		$post_id = ( null === $post_id ) ? get_the_ID() : $post_id;
		
		return get_post_meta( $post_id, '_simple_featured_video_attachment_id', true );
		
	}
		
	/**
	 * Display Post Feature Video Thumbnail.
	 *
	 */
	public function the_post_video( $size = 'post-thumbnail', $attr = '' ) {
	
		echo self::get_the_post_video( null, $size, $attr );
		
	}
	
	/**
	 * Retrieve Post video thumbnail.
	 *
	 */
	public function get_the_post_video( $post_id = null, $size = 'post-thumbnail', $attr = ''  ) {
	
		$post_id = ( null === $post_id ) ? get_the_ID() : $post_id;
		
		$post_video_attachment_id = self::get_post_video_thumbnail_id( $post_id );
		
		if ( $post_video_attachment_id ) {
		
			do_action( 'begin_fetch_post_video_html', $post_id, $post_video_attachment_id );

			if ( in_the_loop() )
				self::update_post_video_thumbnail_cache();
				
			$html = wp_get_attachment_image( $post_video_attachment_id, $size, false, $attr );
			
			do_action( 'end_fetch_post_video_html', $post_id, $post_video_attachment_id );
			
		} else {
		
			$html = '';
			
		}
		
		return apply_filters( 'post_video_html', $html, $post_id, $post_video_attachment_id, $size, $attr );
		
	}

	/**
	 * Update cache for video thumbnails in the current loop
	 *
	 */
	public function update_post_video_thumbnail_cache( $wp_query = null ) {

		if ( ! $wp_query )
			$wp_query = $GLOBALS['wp_query'];

		if ( $wp_query->thumbnails_cached )
			return;

		$thumb_ids = array();
	
		foreach ( $wp_query->posts as $post ) {
			if ( $id = self::get_post_video_thumbnail_id( $post->ID ) )
				$thumb_ids[] = $id;
		}

		if ( ! empty ( $thumb_ids ) ) {
			_prime_post_caches( $thumb_ids, false, true );
		}

		$wp_query->thumbnails_cached = true;
	
	}	
	
	
}

endif;