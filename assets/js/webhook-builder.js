/* global wpforms_builder, wpf, WPForms */

const UAWPFWebhooks = window.UAWPFWebhooks || (function (document, window, $) {

    const app = {
        $holder: $('.wpforms-panel-content-section-uawpf-webhooks'),

        init() {
            const observer = new MutationObserver(() => {
                if (document.querySelector('.wpforms-panel-content-section-uawpf-webhooks')) {
                    app.ready();
                    observer.disconnect();
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });
        },

        ready() {
            app.events();
        },

        /**
         * Register JS events.
         */
        events() {
            $('#wpforms-builder')
                .on('wpformsSaved', app.requiredFields.init)
                .on('wpformsSettingsBlockAdded', app.webhookBlockAdded)
                .on('wpformsFieldMapTableAddedRow', app.fieldMapTableAddRow)
                .on('change', '#wpforms-panel-field-settings-uawpf-webhooks_enable', app.webhooksToggle)
                .on('input', '.wpforms-field-map-table .http-key-source', app.updateNameAttr);

            app.$holder
                .on('change', '.wpforms-field-map-table .wpforms-field-map-select', app.changeSourceSelect)
                .on('click', '.wpforms-field-map-table .wpforms-field-map-custom-value-close', app.closeCustomValue)
                .on('click keydown', '.wpforms-field-map-table .wpforms-field-map-is-secure.disabled input', app.returnFalseHandler);
        },

        /**
         * Reset fields when adding a new webhook block.
         */
        webhookBlockAdded(event, $block) {
            if (!$block.length || $block.data('block-type') !== 'uawpf-webhook') return;
            $block.find('.wpforms-field-map-table .wpforms-field-map-custom-value-close').trigger('click');
        },

        /**
         * Reset fields when adding a new table row for mapping.
         */
        fieldMapTableAddRow(event, $block, $choice) {
            if (!$block.length || $block.data('block-type') !== 'uawpf-webhook' || !$choice.length) return;

            $choice.find('.wpforms-field-map-is-secure-checkbox').val('1');
            $choice.find('.wpforms-field-map-custom-value-close').trigger('click');
        },

        /**
         * Toggle webhook settings visibility.
         */
        webhooksToggle() {
            app.$holder
                .find('.wpforms-builder-settings-block-uawpf-webhook, .uawpf-webhooks-add')
                .toggleClass('hidden', !$(this).is(':checked'));
        },

        /**
         * Update key source names in field map table.
         */
        updateNameAttr() {
            const $this = $(this),
                  value = $this.val();

            if (value === undefined || value === null) return;

            const $row = $this.closest('tr');
            let $targets = $row.find('.wpforms-field-map-select');
            const name = $targets.data('name');

            if ($row.find('td.field').hasClass('field-is-custom-value')) {
                $targets = $row.find('.wpforms-field-map-custom-value, .wpforms-field-map-is-secure-checkbox');
            }

            $targets.each((idx, field) => {
                const newName = name + $(field).data('suffix');
                $(field).attr('name', newName.replace('{source}', value.replace(/[^a-zA-Z0-9._-]/g, '')));
            });
        },

        /**
         * Handle "Add Custom Value" source selection.
         */
        changeSourceSelect() {
            const $row = $(this).closest('tr'),
                  isCustomValue = this.value === 'custom_value';

            if (isCustomValue) {
                $(this).attr('name', '');
                $row.find('td.field').toggleClass('field-is-custom-value', true);
                $row.find('.http-key-source').trigger('input');
            }
        },

        /**
         * Close "Custom Value" field.
         */
        closeCustomValue(event) {
            event.preventDefault();

            const $row = $(this).closest('tr');
            $row.find('td.field').removeClass('field-is-custom-value');
            $row.find('.wpforms-field-map-select').prop('selectedIndex', 0);
            $row.find('.wpforms-field-map-is-secure').removeClass('disabled');
            $row.find('.wpforms-field-map-is-secure-checkbox').attr('name', '').prop('checked', false);
            $row.find('.wpforms-field-map-custom-value').attr('name', '').prop('readonly', false).val('');
            $row.find('.insert-smart-tag-dropdown').addClass('closed');
            WPForms.Admin.Builder.SmartTags.reinitWidgets($row);
        },

        /**
         * Prevent click/keydown on disabled inputs.
         */
        returnFalseHandler() {
            return false;
        },

        /**
         * Required fields validation logic.
         */
        requiredFields: {
            hasErrors: false,
            isNotified: false,

            init() {
                const $blocks = app.$holder.find('.wpforms-builder-settings-block-uawpf-webhook');
                if (!$blocks.length || $blocks.hasClass('hidden')) return;

                app.requiredFields.isNotified = false;
                $blocks.each(app.requiredFields.check);
            },

            check() {
                app.requiredFields.hasErrors = false;

                $(this).find('input.wpforms-required, select.wpforms-required').each(function () {
                    const $field = $(this),
                          value = $field.val();

                    if (_.isEmpty(value) || ($field.hasClass('wpforms-required-url') && !wpf.isURL(value))) {
                        $field.addClass('wpforms-error');
                        app.requiredFields.hasErrors = true;
                    } else {
                        $field.removeClass('wpforms-error');
                    }
                });

                app.requiredFields.notify();
            },

            notify() {
                if (app.requiredFields.hasErrors && !app.requiredFields.isNotified) {
                    $.alert({
                        title: wpforms_builder.heads_up,
                        content: wpforms_builder.webhook_required_flds,
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

                    app.requiredFields.isNotified = true;
                }
            }
        }
    };

    return app;

})(document, window, jQuery);

UAWPFWebhooks.init();
