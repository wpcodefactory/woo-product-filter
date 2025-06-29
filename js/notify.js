(function($) {
"use strict";
	$.sNotify = function(options) {
		if (!this.length) {
			return this;
		}

		var $wrapper = $('<div class="s-notify">').css({
			position: 'fixed',
			display: 'none',
			right: '1.7em',
			top: '3.3em',
			padding: '1em',
			'background-color': 'white',
			'box-shadow': '0px 0px 6px 0px rgba(0,0,0,0.1)'
		});

		$wrapper.wrapInner(this);
		$wrapper.appendTo('body');

		if (options.icon) {
			$('<i/>').addClass(options.icon).appendTo($wrapper);
		}

		if (options.content) {
			$('<div class="notify-content"></div>').css('display', 'inline-block').wrapInner(options.content).appendTo($wrapper);
		}

		setTimeout(function() {
			$wrapper.fadeIn();
			if (options.delay) {
				setTimeout(function() {
					$wrapper.fadeOut(function() {
						$wrapper.remove();
					});
				}, options.delay);
			}
		}, 200);

		return $.extend($wrapper, {
			close: function(timeout) {
				setTimeout(function() {
					$wrapper.fadeOut(function() {
						$wrapper.remove();
					});
				}, timeout || '0');
			},
			update: function(content, icon) {
				this.find('.notify-content').empty().append(content);
				if (icon) {
					this.find('i').removeClass().addClass(icon);
				}
				return this;
			}
		});
	};

})(jQuery);
