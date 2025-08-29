<?php
/*! ============================================================================
*  UTIL NAMESPACE: All methods at the top of the Duplicator Namespace
*  =========================================================================== */
defined("ABSPATH") or die("");
?>

<script>
    Duplicator.Util.ajaxProgress = null;

    Duplicator.Util.ajaxProgressShow = function() {
        if (Duplicator.Util.ajaxProgress === null) {
            Duplicator.Util.ajaxProgress = jQuery('#dup-ajax-loader')
        }
        Duplicator.Util.ajaxProgress
            .stop(true, true)
            .css('display', 'block')
            .delay(1000)
            .animate({
                opacity: 1
            }, 500);
    }

    Duplicator.Util.ajaxProgressHide = function() {
        if (Duplicator.Util.ajaxProgress === null) {
            return;
        }
        Duplicator.Util.ajaxProgress
            .stop(true, true)
            .delay(500)
            .animate({
                opacity: 0
            }, 300, function() {
                jQuery(this).css({
                    'display': 'none'
                });
            });
    }

    Duplicator.Util.ajaxWrapper = function(ajaxData, callbackSuccess, callbackFail, options = {}) {
        let opts = jQuery.extend({
            showProgress: true, // Is true show the ajax loader, can be disabled for custom progress handling
            timeout: 30000
        }, options);

        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            timeout: opts.timeout,
            dataType: "json",
            data: ajaxData,
            beforeSend: function(xhr) {
                if (opts.showProgress) {
                    Duplicator.Util.ajaxProgressShow();
                }
            },
            success: function(result, textStatus, jqXHR) {
                var message = '';
                if (result.success) {
                    if (typeof callbackSuccess === "function") {
                        try {
                            message = callbackSuccess(result, result.data, result.data.funcData, textStatus, jqXHR);
                        } catch (error) {
                            console.error(error);
                            DupPro.addAdminMessage(error.message, 'error');
                            message = '';
                        }
                    } else {
                        message = '<?php echo esc_js(__('RESPONSE SUCCESS', 'duplicator-pro')); ?>';
                    }
                    if (message != null && String(message).length) {
                        DupPro.addAdminMessage(message, 'notice');
                    }
                } else {
                    if (typeof callbackFail === "function") {
                        try {
                            message = callbackFail(result, result.data, result.data.funcData, textStatus, jqXHR);
                        } catch (error) {
                            console.error(error);
                            message = error.message;
                        }
                    } else {
                        message = '<?php echo esc_js(__('RESPONSE ERROR!', 'duplicator-pro')); ?>' + '<br><br>' + result.data.message;
                    }
                    if (message != null && String(message).length) {
                        DupPro.addAdminMessage(message, 'error');
                    }
                }
            },
            error: function(result) {
                // Make sure the progress is hidden even if the request fails
                Duplicator.Util.ajaxProgressHide();
                DupPro.addAdminMessage(
                    <?php echo wp_json_encode(__('AJAX ERROR! <br> Ajax request error', 'duplicator-pro')); ?>,
                    'error'
                );
            },
            complete: function() {
                if (opts.showProgress) {
                    Duplicator.Util.ajaxProgressHide();
                }
            }
        });
    };

    /**
     * Get human size from bytes number.
     * Is size is -1 return unknown
     *
     * @param {size} int bytes size
     */
    Duplicator.Util.humanFileSize = function(size) {
        if (size < 0) {
            return "unknown";
        } else if (size == 0) {
            return "0";
        } else {
            var i = Math.floor(Math.log(size) / Math.log(1024));
            return (size / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + ['B', 'kB', 'MB', 'GB', 'TB'][i];
        }
    };

    Duplicator.Util.isEmpty = function(val) {
        return (val === undefined || val == null || val.length <= 0) ? true : false;
    };

    Duplicator.Util.toggleShow = function(selector, show = 'auto') {
        var element = jQuery(selector);
        if (show === 'auto') {
            show = !element.is(":visible");
        }

        if (show) {
            element.hide().removeClass('no-display');
            element.fadeIn();
        } else {
            element.fadeOut();
        }
    };


    Duplicator.Util.dynamicFormSubmit = function(url, method, params) {
        var form = jQuery('<form>', {
            method: method,
            action: url
        });

        jQuery.each(params, function(key, value) {
            form.append(jQuery('<input>', {
                'type': 'hidden',
                'name': key,
                'value': value
            }));
        });

        jQuery("body").append(form);
        form.submit();
    };
</script>