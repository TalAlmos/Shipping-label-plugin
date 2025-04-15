<?php
// Redirect to the proper admin page
if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

wp_redirect(admin_url('tools.php?page=wsl-fedex-ship-test'));
exit; 