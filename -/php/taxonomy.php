<?php
/** Contains the WebcomicTaxonomy class.
 * 
 * @package Webcomic
 */

/** Handle taxonomy-related tasks.
 * 
 * @package Webcomic
 */
class WebcomicTaxonomy extends Webcomic {
	/** Register hooks.
	 * 
	 * @uses Webcomic::$config
	 * @uses WebcomicTaxonomy::admin_init()
	 * @uses WebcomicTaxonomy::admin_footer()
	 * @uses WebcomicTaxonomy::edit_term()
	 * @uses WebcomicTaxonomy::create_term()
	 * @uses WebcomicTaxonomy::delete_term()
	 * @uses WebcomicTaxonomy::admin_enqueue_scripts()
	 * @uses WebcomicTaxonomy::get_terms_args()
	 * @uses WebcomicTaxonomy::add_form_fields()
	 * @uses WebcomicTaxonomy::edit_form_fields()
	 * @uses WebcomicTaxonomy::storyline_row_actions()
	 * @uses WebcomicTaxonomy::manage_custom_column()
	 * @uses WebcomicTaxonomy::manage_edit_columns()
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'edit_term', array( $this, 'edit_term' ), 10, 3 );
		add_action( 'create_term', array( $this, 'create_term' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'delete_term' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		
		add_filter( 'get_terms_args', array( $this, 'get_terms_args' ), 10, 2 );
		
		foreach ( array_keys( self::$config[ 'collections' ] ) as $k ) {
			add_action( "{$k}_storyline_add_form_fields", array( $this, 'add_form_fields' ), 10, 1 );
			add_action( "{$k}_character_add_form_fields", array( $this, 'add_form_fields' ), 10, 1 );
			add_action( "{$k}_storyline_edit_form_fields", array( $this, 'edit_form_fields' ), 10, 2 );
			add_action( "{$k}_character_edit_form_fields", array( $this, 'edit_form_fields' ), 10, 2 );
			
			add_filter( "{$k}_storyline_row_actions", array( $this, 'storyline_row_actions' ), 10, 2 );
			add_filter( "manage_{$k}_storyline_custom_column", array( $this, 'manage_custom_column' ), 10, 3 );
			add_filter( "manage_{$k}_character_custom_column", array( $this, 'manage_custom_column' ), 10, 3 );
			add_filter( "manage_edit-{$k}_storyline_columns", array( $this, 'manage_edit_columns' ), 10, 3 );
			add_filter( "manage_edit-{$k}_character_columns", array( $this, 'manage_edit_columns' ), 10, 3 );
		}
	}
	
	/** Handle storyline term group changes.
	 * 
	 * @uses Webcomic::$error
	 * @uses Webcomic::$notice
	 * @hook admin_init
	 */
	public function admin_init() {
		global $wpdb;
		
		if ( isset( $_GET[ 'webcomic_action' ], $_GET[ 'taxonomy' ], $_GET[ 'tag_ID' ] ) and ( 'move_term_up' === $_GET[ 'webcomic_action' ] or 'move_term_down' === $_GET[ 'webcomic_action' ] ) and $term = get_term( $_GET[ 'tag_ID' ], $_GET[ 'taxonomy' ] ) ) {
			check_admin_referer( 'webcomic_move_term' );
			
			$terms = get_terms( $term->taxonomy, array(
				'order'      => 'DESC',
				'orderby'    => 'term_group',
				'child_of'   => $term->parent,
				'hide_empty' => false
			) );
			
			if ( !( ( 'move_term_up' === $_GET[ 'webcomic_action' ] and 0 === ( integer ) $term->term_group ) or ( 'move_term_down' === $_GET[ 'webcomic_action' ] and $term->term_group === $terms[ 0 ]->term_group ) ) ) {
				foreach ( $terms as $t ) {
					if ( 'move_term_up' === $_GET[ 'webcomic_action' ] and ( integer ) $t->term_group === ( $term->term_group - 1 ) ) {
						$moved = ( is_wp_error( $wpdb->update( $wpdb->terms, array( 'term_group' => $t->term_group + 1 ), array( 'term_id' => $t->term_id ) ) ) or is_wp_error( $wpdb->update( $wpdb->terms, array( 'term_group' => $term->term_group - 1 ), array( 'term_id' => $term->term_id ) ) ) ) ? false : 'up';
						
						break;
					} elseif ( 'move_term_down' === $_GET[ 'webcomic_action' ] and ( integer ) $t->term_group === ( $term->term_group + 1 ) ) {
						$moved = ( is_wp_error( $wpdb->update( $wpdb->terms, array( 'term_group' => $t->term_group - 1 ), array( 'term_id' => $t->term_id ) ) ) or is_wp_error( $wpdb->update( $wpdb->terms, array( 'term_group' => $term->term_group + 1 ), array( 'term_id' => $term->term_id ) ) ) ) ? false : 'down';
						
						break;
					}
				}
			}
			
			if ( 'up' === $moved ) {
				self::$notice[] = __( 'Moved item up.', 'webcomic' );
			} elseif ( 'down' === $moved ) {
				self::$notice[] = __( 'Moved item down.', 'webcomic' );
			} else {
				self::$error[] = __( 'Item could not be moved.', 'webcomic' );
			}
		}
	}
	
