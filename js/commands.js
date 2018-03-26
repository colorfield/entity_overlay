(function($, Drupal) {
    /**
     * Add new command for displaying an entity overlay.
     */
    Drupal.AjaxCommands.prototype.entityOverlay = function(ajax, response, status){
        $('#entity-overlay__container').html(response.rendered_entity);
        var dialog = Drupal.dialog('#entity-overlay__container', {
            title: response.entity_title,
            width: 500 // @todo fetch from options
        });
        dialog.show();
    }
})(jQuery, Drupal);
