<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Help_View Class
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Help_View extends WooBeWoo_PF_View {

	public function getTabContent() {
		$modPath = $this->getModule()->getModPath();
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-help', $modPath . 'js/admin.help.js', array(), false, true );
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed ) {
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-speed', $speed->getModPath() . 'css/admin.speed.css', array( 'woobewoo-pf-wbw-admin' ) );
		}
		WooBeWoo_PF_Frame::_()->addJSVar(
			'woobewoo-pf-admin-help',
			'wpfHelpData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'woobewoo-pf-save-nonce' ),
			)
		);
		$nonce = wp_create_nonce( 'woobewoo-pf-save-nonce' );
		$this->assign(
			'downloadUrl',
			add_query_arg(
				array( 'action' => 'woobewoo_pf_help_diagnostics', 'mod' => 'help', 'pl' => WPF_CODE, 'reqType' => 'ajax', 'wpfNonce' => $nonce ),
				admin_url( 'admin-ajax.php' )
			)
		);
		$this->assign( 'isPro', (bool) WooBeWoo_PF_Frame::_()->isPro() );
		$this->assign( 'proLink', WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' )->getMainLink() . '&tab=gopro' );
		return parent::getContent( 'helpTab' );
	}
}
