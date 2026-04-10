<?php
/**
 * Product Filter by WBW - Options Settings Tab Content
 *
 * @version 3.1.7
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<section class="woobewoo-bar topBtnsArea">
	<ul class="woobewoo-bar-controls">
		<li title="<?php echo esc_attr__('Save all options', 'woo-product-filter'); ?>">
			<button class="button button-primary" id="wpfSettingsSaveBtn" data-toolbar-button>
				<span>
					<?php esc_html_e('Save changes', 'woo-product-filter'); ?>
				</span>
				<svg class="woobewoo-ms-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M14.1667 2.5H4.16667C3.24167 2.5 2.5 3.25 2.5 4.16667V15.8333C2.5 16.75 3.24167 17.5 4.16667 17.5H15.8333C16.75 17.5 17.5 16.75 17.5 15.8333V5.83333L14.1667 2.5ZM15.8333 15.8333H4.16667V4.16667H13.475L15.8333 6.525V15.8333ZM10 10C8.61667 10 7.5 11.1167 7.5 12.5C7.5 13.8833 8.61667 15 10 15C11.3833 15 12.5 13.8833 12.5 12.5C12.5 11.1167 11.3833 10 10 10ZM5 5H12.5V8.33333H5V5Z" fill="white" />
				</svg>
			</button>
		</li>
	</ul>
	<div class="woobewoo-clear"></div>
</section>
<section>
	<form id="wpfSettingsForm" class="wpfInputsWithDescrForm">
		<div class="woobewoo-item woobewoo-panel">
			<div id="containerWrapper">
				<table class="form-table">
					<?php foreach ($this->options as $optCatKey => $optCatData) { ?>
						<?php if (isset($optCatData['opts']) && !empty($optCatData['opts'])) { ?>
							<?php foreach ($optCatData['opts'] as $optKey => $opt) { ?>
								<?php
								$htmlType = isset($opt['html']) ? $opt['html'] : false;
								if (empty($htmlType)) {
									continue;
								}
								$htmlOpts = array('value' => $opt['value'], 'attrs' => 'data-optkey="' . $optKey . '"');
								if (in_array($htmlType, array('selectbox', 'selectlist')) && isset($opt['options'])) {
									if (is_callable($opt['options'])) {
										$htmlOpts['options'] = call_user_func($opt['options']);
									} elseif (is_array($opt['options'])) {
										$htmlOpts['options'] = $opt['options'];
									}
								}
								if (isset($opt['pro']) && !empty($opt['pro'])) {
									$htmlOpts['attrs'] .= ' class="wpfProOpt"';
								}
								?>
								<tr
									<?php if (isset($opt['connect']) && $opt['connect']) { ?>
									data-connect="<?php echo esc_attr($opt['connect']); ?>" class="woobewoo-hidden"
									<?php } ?>>
									<th scope="row" class="col-w-30perc">
										<?php echo esc_html($opt['label']); ?>
										<?php if (!empty($opt['changed_on'])) { ?>
											<br />
											<span class="description">
												<?php
												if ($opt['value']) {
													/* translators: %s: label */
													echo esc_html(sprintf(__('Turned On %s', 'woo-product-filter'), DateWpf::_($opt['changed_on'])));
												} else {
													/* translators: %s: label */
													echo esc_html(sprintf(__('Turned Off %s', 'woo-product-filter'), DateWpf::_($opt['changed_on'])));
												}
												?>
											</span>
										<?php } ?>
										<?php if (isset($opt['pro']) && !empty($opt['pro'])) { ?>
											<span class="wpfProOptMiniLabel">
												<a href="<?php echo esc_url($opt['pro']); ?>" target="_blank">
													<a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a>
												</a>
											</span>
										<?php } ?>
									</th>
									<td class="col-w-1perc">
										<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr($opt['desc']); ?>"></i>
									</td>
									<td class="col-w-1perc">
										<?php HtmlWpf::$htmlType('opt_values[' . $optKey . ']', $htmlOpts); ?>
									</td>
									<td class="col-w-60perc">
										<div id="wpfFormOptDetails_<?php echo esc_attr($optKey); ?>" class="wpfOptDetailsShell">
											<?php
											if (isset($opt['add_sub_opts']) && !empty($opt['add_sub_opts'])) {
												if (is_string($opt['add_sub_opts'])) {
													HtmlWpf::echoEscapedHtml($opt['add_sub_opts']);
												} elseif (is_callable($opt['add_sub_opts'])) {
													HtmlWpf::echoEscapedHtml(call_user_func_array($opt['add_sub_opts'], array($this->options)));
												}
											}
											?>
										</div>
									</td>
								</tr>
							<?php } ?>
						<?php } ?>
					<?php } ?>
				</table>
				<div class="woobewoo-clear"></div>
			</div>
		</div>
		<?php HtmlWpf::hidden('mod', array('value' => 'options')); ?>
		<?php HtmlWpf::hidden('action', array('value' => 'saveGroup')); ?>
	</form>
</section>