/**
 * Product Filter by WBW - Block JS
 *
 * @version 3.1.7
 * @since   3.1.7
 *
 * @author  woobewoo
 */

const { registerBlockType } = wp.blocks;
const { SelectControl } = wp.components;
const { __ } = wp.i18n;
const { createElement: el } = wp.element;

registerBlockType('wbw/woofilters', {
	title: __('WBW Product Filter', 'woo-product-filter'),
	icon: 'filter',
	category: 'widgets',

	attributes: {
		filter_id: {
			type: 'string',
			default: ''
		}
	},

	edit: function (props) {
		const { attributes, setAttributes } = props;
		const filters = window.wbwFiltersData?.filters || {};

		const options = [];

		Object.keys(filters).forEach(function (id) {
			options.push({
				label: filters[id],
				value: id
			});
		});

		return el(
			'div',
			{ className: 'wbw-filter-block' },

			// Title (same as widget)
			el(
				'strong',
				{ className: 'wbw-filter-title' },
				__('WBW Product Filter', 'woo-product-filter')
			),

			// Label + dropdown (always interactive)
			el(
				'div',
				{ className: 'wbw-filter-control' },
				el(SelectControl, {
					label: __('Select filter', 'woo-product-filter'),
					value: attributes.filter_id,
					options: options,
					onChange: function (value) {
						setAttributes({ filter_id: value });
					}
				})
			),

			// Message only
			attributes.filter_id
				? el(
						'p',
						{
							className: 'wbw-filter-no-preview',
							style: { marginTop: '8px', opacity: 0.7 }
						},
					)
				: null
		);
	},

	save: function () {
		return null;
	}
});
