jQuery( document ).ready( function ( $ ) {

	// Clearly we have JS, so remove the hidden input field marking no-js.
	$( '#wp2d_no_js' ).remove();

	let onSettingsPage = ('settings_page_wp_to_diaspora' === adminpage);

	$( '.wrap, .contextual-help-tabs-wrap' ).on( 'click', '.open-help-tab', function ( e ) {
		e.preventDefault();
		let tab = onSettingsPage ? $( this ).attr( 'data-help-tab' ) : 'wp-to-diaspora';
		let $tabLink = $( '#tab-link-' + tab );

		if ( '' !== tab && $tabLink.length ) {
			// Drop down the help window if it isn't open already.
			let $helpLink = $( '#contextual-help-link' );
			if ( 'false' === $helpLink.attr( 'aria-expanded' ) ) {
				$helpLink.click();
			}
			// Select the tab.
			$tabLink.children( 'a' ).click();
			$( 'html, body' ).animate( { scrollTop: 0 }, 'slow' );
		}
	} );

	// Tag-it
	$( '.wp2d-tags' ).tagit( {
		removeConfirmation: true
	} );

	// Initialise chosen.
	$( '.chosen' ).chosen();

	/**
	 * Make the aspect checkboxes clever, giving the 'public' aspect the power to disable all others.
	 */
	function smartAspectSelection() {
		let $allAspectCheckboxes = $( '#aspects-container' ).find( 'input[type="checkbox"]' );
		let setDisabledAttrs = function () {
			let disabled = ($allAspectCheckboxes.filter( '[value="public"]' ).removeAttr( 'disabled' ).is( ':checked' )) ? 'disabled' : null;
			$allAspectCheckboxes.not( '[value="public"]' ).attr( 'disabled', disabled );
			// We only have a 'Public' checkbox, so it can't be unchecked.
			if ( 1 === $allAspectCheckboxes.length ) {
				$allAspectCheckboxes.attr( 'checked', 'checked' );
			}
		};
		$allAspectCheckboxes.change( setDisabledAttrs );
		setDisabledAttrs();
	}

	smartAspectSelection();

	// Refresh the list of aspects and update the checkboxes.
	$( '#refresh-aspects-list' ).click( function () {
		let $refreshButton = $( this ).hide();
		let $spinner = $refreshButton.next( '.spinner' ).addClass( 'is-active' );
		let $aspectsContainer = $( '#aspects-container' );
		$aspectsContainer.find( '.error-message' ).remove();

		// Before loading the new checkboxes, disable all the current ones.
		let $aspectsCheckboxes = $aspectsContainer.find( 'input[type="checkbox"]' ).attr( 'disabled', 'disabled' );

		$.post( ajaxurl, {
			'nonce': WP2D._nonce,
			'action': 'wp_to_diaspora_update_aspects_list'
		} ).done( function ( aspects ) {
			if ( false === aspects ) {
				$aspectsContainer.append( '<p class="error-message">' + WP2D.conn_failed + ' ' + WP2D.resave_credentials + '</p>' );
				return;
			}

			// Remember the selected aspects and clear the list.
			$aspectsContainer.empty();
			let aspectsSelected = [];
			if ( $aspectsCheckboxes.length ) {
				$aspectsCheckboxes.each( function () {
					if ( this.checked ) {
						aspectsSelected.push( this.value );
					}
				} );
				$aspectsContainer.data( 'aspects-selected', aspectsSelected.join( ',' ) );
			} else {
				aspectsSelected = $aspectsContainer.data( 'aspects-selected' ).split( ',' );
			}

			// Add fresh checkboxes.
			for ( let id in aspects ) {
				if ( aspects.hasOwnProperty( id ) ) {
					let checked = (-1 !== $.inArray( id, aspectsSelected )) ? ' checked="checked"' : '';
					$aspectsContainer.append( '<label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="' + id + '"' + checked + '>' + aspects[ id ] + '</label> ' );
				}
			}

			smartAspectSelection();
		} ).fail( function () {
			$aspectsContainer.append( '<p class="error-message">' + WP2D.nonce_failure + '</p>' );
		} ).always( function () {
			$spinner.removeClass( 'is-active' );
			$refreshButton.show();
		} );
	} );

	// Refresh the list of services and update the checkboxes.
	$( '#refresh-services-list' ).click( function () {
		let $refreshButton = $( this ).hide();
		let $spinner = $refreshButton.next( '.spinner' ).addClass( 'is-active' );
		let $servicesContainer = $( '#services-container' );
		$servicesContainer.find( '.error-message' ).remove();

		// Before loading the new checkboxes, disable all the current ones.
		let $servicesCheckboxes = $servicesContainer.find( 'input[type="checkbox"]' ).attr( 'disabled', 'disabled' );

		$.post( ajaxurl, {
			'nonce': WP2D._nonce,
			'action': 'wp_to_diaspora_update_services_list'
		} ).done( function ( services ) {
			if ( false === services ) {
				$servicesContainer.append( '<p class="error-message">' + WP2D.conn_failed + ' ' + WP2D.resave_credentials + '</p>' );
				return;
			}

			// Remember the selected services and clear the list.
			$servicesContainer.empty();
			let servicesSelected = [];
			if ( $servicesCheckboxes.length ) {
				$servicesCheckboxes.each( function () {
					if ( this.checked ) {
						servicesSelected.push( this.value );
					}
				} );
				$servicesContainer.data( 'services-selected', servicesSelected.join( ',' ) );
			} else {
				servicesSelected = $servicesContainer.data( 'services-selected' ).split( ',' );
			}

			// Add fresh checkboxes if we have connected services.
			if ( services.length > 0 ) {
				for ( let id in services ) {
					if ( services.hasOwnProperty( id ) ) {
						let checked = (-1 !== $.inArray( id, servicesSelected )) ? ' checked="checked"' : '';
						$servicesContainer.append( '<label><input type="checkbox" name="wp_to_diaspora_settings[services][]" value="' + id + '"' + checked + '>' + services[ id ] + '</label> ' );
					}
				}
			} else {
				$servicesContainer.append( WP2D.no_services_connected );
			}
		} ).fail( function () {
			$servicesContainer.append( '<p class="error-message">' + WP2D.nonce_failure + '</p>' );
		} ).always( function () {
			$spinner.removeClass( 'is-active' );
			$refreshButton.show();
		} );
	} );


	if ( onSettingsPage ) {
		// Check the pod connection status.
		let $pcs = $( '#pod-connection-status' );
		let $spinner = $pcs.next( '.spinner' ).addClass( 'is-active' ).show();
		$pcs.parent().attr( 'title', WP2D.conn_testing );

		let $msg = $( '#wp2d-message' );
		let show_debug = (typeof $msg.attr( 'data-debugging' ) !== 'undefined');
		$.post( ajaxurl, {
			'nonce': WP2D._nonce,
			'action': 'wp_to_diaspora_check_pod_connection_status',
			'debugging': show_debug
		} ).done( function ( status ) {
			if ( typeof status.success === 'undefined' ) {
				return;
			}

			// After testing the connection, mark the "Setup" tab appropriately
			// and output an error message if the connection failed.
			let debug_msg = (show_debug) ? '<strong>Debug</strong><textarea rows="5" style="width:100%" readonly>' + status.data.debug + '</textarea>' : '';

			if ( status.success ) {
				$msg.addClass( 'updated' );
				$pcs.parent().attr( 'title', WP2D.conn_successful );
				$pcs.addClass( 'dashicons-yes' )
					.css( 'color', '#008000' )
					.show();
			} else {
				$msg.addClass( 'error' );
				$pcs.parent().attr( 'title', WP2D.conn_failed );
				$pcs.addClass( 'dashicons-no' )
					.css( 'color', '#800000' )
					.show();
			}

			// Show the message panel if the connection failed or debug is enabled.
			if ( show_debug || !status.success ) {
				$msg.html( '<p>' + status.data.message + '</p>' )
					.append( debug_msg )
					.show();
			}
		} ).always( function () {
			$spinner.removeClass( 'is-active' ).hide();
		} );

		// Confirmation when resetting to default settings.
		$( '#reset-defaults' ).click( function () {
			return confirm( WP2D.sure_reset_defaults );
		} );
	}

	// Enable all checkboxes on save, as disabled ones don't get saved.
	$( '#submit, #submit-defaults, #save-post, #publish' ).click( function () {
		$( '#aspects-container' ).find( 'input[type="checkbox"]' ).removeAttr( 'disabled' );
	} );

	(function ( wp ) {
		if ( typeof wp.data === 'undefined' ) {
			return;
		}

		let editPost = wp.data.select( 'core/edit-post' ),
			lastIsSaving = false;

		wp.data.subscribe( function () {
			let isSaving = editPost.isSavingMetaBoxes();
			if ( isSaving !== lastIsSaving && !isSaving ) {
				lastIsSaving = isSaving;

				// Remove any old notice that might still be there.
				wp.data.dispatch( 'core/notices' ).removeNotice( 'wp2d' );

				// Get the URL of the diaspora* post and create an admin notice.
				$.get( ajaxurl, {
					'nonce': WP2D._nonce,
					'action': 'wp_to_diaspora_get_post_history',
					'post_id': wp.data.select( 'core/editor' ).getCurrentPostId()
				} ).done( function ( response ) {
					if ( typeof response.success === 'undefined' ) {
						return;
					}

					let notice = {
						id: 'wp2d',
						isDismissible: true,
					}

					if ( response.success ) {
						$( '#post-to-diaspora' ).prop( 'checked', null );

						// Update the "Already posted to diaspora*" link with the new URL.
						$( '#diaspora-post-url' )
							.prop( 'href', response.data.action.url )
							.parent().show();

						// Attach link to diaspora* post on success.
						notice.actions = [ {
							url: response.data.action.url,
							label: response.data.action.label
						} ];
					}

					wp.data.dispatch( 'core/notices' ).createNotice(
						response.success ? 'success' : 'error',
						response.data.message,
						notice
					);
				} );
			}
			lastIsSaving = isSaving;
		} );
	})( window.wp );
} );
