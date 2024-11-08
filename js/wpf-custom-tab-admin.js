jQuery(document).ready(function($) {
    function initCustomFields() {
        $('.custom .crm-field').each(function() {
            // Remove any existing initialization
            if ($(this).hasClass('select4-hidden-accessible')) {
                $(this).select4('destroy');
            }
            
            // Match WP Fusion's class structure
            $(this).addClass('select4-crm-field');
            
            // Initialize select4 using WP Fusion's core method
            $(this).select4({
                allowClear: true,
                placeholder: wpf_admin.select_field,
                ajax: wpf_admin.ajax_select_field,
                width: 'resolve', // This helps with proper container sizing
                dropdownAutoWidth: true,
                minimumInputLength: 0,
                minimumResultsForSearch: 10,
                templateResult: function(result) {
                    if (!result.id) return result.text;
                    return $('<span>' + result.text + '</span>');
                }
            });
        });
    }

    // Initialize after a slight delay to ensure WP Fusion core is ready
    setTimeout(function() {
        initCustomFields();
    }, 100);

    // Re-initialize when new rows are added
    $(document).on('wpf_fields_added wpf_fields_loaded', function() {
        setTimeout(function() {
            initCustomFields();
        }, 100);
    });
}); 