/** Update collection slug previews. */
function webcomic_slug_preview( url ) {
	jQuery( function( $ ) {
		$( '#webcomic_slugs_archive,#webcomic_slugs_webcomic,#webcomic_slugs_storyline,#webcomic_slugs_character' ).on( 'change', function() {
			$.getJSON( url, {
				slug: $( this ).val(),
				preview: '#' + $( this ).attr( 'id' ),
				collection: $( '[name=webcomic_collection]' ).val(),
				webcomic_admin_ajax: 'WebcomicConfig::ajax_slug_preview'
			}, function( data ) {
				$( data.container ).val( data.slug ).siblings( '.description' ).children( 'b' ).html( data.slug )
			} );
		} );
	} );
}

/** Enable or disable print defaults. */
function webcomic_commerce_defaults( url ) {
	jQuery( function( $ ) {
		$( '#webcomic_commerce_business' ).on( 'change', function() {
			$.getJSON( url, {
				business: $( this ).val(),
				webcomic_admin_ajax: 'WebcomicConfig::ajax_commerce_defaults'
			}, function ( data ) {
				if ( data.clear ) {
					$( '#webcomic_commerce_prints,#webcomic_commerce_originals' ).removeAttr( 'disabled' ).siblings( 'span' ).removeClass( 'description' );
				} else {
					$( '#webcomic_commerce_prints,#webcomic_commerce_originals' ).removeAttr( 'checked' ).attr( 'disabled', true ).siblings( 'span' ).addClass( 'description' );
				}
			} );
		} ).trigger( 'change' );
	} );
}

/** Update Twitter authorized account. */
function webcomic_twitter_account( url ) {
	jQuery( function( $ ) {
		$( '#webcomic_twitter_consumer_key,#webcomic_twitter_consumer_secret' ).on( 'change', function() {
			$.get( url, {
				consumer_key: $( '#webcomic_twitter_consumer_key' ).val(),
				consumer_secret: $( '#webcomic_twitter_consumer_secret' ).val(),
				collection: $( '[name=webcomic_collection]' ).val(),
				webcomic_admin_ajax: 'WebcomicConfig::ajax_twitter_account'
			}, function ( data ) {
				$( '#webcomic_twitter_account' ).html( data );
			} );
		} );
	} );
}

/** Toggle collection setting sections. */
jQuery( function( $ ) {
	$( '.wrap h3' ).css( {
		'border-top': 'thin solid #dfdfdf',
		color: '#21759b',
		cursor: 'pointer',
		padding: '.5em 0 0'
	} ).next().hide();
	
	$( '.wrap h3:first' ).next().show();
	
	
	$( '.wrap h3' ).on( 'mouseenter', function() { $( this ).css( 'color', '#d54e21' ); } );
	$( '.wrap h3' ).on( 'mouseleave', function() { $( this ).css( 'color', '#21759b' ); } );
	
	$( '.wrap h3' ).on( 'click', function() {
		$( this ).next().toggle();
	} );
} );