<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importers_View Class
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importers_View extends WooBeWoo_PF_View {

	public function getTabContent() {
		$module  = $this->getModule();
		$modPath = $module->getModPath();
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-importers', $modPath . 'css/admin.importers.css', array( 'woobewoo-pf-wbw-admin' ) );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-importers', $modPath . 'js/admin.importers.js', array(), false, true );
		WooBeWoo_PF_Frame::_()->addJSVar(
			'woobewoo-pf-admin-importers',
			'wpfImportersData',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'woobewoo-pf-save-nonce' ),
				'filters'  => WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabUrl( 'woofilters' ),
				'i18n'     => array(
					'working'   => __( 'Working', 'woo-product-filter' ),
					'theirs'    => __( 'Their filter', 'woo-product-filter' ),
					'ours'      => __( 'Ours (draft)', 'woo-product-filter' ),
					'mapped'    => __( 'Mapped', 'woo-product-filter' ),
					'unmapped'  => __( 'Not mapped', 'woo-product-filter' ),
					'setting'   => __( 'Their setting', 'woo-product-filter' ),
					'value'     => __( 'Value', 'woo-product-filter' ),
					'ours_col'  => __( 'Ours', 'woo-product-filter' ),
					'reason'    => __( 'Why not', 'woo-product-filter' ),
					'open'      => __( 'Open in the builder', 'woo-product-filter' ),
					'failed'    => __( 'Something went wrong. Try again.', 'woo-product-filter' ),
					'imported'  => __( 'Imported as a draft', 'woo-product-filter' ),
					'blocks'    => __( 'blocks', 'woo-product-filter' ),
					'nothing'   => __( 'Nothing to map in this set.', 'woo-product-filter' ),
				'kicker'    => __( 'Switch from %s', 'woo-product-filter' ),
				'name'      => __( 'Name of the new filter', 'woo-product-filter' ),
				'nameHelp'  => __( 'Shown in your Filters list. Shoppers do not see it.', 'woo-product-filter' ),
				'keep'      => __( 'Keep', 'woo-product-filter' ),
				'theirName' => __( 'Their filter', 'woo-product-filter' ),
				'becomes'   => __( 'Becomes', 'woo-product-filter' ),
				'titleCol'  => __( 'Title shoppers see', 'woo-product-filter' ),
				'lookCol'   => __( 'Shown as', 'woo-product-filter' ),
				'looks'     => array(
					'checkbox'     => __( 'Tick boxes', 'woo-product-filter' ),
					'radio'        => __( 'Pick one (radio)', 'woo-product-filter' ),
					'dropdown'     => __( 'Dropdown', 'woo-product-filter' ),
					'mul_dropdown' => __( 'Dropdown, several', 'woo-product-filter' ),
					'buttons'      => __( 'Buttons', 'woo-product-filter' ),
					'colors'       => __( 'Colour swatches', 'woo-product-filter' ),
					'text'         => __( 'Text links', 'woo-product-filter' ),
				),
				'fixed'     => __( 'As in their filter', 'woo-product-filter' ),
				'notCome'   => __( 'Settings that do not come over', 'woo-product-filter' ),
				'allCome'   => __( 'Every setting comes over.', 'woo-product-filter' ),
				'existing'  => __( 'You already imported this filter (#%d). Going on creates a second copy.', 'woo-product-filter' ),
				'openOld'   => __( 'Open the first one', 'woo-product-filter' ),
				'preview'   => __( 'Preview side by side', 'woo-product-filter' ),
				'create'    => __( 'Create the filter and continue', 'woo-product-filter' ),
				'noBlocks'  => __( 'Keep at least one block.', 'woo-product-filter' ),
				'scanning'  => __( 'Looking for the places their filter is shown', 'woo-product-filter' ),
				'placesH'   => __( 'Their filter is shown here. Ours takes its place where you tick.', 'woo-product-filter' ),
				'noPlaces'  => __( 'We did not find their filter on any page or sidebar. Choose where ours shows:', 'woo-product-filter' ),
				'autoShop'  => __( 'Show ours automatically above the products on the shop and category pages', 'woo-product-filter' ),
				'view'      => __( 'View', 'woo-product-filter' ),
				'edit'      => __( 'Edit', 'woo-product-filter' ),
				'deact'     => __( 'Turn off %s after the switch (you can turn it back on, or undo)', 'woo-product-filter' ),
				'deactMore' => __( 'It still has other filters you have not switched. Turning it off removes those from your shop too.', 'woo-product-filter' ),
				'switchNow' => __( 'Switch now', 'woo-product-filter' ),
				'later'     => __( 'Keep it as a draft, I will place it myself', 'woo-product-filter' ),
				'doneH'     => __( 'Your shop now uses the new filter', 'woo-product-filter' ),
				'draftH'    => __( 'The filter is saved as a draft', 'woo-product-filter' ),
				'draftP'    => __( 'Nothing changed in your shop. Place it from the builder or with its shortcode when you are ready.', 'woo-product-filter' ),
				'changed'   => __( 'places changed', 'woo-product-filter' ),
				'pluginOff' => __( 'The old plugin is turned off.', 'woo-product-filter' ),
				'checkIt'   => __( 'Open a page and try it, like a shopper would.', 'woo-product-filter' ),
				'undo'      => __( 'Undo this switch', 'woo-product-filter' ),
				'undoAsk'   => __( 'Put the old filter back everywhere and turn the old plugin back on?', 'woo-product-filter' ),
				'skipped'   => __( 'Not changed', 'woo-product-filter' ),
				),
			)
		);
		$rivals = $module->detect();
		foreach ( $rivals as $ri => $r ) {
			$m = $module->mapper( $r['slug'] );
			foreach ( $r['sets'] as $si => $s ) {
				$rivals[ $ri ]['sets'][ $si ]['imported'] = $m ? $m->importedAs( $s['id'] ) : 0;
			}
		}
		$ctrl     = $module->getController();
		$switches = array();
		foreach ( $module->getModel( 'importer_switch' )->log() as $snap ) {
			$switches[] = $ctrl->switchRow( $snap );
		}
		$this->assign( 'rivals', $rivals );
		$this->assign( 'switches', $switches );
		return parent::getContent( 'importersTab' );
	}
}
