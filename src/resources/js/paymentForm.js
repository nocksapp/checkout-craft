function hideOrShowIssuers(paymentMethod) {
    if (paymentMethod === 'ideal') {
        $('#nocks-form select[name=issuer]').show();
    } else {
        $('#nocks-form select[name=issuer]').hide();
    }
}

function init() {
    var $paymentMethod = $('#nocks-form select[name=paymentMethod]');

    $paymentMethod.change(function() {
        hideOrShowIssuers($paymentMethod.val());
    });

    hideOrShowIssuers($paymentMethod.val());
}

init();
