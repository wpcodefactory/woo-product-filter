<?php
	$name = WPF_WP_PLUGIN_NAME;
?>
<html>
	<head>
		<title><?php esc_html_e( $name ); ?></title>
		<style type="text/css">
			.wpfDeletePage {
				position: fixed;
				margin-left: 40%;
				margin-right: auto;
				text-align: center;
				background-color: #fdf5ce;
				padding: 10px;
				margin-top: 10%;
			}
		</style>
	</head>
	<body>
		<div class="wpfDeletePage">
			<div><?php esc_html_e( $name ); ?></div>
			<?php HtmlWpf::formStart('deactivatePlugin', array('action' => $this->REQUEST_URI, 'method' => $this->REQUEST_METHOD)); ?>
			<?php
			$formData = array();
			switch ($this->REQUEST_METHOD) {
				case 'GET':
					$formData = $this->GET;
					break;
				case 'POST':
					$formData = $this->POST;
					break;
			}
			foreach ($formData as $key => $val) {
				if (is_array($val)) {
					foreach ($val as $subKey => $subVal) {
						HtmlWpf::hidden($key . '[' . $subKey . ']', array('value' => $subVal));
					}
				} else {
					HtmlWpf::hidden($key, array('value' => $val));
				}
			}
			?>
			<table width="100%">
				<tr>
					<td><?php esc_html_e('Delete Plugin Data (options, setup data, database tables, etc.)', 'woo-product-filter'); ?>:</td>
					<td><?php HtmlWpf::radiobuttons('deleteOptions', array('options' => array(esc_attr__( 'No', 'woo-product-filter' ), esc_attr__( 'Yes', 'woo-product-filter' )))); ?></td>
				</tr>
			</table>
			<?php HtmlWpf::submit('toeGo', array('value' => esc_html__('Done', 'woo-product-filter'))); ?>
			<?php HtmlWpf::formEnd(); ?>
		</div>
	</body>
</html>
