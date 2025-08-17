(function ($) {
    'use strict';

    var AdminSettings = {
        initSettingsUI: function () {
            $('.settings-section').each(function () {
                $(this).find('.description').hide();
            });
        },

        bindEventHandlers: function () {
            var self = this;

            $('.settings-section .toggle-description').on('click', function (e) {
                e.preventDefault();
                $(this).closest('.settings-section').find('.description').slideToggle();
            });

            $('#plugin-settings-form').on('submit', function (e) {
                e.preventDefault();
                self.saveSettings($(this));
            });
        },

        saveSettings: function ($form) {
            var self = this;
            var data = $form.serialize();
            data += '&action=save_plugin_settings';
            data += '&_ajax_nonce=' + (window.pluginSettingsVars && window.pluginSettingsVars.nonce ? window.pluginSettingsVars.nonce : '');

            if (typeof window.ajaxurl === 'undefined' || !window.ajaxurl) {
                self.displayNotification('AJAX URL is missing. Cannot save settings.', 'error');
                return;
            }

            $.post(window.ajaxurl, data)
                .done(function (response) {
                    if (response && response.success) {
                        self.displayNotification(response.data && response.data.message ? response.data.message : 'Settings saved.', 'success');
                    } else {
                        self.displayNotification(response && response.data && response.data.message ? response.data.message : 'Error saving settings.', 'error');
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    var errMsg = 'An unexpected error occurred.';
                    if (textStatus || errorThrown) {
                        errMsg += ' ' + (textStatus ? textStatus : '') + (errorThrown ? ': ' + errorThrown : '');
                    }
                    if (jqXHR && jqXHR.responseText) {
                        errMsg += ' Server says: ' + jqXHR.responseText;
                    }
                    self.displayNotification(errMsg, 'error');
                });
        },

        displayNotification: function (message, type) {
            var $notice = $('<div class="notice is-dismissible"></div>')
                .addClass(type === 'success' ? 'notice-success' : 'notice-error');

            $('<p></p>').text(message).appendTo($notice);

            $('.wrap h1').after($notice);
            $notice.fadeIn();

            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        },

        init: function () {
            this.initSettingsUI();
            this.bindEventHandlers();
        }
    };

    $(document).ready(function () {
        AdminSettings.init();
    });

})(jQuery);