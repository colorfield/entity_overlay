(function ($, Drupal) {

  Drupal.behaviors.entityOverlayBehavior = {
    attach: function (context, settings) {

      /**
       * Replaces the placeholder value (0)
       * for the entity by the actual entity id.
       *
       * @param entityId
       */
      function getOverlayPath(entityId) {
        var overlayPath = settings.overlay_path;
        // replace lastIndexOf 0
        return overlayPath.replace(/(.*)0(.*)$/, "$1" + entityId + "$2")
      }

      /**
       * Displays the overlay based on the list element.
       *
       * @param element
       */
      function displayOverlay(element) {
        $element = element;
        var data = $element.closest('.entity_overlay_list_item').data();
        if(data.hasOwnProperty('entityOverlayId')) {
          $element.attr('href', '/' + getOverlayPath(data.entityOverlayId));
          $element.magnificPopup({
            type: 'ajax',
            overflowY: 'scroll',
            mainClass: 'node-overlay',
            closeBtnInside: false,
            showCloseBtn: false,
            closeOnContentClick: false,
            closeOnBgClick: true,
            callbacks: {
              ajaxContentAdded: function () {
                this.content.find('.button-close').on('click', function (e) {
                    e.preventDefault();
                    $.magnificPopup.close();
                });
              }
            }
          });
        }
      }

      // @todo review selector construction and portability
      $(context).find($('.' + settings.list_selector + ' .node-readmore a')).once('entityOverlayBehavior').each(function (key, value) {
        displayOverlay($(this));
      });

      $(context).find($('.' + settings.list_selector + ' [rel=bookmark]')).once('entityOverlayBehavior').each(function (key, value) {
        displayOverlay($(this));
      });

    }

  };

})(jQuery, Drupal);
