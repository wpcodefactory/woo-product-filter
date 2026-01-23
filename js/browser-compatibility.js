/**
 * Product Filter by WBW - Browser Compatibility
 *
 * @version 3.0.8
 * @since   3.0.8
 *
 * @author  woobewoo
 */

(function () {
	const floatingWrapper = document.querySelector('.wpfFloatingWrapper');
	if (!floatingWrapper) {
		return;
	}

	function hideFloat() {
		floatingWrapper.style.display = 'none';
	}

	document.addEventListener('click', function (e) {
		if (
			e.target.closest('.wpfFilterButton') ||
			e.target.closest('.wpfClearButton')
		) {
			hideFloat();
		}
	});

	window.addEventListener('beforeunload', hideFloat);
})();
