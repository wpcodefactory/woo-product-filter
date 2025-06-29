<style type="text/css">
	.wpfAdminMainLeftSide {
		width: 56%;
		float: left;
	}
	.wpfAdminMainRightSide {
		width: <?php echo ( empty($this->optsDisplayOnMainPage) ? 100 : 40 ); ?>%;
		float: left;
		text-align: center;
	}
	#wpfMainOccupancy {
		box-shadow: none !important;
	}
</style>
<section>
	<div class="woobewoo-item woobewoo-panel">
		<div id="containerWrapper">
			<?php esc_html_e('Main page Go here!!!!', 'woo-product-filter'); ?>
		</div>
		<div class="woobewoo-clear"></div>
	</div>
</section>
