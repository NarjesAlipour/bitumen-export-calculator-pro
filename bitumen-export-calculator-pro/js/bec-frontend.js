jQuery(document).ready(function($) {
    $('#bec-calc-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $res = $('#bec-result');
        var qty = parseFloat($('#bec-qty').val());

        // Clear previous errors
        $('.bec-error').text('');

        var hasError = false;

        if (isNaN(qty) || qty < 100) {
            $('#bec-qty-error').text(bec_vars.min_quantity_alert);
            hasError = true;
        }
        
        if ($('#bec-dest').val() === "") {
            $('#bec-dest-error').text(bec_vars.destination_alert);
            hasError = true;
        }

        if (hasError) {
            return;
        }

        $btn.prop('disabled', true).text(bec_vars.calculating_text);
        $res.hide();

        $.post(bec_vars.ajax_url, {
            action: 'bec_calculate',
            security: bec_vars.nonce,
            qty: qty,
            type: $('#bec-type').val(),
            pack: $('#bec-pack').val(),
            dest: $('#bec-dest').val()
        }, function(response) {
            $btn.prop('disabled', false).text(bec_vars.calculate_text);
            if (response.success) {
                $res.html(response.data.html).fadeIn();
            } else {
                // For server-side errors, you might want to display them in a general error area
                // For now, we'll use an alert for unexpected server errors.
                alert(response.data);
            }
        });
    });
});