	/** Render javascript for webcomic terms.
	 * 
	 * @uses Webcomic::$config
	 * @hook admin_footer
	 */
	public function admin_footer() {
		$screen = get_current_screen();
		
		if ( preg_match( '/^edit-webcomic\d+_(storyline|character)$/', $screen->id ) ) {
			printf( "<script>webcomic_edit_terms( '%s' );</script>", preg_replace( '/^edit-webcomic\d+_/', '', $screen->id ) );
		}
	}
	
	/** Detach media, upload new media, and update term group.
	 * 
	 * @param integer $id The edited term ID.
	 * @param integer $tax_id The edited term taxonomy ID.
	 * @param string $taxonomy The edited term taxonomy.
	 * @uses Webcomic::$config
	 * @hook edit_term
	 */
	public function edit_term( $id, $tax_id, $taxonomy ) {
		global $wpdb;
		
		if ( preg_match( '/^webcomic\d+_(storyline|character)$/', $taxonomy ) ) {
			if ( isset( $_POST[ 'webcomic_detach' ], self::$config[ 'terms' ][ $id ] ) ) {
				delete_post_meta( self::$config[ 'terms' ][ $id ][ 'image' ], '_wp_attachment_context', $taxonomy );
				
				unset( self::$config[ 'terms' ][ $id ][ 'image' ] );
				
				update_option( 'webcomic_options', self::$config );
			}
			
			if ( isset( $_FILES[ 'webcomic_image' ] ) and !is_wp_error( $attachment = media_handle_upload( 'webcomic_image', 0, array( 'context' => $taxonomy ) ) ) ) {
				if ( isset( self::$config[ 'terms' ][ $id ][ 'image' ] ) ) {
					delete_post_meta( self::$config[ 'terms' ][ $id ][ 'image' ], '_wp_attachment_context', $taxonomy );
				}
				
				self::$config[ 'terms' ][ $id ][ 'image' ] = $attachment;
				
				update_option( 'webcomic_options', self::$config );
			}
			
			if ( false !== strpos( $taxonomy, '_storyline' ) ) {
				$terms = get_terms( $taxonomy, array( 'get' => 'all', 'cache_domain' => "webcomic_delete_term{$id}" ) );
				$count = array();
				
				foreach ( $terms as $term ) {
					if ( empty( $count[ $term->parent ] ) ) {
						$count[ $term->parent ] = 0;
					}
					
					$wpdb->update( $wpdb->terms, array( 'term_group' => $count[ $term->parent ] ), array( 'term_id' => $term->term_id ) );
					
					$count[ $term->parent ]++;
				}
			}
		}
	}
	
