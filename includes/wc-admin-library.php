<?php
/**
 * Contains all functions related to the Library page.
 * 
 * @package WebComic
 * @since 1.4.0
 */
 
function comic_page_library() {
	load_webcomic_domain();
	global $current_user, $paged, $post;
	
	//Set important variables
	$page       = 'webcomic/includes/wc-admin.php';
	$paged      = ( $_REQUEST[ 'paged' ] ) ? $_REQUEST[ 'paged' ] : 1;
	$series     = ( $_REQUEST[ 'series' ] ) ? $_REQUEST[ 'series' ] : get_comic_category();
	$view_link  = '?page=' . $page . '&amp;series=' . $series . '&amp;paged=' . $paged;
	$collection = get_the_collection( 'hide_empty=0&depth=3&series=' . $series );
	$categories = get_comic_category( true );
	$comic_dir  = $series;
	$file_path  = get_comic_directory( 'abs', false, $comic_dir );
	$thumb_path = get_comic_directory( 'abs', true, $comic_dir );
	
	/** Set or update the library view */
	if ( !get_usermeta( $current_user->ID, 'comic_library_view' ) )
		update_usermeta( $current_user->ID, 'comic_library_view', 'list' );
	if ( isset( $_REQUEST[ 'comic_library_view' ] ) )
		update_usermeta( $current_user->ID, 'comic_library_view', $_REQUEST[ 'comic_library_view' ] );
	
	/** Attempmt to update posts being matched with comics using the fallback method */
	if ( 'comic_fallback_update' == $_REQUEST[ 'action' ] ) {
		$comics = comic_loop( -1, $series );
		if ( $comics->have_posts() ) : while ( $comics->have_posts() ) : $comics->the_post();
			$comic = get_the_comic();
			
			if ( $comic && $comic->fallback ) {
				if ( !add_post_meta( $comic->ID, 'comic_file', basename( $comic->file ), true ) )
					update_post_meta( $comic->ID, 'comic_file', basename( $comic->file ) );
				
				if ( $comic->large ) {
					if ( !add_post_meta( $comic->ID, 'comic_large', basename( $comic->large ), true ) )
						update_post_meta( $comic->ID, 'comic_large', basename( $comic->large ) );
				}
				
				if ( $comic->medium ) {
					if ( !add_post_meta( $comic->ID, 'comic_medium', basename( $comic->medium ), true ) )
						update_post_meta( $comic->ID, 'comic_medium', basename( $comic->medium ) );
				}
				
				if ( $comic->thumb ) {
					if ( !add_post_meta( $comic->ID, 'comic_thumb', basename( $comic->thumb ), true ) )
						update_post_meta( $comic->ID, 'comic_thumb', basename( $comic->thumb ) );
				}
				
				$i++;
			}
		endwhile; endif; 
		
		$updated = sprintf( __( '%d fallback posts updated.', 'webcomic' ), $i );
		
		$i = 0;
	}
	
	/** Attempt to upload the selected comic file and generate comic thumbnails and a post if necessary */
	if ( 'comic_upload' == $_REQUEST[ 'action' ] && 0 === $_FILES[ 'new_comic_file' ][ 'error' ] ) {
		check_admin_referer( 'comic_upload' );
		
		$file = pathinfo( $_FILES[ 'new_comic_file' ][ 'name' ] );
		
		//Validate the file format. Files must be gif, jpg, jpeg, png, or swf.
		switch ( strtolower( $file[ 'extension' ] ) ) {
			case 'gif':
			case 'jpg':
			case 'jpeg':
			case 'png':
			case 'swf': break;
			default: $invalid_format = true;
		}
		
		if ( !$invalid_format ) {
			//Set the filename key for older versions of PHP
			if ( !$file[ 'filename' ] )
				$file[ 'filename' ] = rtrim( $file[ 'basename' ], '.' . $file[ 'extension' ] );
			
			//Generate a file hash if secure filenames are enabled
			$hash = ( get_option( 'comic_secure_names' ) ) ? '-' . substr( md5( uniqid( rand() ) ), 0, 7) : '';
			
			//Set the target path for the new file
			$target_path = $file_path . $file[ 'filename' ] . $hash . '.' . $file[ 'extension' ];
			
			//Attempt to move the uploaded file to the comic directory if a file with the new files filename doesn't already exist or overwrite is enabled 
			if ( ( !is_file( $target_path ) || $_REQUEST[ 'new_comic_overwrite' ] ) && move_uploaded_file( $_FILES[ 'new_comic_file' ][ 'tmp_name' ], $target_path ) ) {
				//Set the correct file permissions based on the server operating system
				if ( strpos( PHP_OS, 'WIN' ) )
					chmod( $target_path, 0777 );
				else
					chmod( $target_path, 0664 );
				
				//Get the file information
				$img_dim  = getimagesize( $target_path );
				
				//Attempt to create alternative sizes if this isn't an swf file
				if ( 'application/x-shockwave-flash' != $img_dim[ 'mime' ] ) {
					//Get the specified alternative image dimensions
					$img_lw   = get_option( 'comic_large_size_w' );
					$img_lh   = get_option( 'comic_large_size_h' );
					$img_mw   = get_option( 'comic_medium_size_w' );
					$img_mh   = get_option( 'comic_medium_size_h' );
					$img_tw   = get_option( 'comic_thumb_size_w' );
					$img_th   = get_option( 'comic_thumb_size_h' );
					$img_crop = get_option( 'comic_thumb_crop' ) ? true : false;
					
					//Generate a new large size image
					if ( $img_dim[ 0 ] > $img_lw || $img_dim[ 1 ] > $img_lh )
						$file[ 'large' ] = basename( image_resize( $target_path, $img_lw, $img_lh, 0, 'large', $thumb_path ) );
					
					//Generate a new medium size image
					if ( $img_dim[ 0 ] > $img_mw || $img_dim[ 1 ] > $img_mh )
						$file[ 'medium' ] = basename( image_resize( $target_path, $img_mw, $img_mh, 0, 'medium', $thumb_path ) );
					
					//Generate a new thumbnail size image
					if ( $img_dim[ 0 ] > $img_tw || $img_dim[ 1 ] > $img_th )
						$file[ 'thumb' ] = basename( image_resize( $target_path, $img_tw, $img_th, $img_crop, 'thumb', $thumb_path ) );
				}
				
				//Attempt to automatically generate a post
				if ( $_REQUEST[ 'new_comic_publish' ] ) {
					$post_date_std = $_REQUEST[ 'aa' ] . '-' . $_REQUEST[ 'mm' ] . '-' . $_REQUEST[ 'jj' ] . ' ' . $_REQUEST[ 'hh' ] . ':' . $_REQUEST[ 'mn' ] . ':' . $_REQUEST[ 'ss' ];
					$psot_date_gmt = get_gmt_from_date( $post_date );
					$post_status   = ( get_option( 'comic_post_draft' ) ) ? 'draft' : 'publish';
					
					$new_post = wp_insert_post( array(
						'post_content' => '&nbsp;',
						'post_status' => $post_status,
						'post_category' => array( $series ),
						'post_date' => $post_date_std,
						'post_date_gmt' => $post_date_gmt,
						'post_title' => $file[ 'filename' ]
					) );
					
					if ( $new_post ) {
						add_post_meta( $new_post, 'comic_file', $file[ 'basename' ] );
						if ( $file[ 'large' ] )
							add_post_meta( $new_post, 'comic_large', $file[ 'large' ] );
						if ( $file[ 'medium' ] )
							add_post_meta( $new_post, 'comic_medium', $file[ 'medium' ] );
						if ( $file[ 'thumb' ] )
							add_post_meta( $new_post, 'comic_thumb', $file[ 'thumb' ] );
					} else {
						$error = __( 'A post could not be automatically generated.', 'webcomic' );
					}
				}
				
				$updated = __( 'New comic uploaded.', 'webcomic' );
			} else {
				$error = __( 'A comic with that name already exists.', 'webcomic' );
			}
		} else {
			$error = __( 'Invalid file format. Comics must be gif, jpg, jpeg, png, or swf.', 'webcomic' );
		}
	} elseif ( 'comic_upload' == $_REQUEST[ 'action' ] && 4 != $_FILES[ 'new_comic_file' ][ 'error' ] ) {
		switch ( $_FILES[ 'new_comic_file' ][ 'error' ] ) {
			case 1: //For simplicities sake we treat these as the same error.
			case 2:
				$error = __( 'The file is too large to upload.', 'webcomic' );	break;
			case 3:
				$error = __( 'The file was only partially uploaded.', 'webcomic' );	break;
			case 6:
				$error = __( 'Your servers temporary directory could not be found.', 'webcomic' ); break;
			case 7:
				$error = __( 'The file could not be saved properly after upload.', 'webcomic' ); break;
			case 8:
				$error = __( 'The upload was haulted by a PHP extensions.', 'webcomic'); break;
		}
	}
	
	/** Attempt to automatically generate posts for orphaned comics */
	if ( 'comic_chapters_update' == $_REQUEST[ 'action' ] ) {
		check_admin_referer( 'comic_chapters_update' );
		
		$comics = $_REQUEST[ 'comics' ];
		
		if ( $comics ) {
			$chapter = ( $_REQUEST['Submit1'] ) ? $_REQUEST[ 'comic_chapter1' ] : $_REQUEST[ 'comic_chapter2' ];
			
			foreach ( $comics as $comic )
				add_post_to_chapter( $comic, $chapter );
			
			$updated = ( -1 == $chapter ) ? __('All comics removed from chapters.', 'webcomic' ) : __( 'All comics assigned to new chapter.', 'webcomic' );
		} else {
			$error = __( 'Please select at least one comic.', 'webcomic' );
		}
	}
	
	/** Attempt to regenerate all comic thumbnail files */
	if ( 'comic_regen_thumbs' == $_REQUEST[ 'action' ] ) {
		check_admin_referer( 'comic_regen_thumbs' );
		
		echo '<div id="message" class="updated fade"><p><img src="' . plugins_url( 'webcomic/includes/load.gif' ) . '" alt="Working&hellip;." style="vertical-align:middle" /> ' . __( 'Please wait while WebComic attempts to regenerate your comic thumbnails&hellip;', 'webcomic' ) . '</p></div>';
		
		$files    = glob( $file_path . '*.*' );
		$thumbs   = glob( $thumb_path . '*.*' );
		$img_lw   = get_option( 'comic_large_size_w' );
		$img_lh   = get_option( 'comic_large_size_h' );
		$img_mw   = get_option( 'comic_medium_size_w' );
		$img_mh   = get_option( 'comic_medium_size_h' );
		$img_tw   = get_option( 'comic_thumb_size_w' );
		$img_th   = get_option( 'comic_thumb_size_h' );
		$img_crop = get_option( 'comic_thumb_crop' ) ? true : false;
		
		foreach ( $thumbs as $thumb )
			unlink( $thumb );
		
		foreach ( $files as $file ) {
			$img_dim = getimagesize( $file );
			
			if ( 'application/x-shockwave-flash' != $img_dim[ 'mime' ] ) {
				if ( $img_dim[ 0 ] > $img_lw || $img_dim[ 1 ] > $img_lh )
						image_resize( $file, $img_lw, $img_lh, 0, 'large', $thumb_path );
				
				if ( $img_dim[ 0 ] > $img_mw || $img_dim[ 1 ] > $img_mh )
						image_resize( $file, $img_mw, $img_mh, 0, 'medium', $thumb_path );
				
				if ( $img_dim[ 0 ] > $img_tw || $img_dim[ 1 ] > $img_th )
					image_resize( $file, $img_tw, $img_th, $img_crop, 'thumb', $thumb_path );
			}
		}
		
		echo '<script type="text/javascript">jQuery("#message").hide(0);</script><div id="message" class="updated fade"><p>' . __( 'All thumbnails regenerated.', 'webcomic' ) . '</p></div>';
	}
	
	/** Attempt to regenerate an individual comics thumbnail files */
	if ( 'comic_thumb_regen' == $_REQUEST[ 'action' ] ) {
		check_admin_referer( 'comic_thumb_regen' );
		
		$img_dim = getimagesize( $file_path . $_REQUEST[ 'file' ] );
		
		if ( 'application/x-shockwave-flash' != $img_dim[ 'mime' ] ) {
			$thumbs   = glob( $thumb_path . '*.*' );
			$img_lw   = get_option( 'comic_large_size_w' );
			$img_lh   = get_option( 'comic_large_size_h' );
			$img_mw   = get_option( 'comic_medium_size_w' );
			$img_mh   = get_option( 'comic_medium_size_h' );
			$img_tw   = get_option( 'comic_thumb_size_w' );
			$img_th   = get_option( 'comic_thumb_size_h' );
			$img_crop = get_option( 'comic_thumb_crop' ) ? true : false;
			$file     = pathinfo( $file_path . $_REQUEST[ 'file' ] );
			
			if ( !$file[ 'filename' ] )
				$file[ 'filename' ] = rtrim( $_REQUEST[ 'file' ], '.' . $file[ 'extension' ] );
			
			foreach ( $thumbs as $thumb )
				if ( false !== strpos( $thumb, $file[ 'filename' ] ) )
					unlink( $thumb );
			
			if ( $img_dim[ 0 ] > $img_lw || $img_dim[ 1 ] > $img_lh )
					image_resize( $file_path . $file[ 'basename' ], $img_lw, $img_lh, 0, 'large', $thumb_path );
			
			if ( $img_dim[ 0 ] > $img_mw || $img_dim[ 1 ] > $img_mh )
					image_resize( $file_path . $file[ 'basename' ], $img_mw, $img_mh, 0, 'medium', $thumb_path );
			
			if ( $img_dim[ 0 ] > $img_tw || $img_dim[ 1 ] > $img_th )
				image_resize( $file_path . $file[ 'basename' ], $img_tw, $img_th, $img_crop, 'thumb', $thumb_path );
		
			$updated = sprintf( __( '%s thumbnails regenerated.', 'webcomic' ), $_REQUEST[ 'file' ] );
		}
	}
	
	/** Attempt to regenerate all comic thumbnail files */
	if ( 'comic_delete' == $_REQUEST[ 'action' ] ) {
		check_admin_referer( 'comic_delete' );
		
		if ( is_file( $file_path . $_REQUEST[ 'file' ] ) ) {
			$file   = pathinfo( $file_path . $_REQUEST[ 'file' ] );
			$thumbs = glob( $thumb_path . '*.*' );
			
			if ( !$file[ 'filename' ] )
				$file[ 'filename' ] = rtrim( $_REQUEST[ 'file' ], '.' . $file[ 'extension' ] );
				
			unlink( $file_path . $_REQUEST[ 'file' ] );
			
			foreach ( $thumbs as $thumb )
				if ( false !== strpos( $thumb, $file[ 'filename' ] ) )
					unlink( $thumb );
			
			$updated = sprintf( __( 'Deleted %s', 'webcomic' ), $_REQUEST[ 'file' ] );
		} else {
			$error = sprintf( __( '%s could not be deleted because it does not exist.', 'webcomic' ), $_REQUEST[ 'file' ] );
		}
	}
	
	/** Attempt to automatically generate posts for orphaned comics */
	if ( 'comic_orphans_post' == $_REQUEST[ 'action' ] ) {
		check_admin_referer( 'comic_orphans_post' );
		
		switch( $_REQUEST[ 'ap' ] ) {
			case 'mi': $ap = 60; break;
			case 'ho': $ap = 3600; break;
			case 'da': $ap = 86400; break;
			case 'we': $ap = 604800; break;
			case 'mo': $ap = 2629743.83; break;
			case 'yr': $ap = 31556926; break;
		}
		
		$bt = strtotime( $_REQUEST[ 'aa' ] . '-' . $_REQUEST[ 'mm' ] . '-' . $_REQUEST[ 'jj' ] ) + ( 60 * 60 * $_REQUEST[ 'hh' ] ) + ( 60 * $_REQUEST[ 'mn' ] ) + $_REQUEST[ 'ss' ];
		$ai = intval( $_REQUEST[ 'ai' ] ) * $ap;
		
		if ( $ai ) {
			$orphans = explode( '/', $_REQUEST[ 'orphaned_comics' ] );
			array_pop( $orphans );
			
			foreach ( $orphans as $orphan ) {
				$file = pathinfo( $file_path . $orphan );
				
				if ( !$file[ 'filename' ] )
					$file[ 'filename' ] = rtrim( $orphan, '.' . $file[ 'extension' ] );
				
				$post_date_std = date( 'Y-m-d H:i:s', $bt );
				$psot_date_gmt = get_gmt_from_date( $post_date );
				$post_status   = ( get_option( 'comic_post_draft' ) ) ? 'draft' : 'publish';
				
				$new_post = wp_insert_post( array(
					'post_content' => '&nbsp;',
					'post_status' => $post_status,
					'post_category' => array( $series ),
					'post_date' => $post_date_std,
					'post_date_gmt' => $post_date_gmt,
					'post_title' => $file[ 'filename' ]
				) );
				
				if ( $new_post ) {
					add_post_meta( $new_post, 'comic_file', $file[ 'basename' ] );
					
					if ( 'swf' != $file[ 'extension' ] ) {
						$thumbs = glob( $thumb_path . '*.*' );
						
						foreach ( $thumbs as $thumb ) {
							if ( false !== strpos( $thumb, $file[ 'filename' ] . '-large' ) )
								add_post_meta( $new_post, 'comic_large', $file[ 'filename' ] . '-large.' . $file[ 'extension' ] );
							
							if ( false !== strpos( $thumb, $file[ 'filename' ] . '-medium' ) )
								add_post_meta( $new_post, 'comic_medium', $file[ 'filename' ] . '-medium.' . $file[ 'extension' ] );
							
							if ( false !== strpos( $thumb, $file[ 'filename' ] . '-thumb' ) )
								add_post_meta( $new_post, 'comic_thumb', $file[ 'filename' ] . '-thumb.' . $file[ 'extension' ] );
						}
					}
					
					$i++;
				}
				
				$bt += $ai;
			}
			
			if ( $i )
				$updated = sprintf( __ngettext( '%d post automatically generated.', '%d posts automatically generated.', $i, 'webcomic' ), $i );
				
			else
				$error = __( 'No posts could be automatically generated.', 'webcomic' );
			
		} else {
			$error = __( 'Invalid time increment.', 'webcomic' );
		}
	}
	
	/** Attempmt to rename an orphaned comic */
	if ( 'comic_rename' == $_REQUEST[ 'action' ] && $_REQUEST[ 'comic_new_name' ] ) {
		check_admin_referer( 'comic_rename' );
		
		if ( is_file( $file_path . $_REQUEST[ 'comic_old_name' ] . $_REQUEST[ 'comic_ext' ] ) && !is_file( $file_path . $_REQUEST[ 'comic_new_name' ] . $_REQUEST[ 'comic_ext' ] ) ) {
			rename( $file_path . $_REQUEST[ 'comic_old_name' ] . $_REQUEST[ 'comic_ext' ], $file_path . $_REQUEST[ 'comic_new_name' ] . $_REQUEST[ 'comic_ext' ] );
			
			$thumbs = glob( $thumb_path . '*.*' );
			
			foreach ( $thumbs as $thumb ) {
				if ( false !== strpos( $thumb, $_REQUEST[ 'comic_old_name' ] . '-large' ) )
					rename( $thumb, $thumb_path . $_REQUEST[ 'comic_new_name' ] . '-large' . $_REQUEST[ 'comic_ext' ] );
				
				if ( false !== strpos( $thumb, $_REQUEST[ 'comic_old_name' ] . '-medium' ) )
					rename( $thumb, $thumb_path . $_REQUEST[ 'comic_new_name' ] . '-medium' . $_REQUEST[ 'comic_ext' ] );
				
				if ( false !== strpos( $thumb, $_REQUEST[ 'comic_old_name' ] . '-thumb' ) )
					rename( $thumb, $thumb_path . $_REQUEST[ 'comic_new_name' ] . '-thumb' . $_REQUEST[ 'comic_ext' ] );
			}
			
			$updated = sprintf( __( 'Renamed %1$s to %2$s', 'webcomic' ), $_REQUEST[ 'comic_old_name' ] . $_REQUEST[ 'comic_ext' ], $_REQUEST[ 'comic_new_name' ] . $_REQUEST[ 'comic_ext' ] );
		} elseif ( is_file( $file_path . $_REQUEST[ 'comic_new_name' ] . $_REQUEST[ 'comic_ext' ] ) ) {
			$error = sprintf( __( '%1$s could not be renamed because a file named %2$s already exists.', 'webcomic' ), $_REQUEST[ 'comic_old_name' ] . $_REQUEST[ 'comic_ext' ], $_REQUEST[ 'comic_new_name' ] . $_REQUEST[ 'comic_ext' ] );
		} else {
			$error = sprintf( __( '%s could not be renamed because it does not exist.', 'webcomic' ), $_REQUEST[ 'file' ] );
		}
	} elseif ( 'comic_rename' == $_REQUEST[ 'action' ] && !$_REQUEST[ 'comic_new_name' ] ) {
		$error = __( 'A new filename must be provided.', 'webcomic' );
	}
	
	/** Attempt to automatically generate a post for an orphaned comic */
	if ( 'orphan_comic_post' == $_REQUEST[ 'action' ] ) {
		check_admin_referer( 'orphan_comic_post' );
		
		$file = pathinfo( $file_path . $_REQUEST[ 'comic_name' ] . '.' . $_REQUEST[ 'comic_ext' ] );
				
		if ( !$file[ 'filename' ] )
			$file[ 'filename' ] = rtrim( $_REQUEST[ 'comic_name' ] );

		$post_date_std = $_REQUEST[ 'aa' ] . '-' . $_REQUEST[ 'mm' ] . '-' . $_REQUEST[ 'jj' ] . ' ' . $_REQUEST[ 'hh' ] . ':' . $_REQUEST[ 'mn' ] . ':' . $_REQUEST[ 'ss' ];
		$psot_date_gmt = get_gmt_from_date( $post_date );
		$post_status   = ( get_option( 'comic_post_draft' ) ) ? 'draft' : 'publish';
		
		$new_post = wp_insert_post( array(
			'post_content' => '&nbsp;',
			'post_status' => $post_status,
			'post_category' => array( $series ),
			'post_date' => $post_date_std,
			'post_date_gmt' => $post_date_gmt,
			'post_title' => $file[ 'filename' ]
		) );
		
		if ( $new_post ) {
			add_post_meta( $new_post, 'comic_file', $file[ 'basename' ] );
			
			if ( 'swf' != $file[ 'extension' ] ) {
				$thumbs = glob( $thumb_path . '*.*' );
						
				foreach ( $thumbs as $thumb ) {
					if ( false !== strpos( $thumb, $file[ 'filename' ] . '-large' ) )
						add_post_meta( $new_post, 'comic_large', $file[ 'filename' ] . '-large.' . $file[ 'extension' ] );
					
					if ( false !== strpos( $thumb, $file[ 'filename' ] . '-medium' ) )
						add_post_meta( $new_post, 'comic_medium', $file[ 'filename' ] . '-medium.' . $file[ 'extension' ] );
					
					if ( false !== strpos( $thumb, $file[ 'filename' ] . '-thumb' ) )
						add_post_meta( $new_post, 'comic_thumb', $file[ 'filename' ] . '-thumb.' . $file[ 'extension' ] );
				}
			}
			
			$updated = sprintf( __( 'Post generated for %s', 'webcomic' ), $file[ 'basename' ] );
		} else {
			$error = __( 'A post could not be automatically generated.', 'webcomic' );
		}
	}
	
	//Display update and error messages
	if ( $updated )
		echo '<div id="message" class="updated fade"><p>' . $updated . '</p></div>';
	
	if ( $error )
		echo '<div id="message" class="error"><p>' . $error	. '</p></div>';
	
	
	
	//
	// Begin Library Output
	//
	
	//Get all the comic files
	$comic_files = glob( $file_path . '*.*' );
	
	//Get just the comic files associated with a post
	$comic_posts = array();
	$comics = comic_loop( -1, $series );
	if ( $comics->have_posts() ) : while ( $comics->have_posts() ) : $comics->the_post();
		$comic = get_the_comic();
		
		if ( $comic ) {
			array_push( $comic_posts, $file_path . end( explode( '/' , $comic->file ) ) );
			$fallback_comics += ( $comic->fallback ) ? 1 : 0;
		} else {
			$orphan_posts += 1;
		}
		
		$max_num_posts += 1;
	endwhile; endif;
	
	//Compare our lists to see if there are any orphaned files
	$comic_orphans = array_diff( $comic_files, $comic_posts );
	
	//Construct the library array
	$library = array();
	$comics  = comic_loop( 15, $series );
	
	if ( $comics->have_posts() ) : while ( $comics->have_posts() ) : $comics->the_post();
		$comic   = get_the_comic();
		$chapter = get_the_chapter();
		$volume  = get_the_chapter( 'volume' );
		$author  = get_userdata( $post->post_author );
		
		$data            = new stdClass();
		$data->ID        = $post->ID;
		$data->link      = get_permalink();
		$data->title     = ( get_the_title() ) ? get_the_title() : '(no title)';
		$data->author    = $author->display_name;
		$data->author_id = $author->ID;
		$data->date      = get_the_time( get_option( 'date_format' ) );
		$data->file      = end( explode( '/', $comic->file ) );
		$data->comic     = $comic->file;
		$data->thumb     = get_comic_object( $comic, 'thumb' );
		$data->volume    = ( $volume ) ? $volume->title : '&mdash;';
		$data->chapter   = ( $chapter ) ? $chapter->title . ' &laquo; ' : '';
		$data->flash     = ( $comic->flash ) ? true : false;
		
		switch ( $post->post_status ) {
			case 'future':  $data->status = __( 'Scheduled', 'webcomic' ); break;
			case 'publish':	$data->status = __( 'Published', 'webcomic' ); break;
			case 'pending':	$data->status = __( 'Pending', 'webcomic' ); break;
			case 'draft':   $data->status = __( 'Draft', 'webcomic' ); break;
			default:        $data->status = __( 'Unknown', 'webcomic' ); break;
		}
		
		$cur_num_posts += 1;
		
		array_push( $library, $data );
	endwhile; $max_num_pages = $comics->max_num_pages; endif;
	
	//Determine the number of pages and build the paged navigation
	if ( 1 < $max_num_pages ) {
		$i    = 1;
		$prev = $paged - 1;
		$next = $paged + 1;
		
		$to_num_post   = ( ( $paged * 15 ) > $max_num_posts ) ? $max_num_posts : $paged * 15;
		$fro_num_posts = ( $to_num_post == $max_num_posts ) ? $to_num_post - $cur_num_posts + 1 : $to_num_post - 14;
		
		$paged_links = '<div class="tablenav-pages"><span class="displaying-num">' . sprintf( __( 'Displaying %1$d &#8211; %2$d of %3$d', 'webcomic' ) , $fro_num_post, $to_num_post, $max_num_posts ) . '</span>';
		
		if ( 1 != $paged )
			$paged_links .= '<a href="?page=' . $page . '&amp;series=' . $series . '&amp;paged=' . $prev . '" class="prev page-numbers">&laquo;</a> ';
		
		while ( $i <= $max_num_pages ) {
			if ( ($i != 1 && $i < $paged - 2 ) || ( $i != $max_num_pages && $i > $paged + 2) ) {
				if ( $i == 2 || $i == $max_num_pages - 1 )
					$paged_links .= '<span class="page-numbers dots">...</span>';
				
				$i++;
				
				continue;
			}
			
			$paged_links .= ( $paged == $i ) ? '<span class="page-numbers current">' . $i . '</span> ' : '<a href="?page=' . $page . '&amp;series=' . $series . '&amp;paged=' . $i . '" class="page-numbers">' . $i . '</a>';
			
			$i++;
		}
	
		$i = 0;
		
		if ( $paged < $max_num_pages )
			$paged_links .= '<a href="?page=' . $page . '&amp;series=' . $series . '&amp;paged=' . $next . '" class="next page-numbers">&raquo;</a>';
		
		$paged_links = '</div>';
	}
	
	if ( 'thumbnail' == get_comic_library_view() )
		$thumb_col = '<th scope="col" style="width:' . get_option( 'thumbnail_size_h' ) . 'px"></th>';
?>
<div class="wrap">
	<div id="icon-webcomic" class="icon32"><img src="<?php echo plugins_url( 'webcomic/includes/webcomic.png' ); ?>" alt="icon" /></div>
	<h2><?php _e( 'Library', 'webcomic' ); ?></h2>
	<?php if ( 1 < count( $categories ) ) { ?>
	<form action="" method="get" class="search-form topmargin">
		<p class="alignright">
			<input type="hidden" name="page" value="<?php echo $page; ?>" />
			<select name="series">
			<?php foreach ( $categories as $cat ) { ?>
				<option value="<?php echo $cat ?>"<?php if ( $series == $cat ) echo ' selected="selected"'; echo '>' . get_term_field( 'name', $cat, 'chapter' ); ?></option>
			<?php } ?>
			</select>
			<input type="submit" value="<?php _e( 'Change Series', 'webcomic' ); ?>" class="button-secondary action" />
		</p>
	</form>
	<?php }
		if ( $fallback_comics && current_user_can( 'edit_others_posts' ) )
			echo '<div class="updated" style="clear:right;"><p>' . sprintf( __ngettext( '%d comic is being matched using the fallback method and <a href="%s">should be updated</a>.', '%d comics are being matched using the fallback method and <a href="%s">should be updated</a>.', $fallback_comics, 'webcomic' ), $fallback_comics, $view_link . '&amp;action=comic_fallback_update' ) . '</p></div>';
		if ( $orphan_posts && current_user_can( 'edit_others_posts' ) )
			echo '<div class="error" style="clear:right;"><p>' . __ngettext( '%d post is not linked to and cannot be matched with a comic.', '%d posts are not linked to and cannot be matched with a comic.', $orphan_posts, 'webcomic' ) . '</p></div>';
	?>
	<form action="" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'comic_upload' ); ?>
		<p class="alignleft">
			<input type="file" name="new_comic_file" id="new_comic_file" /><br />
			<span id="timestampdiv" class="misc-pub-section curtime misc-pub-section-last">
				<span id="timestamp">
					<label><input type="checkbox" name="new_comic_publish" id="new_comic_publish" checked="checked" value="1" /> <?php if ( get_option( 'comic_post_draft' ) ) _e( 'Save draft on:', 'webcomic' ); else _e( 'Publish on:', 'webcomic' ); ?></label>
					<select id="mm" name="mm">
						<option value="01"<?php if ( '01' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jan', 'webcomic'); ?></option>
						<option value="02"<?php if ( '02' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Feb', 'webcomic'); ?></option>
						<option value="03"<?php if ( '03' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Mar', 'webcomic'); ?></option>
						<option value="04"<?php if ( '04' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Apr', 'webcomic'); ?></option>
						<option value="05"<?php if ( '05' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'May', 'webcomic'); ?></option>
						<option value="06"<?php if ( '06' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jun', 'webcomic'); ?></option>
						<option value="07"<?php if ( '07' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jul', 'webcomic'); ?></option>
						<option value="08"<?php if ( '08' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Aug', 'webcomic'); ?></option>
						<option value="09"<?php if ( '09' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Sep', 'webcomic'); ?></option>
						<option value="10"<?php if ( '10' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Oct', 'webcomic'); ?></option>
						<option value="11"<?php if ( '11' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Nov', 'webcomic'); ?></option>
						<option value="12"<?php if ( '12' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Dec', 'webcomic'); ?></option>
					</select>
					<input type="text" id="jj" name="jj" value="<?php echo date( 'd', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />,
					<input type="text" id="aa" name="aa" value="<?php echo date( 'Y', strtotime( 'tomorrow' ) ); ?>" size="4" maxlength="5" />
					@ <input type="text" id="hh" name="hh" value="<?php echo date( 'H', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />
					: <input type="text" id="mn" name="mn" value="<?php echo date( 'i', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />
					<input type="hidden" id="ss" name="ss" value="<?php echo date( 's', strtotime( 'tomorrow' ) ); ?>" />
				</span>
			</span>
			<?php if ( current_user_can( 'edit_others_posts' ) ) { ?>
			<label><input type="checkbox" name="new_comic_overwrite" id="new_comic_overwrite" value="1" /> <?php _e( 'Overwrite', 'webcomic' ); ?></label>
			<?php } ?>
			<input type="submit" name="submit-upload" class="button-primary" value="<?php _e( 'Upload Comic', 'webcomic' ); ?>" />
			<input type="hidden" name="MAX_FILE_SIZE" value="20000000" />
			<input type="hidden" name="comic_upload_status" value="0" />
			<input type="hidden" name="action" value="comic_upload" />
			<input type="hidden" name="series" value="<?php echo $series; ?>" />
		</p>
	</form>
	<?php if ( $library ) { ?>
	<form action="" method="post">
		<?php wp_nonce_field('comic_chapters_update'); ?>
		<div class="tablenav">
			<div class="alignleft actions">
			<?php if ( current_user_can( 'manage_categories' ) ) { ?>
				<select name="comic_chapter1">
					<option value="-1"><?php _e( 'N\A', 'webcomic' ); ?></option>
				<?php foreach ( array_keys( get_object_vars( $collection->$series->volumes ) ) as $volume ) { ?>
					<optgroup label="<?php echo $collection->$series->volumes->$volume->title; ?>">
					<?php foreach ( array_keys( get_object_vars( $collection->$series->volumes->$volume->chapters ) ) as $chapter ) { ?>
						<option value="<?php echo $collection->$series->volumes->$volume->chapters->$chapter->ID; ?>"><?php echo $collection->$series->volumes->$volume->chapters->$chapter->title; ?></option>
					<?php } ?>
					</optgroup>
				<?php } ?>
				</select>
				<input type="submit" value="<?php _e( 'Update Collection', 'webcomic' ); ?>" name="Submit1" class="button-secondary action" />
				<?php } if ( current_user_can( 'edit_others_posts' ) ) { ?>
					<a href="<?php echo wp_nonce_url( $view_link . '&amp;action=comic_regen_thumbs', 'comic_regen_thumbs' ); ?>" class="button"><?php _e( 'Regenerate All Thumbnails', 'webcomic' ); ?></a>
				<?php } ?>
			</div>
		<?php echo $paged_links; ?>
			<div class="view-switch">
				<a href="<?php echo $view_link; ?>&amp;comic_library_view=list"><img<?php get_comic_library_view( 'list' ); ?> id="view-switch-list" src="../wp-includes/images/blank.gif" width="20" height="20" title="<?php _e( 'List View', 'webcomic' ); ?>" alt="<?php _e( 'List View', 'webcomic' ); ?>" /></a>
				<a href="<?php echo $view_link; ?>&amp;comic_library_view=thumbnail"><img<?php get_comic_library_view( 'thumbnail' ); ?>  id="view-switch-excerpt" src="../wp-includes/images/blank.gif" width="20" height="20" title="<?php _e( 'Thumbnail View', 'webcomic' ); ?>" alt="<?php _e( 'Thumbnail View', 'webcomic' ); ?>" /></a>
			</div>
		</div>
		<table class="widefat">
			<thead>
				<tr>
					<?php if ( current_user_can( 'manage_categories' ) ) { ?><th scope="col" class="check-column"><input type="checkbox" /></th><?php } echo $thumb_col; ?>
					<th scope="col"><?php _e( 'Comic', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Post', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Author', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Collection', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Date', 'webcomic' ); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<?php if ( current_user_can( 'manage_categories' ) ) { ?><th scope="col" class="check-column"><input type="checkbox" /></th><?php } echo $thumb_col; ?>
					<th scope="col"><?php _e( 'Comic', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Post', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Author', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Collection', 'webcomic' ); ?></th>
					<th scope="col"><?php _e( 'Date', 'webcomic' ); ?></th>
				</tr>
			</tfoot>
			<tbody>
			<?php foreach ( $library as $item ) { ?>
				<tr<?php if ( $item->comic && !( $i % 2 ) ) echo ' class="alt"'; elseif ( !$item->comic ) echo ' style="background:#fdd"'; ?>>
					<?php if ( $collection && current_user_can( 'manage_categories' ) ) { ?>
					<th scope="row" class="check-column"><input type="checkbox" name="comics[]" value="<?php echo $item->ID; ?>" /></th>
					<?php } if ( $thumb_col ) { ?>
					<td style="text-align:center"><?php echo $item->thumb; ?></td>
					<?php } ?>
					<td>
					<?php if ( $item->file ) { ?>
						<?php if ( current_user_can( 'edit_others_posts' ) || $current_user->ID == $post[ 'author_id' ] ) { ?><a href="post.php?action=edit&amp;post=<?php echo $item->ID; ?>" title="<?php _e( 'Edit this comic', 'webcomic' ); ?>" class="row-title"><?php echo $item->file; ?> </a><?php } else { echo '<span class="row-title">' . $item->file . '</span>'; } ?>
						<?php if ( current_user_can( 'edit_others_posts' ) || $current_user->ID == $item->author_id ){ ?>
						<div class="row-actions">
							<a href="post.php?action=edit&amp;post=<?php echo $item->ID; ?>" title="<?php _e( 'Edit this comic', 'webcomic' ); ?>"><?php _e( 'Edit', 'webcomic' ); ?></a>
							<?php if ( !$item->flash ) { ?>
							| <a href="<?php echo wp_nonce_url( $view_link . '&amp;action=comic_thumb_regen&amp;file=' . $item->file, 'comic_thumb_regen' ); ?>" title="<?php _e( 'Regenerate thumbnails for this comic', 'webcomic' ); ?>"><?php _e( 'Regenerate Thumbnails', 'webcomic' ); ?></a>
							<?php } ?>
							| <span class="delete"><a href="<?php echo wp_nonce_url( $view_link . '&amp;action=comic_delete&amp;file=' . $item->file, 'comic_delete' ); ?>" onclick="if (confirm('<?php echo js_escape( sprintf( __( "You are about to delete '%s'\n 'Cancel' to stop, 'OK' to delete.", "webcomic" ), $item->file ) ); ?>')) {return true;}return;" title="<?php _e( 'Delete this comic', 'webcomic' ); ?>"><?php _e( 'Delete', 'webcomic' ); ?></a></span>
						</div>
					<?php } } else { ?>
						<span class="row-title"><?php _e( 'No Comic Found', 'webcomic' ); ?></span>
					<?php } ?>
					</td>
					<td>
						<strong><?php if ( current_user_can( 'edit_others_posts' ) || $current_user->ID == $post[ 'author_id' ] ) { ?><a href="post.php?action=edit&amp;post=<?php echo $item->ID; ?>" title="<?php _e( 'Edit this post', 'webcomic' ); ?>"><?php echo $item->title; ?></a><?php } else { echo $item->title; } ?></strong>
						<div class="row-actions">
						<?php if ( current_user_can( 'edit_others_posts' ) || $current_user->ID == $item->author_id ) { ?>
							<a href="post.php?action=edit&amp;post=<?php echo $item->ID; ?>" title="<?php _e( 'Edit this post', 'webcomic' ); ?>"><?php _e( 'Edit', 'webcomic' ); ?></a>
							| <span class="delete"><a href="<?php echo wp_nonce_url( 'post.php?action=delete&amp;post=' . $item->ID, 'delete-post_' . $item->ID ); ?>" title="<?php _e( 'Delete this post', 'webcomic' ); ?>" onclick="if (confirm('<?php echo js_escape( sprintf( __( "You are about to delete '%s'\n 'Cancel' to stop, 'OK' to delete.", "webcomic" ), $item->title ) ); ?>')) {return true;}return;"><?php _e( 'Delete', 'webcomic' ); ?></a> | </span>
						<?php } ?>
							<span class="view"><a href="<?php echo $item->link; ?>" title="<?php _e( 'View', 'webcomic' ); ?> &quot;<?php echo $item->title ?>&quot;"><?php _e( 'View', 'webcomic' ); ?></a></span>
						</div>
					</td>
					<td><?php echo $item->author; ?></td>
					<td><?php echo $item->chapter . $item->volume; ?></td>
					<td><?php echo $item->date . '<br />' . $item->status; ?></td>
				</tr>
			<?php $i++; } $i = 0; ?>
			</tbody>
		</table>
		<div class="tablenav">
			<div class="alignleft actions">
			<?php if ( current_user_can( 'manage_categories' ) ) { ?>
				<select name="comic_chapter2">
					<option value="-1"><?php _e( 'N\A', 'webcomic' ); ?></option>
				<?php foreach ( array_keys( get_object_vars( $collection->$series->volumes ) ) as $volume ) { ?>
					<optgroup label="<?php echo $collection->$series->volumes->$volume->title; ?>">
					<?php foreach ( array_keys( get_object_vars( $collection->$series->volumes->$volume->chapters ) ) as $chapter ) { ?>
						<option value="<?php echo $collection->$series->volumes->$volume->chapters->$chapter->ID; ?>"><?php echo $collection->$series->volumes->$volume->chapters->$chapter->title; ?></option>
					<?php } ?>
					</optgroup>
				<?php } ?>
				</select>
				<input type="submit" value="<?php _e( 'Update Collection', 'webcomic' ); ?>" name="Submit2" class="button-secondary action" />
				<input type="hidden" name="action" value="comic_chapters_update" />
				<input type="hidden" name="series" value="<?php echo $series; ?>" />
				<input type="hidden" name="paged" value="<?php echo $paged; ?>" />
				<?php } ?>
			</div>
		<?php echo $paged_links; ?>
		</div>
	</form>
	<?php } else { ?>
	<p><?php _e( 'No posts have been assigned to this series yet.', 'webcomic' ); ?></p>
	<?php } if ( $comic_orphans && current_user_can( 'edit_others_posts' ) ) { ?>
	<h3 class="alignleft" style="clear:left"><?php _e( 'Orphaned Comics', 'webcomic' ); ?></h3>
	<form action="" method="post">
		<?php wp_nonce_field( 'comic_orphans_post' ); ?>
		<p class="alignright">
			<span id="timestampdiv" class="misc-pub-section curtime misc-pub-section-last">
				<span id="timestamp">
					<?php _e( 'Start on:' , 'webcomic' ); ?>
					<select id="mm" name="mm">
						<option value="01"<?php if ( '01' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jan', 'webcomic'); ?></option>
						<option value="02"<?php if ( '02' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Feb', 'webcomic'); ?></option>
						<option value="03"<?php if ( '03' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Mar', 'webcomic'); ?></option>
						<option value="04"<?php if ( '04' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Apr', 'webcomic'); ?></option>
						<option value="05"<?php if ( '05' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'May', 'webcomic'); ?></option>
						<option value="06"<?php if ( '06' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jun', 'webcomic'); ?></option>
						<option value="07"<?php if ( '07' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jul', 'webcomic'); ?></option>
						<option value="08"<?php if ( '08' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Aug', 'webcomic'); ?></option>
						<option value="09"<?php if ( '09' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Sep', 'webcomic'); ?></option>
						<option value="10"<?php if ( '10' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Oct', 'webcomic'); ?></option>
						<option value="11"<?php if ( '11' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Nov', 'webcomic'); ?></option>
						<option value="12"<?php if ( '12' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Dec', 'webcomic'); ?></option>
					</select>
					<input type="text" id="jj" name="jj" value="<?php echo date( 'd', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />,
					<input type="text" id="aa" name="aa" value="<?php echo date( 'Y', strtotime( 'tomorrow' ) ); ?>" size="4" maxlength="5" />
					@ <input type="text" id="hh" name="hh" value="<?php echo date( 'H', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />
					: <input type="text" id="mn" name="mn" value="<?php echo date( 'i', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />
					<input type="hidden" id="ss" name="ss" value="<?php echo date( 's', strtotime( 'tomorrow' ) ); ?>" />
					<?php
						if ( get_option( 'comic_post_draft' ) )
							_e( 'and save a draft every', 'webcomic' );
						else
							_e( 'and publish every', 'webcomic' );
					?>
					<input type="text" name="ai" id="jj" value="1" size="2" maxlength="2" />
					<select name="ap" id="ap">
						<option value="mi"><?php _e('Minutes', 'webcomic' ); ?></option>
						<option value="ho"><?php _e( 'Hours', 'webcomic' ); ?></option>
						<option value="da" selected="selected"><?php _e( 'Days', 'webcomic' ); ?></option>
						<option value="we"><?php _e( 'Weeks', 'webcomic' ); ?></option>
						<option value="mo"><?php _e( 'Months', 'webcomic' ); ?></option>
						<option value="yr"><?php _e( 'Years', 'webcomic' ); ?></option>
					</select>
				</span>
			</span>
			<input type="submit" class="button-secondary" value="<?php _e( 'Generate Posts', 'webcomic' ); ?>" />
			<input type="hidden" name="orphaned_comics" value="<?php foreach( $comic_orphans as $orphan) echo basename( $orphan ) . '/'; ?>" />
			<input type="hidden" name="action" value="comic_orphans_post" />
			<input type="hidden" name="series" value="<?php echo $series; ?>" />
		</p>
	</form>
	<table class="widefat">
		<thead>
			<tr>
				<?php echo $thumb_col; ?>
				<th scope="col"><?php _e( 'Comic', 'webcomic' ); ?></th>
				<th scope="col"><?php _e( 'Actions', 'webcomic' ); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<?php echo $thumb_col; ?>
				<th scope="col"><?php _e( 'Comic', 'webcomic' ); ?></th>
				<th scope="col"><?php _e( 'Actions', 'webcomic' ); ?></th>
			</tr>
		</tfoot>
		<tbody>
		<?php foreach ( $comic_orphans as $orphan ) { $orphan_info = pathinfo( $orphan ); if ( !$orphan_info[ 'filename' ] ) $orphan_info[ 'filename' ] = rtrim( $orphan_info[ 'basename' ], '.' . $orphan_info[ 'extension' ] ); ?>
			<tr<?php if ( !( $i % 2 ) ) echo ' class="alt"'; ?>>
				<?php if ( $thumb_col ) { ?>
				<td style="text-align:center">
				<?php if ( 'swf' == $orphan_info[ 'extension' ] ) { ?>
					<object type="application/x-shockwave-flash" data="<?php echo get_comic_directory( 'url', false, $comic_dir ) . $orphan_info[ 'basename' ]; ?>" height="<?php echo get_option( 'comic_thumb_size_h' ); ?>" width="<?php echo get_option( 'comic_thumb_size_w' ); ?>"><param name="movie" value="<?php echo get_comic_directory( 'url', false, $comic_dir ) . $orphan_info[ 'basename' ]; ?>" /></object>
				<?php } else { ?>
					<a href="<?php echo get_comic_directory( 'url', true, $comic_dir ) . $orphan_info[ 'basename' ]; ?>" title="<?php _e( 'View ', 'webcomic' ); echo $orphan_info[ 'basename' ]; ?>"><img src="<?php echo get_comic_directory( 'url', true, $comic_dir ) . $orphan_info[ 'filename' ] . '-thumb.' . $orphan_info[ 'extension' ]; ?>" alt="<?php echo $orphan_info[ 'basename' ]; ?>" /></a>
				<?php } ?>
				</td>
				<?php } ?>
				<td>
					<a href="<?php echo get_comic_directory( 'url', true, $comic_dir ) . $orphan_info[ 'basename' ]; ?>" title="<?php _e( 'View ', 'webcomic' ); echo $orphan_info[ 'basename' ]; ?>" class="row-title"><?php echo $orphan_info[ 'basename' ] ?></a>
					<div class="row-actions"><span class="delete"><a href="<?php echo wp_nonce_url( $view_link . '&amp;action=comic_delete&amp;file=' . $orphan_info[ 'basename' ], 'comic_delete' ) ?>" onclick="if (confirm('<?php echo js_escape( sprintf( __( "You are about to delete '%s'\n 'Cancel' to stop, 'OK' to delete.", "webcomic" ), $orphan_info[ 'basename' ] ) ); ?>')) { return true;}return;" title="<?php _e( 'Delete this comic', 'webcomic' ); ?>"><?php _e( 'Delete', 'webcomic' ); ?></a></span></div>
				</td>
				<td>
					<form action="" method="post">
						<?php wp_nonce_field( 'comic_rename' ); ?>
							<input type="text" name="comic_new_name" class="small-text" />
							<input type="text" name="comic_ext" readonly="readonly" class="small-text" style="background:#eee;border:1px solid #ccc;color:#999;width:3.25em" value="<?php echo '.' . $orphan_info[ 'extension' ]; ?>" />
							<input type="submit" name="submit" class="button-secondary" value="<?php _e( 'Rename', 'webcomic'); ?>" />
						<input type="hidden" name="comic_old_name" value="<?php echo $orphan_info[ 'filename' ]; ?>" />
						<input type="hidden" name="action" value="comic_rename" />
						<input type="hidden" name="series" value="<?php echo $series; ?>" />
					</form>
					<form action-"" method="post">
						<?php wp_nonce_field( 'orphan_comic_post' ); ?>
						<p>
							<span id="timestampdiv" class="misc-pub-section curtime misc-pub-section-last">
								<span id="timestamp">					
									<?php if ( get_option( 'comic_post_draft' ) ) _e( 'Save draft on:', 'webcomic' ); else _e( 'Publish on:', 'webcomic' ); ?>
									<select id="mm" name="mm">
										<option value="01"<?php if ( '01' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jan', 'webcomic'); ?></option>
										<option value="02"<?php if ( '02' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Feb', 'webcomic'); ?></option>
										<option value="03"<?php if ( '03' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Mar', 'webcomic'); ?></option>
										<option value="04"<?php if ( '04' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Apr', 'webcomic'); ?></option>
										<option value="05"<?php if ( '05' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'May', 'webcomic'); ?></option>
										<option value="06"<?php if ( '06' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jun', 'webcomic'); ?></option>
										<option value="07"<?php if ( '07' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Jul', 'webcomic'); ?></option>
										<option value="08"<?php if ( '08' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Aug', 'webcomic'); ?></option>
										<option value="09"<?php if ( '09' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Sep', 'webcomic'); ?></option>
										<option value="10"<?php if ( '10' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Oct', 'webcomic'); ?></option>
										<option value="11"<?php if ( '11' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Nov', 'webcomic'); ?></option>
										<option value="12"<?php if ( '12' == date( 'm', strtotime( 'tomorrow') ) ) echo ' selected="selected"'; echo '>' . __( 'Dec', 'webcomic'); ?></option>
									</select>
									<input type="text" id="jj" name="jj" value="<?php echo date( 'd', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />,
									<input type="text" id="aa" name="aa" value="<?php echo date( 'Y', strtotime( 'tomorrow' ) ); ?>" size="4" maxlength="5" />
									@ <input type="text" id="hh" name="hh" value="<?php echo date( 'H', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />
									: <input type="text" id="mn" name="mn" value="<?php echo date( 'i', strtotime( 'tomorrow' ) ); ?>" size="2" maxlength="2" />
									<input type="hidden" id="ss" name="ss" value="<?php echo date( 's', strtotime( 'tomorrow' ) ); ?>" />
								</span>
							</span>
							<input type="submit" class="button-secondary" value="<?php _e( 'Generate Post', 'webcomic' ); ?>" />
						</p>
						<input type="hidden" name="comic_name" value="<?php echo $orphan_info[ 'filename' ]; ?>" />
						<input type="hidden" name="comic_ext" value="<?php echo $orphan_info[ 'extension' ]; ?>" />
						<input type="hidden" name="action" value="orphan_comic_post" />
						<input type="hidden" name="series" value="<?php echo $series; ?>" />
					</form>
				</td>
			</tr>
		<?php $i++; } ?>
		</tbody>
	</table>
	<?php } ?>
</div>
<?php } ?>