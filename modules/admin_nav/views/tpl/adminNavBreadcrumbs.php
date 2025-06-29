<?php
	$countBreadcrumbs = count($this->breadcrumbsList);
?>
<nav id="woobewoo-breadcrumbs" class="woobewoo-breadcrumbs <?php DispatcherWpf::doAction('adminBreadcrumbsClassAdd'); ?>">
	<?php DispatcherWpf::doAction('beforeAdminBreadcrumbs'); ?>
	<?php foreach ($this->breadcrumbsList as $i => $crumb) { ?>
		<a class="woobewoo-breadcrumb-el" href="<?php echo esc_url($crumb['url']); ?>"><?php echo esc_html($crumb['label']); ?></a>
		<?php if ( $i < ( $countBreadcrumbs - 1 ) ) { ?>
			<span class="breadcrumbs-separator"></span>
		<?php } ?>
	<?php } ?>
	<?php DispatcherWpf::doAction('afterAdminBreadcrumbs'); ?>
</nav>