	/** Upload media and set storyline group when creating a new term.
	 * 
	 * @param integer $id The created term ID.
	 * @param integer $tax_id The created term taxonomy ID.
	 * @param string $taxonomy The created term taxonomy.
	 * @uses Webcomic::$config
	 * @hook create_term
	 */
	public function create_term( $id, $tax_id, $taxonomy ) {
		global $wpdb;
		
		if ( preg_match( '/^webcomic\d+_(storyline|character)$/', $taxonomy ) ) {
			if ( isset( $_FILES[ 'webcomic_image' ] ) and !is_wp_error( $attachment = media_handle_upload( 'webcomic_image', 0, array( 'context' => $taxonomy ) ) ) ) {
				self::$config[ 'terms' ][ $id ][ 'image' ] = $attachment;
				
				update_option( 'webcomic_options', self::$config );
			}
			
			if ( false !== strpos( $taxonomy, '_storyline' ) ) {
				$terms = get_terms( $taxonomy, array(
					'order'      => 'DESC',
					'orderby'    => 'term_group',
					'child_of'   => ( isset( $_POST[ 'parent' ] ) and 0 < $_POST[ 'parent' ] ) ? $_POST[ 'parent' ] : 0,
					'hide_empty' => false
				) );
				
				foreach ( $terms as $k => $v ) {
					if ( $id === ( integer ) $v->term_id ) {
						unset( $terms[ $k ] );
						break;
					}
				}
				
				if ( $terms ) {
					$term = array_shift( $terms );
					
					$wpdb->update( $wpdb->terms, array( 'term_group' => $term->term_group + 1 ), array( 'term_id' => $id ) );
				}
			}
		}
	}
	
	/** Remove term meta and normalize storyline groups.
	 * 
	 * @param integer $id The deleted term ID.
	 * @param integer $tax_id The deleted term taxonomy ID.
	 * @param string $taxonomy The deleted term taxonomy.
	 * @uses Webcomic::$config
	 * @hook delete_term
	 */
	public function delete_term( $id, $tax_id, $taxonomy ) {
		global $wpdb;
		
		if ( 'webcomic_language' === $taxonomy ) {
			foreach ( self::$config[ 'collections' ] as $k => $v ) {
				if ( false !== ( $key = array_search( $id, $v[ 'transcripts' ][ 'languages' ] ) ) ) {
					unset( self::$config[ 'collections' ][ $k ][ 'transcripts' ][ 'languages' ][ $key ] );
				}
			}
			
			update_option( 'webcomic_options', self::$config );
		} elseif ( preg_match( '/^webcomic\d+_(storyline|character)$/', $taxonomy ) ) {
			if ( isset( self::$config[ 'terms' ][ $id ] ) ) {
				delete_post_meta( self::$config[ 'terms' ][ $id ][ 'image' ], '_wp_attachment_context' );
				
				unset( self::$config[ 'terms' ][ $id ] );
				
				update_option( 'webcomic_options', self::$config );
			}
			
			if ( false !== strpos( $taxonomy, '_storyline' ) ) {
				$terms = get_terms( $taxonomy, array( 'get' => 'all', 'cache_domain' => "webcomic_delete_term{$id}" ) );
				$count = array();
				
				foreach ( $terms as $term ) {
					if ( empty( $count[ $term->parent ] ) ) {
						$count[ $term->parent ] = 0;
					}
					
					$wpdb->update( $wpdb->terms, array( 'term_group' => $count[ $term->parent ] ), array( 'term_id' => $term->term_id ) );
					
					$count[ $term->parent ]++;
				}
			}
		}
	}
	
	/** Register and enqueue scripts.
	 * 
	 * @uses Webcomic::$url
	 * @hook admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();
		
		if ( preg_match( '/^edit-webcomic\d+_(storyline|character)$/', $screen->id ) ) {
			wp_register_script( 'webcomic-admin-terms', self::$url . '-/js/admin-terms.js', array( 'jquery' ) );
			
			wp_enqueue_script( 'webcomic-admin-terms' );
		}
	}
	
	/** Force sorting storylines by term_group in the admin dashboard.
	 * 
	 * @param array $args The get_terms arguments array.
	 * @param array $taxonomies The get_terms taxonomies array.
	 * @return array
	 * @hook get_terms_args
	 */
	public function get_terms_args( $args, $taxonomies ) {
		if ( 1 === count( $taxonomies ) and preg_match( '/^webcomic\d+_storyline$/', $taxonomies[ 0 ] ) ) {
			$args[ 'orderby' ] = 'term_group';
		}
		
		return $args;
	}
	
