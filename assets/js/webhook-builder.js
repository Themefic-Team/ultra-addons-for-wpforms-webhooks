const WebhooksManager = (function (document, window, $) {
    const selector = '.wpforms-panel-content-section-uawpf-webhooks';

    const app = {
        $holder: $(selector),

        init() {
            this.waitForElement(selector, this.ready);
        },

        waitForElement(selector, callback) {
            const observer = new MutationObserver(() => {
                if ($(selector).length) {
                    callback.call(app);
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        },

        ready() {
            this.bindEvents();

            jQuery(function($) {
                const $builder = $('#wpforms-builder');

                // Override the click behavior only for our custom button.
                $builder.on('click', '.uawpf-webhooks-add', function(e) {
                    e.preventDefault();

                    if (typeof app !== 'undefined' && typeof app.settingsBlockAdd === 'function') {
                        app.settingsBlockAdd($(this));
                    } else if (typeof WPForms !== 'undefined' && WPForms.Admin?.Builder?.app?.settingsBlockAdd) {
                        WPForms.Admin.Builder.app.settingsBlockAdd($(this));
                    } else {
                        console.warn('settingsBlockAdd not found — check WPForms builder context.');
                    }
                });
            });

        },

        bindEvents() {
            $('#wpforms-builder')
                .on('wpformsSaved', this.requiredFields.init)
                .on('wpformsSettingsBlockAdded', this.handleBlockAdded)
                .on('wpformsFieldMapTableAddedRow', this.handleRowAdded)
                .on('change', '#wpforms-panel-field-settings-uawpf-webhooks_enable', this.toggleWebhooks)
                .on('input', '.wpforms-field-map-table .http-key-source', this.updateFieldNames);

            this.$holder
                .on('change', '.wpforms-field-map-table .uawpf-field-map-select', this.handleSourceChange)
                .on('click', '.wpforms-field-map-table .uawpf-field-map-custom-value-close', this.closeCustomValue)
                .on('click keydown', '.wpforms-field-map-table .uawpf-field-map-is-secure.disabled input', this.preventAction);
        },

        handleBlockAdded(event, $block) {
            if ($block?.data('block-type') !== 'uawpf-webhook') return;
            $block.find('.uawpf-field-map-custom-value-close').trigger('click');
        },

        handleRowAdded(event, $block, $choice) {
            if ($block?.data('block-type') !== 'uawpf-webhook' || !$choice.length) return;
            $choice.find('.uawpf-field-map-is-secure-checkbox').val('1');
            $choice.find('.uawpf-field-map-custom-value-close').trigger('click');
        },

        toggleWebhooks() {
            app.$holder
                .find('.wpforms-builder-settings-block-uawpf-webhook, .uawpf-webhooks-add')
                .toggleClass('hidden', !$(this).is(':checked'));
        },

        updateFieldNames() {
            const $row = $(this).closest('tr');
            let $fields = $row.find('.uawpf-field-map-select');
            const name = $fields.data('name');

            if ($row.find('td.field').hasClass('uawpf-field-is-custom-value')) {
                $fields = $row.find('.uawpf-field-map-custom-value, .uawpf-field-map-is-secure-checkbox');
            }

            const cleanVal = $(this).val()?.replace(/[^a-zA-Z0-9._-]/g, '') || '';
            $fields.each((_, field) => {
                const suffix = $(field).data('suffix');
                $(field).attr('name', name + suffix.replace('{source}', cleanVal));
            });
        },

        handleSourceChange() {
            const $row = $(this).closest('tr');
            const isCustom = this.value === 'custom_value';

            if (isCustom) {
                $(this).attr('name', '');
                $row.find('td.field').addClass('uawpf-field-is-custom-value');
                $row.find('.http-key-source').trigger('input');
            }
        },

        closeCustomValue(event) {
            event.preventDefault();
            const $row = $(this).closest('tr');

            $row.find('td.field').removeClass('uawpf-field-is-custom-value');
            $row.find('.uawpf-field-map-select').prop('selectedIndex', 0);
            $row.find('.uawpf-field-map-is-secure').removeClass('disabled');
            $row.find('.uawpf-field-map-is-secure-checkbox').attr('name', '').prop('checked', false);
            $row.find('.uawpf-field-map-custom-value').attr('name', '').prop('readonly', false).val('');
            $row.find('.insert-smart-tag-dropdown').addClass('closed');

            if (WPForms?.Admin?.Builder?.SmartTags) {
                WPForms.Admin.Builder.SmartTags.reinitWidgets($row);
            }
        },

        preventAction() {
            return false;
        },

        requiredFields: {
            hasErrors: false,
            alertShowed: false,

            init() {
                const $blocks = app.$holder.find('.wpforms-builder-settings-block-uawpf-webhook');
                if (!$blocks.length || $blocks.hasClass('hidden')) return;

                this.alertShowed = false;
                $blocks.each(this.check);
            },

            check() {
                app.requiredFields.hasErrors = false;

                $(this).find('input.wpforms-required, select.wpforms-required').each(function () {
                    const $field = $(this);
                    const value = $field.val();

                    if (_.isEmpty(value) || ($field.hasClass('wpforms-required-url') && !wpf.isURL(value))) {
                        $field.addClass('wpforms-error');
                        app.requiredFields.hasErrors = true;
                    } else {
                        $field.removeClass('wpforms-error');
                    }
                });

                app.requiredFields.showAlert();
            },

            showAlert() {
                if (!this.hasErrors || this.alertShowed) return;

                $.alert({
                    title: wpforms_builder.heads_up,
                    content: wpforms_builder.uawpf_webhook_required_flds,
                    icon: 'fa fa-exclamation-circle',
                    type: 'orange',
                    buttons: {
                        confirm: {
                            text: wpforms_builder.ok,
                            btnClass: 'btn-confirm',
                            keys: ['enter']
                        }
                    }
                });

                this.alertShowed = true;
            }
        }
    };

    return app;
})(document, window, jQuery);

WebhooksManager.init();



