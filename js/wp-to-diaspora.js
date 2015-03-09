jQuery(document).ready(function ($) {

  // Tabbed container for post types.
  if ( $('.settings_page_wp_to_diaspora').length ) {
    var $tabs = $('ul.tabs li');
    var $contents = $('.tab-content');

    $tabs.first().addClass('current');
    $contents.first().addClass('current');

    $tabs.click(function(){
      var tab_id = $(this).attr('data-tab');

      $tabs.removeClass('current');
      $contents.removeClass('current');

      $(this).addClass('current');
      $('#'+tab_id).addClass('current');
    });
  }

  // Tag-it
  $('.wp2dtags').tagit({
    removeConfirmation: true
  });

  // Refresh the list of pods and repopulate the autocomplete list.
  $('#refresh-pod-list').click(function() {
    var $refreshButton = $(this).hide();
    var $spinner = $refreshButton.next('.spinner').show();

    $.post(ajaxurl, { 'action': 'wp_to_diaspora_update_pod_list' }, function(pods) {
      // Empty the current pod list and repopulate it.
      var $podList = $('#pod-list').empty();
      pods.forEach(function(pod) {
        $podList.append( '<option data-secure="' + pod.secure + '" value="' + pod.domain + '"></option>' );
      });

      $spinner.hide();
      $refreshButton.show();
    });
  });


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
    var $spinner = $refreshButton.next('.spinner').show();
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
          $aspectsContainer.append( '<label><input type="checkbox" name="wp_to_diaspora_settings[aspects][]" value="' + id + '"' + checked + '>' + aspects[id] + '</label> ' )
        }
      }
      smartAspectSelection();

      $spinner.hide();
      $refreshButton.show();
    });
  });

  // Refresh the list of services and update the checkboxes.
  $('#refresh-services-list').click(function() {
    var $refreshButton = $(this).hide();
    var $spinner = $refreshButton.next('.spinner').show();
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

      // Add fresh checkboxes.
      for(var id in services) {
        if(services.hasOwnProperty(id)) {
          var checked = ( -1 !== $.inArray( id, servicesSelected ) ) ? ' checked="checked"' : '';
          $servicesContainer.append( '<label><input type="checkbox" name="wp_to_diaspora_settings[services][]" value="' + id + '"' + checked + '>' + services[id] + '</label> ' )
        }
      }

      $spinner.hide();
      $refreshButton.show();
    });
  });

  // Enable all checkboxes on save, as disabled ones don't get saved.
  $('#submit, #save-post, #publish').click(function() {
    $('#aspects-container input[type="checkbox"]').removeAttr('disabled');
  });
});
