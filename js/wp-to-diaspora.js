jQuery(document).ready(function ($) {

	// Clearly we have JS, so remove the hidden input field marking no-js.
	$('#wp2d_no_js').remove();

	var onSettingsPage = ('settings_page_wp_to_diaspora' === adminpage);

	$('.wrap, .contextual-help-tabs-wrap').on('click', '.open-help-tab', function(e) {
		e.preventDefault();
		var tab = (onSettingsPage) ? $(this).attr('data-help-tab') : 'wp-to-diaspora';

		if ('' !== tab && $('#tab-link-'+tab).length) {
			// Drop down the help window if it isn't open already.
			if ('false' == $('#contextual-help-link').attr('aria-expanded')) {
				$('#contextual-help-link').click();
			}
			// Select the tab.
			$('#tab-link-'+tab).children('a').click();
			$('html, body').animate({ scrollTop: 0 }, 'slow');
		}
	});

	// Tag-it
	$('.wp2dtags').tagit({
		removeConfirmation: true
	});

	// Initialise chosen.
	$('.chosen').chosen();

	/**
	 * Make the aspect checkboxes clever, giving the 'public' aspect the power to disable all others.
	 */
	function smartAspectSelection() {
		var $allAspectCheckboxes = $('#aspects-container input[type="checkbox"]');
		var setDisabledAttrs = function() {
			var disabled = ( $allAspectCheckboxes.filter('[value="public"]').removeAttr('disabled').is(':checked') ) ? 'disabled' : null;
			$allAspectCheckboxes.not('[value="public"]').attr('disabled', disabled);
			// We only have a 'Public' checkbox, so it can't be unchecked.
			if ( 1 === $allAspectCheckboxes.length ) {
				$allAspectCheckboxes.attr( 'checked', 'checked' );
			}
		};
		$allAspectCheckboxes.change(setDisabledAttrs);
		setDisabledAttrs();
	}
	smartAspectSelection();

	// Refresh the list of aspects and update the checkboxes.
	$('#refresh-aspects-list').click(function() {
		var $refreshButton = $(this).hide();
		var $spinner = $refreshButton.next('.spinner').addClass('is-active');
		var $aspectsContainer = $('#aspects-container');

		// Before loading the new checkboxes, disable all the current ones.
		var $aspectsCheckboxes = $aspectsContainer.find('input[type="checkbox"]').attr('disabled', 'disabled');

		$.post(ajaxurl, { 'action': 'wp_to_diaspora_update_aspects_list' }, function(aspects) {
			// Remember the selected aspects and clear the list.
			$aspectsContainer.empty();
			var aspectsSelected = [];
			if ( $aspectsCheckboxes.length ) {
				$aspectsCheckboxes.each(function() {
					if ( this.checked ) {
						aspectsSelected.push(this.value);
					}
				});
				$aspectsContainer.data('aspects-selected', aspectsSelected.join(','));
			} else {
				aspectsSelected = $aspectsContainer.data('aspects-selected').split(',');
			}

			// Add fresh checkboxes.
			for(var id in aspects) {
				if(aspects.hasOwnProperty(id)) {
					var checked = ( -1 !== $.inArray( id, aspectsSelected ) ) ? ' checked="checked"' : '';
					$aspectsContainer.append( '<label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="' + id + '"' + checked + '>' + aspects[id] + '</label> ' );
				}
			}
			smartAspectSelection();

			$spinner.removeClass('is-active');
			$refreshButton.show();
		});
	});

	// Refresh the list of services and update the checkboxes.
	$('#refresh-services-list').click(function() {
		var $refreshButton = $(this).hide();
		var $spinner = $refreshButton.next('.spinner').addClass('is-active');
		var $servicesContainer = $('#services-container');

		// Before loading the new checkboxes, disable all the current ones.
		var $servicesCheckboxes = $servicesContainer.find('input[type="checkbox"]').attr('disabled', 'disabled');

		$.post(ajaxurl, { 'action': 'wp_to_diaspora_update_services_list' }, function(services) {
			// Remember the selected services and clear the list.
			$servicesContainer.empty();
			var servicesSelected = [];
			if ( $servicesCheckboxes.length ) {
				$servicesCheckboxes.each(function() {
					if ( this.checked ) {
						servicesSelected.push(this.value);
					}
				});
				$servicesContainer.data('services-selected', servicesSelected.join(','));
			} else {
				servicesSelected = $servicesContainer.data('services-selected').split(',');
			}

			// Add fresh checkboxes if we have connected services.
			if ( services.length > 0 ) {
				for(var id in services) {
					if(services.hasOwnProperty(id)) {
						var checked = ( -1 !== $.inArray( id, servicesSelected ) ) ? ' checked="checked"' : '';
						$servicesContainer.append( '<label><input type="checkbox" name="wp_to_diaspora_settings[services][]" value="' + id + '"' + checked + '>' + services[id] + '</label> ' );
					}
				}
			} else {
				$servicesContainer.append(WP2DL10n.no_services_connected);
			}

			$spinner.removeClass('is-active');
			$refreshButton.show();
		});
	});


	if (onSettingsPage) {
		// Refresh the list of pods and repopulate the autocomplete list.
		$('#refresh-pod-list').click(function() {
			var $refreshButton = $(this).hide();
			var $spinner = $refreshButton.next('.spinner').addClass('is-active');

			$.post(ajaxurl, { 'action': 'wp_to_diaspora_update_pod_list' }, function(pods) {
				// Empty the current pod list and repopulate it.
				var $podList = $('#pod-list').empty();
				pods.forEach(function(pod) {
					$podList.append( '<option data-secure="' + pod.secure + '" value="' + pod.domain + '"></option>' );
				});

				$spinner.removeClass('is-active');
				$refreshButton.show();
			});
		});

		// Check the pod connection status.
		var $pcs = $('#pod-connection-status');
		var $spinner = $pcs.next('.spinner').addClass('is-active').show();
		$pcs.parent().attr('title', WP2DL10n.conn_testing);

		var $msg = $('#wp2d-message');
		var show_debug = (typeof $msg.attr('data-debugging') !== 'undefined');
		$.post(ajaxurl, {
			'action': 'wp_to_diaspora_check_pod_connection_status',
			'debugging': show_debug
		})
		.done(function(status) {
			if (typeof status.success === 'undefined') {
				return;
			}

			// After testing the connection, mark the "Setup" tab appropriately
			// and output an error message if the connection failed.
			var debug_msg = (show_debug) ? '<strong>Debug</strong><textarea rows="5" style="width:100%" readonly>' + status.data.debug + '</textarea>' : '';

			if (status.success) {
				$msg.addClass('updated');
				$pcs.parent().attr('title', WP2DL10n.conn_successful);
				$pcs.addClass('dashicons-yes')
				.css('color', '#008000')
				.show();
			} else {
				$msg.addClass('error');
				$pcs.parent().attr('title', WP2DL10n.conn_failed);
				$pcs.addClass('dashicons-no')
				.css('color', '#800000')
				.show();
			}

			// Show the message panel if the connection failed or debug is enabled.
			if (show_debug || ! status.success) {
				$msg.html('<p>' + status.data.message + '</p>')
				.append(debug_msg)
				.show();
			}
		})
		.always(function() {
			$spinner.removeClass('is-active').hide();
		});

		// Confirmation when resetting to default settings.
		$('#reset-defaults').click(function() {
			return confirm(WP2DL10n.sure_reset_defaults);
		});
	}

	// Enable all checkboxes on save, as disabled ones don't get saved.
	$('#submit, #submit-defaults, #save-post, #publish').click(function() {
		$('#aspects-container input[type="checkbox"]').removeAttr('disabled');
	});

});
