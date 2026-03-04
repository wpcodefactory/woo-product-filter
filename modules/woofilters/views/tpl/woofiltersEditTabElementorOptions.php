<?php
$isPro = FrameWpf::_()->isPro();
$labelPro = '';
if (!$isPro) {
	$adPath = FrameWpf::_()->getModule('woofilters')->getModPath() . 'img/ad/';
	$labelPro = ' Pro';
}

$formLink = FrameWpf::_()->getModule('options')->getTabUrl( FrameWpf::_()->getModule('woofilters')->getView()->getCode() );
?>

<div class="woobewoo-plugin containerWrapperElementor" id="containerWrapperElementorOptions">
	<form id="wpfFiltersEditForm" data-href="<?php echo esc_attr($formLink); ?>">
		<?php
		HtmlWpf::hidden('settings', array(
			'value' => '',
		));
		?>
		<div class="row">
			<div class="col-md-12">
				<div class="woobewoo-input-group" id="wpfChooseFiltersBlock" data-no-preview="1">
					<?php HtmlWpf::hidden( 'title', array( 'value' => '' ) ); ?>
				</div>
			</div>
		</div>
		<div class="wpfMainTabsContainer">
			<div class="row">
				<div class="col-md-12 wpfFiltersTabContents">
					<?php include 'woofiltersEditTabOptions.php'; ?>
					<div class="wpfHidden">
						<?php include 'woofiltersEditTabDesign.php'; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		HtmlWpf::hidden('settings[filters][order]', array(
			'value' => '',
		));
		HtmlWpf::hidden('settings[filters][preselect]', array(
			'value' => ''
		));
		?>
		<?php HtmlWpf::hidden( 'mod', array( 'value' => 'woofilters' ) ); ?>
		<?php HtmlWpf::hidden( 'action', array( 'value' => 'save' ) ); ?>
		<?php HtmlWpf::hidden( 'id', array( 'value' => '' ) ); ?>
	</form>
	<div class="woobewoo-clear"></div>
</div>
