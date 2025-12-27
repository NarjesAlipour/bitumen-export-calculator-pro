jQuery(document).ready(function($) {
    $('#bec-calc-btn').on('click', function() {
        $('.bec-err').text('');
        var qty = parseFloat($('#bec-qty').val()), dest = $('#bec-dest').val(), err = false;
        if (qty < 100 || qty > 1000 || isNaN(qty)) { $('#err-qty').text('Range: 100-1000 MT'); err = true; }
        if (!dest) { $('#err-dest').text('Select destination'); err = true; }
        if (err) return;

        $(this).prop('disabled', true).text('...');
        $.post(bec_vars.ajax_url, {
            action: 'bec_calculate',
            security: bec_vars.nonce,
            qty: qty,
            type: $('#bec-type').val(),
            pack: $('#bec-pack').val(),
            dest: dest
        }, function(r) {
            $('#bec-calc-btn').prop('disabled', false).text('Calculate');
            if (r.success) $('#bec-result').html(r.data.html).show();
        });
    });
});