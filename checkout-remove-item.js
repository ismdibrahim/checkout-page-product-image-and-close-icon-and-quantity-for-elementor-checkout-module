jQuery(function ($) {

    function ed_position_remove_icons() {
        $('.checkout-remove-item').each(function () {
            var $icon = $(this);

            if ($icon.data('ed-positioned')) {
                return;
            }

            var $row = $icon.closest('tr.cart_item');
            var $imageCell = $row.find('td.product-image').first();

            if ($imageCell.length) {
                $imageCell.css('position', 'relative');
                $imageCell.prepend($icon);
                $icon.data('ed-positioned', true);
            }
        });
    }

    ed_position_remove_icons();

    $(document.body).on('updated_checkout', function () {
        ed_position_remove_icons();
    });

    $(document.body).on('click', '.checkout-remove-item', function (e) {
        e.preventDefault();

        var $link = $(this);
        var key = $link.data('cart_item_key');
        var $row = $link.closest('tr.cart_item');

        $row.css('opacity', '0.5');

        $.post(edCheckoutRemove.ajax_url, {
            action: 'ed_remove_checkout_item',
            cart_item_key: key,
            security: edCheckoutRemove.nonce
        }).done(function (response) {
            if (response.success) {
                if (response.data.cart_empty) {
                    window.location.href = edCheckoutRemove.cart_url;
                    return;
                }
                $(document.body).trigger('update_checkout');
            } else {
                $row.css('opacity', '1');
            }
        });
    });
	
	// checkout page button
	
	function updateQtyAjax($wrapper, key, qty) {
        $wrapper.addClass('is-updating');

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                action: 'ed_update_checkout_qty',
                cart_item_key: key,
                quantity: qty,
                security: ed_checkout_qty_vars.nonce
            },
            success: function (response) {
                $wrapper.removeClass('is-updating');
                console.log('Qty update response:', response);
                if (response && response.success) {
                    $(document.body).trigger('update_checkout');
                } else {
                    console.error('Qty update failed:', response);
                }
            },
            error: function (xhr, status, err) {
                $wrapper.removeClass('is-updating');
                console.error('AJAX error:', status, err, xhr.responseText);
            }
        });
    }

    function clampQty($input) {
        var min = parseInt($input.attr('min'), 10) || 1;
        var max = parseInt($input.attr('max'), 10) || Infinity;
        var val = parseInt($input.val(), 10);
        if (isNaN(val) || val < min) val = min;
        if (val > max) val = max;
        $input.val(val);
        return val;
    }

    function toggleButtonStates($wrapper) {
        var $input = $wrapper.find('.checkout-item-qty');
        var min = parseInt($input.attr('min'), 10) || 1;
        var max = parseInt($input.attr('max'), 10) || Infinity;
        var val = parseInt($input.val(), 10);
        $wrapper.find('.qty-btn-minus').prop('disabled', val <= min);
        $wrapper.find('.qty-btn-plus').prop('disabled', val >= max);
    }

    function initStepperStates() {
        $('.checkout-qty-wrapper').each(function () {
            toggleButtonStates($(this));
        });
    }
    initStepperStates();
    $(document.body).on('updated_checkout', initStepperStates);

    $(document).on('click', '.qty-btn-plus, .qty-btn-minus', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        var $wrapper = $btn.closest('.checkout-qty-wrapper');
        var $input = $wrapper.find('.checkout-item-qty');
        var key = $btn.data('cart_item_key');

        var current = clampQty($input);
        var min = parseInt($input.attr('min'), 10) || 1;
        var max = parseInt($input.attr('max'), 10) || Infinity;

        var val = $btn.hasClass('qty-btn-plus') ? current + 1 : current - 1;
        if (val < min) val = min;
        if (val > max) val = max;

        $input.val(val);
        toggleButtonStates($wrapper);
        updateQtyAjax($wrapper, key, val);
    });

    $(document).on('change', '.checkout-item-qty', function () {
        var $input = $(this);
        var $wrapper = $input.closest('.checkout-qty-wrapper');
        var key = $input.data('cart_item_key');
        var val = clampQty($input);
        toggleButtonStates($wrapper);
        updateQtyAjax($wrapper, key, val);
    });
	
	
});
