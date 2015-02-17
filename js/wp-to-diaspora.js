jQuery(document).ready(function ($) {

    $('ul.tabs li').first().addClass("current");
    $('.tab-content').first().addClass("current");

    $('ul.tabs li').click(function(){
        var tab_id = $(this).attr('data-tab');

        $('ul.tabs li').removeClass('current');
        $('.tab-content').removeClass('current');

        $(this).addClass('current');
        $("#"+tab_id).addClass('current');
    })

});