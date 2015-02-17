jQuery(document).ready(function ($) {

  $('ul.tabs li').first().addClass('current');
  $('.tab-content').first().addClass('current');

  $('ul.tabs li').click(function(){
    var tab_id = $(this).attr('data-tab');

    $('ul.tabs li').removeClass('current');
    $('.tab-content').removeClass('current');

    $(this).addClass('current');
    $('#'+tab_id).addClass('current');
  });

  // Refresh the list of pods and repopulate the autocomplete list.
  $('#refresh_pod_list').click(function() {
    var $refreshButton = $(this).hide();
    var $spinner = $refreshButton.next('.spinner').show();

    $.post(ajaxurl, { 'action': 'wp_to_diaspora_update_pod_list' }, function(pods) {
      // Empty the current pod list and repopulate it.
      var $podList = $('#wp_to_diaspora_pod_list').empty();
      pods.forEach(function(pod) {
        $podList.append( '<option data-secure="' + pod.secure + '" value="' + pod.domain + '"></option>' );
      });

      $spinner.hide();
      $refreshButton.show();
    });
  });
});
