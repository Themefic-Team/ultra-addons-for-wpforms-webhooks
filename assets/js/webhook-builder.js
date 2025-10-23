/* global wpforms_builder, wpf, WPForms */

const UAWPFWebhooks = window.UAWPFWebhooks || ( function( document, window, $ ) {

	var app = {

		$holder: $( '.wpforms-panel-content-section-uawpf-webhooks' ),

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
		 *
		 * @since 1.0.0
		 */
		events() {
			$( '#wpforms-builder' )
				.on( 'wpformsSaved', app.requiredFields.init )
				.on( 'wpformsSettingsBlockAdded', app.webhookBlockAdded )
				.on( 'wpformsFieldMapTableAddedRow', app.fieldMapTableAddRow )
				.on( 'change', '#wpforms-panel-field-settings-uawpf-webhooks_enable', app.webhooksToggle )
				.on( 'input', '.wpforms-field-map-table .http-key-source', app.updateNameAttr );

			app.$holder
				.on( 'change', '.wpforms-field-map-table .wpforms-field-map-select', app.changeSourceSelect )
				.on( 'click', '.wpforms-field-map-table .wpforms-field-map-custom-value-close', app.closeCustomValue )
				.on( 'click', '.wpforms-field-map-table .wpforms-field-map-is-secure.disabled input', app.returnFalseHandler )
				.on( 'keydown', '.wpforms-field-map-table .wpforms-field-map-is-secure.disabled input', app.returnFalseHandler );
		},

		/**
		 * Resetting fields when we add a new webhook.
		 *
		 * @since 1.1.0
		 *
		 * @param {Object} event  Event object.
		 * @param {Object} $block New Webhook block.
		 */
		webhookBlockAdded( event, $block ) {
			if ( ! $block.length || 'uawpf-webhook' !== $block.data( 'block-type' ) ) {
				return;
			}

			$block.find( '.wpforms-field-map-table .wpforms-field-map-custom-value-close' ).trigger( 'click' );
		},

		/**
		 * Resetting fields when we add a new table row for mapping.
		 *
		 * @since 1.1.0
		 *
		 * @param {Object} event   Event object.
		 * @param {Object} $block  jQuery selector on Webhook block.
		 * @param {Object} $choice jQuery selector on new table row.
		 */
		fieldMapTableAddRow( event, $block, $choice ) {
			if ( ! $block.length || 'uawpf-webhook' !== $block.data( 'block-type' ) || ! $choice.length ) {
				return;
			}

			// Secure? checkbox value should always be 1.
			$choice.find( '.wpforms-field-map-is-secure-checkbox' ).val( '1' );

			// Close the "Custom Value" field.
			$choice.find( '.wpforms-field-map-custom-value-close' ).trigger( 'click' );
		},

		/**
		 * Toggle the displaying webhook settings depending on if the
		 * webhooks are enabled.
		 *
		 * @since 1.0.0
		 */
		webhooksToggle() {
			app.$holder
				.find( '.wpforms-builder-settings-block-webhook, .wpforms-uawpf-webooks-add' )
				.toggleClass( 'hidden', $( this ).not( ':checked' ) );
		},

		/**
		 * Field map table, update a key source.
		 *
		 * @since 1.0.0
		 */
		updateNameAttr() {
			const $this = $( this ),
				value = $this.val();

			if ( ! value && '' !== value ) {
				return;
			}

			const $row = $this.closest( 'tr' );
			let $targets = $row.find( '.wpforms-field-map-select' );
			const name = $targets.data( 'name' );

			if ( $row.find( 'td.field' ).hasClass( 'field-is-custom-value' ) ) {
				$targets = $row.find( '.wpforms-field-map-custom-value, .wpforms-field-map-is-secure-checkbox' );
			}

			$targets.each( function( idx, field ) {
				const newName = name + $( field ).data( 'suffix' );

				// Allow characters (lowercase and uppercase), numbers, decimal point, underscore and minus.
				$( field ).attr( 'name', newName.replace( '{source}', value.replace( /[^a-zA-Z0-9._-]/gi, '' ) ) );
			} );
		},

		/**
		 * Event-callback when the source select was changed on "Add Custom Value".
		 *
		 * @since 1.1.0
		 */
		changeSourceSelect() {
			const $row = $( this ).closest( 'tr' ),
				isCustomValue = ( this.value && this.value === 'custom_value' );

			if ( isCustomValue ) {
				$( this ).attr( 'name', '' );
				$row.find( 'td.field' ).toggleClass( 'field-is-custom-value', isCustomValue );
				$row.find( '.http-key-source' ).trigger( 'input.wpformsWebhooks' );
			}
		},

		/**
		 * Event-callback when the close button for "Custom Value" was clicked.
		 *
		 * @since 1.1.0
		 *
		 * @param {Object} event Event object.
		 */
		closeCustomValue( event ) {
			const $row = $( this ).closest( 'tr' );

			event.preventDefault();

			$row.find( 'td.field' ).removeClass( 'field-is-custom-value' );
			$row.find( '.wpforms-field-map-select' ).prop( 'selectedIndex', 0 );
			$row.find( '.wpforms-field-map-is-secure' ).removeClass( 'disabled' );
			$row.find( '.wpforms-field-map-is-secure-checkbox' )
				.attr( 'name', '' )
				.prop( 'checked', false );
			$row.find( '.wpforms-field-map-custom-value' )
				.attr( 'name', '' )
				.prop( 'readonly', false )
				.val( '' );

			// Close opened dropdown and re-init Smart Tags for the row.
			$row.find( '.insert-smart-tag-dropdown' ).addClass( 'closed' );
			WPForms.Admin.Builder.SmartTags.reinitWidgets( $row );
		},

		/**
		 * Prevent from click/keydown events.
		 *
		 * @since 1.1.0
		 *
		 * @return {boolean} False.
		 */
		returnFalseHandler() {
			return false;
		},

		/**
		 * On form save notify the user about "Required fields".
		 *
		 * @since 1.0.0
		 *
		 * @type {Object}
		 */
		requiredFields: {

			/**
			 * True if we have not filled the required fields.
			 *
			 * @since 1.0.0
			 *
			 * @type {boolean}
			 */
			hasErrors: false,

			/**
			 * We need to notify the user only once.
			 *
			 * @since 1.0.0
			 *
			 * @type {boolean}
			 */
			isNotified: false,

			/**
			 * Initialization for required fields checking.
			 *
			 * @since 1.0.0
			 */
			init() {
				const $settingBlocks = app.$holder.find( '.wpforms-builder-settings-block-webhook' );

				if (
					! $settingBlocks.length ||
					$settingBlocks.hasClass( 'hidden' )
				) {
					return;
				}

				app.requiredFields.isNotified = false;
				$settingBlocks.each( app.requiredFields.check );
			},

			/**
			 * Do the actual required fields check.
			 *
			 * @since 1.0.0
			 */
			check() {
				app.requiredFields.hasErrors = false;

				$( this ).find( 'input.wpforms-required, select.wpforms-required' ).each( function() {
					const $field = $( this ),
						value = $field.val();

					if (
						_.isEmpty( value ) ||
						( $field.hasClass( 'wpforms-required-url' ) && ! wpf.isURL( value ) )
					) {
						$field.addClass( 'wpforms-error' );
						app.requiredFields.hasErrors = true;
					} else {
						$field.removeClass( 'wpforms-error' );
					}
				} );

				// Notify user.
				app.requiredFields.notify();
			},

			/**
			 * Modal that is used for user notification.
			 *
			 * @since 1.0.0
			 */
			notify() {
				if ( app.requiredFields.hasErrors && ! app.requiredFields.isNotified ) {
					$.alert( {
						title: wpforms_builder.heads_up,
						content: wpforms_builder.webhook_required_flds,
						icon: 'fa fa-exclamation-circle',
						type: 'orange',
						buttons: {
							confirm: {
								text: wpforms_builder.ok,
								btnClass: 'btn-confirm',
								keys: [ 'enter' ],
							},
						},
					} );

					// Save that we have already showed the user.
					app.requiredFields.isNotified = true;
				}
			},
		},
	};

	// Provide access to public functions/properties.
	return app;
}( document, window, jQuery ) );

UAWPFWebhooks.init();
