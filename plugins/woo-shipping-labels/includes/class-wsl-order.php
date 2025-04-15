/**
 * Add package management link to the order actions dropdown
 */
public function add_package_management_to_order_actions($actions) {
    $actions['wsl_manage_packages'] = __('Manage Shipping Packages', 'woo-shipping-labels');
    return $actions;
}

/**
 * Handle the package management action
 */
public function handle_package_management_action($order) {
    wp_redirect(admin_url('admin.php?page=woo-shipping-labels-packages'));
    exit;
} 