	/** Render file field for term imagery on the add term form.
	 * 
	 * We have to use a small amount of Javascript to disable the AJAX
	 * submission and ensure any files are actually uploaded.
	 * 
	 * @filter integer webcomic_upload_size_limit Filters the maximum allowed upload size for cover and avatar uploads. Defaults to the value returned by `wp_max_upload_size`.
	 * @param string $taxonomy The add term form taxonomy.
	 * @hook (webcomic\d+)_storyline_add_form_fields, (webcomic\d+)_character_add_form_fields
	 */
	public function add_form_fields( $taxonomy ) {
		$sizes       = array( 'KB', 'MB', 'GB' );
		$storyline   = strstr( $taxonomy, '_storyline' );
		$upload_size = apply_filters( 'webcomic_upload_size_limit', wp_max_upload_size() );
	
		for ( $u = -1; $upload_size > 1024 && $u < count( $sizes ) - 1; $u++ ) {
			$upload_size /= 1024;
		}
	
		if ( $u < 0 ) {
			$upload_size = $u = 0;
		} else {
			$upload_size = ( integer ) $upload_size;
		}
		?>
		<div class="form-field">
			<?php
				if ( is_multisite() and !is_upload_space_available() ) {
					printf( '<p>%s</p>', sprintf( __( 'Sorry, you have filled your storage quota (%s MB)', 'webcomic' ), get_space_allowed() ) );
				} else {
			?>
			<input type="hidden" name="max_file_size" value="<?php echo apply_filters( 'webcomic_upload_size_limit', wp_max_upload_size() ); ?>">
			<label>
				<?php $storyline ? _e( 'Cover', 'webcomic' ) : _e( 'Avatar', 'webcomic' ); ?>
				<input type="file" name="webcomic_image" id="webcomic_image">
			</label>
			<p><?php $storyline ? printf( __( 'The cover is a representative image that can be displayed on your site. Covers are uploaded to the Media Library. Maximum upload file size: %1$s%2$s', 'webcomic' ), $upload_size, $sizes[ $u ] ) : printf( __( 'The avatar is a representative image that can be displayed on your site. Avatars are uploaded to the Media Library. Maximum upload file size: %1$s%2$s', 'webcomic' ), $upload_size, $sizes[ $u ] ); ?></p>
		</div>
		<?php }
	}
	
	/** Render file field for term imagery on the edit form.
	 * 
	 * We have to use a small amount of Javascript to disable the AJAX
	 * submission and ensure any files are actually uploaded.
	 * 
	 * @filter integer webcomic_upload_size_limit Filters the maximum allowed upload size for cover and avatar uploads. Defaults to the value returned by `wp_max_upload_size`.
	 * @param object $term The current term object.
	 * @param string $taxonomy The taxonomy of the current term.
	 * @hook (webcomic\d+)_storyline_edit_form_fields, (webcomic\d+)_character_edit_form_fields
	 */
	public function edit_form_fields( $term, $taxonomy ) {
		$sizes       = array( 'KB', 'MB', 'GB' );
		$storyline   = strstr( $taxonomy, '_storyline' );
		$upload_size = apply_filters( 'webcomic_upload_size_limit', wp_max_upload_size() );
	
		for ( $u = -1; $upload_size > 1024 && $u < count( $sizes ) - 1; $u++ ) {
			$upload_size /= 1024;
		}
	
		if ( $u < 0 ) {
			$upload_size = $u = 0;
		} else {
			$upload_size = ( integer ) $upload_size;
		}
		?>
		<tr class="form-field">
			<th><label for="webcomic_image"><?php $storyline ? _e( 'Cover', 'webcomic' ) : _e( 'Avatar', 'webcomic' ); ?></label></th>
			<td>
				<?php
					if ( $term->webcomic_image ) {
						printf( '<a href="%s">%s</a><br>',
							esc_url( add_query_arg( array( 'post' => $term->webcomic_image, 'action' => 'edit' ), admin_url( 'post.php' ) ) ),
							wp_get_attachment_image( $term->webcomic_image )
						);
					}
					
					if ( is_multisite() and !is_upload_space_available() ) {
						echo '<p>', sprintf( __( 'Sorry, you have filled your storage quota (%s MB)', 'webcomic' ), get_space_allowed() ), '</p>';
					} else {
						printf( '<input type="hidden" name="max_file_size" value="%s"><input type="file" name="webcomic_image" id="webcomic_image" style="width:auto">', apply_filters( 'webcomic_upload_size_limit', wp_max_upload_size() ) );
					}
					
					if ( $term->webcomic_image ) {
						printf( '<label><input type="checkbox" name="webcomic_detach" style="width:auto"> %s</label><br>', __( 'Detach', 'webcomic' ) );
					}
				?>
				<p class="description"><?php $storyline ? printf( __( 'The cover is a representative image that can be displayed on your site. Covers are uploaded to the Media Library. Maximum upload file size: %1$s%2$s', 'webcomic' ), $upload_size, $sizes[ $u ] ) : printf( __( 'The avatar is a representative image that can be displayed on your site. Avatars are uploaded to the Media Library. Maximum upload file size: %1$s%2$s', 'webcomic' ), $upload_size, $sizes[ $u ] ); ?></p>
			</td>
		</tr>
		<?php
	}
	
