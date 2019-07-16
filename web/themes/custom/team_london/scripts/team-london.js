/**
 * Team London Core JS.
 */

(($, Drupal) => {
  Drupal.behaviors.teamLondonCore = {
    attach: () => {
      // Set boolean for if on search page.
      const onSearchPage =
        $(window.location)
          .attr("href")
          .indexOf("/search-opportunities") > -1;
      // Remove session storage if not on search page.
      if (!onSearchPage) {
        sessionStorage.removeItem("mapViewMode");
      }
    }
  };
})(jQuery, Drupal);
