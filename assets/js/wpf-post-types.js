jQuery(document).ready(function ($) {
  $(".sync-post-type-fields").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var post_type = $button.data("post_type");
    var nonce = $button.data("nonce");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "wpf_sync_post_type_fields",
        post_type: post_type,
        _ajax_nonce: nonce,
      },
      success: function (response) {
        if (response.success) {
          alert("Fields synced successfully.");
        } else {
          alert("Error syncing fields: " + response.data);
        }
      },
      error: function (response) {
        alert("AJAX error: " + response.statusText);
      },
    });
  });
});
