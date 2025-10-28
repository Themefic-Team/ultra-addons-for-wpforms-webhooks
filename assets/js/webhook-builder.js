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

                    if (typeof WPForms !== 'undefined' && WPForms.Admin?.Builder?.app?.settingsBlockAdd && wpforms_builder.pro) {
                        WPForms.Admin.Builder.app.settingsBlockAdd($(this));
                    } else if (typeof app.settingsBlockAdd === 'function') {
                        app.settingsBlockAdd($(this)); // Our fallback for WPForms Lite
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
        },

        handleBlockAdded(event, $block) {
            if ($block?.data('block-type') !== 'uawpf-webhook') return;
            $block.find('.uawpf-field-map-custom-value-close').trigger('click');
        },

        handleRowAdded(event, $block, $choice) {
            if ($block?.data('block-type') !== 'uawpf-webhook' || !$choice.length) return;
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
                $fields = $row.find('.uawpf-field-map-custom-value');
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
            $row.find('.uawpf-field-map-custom-value').attr('name', '').prop('readonly', false).val('');
            $row.find('.insert-smart-tag-dropdown').addClass('closed');

            if (WPForms?.Admin?.Builder?.SmartTags) {
                WPForms.Admin.Builder.SmartTags.reinitWidgets($row);
            }
        },

        settingsBlockAdd($el) {
            const nextID = Number($el.attr('data-next-id'));
            const $section = $el.closest('.wpforms-panel-content-section');
            const blockType = 'uawpf-webhook';

            // Ask user for name
            const name = prompt('Enter a name for your new Webhook connection:', '');
            if (!name) return;

            // Clone the first existing block or use template
            const $firstBlock = $section.find('.wpforms-builder-settings-block-uawpf-webhook').first();
            let $newBlock;

            if ($firstBlock.length) {
                $newBlock = $firstBlock.clone();
            } else {
                const template = $('#wpforms-uawpf-webhook-template-block').text();
                $newBlock = $(template.replace(/{CLONE}/g, nextID).replace(/CLONE/g, nextID));
            }

            // Reset fields
            $newBlock.find('input, textarea, select').each(function() {
                const $field = $(this);
                $field.val('').removeClass('wpforms-error');
            });

            $newBlock.attr('data-block-id', nextID);
            $newBlock.find('.wpforms-builder-settings-block-name-holder span').text(name);

            // Insert new block before first one or append if none
            if ($firstBlock.length) {
                $firstBlock.before($newBlock);
            } else {
                $section.find('.uawpf-webhooks-add').before($newBlock);
            }

            // Increment ID
            $el.attr('data-next-id', nextID + 1);

            // Trigger hook for consistency
            $('#wpforms-builder').trigger('wpformsSettingsBlockAdded', [$newBlock]);
        },


        requiredFields: {
            hasErrors: false,
            alertShowed: false,

            init() {
                const $blocks = app.$holder.find('.wpforms-builder-settings-block-uawpf-webhook');
                if (!$blocks.length || $blocks.hasClass('hidden')) return;

                this.alertShowed = false;
                $blocks.each(function() {
                    app.requiredFields.check.call(this);
                });
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
                console.log(wpforms_builder);
                $.alert({
                    title: 'Fields Required',
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



