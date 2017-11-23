(function ($, Drupal) {

  Drupal.behaviors.entityOverlayBehavior = {
    attach: function (context, settings) {

      /**
       * Replaces the placeholder value (0)
       * for the node by the actual node id.
       *
       * @param nodeId
       */
      function getOverlayPath(nodeId) {
        var overlayPath = settings.overlay_path;
        // replace lastIndexOf 0
        return overlayPath.replace(/(.*)0(.*)$/, "$1" + nodeId + "$2")
      }

      // @todo review selector construction and portability
      $(context).find($('.' + settings.list_selector + ' .node-readmore a')).once('entityOverlayBehavior').each(function (key, value) {
        $this = $(this);
        // @todo review portability
        var nodeId = $this.closest('article').attr('data-history-node-id');
        $this.attr('href', '/' + getOverlayPath(nodeId));
        $this.magnificPopup({
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
      });

    }

  };

})(jQuery, Drupal);
