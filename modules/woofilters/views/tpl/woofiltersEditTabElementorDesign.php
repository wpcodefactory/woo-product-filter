<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Elementor Design
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

$labelPro = apply_filters( 'woobewoo_pf_pro_label', ' - Pro feature' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$formLink = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabUrl( WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getView()->getCode() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="woobewoo-plugin containerWrapperElementor" id="containerWrapperElementorDesign">
	<form id="wpfFiltersEditForm" data-href="<?php echo esc_attr( $formLink ); ?>">
		<?php
		WooBeWoo_PF_Html::hidden(
			'settings',
			array(
				'value' => '',
			)
		);
		?>
		<div class="woobewoo_row">
			<div class="col-md-12">
				<div class="woobewoo-input-group" id="wpfChooseFiltersBlock" data-no-preview="1">
					<?php WooBeWoo_PF_Html::hidden( 'title', array( 'value' => '' ) ); ?>
				</div>
			</div>
		</div>
		<div class="wpfMainTabsContainer">
			<div class="woobewoo_row">
				<div class="col-md-12 wpfFiltersTabContents">
					<?php require 'woofiltersEditTabDesign.php'; ?>
					<div class="wpfHidden">
						<?php require 'woofiltersEditTabOptions.php'; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		WooBeWoo_PF_Html::hidden(
			'settings[filters][order]',
			array(
				'value' => '',
			)
		);
		WooBeWoo_PF_Html::hidden(
			'settings[filters][preselect]',
			array(
				'value' => '',
			)
		);
		?>
		<?php WooBeWoo_PF_Html::hidden( 'mod', array( 'value' => 'woofilters' ) ); ?>
		<?php WooBeWoo_PF_Html::hidden( 'action', array( 'value' => 'woobewoo_pf_save' ) ); ?>
		<?php WooBeWoo_PF_Html::hidden( 'id', array( 'value' => '' ) ); ?>
	</form>
	<div class="woobewoo-clear"></div>
</div>
<?php
