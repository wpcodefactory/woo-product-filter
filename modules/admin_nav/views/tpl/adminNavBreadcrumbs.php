<?php
/**
 * Product Filter by WBW - Admin Nav Breadcrumbs
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$countBreadcrumbs = count( $this->breadcrumbsList );
?>
<?php if ( $countBreadcrumbs > 0 ) : ?>
	<h2 class="woobewoo-last-breadcrumb">
		<?php echo esc_html( $this->breadcrumbsList[ $countBreadcrumbs - 1 ]['label'] ); ?>
	</h2>
<?php endif; ?>
<nav id="woobewoo-breadcrumbs" class="woobewoo-breadcrumbs <?php WooBeWoo_PF_Dispatcher::doAction( 'adminBreadcrumbsClassAdd' ); ?>">
	<?php WooBeWoo_PF_Dispatcher::doAction( 'beforeAdminBreadcrumbs' ); ?>
	<?php foreach ( $this->breadcrumbsList as $i => $crumb ) { ?>
		<a class="woobewoo-breadcrumb-el" href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
		<?php if ( $i < ( $countBreadcrumbs - 1 ) ) { ?>
			<span class="breadcrumbs-separator"></span>
		<?php } ?>
	<?php } ?>
	<?php WooBeWoo_PF_Dispatcher::doAction( 'afterAdminBreadcrumbs' ); ?>
</nav>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
