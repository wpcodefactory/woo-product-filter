<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Admin_Nav Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Admin_Nav extends WooBeWoo_PF_Module {

	/**
	 * getBreadcrumbsList.
	 *
	 * @version 3.4.0
	 */
	public function getBreadcrumbsList() {
		$res = array(
			array(
				'label' => WPF_WP_PLUGIN_NAME,
				'url'   => WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' )->getMainLink(),
			),
		);
		// Try to get current tab breadcrumb
		$activeTab = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getActiveTab();
		if ( ! empty( $activeTab ) && 'main_page' != $activeTab ) {
			$tabs = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabs();
			if ( ! empty( $tabs ) && isset( $tabs[ $activeTab ] ) ) {
				if ( isset( $tabs[ $activeTab ]['add_bread'] ) && ! empty( $tabs[ $activeTab ]['add_bread'] ) ) {
					if ( ! is_array( $tabs[ $activeTab ]['add_bread'] ) ) {
						$tabs[ $activeTab ]['add_bread'] = array( $tabs[ $activeTab ]['add_bread'] );
					}
					foreach ( $tabs[ $activeTab ]['add_bread'] as $addForBread ) {
						$res[] = array(
							'label' => $tabs[ $addForBread ]['label'],
							'url'   => $tabs[ $addForBread ]['url'],
						);
					}
				}
				if ( 'comparison_edit' == $activeTab || 'woofilters_edit' == $activeTab ) {
					$id = (int) WooBeWoo_PF_Req::getVar( 'id', 'get' );
					if ( $id ) {
						$tabs[ $activeTab ]['url'] .= '&id=' . $id;
					}
				}
				$res[] = array(
					'label' => $tabs[ $activeTab ]['label'],
					'url'   => $tabs[ $activeTab ]['url'],
				);
				if ( 'statistwpf' == $activeTab ) {
					$statTabs       = WooBeWoo_PF_Frame::_()->getModule( 'statistwpf' )->getStatTabs();
					$currentStatTab = WooBeWoo_PF_Frame::_()->getModule( 'statistwpf' )->getCurrentStatTab();
					if ( isset( $statTabs[ $currentStatTab ] ) ) {
						$res[] = array(
							'label' => $statTabs[ $currentStatTab ]['label'],
							'url'   => $statTabs[ $currentStatTab ]['url'],
						);
					}
				}
			}
		}
		return $res;
	}
}