	/** Add term_group adjustment row actions for storylines.
	 * 
	 * @param array $actions The array of available actions.
	 * @param object $term The current term object.
	 * @return array
	 * @hook (webcomic\d+)_storyline_row_actions
	 */
	public function storyline_row_actions( $actions, $term ) {
		global $post_type;
		
		return array_merge( array( 'webcomic_term_group' => sprintf( '<a href="%s">&uarr;</a> <a href="%s">&darr;</a>',
			esc_html( wp_nonce_url( add_query_arg( array( 'taxonomy' => $term->taxonomy, 'post_type' => $post_type, 'tag_ID' => $term->term_id, 'webcomic_action' => 'move_term_up' ), 'edit-tags.php' ), 'webcomic_move_term' ) ),
			esc_html( wp_nonce_url( add_query_arg( array( 'taxonomy' => $term->taxonomy, 'post_type' => $post_type, 'tag_ID' => $term->term_id, 'webcomic_action' => 'move_term_down' ), 'edit-tags.php' ), 'webcomic_move_term' ) )
		) ), $actions );
	}
	
	/** Render custom taxonomy columns.
	 * 
	 * @param string $value
	 * @param string $column Name of the current column.
	 * @param integer $id Current term ID.
	 * @hook manage_(webcomic\d+)_storyline_custom_column, manage_(webcomic\d+)_character_custom_column
	 */
	public function manage_custom_column( $value, $column, $id ) {
		if ( 'webcomic_image' === $column and isset( self::$config[ 'terms' ][ $id ][ 'image' ] ) and $image = wp_get_attachment_image( self::$config[ 'terms' ][ $id ][ 'image' ] ) ) {
			echo current_user_can( 'edit_post', self::$config[ 'terms' ][ $id ][ 'image' ] ) ? sprintf( '<a href="%s">%s</a>',
				esc_url( add_query_arg( array( 'post' => self::$config[ 'terms' ][ $id ][ 'image' ], 'action' => 'edit' ), admin_url( 'post.php' ) ) ),
				$image
			) : $image;
		}
	}
	
	/** Rename the 'Posts' column and add the image column.
	 * 
	 * @param array $columns Array of of term columns.
	 * @return array
	 * @hook manage_edit-(webcomic\d+)_storyline_columns, manage_edit-(webcomic\d+)_character_columns
	 */
	public function manage_edit_columns( $columns ) {
		$screen                  = get_current_screen();
		$pre                     = array_slice( $columns, 0, 1 );
		$pre[ 'webcomic_image' ] = false !== strpos( $screen->taxonomy, '_storyline' ) ? __( 'Cover', 'webcomic' ) : __( 'Avatar', 'webcomic' );
		$columns[ 'posts' ]      = __( 'Webcomics', 'webcomic' );
		
		return array_merge( $pre, $columns );
	}
}