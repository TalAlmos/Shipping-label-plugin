<?php
/**
 * Package Management Tab Content
 */

if (!defined('WPINC')) {
    die;
}

// Get existing packages (user-defined and carrier-specific)
$user_packages = get_option('wsl_user_packages', array());
$carrier_packages = get_option('wsl_carrier_packages', array(
    'fedex' => array(
        'small_box' => array(
            'name' => 'FedEx Small Box',
            'weight' => 5,
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'description' => 'FedEx Small Box (30x20x10cm, max 5kg)',
        ),
        'large_box' => array(
            'name' => 'FedEx Large Box',
            'weight' => 20,
            'length' => 50,
            'width' => 50,
            'height' => 30,
            'description' => 'FedEx Large Box (50x50x30cm, max 20kg)',
        ),
    ),
    'ups' => array(
        'small_box' => array(
            'name' => 'UPS Small Box',
            'weight' => 5,
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'description' => 'UPS Small Box (30x20x10cm, max 5kg)',
        ),
        'large_box' => array(
            'name' => 'UPS Large Box',
            'weight' => 20,
            'length' => 50,
            'width' => 50,
            'height' => 30,
            'description' => 'UPS Large Box (50x50x30cm, max 20kg)',
        ),
    ),
    'dhl' => array(
        'small_box' => array(
            'name' => 'DHL Small Box',
            'weight' => 5,
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'description' => 'DHL Small Box (30x20x10cm, max 5kg)',
        ),
        'large_box' => array(
            'name' => 'DHL Large Box',
            'weight' => 20,
            'length' => 50,
            'width' => 50,
            'height' => 30,
            'description' => 'DHL Large Box (50x50x30cm, max 20kg)',
        ),
    ),
    'usps' => array(
        'small_box' => array(
            'name' => 'USPS Small Box',
            'weight' => 5,
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'description' => 'USPS Small Box (30x20x10cm, max 5kg)',
        ),
        'large_box' => array(
            'name' => 'USPS Large Box',
            'weight' => 20,
            'length' => 50,
            'width' => 50,
            'height' => 30,
            'description' => 'USPS Large Box (50x50x30cm, max 20kg)',
        ),
    ),
));

// Get enabled packages
$enabled_packages = get_option('wsl_enabled_packages', array());
?>

<div class="wrap">
    <h2><?php _e('Package Management', 'woo-shipping-labels'); ?></h2>

    <!-- Add/Edit User-Defined Package Form -->
    <div class="wsl-package-form">
        <h3><?php _e('Add/Edit User-Defined Package', 'woo-shipping-labels'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('wsl_save_package', 'wsl_package_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="package_name"><?php _e('Package Name', 'woo-shipping-labels'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="package_name" name="package_name" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="package_weight"><?php _e('Weight (kg)', 'woo-shipping-labels'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="package_weight" name="package_weight" step="0.01" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="package_dimensions"><?php _e('Dimensions (L x W x H in cm)', 'woo-shipping-labels'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="package_length" name="package_length" placeholder="Length" step="0.01" required>
                        <input type="number" id="package_width" name="package_width" placeholder="Width" step="0.01" required>
                        <input type="number" id="package_height" name="package_height" placeholder="Height" step="0.01" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="package_description"><?php _e('Description', 'woo-shipping-labels'); ?></label>
                    </th>
                    <td>
                        <textarea id="package_description" name="package_description" rows="3"></textarea>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary" name="wsl_save_package">
                    <?php _e('Save Package', 'woo-shipping-labels'); ?>
                </button>
            </p>
        </form>
    </div>

    <!-- List of Enabled Packages -->
    <div class="wsl-package-list">
        <h3><?php _e('Enabled Packages', 'woo-shipping-labels'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('wsl_save_enabled_packages', 'wsl_enabled_packages_nonce'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Enable', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Name', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Weight (kg)', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Dimensions (L x W x H)', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Description', 'woo-shipping-labels'); ?></th>
                        <th><?php _e('Type', 'woo-shipping-labels'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- User-Defined Packages -->
                    <?php foreach ($user_packages as $id => $package) : ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="enabled_packages[]" value="user_<?php echo esc_attr($id); ?>" <?php checked(in_array("user_$id", $enabled_packages)); ?>>
                            </td>
                            <td><?php echo esc_html($package['name']); ?></td>
                            <td><?php echo esc_html($package['weight']); ?></td>
                            <td><?php echo esc_html($package['length'] . ' x ' . $package['width'] . ' x ' . $package['height']); ?></td>
                            <td><?php echo esc_html($package['description']); ?></td>
                            <td><?php _e('User-Defined', 'woo-shipping-labels'); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Carrier Packages -->
                    <?php foreach ($carrier_packages as $carrier => $packages) : ?>
                        <?php foreach ($packages as $id => $package) : ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="enabled_packages[]" value="<?php echo esc_attr($carrier . '_' . $id); ?>" <?php checked(in_array("$carrier_$id", $enabled_packages)); ?>>
                                </td>
                                <td><?php echo esc_html($package['name']); ?></td>
                                <td><?php echo esc_html($package['weight']); ?></td>
                                <td><?php echo esc_html($package['length'] . ' x ' . $package['width'] . ' x ' . $package['height']); ?></td>
                                <td><?php echo esc_html($package['description']); ?></td>
                                <td><?php echo esc_html(ucfirst($carrier)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary" name="wsl_save_enabled_packages">
                    <?php _e('Save Enabled Packages', 'woo-shipping-labels'); ?>
                </button>
            </p>
        </form>
    </div>
</div>