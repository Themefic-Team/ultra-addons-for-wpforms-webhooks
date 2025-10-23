// (function($) {
// 	'use strict';

// 	$(document).on('click', '.wpforms-webooks-add', function(e) {
// 		e.preventDefault();
// 		var $btn = $(this);
// 		var $section = $btn.closest('.wpforms-panel-content-section');
// 		var $nextId = $section.find('.next-webhook-id');
// 		var id = parseInt($nextId.val(), 10);

// 		// clone first block as template
// 		var $first = $section.find('.wpforms-builder-settings-block').first();
// 		var $clone = $first.clone();

// 		$clone.attr('data-block-id', id);
// 		$clone.find('[name]').each(function() {
// 			this.name = this.name.replace(/\[\d+\]/, '[' + id + ']');
// 			$(this).val('');
// 		});

// 		$section.find('.wpforms-webooks-add').before($clone);
// 		$nextId.val(id + 1);
// 	});

// 	$(document).on('click', '.wpforms-builder-settings-block-delete', function(e) {
// 		e.preventDefault();
// 		if (confirm(wpforms_builder.strings.webhook_delete)) {
// 			$(this).closest('.wpforms-builder-settings-block').remove();
// 		}
// 	});

// })(jQuery);

(function($){
    'use strict';

    $(function(){

        var $repeater = $('#uawpf-webhooks-repeater');
        var $toggle   = $('select[name="settings[uawpf_webhooks_enable]"]');

        // Initial state
        if ($toggle.val() === '0') {
            $repeater.hide();
        }

        // Change listener
        $toggle.on('change', function(){
            if ($(this).val() === '1') {
                $repeater.slideDown();
            } else {
                $repeater.slideUp();
            }
        });

        // Existing add/delete logic...
        var template  = $repeater.data('template') || '';
        var $nextInput = $('.next-webhook-id');
        var nextId = parseInt($nextInput.val(), 10) || 1;

        $(document).on('click', '.wpforms-webooks-add', function(e){
            e.preventDefault();
            var html = template.replace(/\{\{index\}\}/g, nextId);
            $repeater.append(html);
            nextId++;
            $nextInput.val(nextId);
        });

        $(document).on('click', '.wpforms-builder-settings-block-delete', function(e){
            e.preventDefault();
            if (confirm(UAWPFWebhooks.i18n_delete)) {
                $(this).closest('.uawpf-webhook-block').remove();
            }
        });

        $(document).on('click', '.wpforms-builder-settings-block-toggle', function(e){
            e.preventDefault();
            $(this).closest('.uawpf-webhook-block').find('.wpforms-builder-settings-block-content').toggle();
        });

        $(document).on('input', '.wpforms-builder-settings-block-name-edit input[type=text]', function(){
            var val = $(this).val();
            $(this).closest('.uawpf-webhook-block').find('.wpforms-builder-settings-block-name').text(val);
        });

    });

})(jQuery);


