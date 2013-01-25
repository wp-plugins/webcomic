/** Warn if no selected days match the start date. */
function webcomic_generator( warning ) {
	jQuery( function( $ ) {
		$( 'form' ).on( 'submit', function() {
			var v = $( 'input[name="webcomic_generate_start"]' ).val().split( '-' ),
				d = new Date( v[ 0 ], parseInt( v[ 1 ] ) - 1, v[ 2 ] ),
				x = false,
				d1 = d2 = 0;
			
			$.each( $( 'input[name="webcomic_generate_days[]"]:checked' ), function( i, e ) {
				d1 = parseInt( $( e ).val() );
				d2 = parseInt( d.getDay() );
				
				if ( d1 === d2 || ( 7 === d1 && 0 === d2 ) ) {
					x = true;
					
					return false;
				}
			} );
			
			if ( !x ) {
				return window.confirm( warning );
			}
		} );
		
		$( '.wp-list-table tbody' ).sortable();
	} );	
}

/** Handle webcomic media reordering. */
function webcomic_media( url ) {
	jQuery( function( $ ) {
		$( 'ul' ).sortable( {
			update: function() {
				$.post( url, {
					ids: $( '[name="ids[]"]' ).serializeArray(),
					webcomic_admin_ajax: 'WebcomicMedia::ajax_order_media'
				}, function( data ) {
					var message = $( 'div p' ).html();
					
					$( 'div p' ).html( data );
					
					setTimeout( function() {
						$( 'div p' ).html( message );
					}, 5000 );
				} );
			}
		} );
	} );
}