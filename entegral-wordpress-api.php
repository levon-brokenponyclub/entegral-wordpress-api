<?php
/**
* Plugin Name: Entegral Sync for Houzez
* Description: Syncs property listings from Houzez to Entegral Sync API
* Version: 1.0.1
* Author: Broken Pony Club
* Author URI: https://brokenpony.club
* License: GPL-2.0+
**/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Entegral_Sync_Houzez {
    private $api_base = 'https://sync.entegral.net/api/';
    private $log_file;

    public function __construct() {
        $this->log_file = plugin_dir_path(__FILE__) . 'debug.log';
        // Add admin menu and submenus
        add_action('admin_menu', array($this, 'add_dashboard_menu'));
        // Enqueue admin CSS
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_css'));
        // Schedule cron job
        add_action('houzez_entegral_sync_cron', array($this, 'sync_properties'));
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        // Handle log download
        add_action('admin_init', array($this, 'handle_log_download'));
    }

    // Enqueue admin CSS
    public function enqueue_admin_css($hook) {
        if (strpos($hook, 'entegral-sync') !== false) {
            wp_enqueue_style('entegral-sync-admin', plugins_url('assets/admin.css', __FILE__), array(), '1.0.1');
        }
    }

    // Add dashboard menu and submenus
    public function add_dashboard_menu() {
        add_menu_page(
            'Entegral Sync',
            'Entegral Sync',
            'manage_options',
            'entegral-sync',
            array($this, 'settings_page'),
            'dashicons-admin-multisite',
            3
        );
        add_submenu_page(
            'entegral-sync',
            'Settings',
            'Settings',
            'manage_options',
            'entegral-sync',
            array($this, 'settings_page')
        );
        add_submenu_page(
            'entegral-sync',
            'Sync All Listings',
            'Sync All Listings',
            'manage_options',
            'entegral-sync-sync',
            array($this, 'sync_all_listings_page')
        );
        add_submenu_page(
            'entegral-sync',
            'View All Listings',
            'View All Listings',
            'manage_options',
            'entegral-sync-listings',
            array($this, 'view_all_listings_page')
        );
        add_submenu_page(
            'entegral-sync',
            'View Listing Details',
            'View Listing Details',
            'manage_options',
            'entegral-sync-listing-details',
            array($this, 'view_listing_details_page')
        );
        add_submenu_page(
            'entegral-sync',
            'View Listing Logs',
            'View Listing Logs',
            'manage_options',
            'entegral-sync-listing-logs',
            array($this, 'view_listing_logs_page')
        );
    }

    // Sync All Listings Page
    public function sync_all_listings_page() {
        echo '<div class="entegral-sync-admin">';
        echo '<h1>Sync All Listings</h1>';
        if (isset($_POST['entegral_sync_all'])) {
            $result = $this->sync_properties();
            echo '<div class="entegral-sync-message">' . ($result ? 'All listings synced successfully!' : 'Sync failed. Check debug log.') . '</div>';
        }
        echo '<form method="post"><button type="submit" name="entegral_sync_all" class="entegral-btn-primary">Sync All Listings</button></form>';
        echo '</div>';
    }

    // View All Listings Page
    public function view_all_listings_page() {
        echo '<div class="entegral-sync-admin">';
        echo '<h1>All Listings</h1>';
        $listings = $this->get_all_listings();
        if ($listings && is_array($listings)) {
            echo '<table class="entegral-table"><thead><tr><th>ID</th><th>Status</th><th>Type</th><th>Price</th><th>Actions</th></tr></thead><tbody>';
            foreach ($listings as $listing) {
                $id = isset($listing['listingID']) ? esc_html($listing['listingID']) : '';
                $status = isset($listing['propertyStatus']) ? esc_html($listing['propertyStatus']) : '';
                $type = isset($listing['propertyType']) ? esc_html($listing['propertyType']) : '';
                $price = isset($listing['price']) ? esc_html($listing['price']) : '';
                echo '<tr>';
                echo '<td>' . $id . '</td>';
                echo '<td>' . $status . '</td>';
                echo '<td>' . $type . '</td>';
                echo '<td>' . $price . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('admin.php?page=entegral-sync-listing-details&listing_id=' . $id) . '" class="entegral-btn-secondary">Details</a> ';
                echo '<a href="' . admin_url('admin.php?page=entegral-sync-listing-logs&listing_id=' . $id) . '" class="entegral-btn-tertiary">Logs</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="entegral-sync-message">No listings found or failed to fetch listings.</div>';
        }
        echo '</div>';
    }

    // View Listing Details Page
    public function view_listing_details_page() {
        echo '<div class="entegral-sync-admin">';
        echo '<h1>Listing Details</h1>';
        $id = isset($_GET['listing_id']) ? sanitize_text_field($_GET['listing_id']) : '';
        if ($id) {
            $details = $this->get_listing($id);
            if ($details && is_array($details)) {
                echo '<div class="entegral-modal active"><div class="entegral-modal-content">';
                echo '<button class="entegral-modal-close" onclick="window.history.back();">&times;</button>';
                echo '<pre>' . esc_html(print_r($details, true)) . '</pre>';
                echo '</div></div>';
            } else {
                echo '<div class="entegral-sync-message">Failed to fetch listing details.</div>';
            }
        } else {
            echo '<div class="entegral-sync-message">No listing ID provided.</div>';
        }
        echo '</div>';
    }

    // View Listing Logs Page
    public function view_listing_logs_page() {
        echo '<div class="entegral-sync-admin">';
        echo '<h1>Listing Logs</h1>';
        $id = isset($_GET['listing_id']) ? sanitize_text_field($_GET['listing_id']) : '';
        if ($id) {
            $logs = $this->get_listing_logs($id);
            if ($logs && is_array($logs)) {
                echo '<div class="entegral-modal active"><div class="entegral-modal-content">';
                echo '<button class="entegral-modal-close" onclick="window.history.back();">&times;</button>';
                echo '<pre>' . esc_html(print_r($logs, true)) . '</pre>';
                echo '</div></div>';
            } else {
                echo '<div class="entegral-sync-message">Failed to fetch listing logs.</div>';
            }
        } else {
            echo '<div class="entegral-sync-message">No listing ID provided.</div>';
        }
        echo '</div>';
    }

    // Custom logging function
    private function log_message($message) {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        
        // Ensure log file exists and is writable
        if (!file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
            chmod($this->log_file, 0644);
        }
        
        if (is_writable($this->log_file)) {
            file_put_contents($this->log_file, $log_entry, FILE_APPEND);
        } else {
            error_log("Entegral Sync: Cannot write to log file {$this->log_file}");
        }
    }

    // Add admin menu for plugin settings
    public function add_admin_menu() {
        add_options_page(
            'Entegral Sync Settings',
            'Entegral Sync',
            'manage_options',
            'entegral-sync',
            array($this, 'settings_page')
        );
    }

    // Register plugin settings
    public function register_settings() {
        register_setting('entegral_sync_options', 'entegral_sync_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        add_settings_section(
            'entegral_sync_main',
            'Entegral API Settings',
            null,
            'entegral-sync'
        );

        add_settings_field(
            'sync_frequency',
            'Sync Frequency',
            array($this, 'sync_frequency_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'office_name',
            'Office Name',
            array($this, 'office_name_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'office_email',
            'Office Email',
            array($this, 'office_email_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'office_endpoint',
            'Office API Endpoint',
            array($this, 'office_endpoint_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'credential_endpoint',
            'Credential API Endpoint',
            array($this, 'credential_endpoint_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'master_username',
            'Master API Username',
            array($this, 'master_username_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'master_password',
            'Master API Password',
            array($this, 'master_password_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'office_username',
            'Office API Username',
            array($this, 'office_username_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'office_password',
            'Office API Password',
            array($this, 'office_password_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'source_id',
            'Source ID',
            array($this, 'source_id_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );

        add_settings_field(
            'client_office_id',
            'Client Office ID',
            array($this, 'client_office_id_callback'),
            'entegral-sync',
            'entegral_sync_main'
        );
    }

    // Sanitize settings
    public function sanitize_settings($input) {
        $new_input = array();
        if (isset($input['sync_frequency'])) {
            $new_input['sync_frequency'] = sanitize_text_field($input['sync_frequency']);
        }
        if (isset($input['office_name'])) {
            $new_input['office_name'] = sanitize_text_field($input['office_name']);
        }
        if (isset($input['office_email'])) {
            $new_input['office_email'] = sanitize_email($input['office_email']);
        }
        if (isset($input['office_endpoint'])) {
            $new_input['office_endpoint'] = sanitize_text_field(trim($input['office_endpoint'], '/'));
        }
        if (isset($input['credential_endpoint'])) {
            $new_input['credential_endpoint'] = sanitize_text_field(trim($input['credential_endpoint'], '/'));
        }
        if (isset($input['master_username'])) {
            $new_input['master_username'] = sanitize_text_field($input['master_username']);
        }
        if (isset($input['master_password'])) {
            $new_input['master_password'] = sanitize_text_field($input['master_password']);
        }
        if (isset($input['office_username'])) {
            $new_input['office_username'] = sanitize_text_field($input['office_username']);
        }
        if (isset($input['office_password'])) {
            $new_input['office_password'] = sanitize_text_field($input['office_password']);
        }
        if (isset($input['source_id'])) {
            $new_input['source_id'] = sanitize_text_field($input['source_id']);
        }
        if (isset($input['client_office_id'])) {
            $new_input['client_office_id'] = sanitize_text_field($input['client_office_id']);
        }
        return $new_input;
    }

    // Sync frequency field callback
    public function sync_frequency_callback() {
        $options = get_option('entegral_sync_settings');
        $frequency = isset($options['sync_frequency']) ? $options['sync_frequency'] : 'hourly';
        ?>
        <select name="entegral_sync_settings[sync_frequency]">
            <option value="hourly" <?php selected($frequency, 'hourly'); ?>>Hourly</option>
            <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>>Twice Daily</option>
            <option value="daily" <?php selected($frequency, 'daily'); ?>>Daily</option>
        </select>
        <?php
    }

    // Office name field callback
    public function office_name_callback() {
        $options = get_option('entegral_sync_settings');
        $office_name = isset($options['office_name']) ? $options['office_name'] : '';
        ?>
        <input type="text" name="entegral_sync_settings[office_name]" value="<?php echo esc_attr($office_name); ?>" />
        <p class="description">Enter the name of your office for Entegral API (e.g., Test Houzez Office).</p>
        <?php
    }

    // Office email field callback
    public function office_email_callback() {
        $options = get_option('entegral_sync_settings');
        $office_email = isset($options['office_email']) ? $options['office_email'] : '';
        ?>
        <input type="email" name="entegral_sync_settings[office_email]" value="<?php echo esc_attr($office_email); ?>" />
        <p class="description">Enter the office email for Entegral API (optional).</p>
        <?php
    }

    // Office endpoint field callback
    public function office_endpoint_callback() {
        $options = get_option('entegral_sync_settings');
        $office_endpoint = isset($options['office_endpoint']) ? $options['office_endpoint'] : 'CreateOffice';
        ?>
        <input type="text" name="entegral_sync_settings[office_endpoint]" value="<?php echo esc_attr($office_endpoint); ?>" />
        <p class="description">Enter the API endpoint for office creation/checking (e.g., 'CreateOffice', 'offices'). Default: 'CreateOffice'.</p>
        <?php
    }

    // Credential endpoint field callback
    public function credential_endpoint_callback() {
        $options = get_option('entegral_sync_settings');
        $credential_endpoint = isset($options['credential_endpoint']) ? $options['credential_endpoint'] : 'admin';
        ?>
        <input type="text" name="entegral_sync_settings[credential_endpoint]" value="<?php echo esc_attr($credential_endpoint); ?>" />
        <p class="description">Enter the API endpoint for generating office credentials (e.g., 'admin'). Default: 'admin'.</p>
        <?php
    }

    // Master username field callback
    public function master_username_callback() {
        $options = get_option('entegral_sync_settings');
        $master_username = isset($options['master_username']) ? $options['master_username'] : 'DEP Sync API Sandbox';
        ?>
        <input type="text" name="entegral_sync_settings[master_username]" value="<?php echo esc_attr($master_username); ?>" />
        <p class="description">Enter the master API username for generating office credentials.</p>
        <?php
    }

    // Master password field callback
    public function master_password_callback() {
        $options = get_option('entegral_sync_settings');
        $master_password = isset($options['master_password']) ? $options['master_password'] : 'f1b35a3a-88a8-41c2-a8ca-f3eff0b23cea';
        ?>
        <input type="password" name="entegral_sync_settings[master_password]" value="<?php echo esc_attr($master_password); ?>" />
        <p class="description">Enter the master API password for generating office credentials.</p>
        <?php
    }

    // Office username field callback
    public function office_username_callback() {
        $options = get_option('entegral_sync_settings');
        $office_username = isset($options['office_username']) ? $options['office_username'] : '';
        ?>
        <input type="text" name="entegral_sync_settings[office_username]" value="<?php echo esc_attr($office_username); ?>" />
        <p class="description">Enter the office-specific API username (generated via credential endpoint).</p>
        <?php
    }

    // Office password field callback
    public function office_password_callback() {
        $options = get_option('entegral_sync_settings');
        $office_password = isset($options['office_password']) ? $options['office_password'] : '';
        ?>
        <input type="password" name="entegral_sync_settings[office_password]" value="<?php echo esc_attr($office_password); ?>" />
        <p class="description">Enter the office-specific API password (generated via credential endpoint).</p>
        <?php
    }

    // Source ID field callback
    public function source_id_callback() {
        $options = get_option('entegral_sync_settings');
        $source_id = isset($options['source_id']) ? $options['source_id'] : '';
        ?>
        <input type="text" name="entegral_sync_settings[source_id]" value="<?php echo esc_attr($source_id); ?>" />
        <p class="description">Enter the Source ID for the office (generated or provided by Entegral).</p>
        <?php
    }

    // Client Office ID field callback
    public function client_office_id_callback() {
        $options = get_option('entegral_sync_settings');
        $client_office_id = isset($options['client_office_id']) ? $options['client_office_id'] : '123456';
        ?>
        <input type="text" name="entegral_sync_settings[client_office_id]" value="<?php echo esc_attr($client_office_id); ?>" />
        <p class="description">Enter the Client Office ID for credential generation (e.g., 123456).</p>
        <?php
    }

    // Handle log file download
    public function handle_log_download() {
        if (isset($_GET['entegral_download_log']) && current_user_can('manage_options')) {
            if (file_exists($this->log_file)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="entegral-sync-debug.log"');
                readfile($this->log_file);
                exit;
            } else {
                wp_die('Log file not found.');
            }
        }
    }

    // Settings page HTML
    public function settings_page() {
        ?>
        <style>
        .entegral-sync-settings {
            background: #fff;
            padding: 2rem 2rem 2rem 2rem;
            border-radius: 1.2rem;
            box-shadow: 0 2px 16px 0 rgba(8,71,84,0.08);
            max-width: 980px;
            margin: 2rem auto;
        }
        .entegral-sync-settings h1 {
            color: #084754;
            font-size: 2.2rem;
            margin-bottom: 1.4rem;
        }
        .entegral-sync-message {
            background: #def592;
            color: #084754;
            border-radius: 0.7rem;
            padding: 1.1rem 1.1rem;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .entegral-sync-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .entegral-btn-primary {
            background: #714894;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.7rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .entegral-btn-primary:hover {
            background: #5a3576;
        }
        .entegral-btn-secondary {
            background: #084754;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.7rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .entegral-btn-secondary:hover {
            background: #06323a;
        }
        .entegral-btn-tertiary {
            background: #def592;
            color: #084754;
            border: none;
            border-radius: 0.5rem;
            padding: 0.7rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .entegral-btn-tertiary:hover {
            background: #cbe87a;
        }
        </style>
        <div class="entegral-sync-settings">
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.querySelector('.entegral-sync-settings form[action="options.php"]');
            if (!form) return;
            var saveBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!saveBtn) return;
            saveBtn.style.display = 'none';
            var initial = new FormData(form);
            form.addEventListener('input', function() {
                var current = new FormData(form);
                var changed = false;
                for (var [key, value] of current.entries()) {
                    if (initial.get(key) !== value) { changed = true; break; }
                }
                saveBtn.style.display = changed ? '' : 'none';
            });
        });
        </script>
            <h1>Entegral Sync Settings</h1>
            <div class="entegral-sync-message">
                <span>✅ API connection is active. You can now sync your office, agents, and listings with Entegral.</span>
            </div>
            <form method="post" action="options.php" style="margin-bottom:1.5rem;">
                <?php
                settings_fields('entegral_sync_options');
                do_settings_sections('entegral-sync');
                submit_button('Save Settings', 'primary', '', false, array('class' => 'entegral-btn-primary'));
                ?>
            </form>
            <form method="post" class="entegral-sync-buttons">
                <input type="submit" name="test_office" class="entegral-btn-secondary" value="Test Office Connection" />
                <input type="submit" name="manual_sync" class="entegral-btn-primary" value="Sync Now" />
                <a href="<?php echo esc_url(admin_url('options-general.php?page=entegral-sync&entegral_download_log=1')); ?>" class="entegral-btn-tertiary" style="text-decoration:none;display:inline-block;">Download Debug Log</a>
            </form>
            <?php
            if (isset($_POST['test_office'])) {
                $result = $this->setup_office();
                echo '<div class="' . ($result ? 'updated' : 'error') . '"><p>' . ($result ? 'Office connection successful!' : 'Office connection failed. Check debug log for details.') . '</p></div>';
            }
            if (isset($_POST['manual_sync'])) {
                $result = $this->sync_properties();
                echo '<div class="' . ($result ? 'updated' : 'error') . '"><p>' . ($result ? 'Manual sync completed!' : 'Sync failed. Check debug log in plugin directory for details.') . '</p></div>';
            }
            ?>
        </div>
        <?php
    }

    // Plugin activation
    public function activate() {
        if (!wp_next_scheduled('houzez_entegral_sync_cron')) {
            $options = get_option('entegral_sync_settings');
            $frequency = isset($options['sync_frequency']) ? $options['sync_frequency'] : 'hourly';
            wp_schedule_event(time(), $frequency, 'houzez_entegral_sync_cron');
        }
        // Initialize office on activation
        $this->setup_office();
        // Create log file
        if (!file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
            chmod($this->log_file, 0644);
        }
    }

    // Generate office credentials
    private function generate_office_credentials() {
        $options = get_option('entegral_sync_settings');
        $master_username = !empty($options['master_username']) ? $options['master_username'] : 'DEP Sync API Sandbox';
        $master_password = !empty($options['master_password']) ? $options['master_password'] : 'f1b35a3a-88a8-41c2-a8ca-f3eff0b23cea';
        $credential_endpoint = !empty($options['credential_endpoint']) ? $options['credential_endpoint'] : 'admin';
        $office_name = !empty($options['office_name']) ? $options['office_name'] : 'Default Houzez Office';
        $client_office_id = !empty($options['client_office_id']) ? $options['client_office_id'] : '123456';

        $this->log_message("Attempting to generate office credentials via {$credential_endpoint}");
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($master_username . ':' . $master_password),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode(array(
                'ApiUserName' => $office_name,
                'ClientOfficeId' => $client_office_id,
                'SourceId' => -1
            )),
            'method' => 'POST',
            'timeout' => 30
        );

        $url = rtrim($this->api_base, '/') . '/' . ltrim($credential_endpoint, '/');
        $this->log_message("Credential generation request: URL={$url}, Headers=" . print_r($args['headers'], true) . ", Payload=" . $args['body']);
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log_message("Credential generation failed at {$credential_endpoint}: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        if ($response_code >= 200 && $response_code < 300) {
            $body = json_decode($response_body, true);
            if (isset($body['apiUserName']) && isset($body['apiKey']) && isset($body['sourceId'])) {
                $options = get_option('entegral_sync_settings', array());
                $options['office_username'] = $body['apiUserName'];
                $options['office_password'] = $body['apiKey'];
                $options['source_id'] = $body['sourceId'];
                update_option('entegral_sync_settings', $options);
                $this->log_message("Office credentials generated: Username={$body['apiUserName']}, SourceID={$body['sourceId']}");
                return array(
                    'username' => $body['apiUserName'],
                    'password' => $body['apiKey'],
                    'source_id' => $body['sourceId']
                );
            }
            $this->log_message("Credential generation failed at {$credential_endpoint}: No valid credentials returned in response: {$response_body}");
            return false;
        } else {
            $this->log_message("Credential generation failed at {$credential_endpoint} (Code: {$response_code}): {$response_body} Headers: " . print_r($response_headers, true));
            return false;
        }
    }

    // Create or verify office
    private function setup_office() {
        $options = get_option('entegral_sync_settings');
        $office_name = !empty($options['office_name']) ? $options['office_name'] : 'Default Houzez Office';
        $office_email = !empty($options['office_email']) ? $options['office_email'] : 'info@example.com';
        $office_endpoint = !empty($options['office_endpoint']) ? $options['office_endpoint'] : 'offices';
        $username = !empty($options['office_username']) ? $options['office_username'] : '';
        $password = !empty($options['office_password']) ? $options['office_password'] : '';
        $source_id = !empty($options['source_id']) ? (int)$options['source_id'] : 0;

        // Generate office credentials if not set
        if (empty($username) || empty($password) || empty($source_id)) {
            $credentials = $this->generate_office_credentials();
            if (!$credentials) {
                $this->log_message("Aborting office setup due to failed credential generation");
                return false;
            }
            $username = $credentials['username'];
            $password = $credentials['password'];
            $source_id = $credentials['source_id'];
        }

        $this->log_message("Attempting to create/check office using office credentials at endpoint: {$office_endpoint}");
        $client_office_id = !empty($options['client_office_id']) ? (string)$options['client_office_id'] : '123456';
        $portal_office = array(array('name' => 'flex'));
        $client_group = array(array('name' => 'DefaultGroup', 'id' => '1'));
        $tel = !empty($options['office_tel']) ? $options['office_tel'] : '0123456789';
        $fax = !empty($options['office_fax']) ? $options['office_fax'] : '0000000000';
        $profile = !empty($options['office_profile']) ? $options['office_profile'] : 'Default office profile.';
        $postal_address = !empty($options['office_postal_address']) ? $options['office_postal_address'] : 'Default postal address';
        $physical_address = !empty($options['office_physical_address']) ? $options['office_physical_address'] : 'Default physical address';
        $website = !empty($options['office_website']) ? $options['office_website'] : 'https://www.example.com/';
        $logo = !empty($options['office_logo']) ? $options['office_logo'] : 'https://www.example.com/logo.png';
        $timestamp = date('Y/m/d h:i:s A');
        $office_data = array(
            'clientOfficeID' => $client_office_id,
            'portalOffice' => $portal_office,
            'clientGroup' => $client_group,
            'name' => $office_name,
            'country' => 'South Africa',
            'tel' => $tel,
            'fax' => $fax,
            'email' => $office_email,
            'profile' => $profile,
            'postalAddress' => $postal_address,
            'physicalAddress' => $physical_address,
            'website' => $website,
            'action' => 'create',
            'logo' => $logo,
            'timestamp' => $timestamp
        );
        $this->log_message('Office creation payload: ' . json_encode($office_data));

        // Build full URL for office endpoint
        $url_office = rtrim($this->api_base, '/') . '/' . ltrim($office_endpoint, '/');

        // Try checking existing office with office credentials
        $args_check = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json',
                'SourceID' => $source_id
            ),
            'method' => 'GET',
            'timeout' => 30
        );
        $this->log_message("Check office request: URL={$url_office}, Headers=" . print_r($args_check['headers'], true));
        $response = wp_remote_get($url_office, $args_check);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['offices'])) {
                $this->office_id = $body['offices'][0]['officeID'];
                update_option('entegral_office_id', $this->office_id);
                $this->log_message("Connected to existing office ID {$this->office_id} at endpoint {$office_endpoint}");
                return true;
            }
            $this->log_message("No existing offices found at endpoint {$office_endpoint}");
        } else {
            $error = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $this->log_message("Failed to check existing office at {$office_endpoint}: {$error}");
        }

        // Try creating office with office credentials
        $args_create = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json',
                'SourceID' => $source_id
            ),
            'body' => json_encode($office_data),
            'method' => 'POST',
            'timeout' => 30
        );
        $this->log_message("Office creation request: URL={$url_office}, Headers=" . print_r($args_create['headers'], true) . ", Payload=" . $args_create['body']);
        $response = wp_remote_post($url_office, $args_create);
        $this->log_message('Office creation raw response: ' . print_r($response, true));

        if (is_wp_error($response)) {
            $this->log_message("Office creation failed at {$office_endpoint}: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $this->log_message('Office creation response code: ' . $response_code);
        $this->log_message('Office creation response body: ' . $response_body);
        $this->log_message('Office creation response headers: ' . print_r($response_headers, true));

        if ($response_code >= 200 && $response_code < 300) {
            $body = json_decode($response_body, true);
            if (isset($body['officeID'])) {
                $this->office_id = $body['officeID'];
                update_option('entegral_office_id', $this->office_id);
                $this->log_message("Office created with ID {$this->office_id} at endpoint {$office_endpoint}");
                return true;
            } elseif (isset($body['statusCode']) && $body['statusCode'] == 201) {
                $this->log_message("Office created successfully (no officeID returned, check Entegral dashboard for details).");
                return true;
            }
            $this->log_message("Office creation failed at {$office_endpoint}: No officeID returned in response: {$response_body}");
        } else {
            $this->log_message("Office creation failed at {$office_endpoint} (Code: {$response_code}): {$response_body} Headers: " . print_r($response_headers, true));
        }

        // Fallback to /offices with office credentials
        $this->log_message("Trying fallback endpoint: offices");
        $url_fallback = rtrim($this->api_base, '/') . '/offices';
        $args_fallback = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json',
                'SourceID' => $source_id
            ),
            'body' => json_encode($office_data),
            'method' => 'POST',
            'timeout' => 30
        );
        $response = wp_remote_post($url_fallback, $args_fallback);

        if (is_wp_error($response)) {
            $this->log_message("Office creation failed at offices: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        if ($response_code >= 200 && $response_code < 300) {
            $body = json_decode($response_body, true);
            if (isset($body['officeID'])) {
                $this->office_id = $body['officeID'];
                update_option('entegral_office_id', $this->office_id);
                $this->log_message("Office created with ID {$this->office_id} at endpoint offices");
                return true;
            }
            $this->log_message("Office creation failed at offices: No officeID returned in response: {$response_body}");
        } else {
            $this->log_message("Office creation failed at offices (Code: {$response_code}): {$response_body} Headers: " . print_r($response_headers, true));
        }

        $this->log_message("All office creation attempts failed. Please verify API endpoint and credentials.");
        return false;
    }

    // Get Houzez properties
    private function get_houzez_properties() {
        $properties = array();
        
        $args = array(
            'post_type' => 'property',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Get property meta data
                $price = get_post_meta($post_id, 'fave_property_price', true);
                $rates = get_post_meta($post_id, 'fave_property_tax', true);
                $levy = get_post_meta($post_id, 'fave_property_hoa', true);
                $size = get_post_meta($post_id, 'fave_property_size', true);
                $building_size = get_post_meta($post_id, 'fave_property_land', true);
                $building_size_type = 'm2';
                $prop_type = get_post_meta($post_id, 'fave_property_type', true);
                $prop_status = get_post_meta($post_id, 'fave_property_status', true);
                $province = get_post_meta($post_id, 'fave_property_state', true);
                $town = get_post_meta($post_id, 'fave_property_city', true);
                $suburb = get_post_meta($post_id, 'fave_property_area', true);
                $beds = get_post_meta($post_id, 'fave_property_bedrooms', true);
                $baths = get_post_meta($post_id, 'fave_property_bathrooms', true);
                $study = get_post_meta($post_id, 'fave_property_rooms', true);
                $living_areas = get_post_meta($post_id, 'fave_property_living_areas', true);
                $staff_accommodation = get_post_meta($post_id, 'fave_property_staff_accommodation', true);
                $pets_allowed = get_post_meta($post_id, 'fave_property_pets', true);
                $carports = get_post_meta($post_id, 'fave_property_carports', true);
                $openparking = get_post_meta($post_id, 'fave_property_open_parking', true);
                $garages = get_post_meta($post_id, 'fave_property_garage', true);
                $street_number = get_post_meta($post_id, 'fave_property_map_street_no', true);
                $address = get_post_meta($post_id, 'fave_property_address', true);
                $unit_number = get_post_meta($post_id, 'fave_property_unit_number', true);
                $flatlet = get_post_meta($post_id, 'fave_property_flatlet', true);
                $complex_name = get_post_meta($post_id, 'fave_property_complex_name', true);
                $latlng = get_post_meta($post_id, 'fave_property_location', true);
                $show_on_map = get_post_meta($post_id, 'fave_property_map', true);
                $description = get_the_content();
                $is_reduced = get_post_meta($post_id, 'fave_property_price_reduced', true);
                $vt_url = get_post_meta($post_id, 'fave_virtual_tour', true);
                $is_development = get_post_meta($post_id, 'fave_property_development', true);
                $mandate = get_post_meta($post_id, 'fave_property_mandate', true);
                $expiry_date = get_post_meta($post_id, 'fave_property_expiry', true);
                
                // Get features
                $bedroom_features = get_post_meta($post_id, 'fave_property_bedroom_features', true);
                $bathroom_features = get_post_meta($post_id, 'fave_property_bathroom_features', true);
                $security_features = get_post_meta($post_id, 'fave_property_security_features', true);
                $garden_features = get_post_meta($post_id, 'fave_property_garden_features', true);
                $kitchen_features = get_post_meta($post_id, 'fave_property_kitchen_features', true);
                $property_features = get_post_meta($post_id, 'fave_property_features', true);
                $pool = get_post_meta($post_id, 'fave_property_pool', true);

                // Photos
                $images = get_post_meta($post_id, 'fave_property_images', true);
                $photos = array();
                if ($images) {
                    foreach ($images as $image_id) {
                        $image_url = wp_get_attachment_url($image_id);
                        $photos[] = array(
                            'imgUrl' => $image_url,
                            'imgDescription' => get_the_title($image_id)
                        );
                    }
                }

                // Contacts (dummy for now)
                $contact = array(array(
                    'clientAgentID' => '78952',
                    'clientOfficeID' => get_option('entegral_sync_settings')['client_office_id'] ?? '123456',
                    'fullName' => 'Jenny Penny',
                    'cell' => '0827894561',
                    'email' => 'jenny@realtest.co.za',
                    'profile' => 'We sell Properties',
                    'logo' => 'https://realtest.co.za/123_123.jpg'
                ));

                // Portal Listing (dummy for now)
                $portal_listing = array(array(
                    'name' => 'flex',
                    'id' => '254555454'
                ));

                // Onshow (empty for now)
                $onshow = array();

                // Files (empty for now)
                $files = array();

                // Electrical/Water supply (empty for now)
                $electrical_supply = array();
                $water_supply = array();

                $properties[] = array(
                    'clientPropertyID' => strval($post_id),
                    'currency' => 'ZAR',
                    'price' => isset($price) && $price !== '' ? (int)$price : 0,
                    'ratesAndTaxes' => isset($rates) && $rates !== '' ? (float)$rates : 0,
                    'levy' => isset($levy) && $levy !== '' ? (float)$levy : 0,
                    'landSize' => isset($size) && $size !== '' ? (float)$size : 0,
                    'landSizeType' => 'm2',
                    'buildingSize' => isset($building_size) && $building_size !== '' ? (float)$building_size : 0,
                    'buildingSizeType' => isset($building_size_type) && $building_size_type !== '' ? $building_size_type : 'm2',
                    'propertyType' => isset($prop_type) && $prop_type !== '' ? $prop_type : '-',
                    'propertyStatus' => isset($prop_status) && $prop_status !== '' ? $prop_status : '-',
                    'country' => 'South Africa',
                    'province' => isset($province) && $province !== '' ? $province : '',
                    'town' => isset($town) && $town !== '' ? $town : '',
                    'suburb' => isset($suburb) && $suburb !== '' ? $suburb : '',
                    'beds' => isset($beds) && $beds !== '' ? (int)$beds : 0,
                    'bedroomFeatures' => isset($bedroom_features) && $bedroom_features !== '' ? $bedroom_features : '',
                    'baths' => isset($baths) && $baths !== '' ? (float)$baths : 0,
                    'bathroomFeatures' => isset($bathroom_features) && $bathroom_features !== '' ? $bathroom_features : '',
                    'securityFeatures' => isset($security_features) && $security_features !== '' ? $security_features : '',
                    'gardenFeatures' => isset($garden_features) && $garden_features !== '' ? $garden_features : '',
                    'kitchenFeatures' => isset($kitchen_features) && $kitchen_features !== '' ? $kitchen_features : '',
                    'pool' => isset($pool) && $pool !== '' ? (int)$pool : 0,
                    'listDate' => get_the_date('Y/m/d H:i:s'),
                    'expiryDate' => isset($expiry_date) && $expiry_date !== '' ? $expiry_date : '',
                    'study' => isset($study) && $study !== '' ? (int)$study : 0,
                    'livingAreas' => isset($living_areas) && $living_areas !== '' ? (int)$living_areas : 0,
                    'staffAccommodation' => isset($staff_accommodation) && $staff_accommodation !== '' ? (int)$staff_accommodation : 0,
                    'petsAllowed' => isset($pets_allowed) && $pets_allowed !== '' ? (int)$pets_allowed : 0,
                    'carports' => isset($carports) && $carports !== '' ? (int)$carports : 0,
                    'openparking' => isset($openparking) && $openparking !== '' ? (int)$openparking : 0,
                    'garages' => isset($garages) && $garages !== '' ? (int)$garages : 0,
                    'photos' => $photos,
                    'propertyFeatures' => isset($property_features) && $property_features !== '' ? $property_features : '',
                    'electricalSupply' => $electrical_supply,
                    'waterSupply' => $water_supply,
                    'streetNumber' => isset($street_number) && $street_number !== '' ? $street_number : '',
                    'streetName' => isset($address) && $address !== '' ? $address : '',
                    'unitNumber' => isset($unit_number) && $unit_number !== '' ? (int)$unit_number : 0,
                    'flatLet' => isset($flatlet) && $flatlet !== '' ? (int)$flatlet : 0,
                    'complexName' => isset($complex_name) && $complex_name !== '' ? $complex_name : '',
                    'latlng' => isset($latlng) && $latlng !== '' ? $latlng : '',
                    'showOnMap' => isset($show_on_map) && $show_on_map !== '' ? (int)$show_on_map : 0,
                    'description' => isset($description) && $description !== '' ? $description : '',
                    'isReduced' => isset($is_reduced) && $is_reduced !== '' ? (int)$is_reduced : 0,
                    'vtUrl' => isset($vt_url) && $vt_url !== '' ? $vt_url : '',
                    'isDevelopment' => isset($is_development) && $is_development !== '' ? (int)$is_development : 0,
                    'mandate' => isset($mandate) && $mandate !== '' ? $mandate : '',
                    'contact' => $contact,
                    'portalListing' => $portal_listing,
                    'onshow' => $onshow,
                    'files' => $files,
                    'action' => 'create',
                    'timeStamp' => current_time('Y/m/d H:i:s')
                );
            }
        }
        
        wp_reset_postdata();
        return $properties;
    }

    // Send request to Entegral API for property create/update
    private function send_to_entegral($data) {
        $options = get_option('entegral_sync_settings');
        $username = !empty($options['office_username']) ? $options['office_username'] : '';
        $password = !empty($options['office_password']) ? $options['office_password'] : '';
        $source_id = !empty($options['source_id']) ? $options['source_id'] : '';

        // Ensure endpoint is correct
        $endpoint = rtrim($this->api_base, '/') . '/listings';
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json',
                'SourceID' => $source_id
            ),
            'body' => json_encode($data),
            'method' => 'POST',
            'timeout' => 30
        );

        // Log to plugin directory debug.log
        $this->log_message("Sending API request: URL={$endpoint}, Headers=" . print_r($args['headers'], true) . ", Payload=" . $args['body']);
        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            $this->log_message('API request failed: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        if ($response_code >= 200 && $response_code < 300) {
            $this->log_message('API request successful: ' . $response_body);
            return json_decode($response_body, true);
        } else {
            $this->log_message('API request failed (Code: ' . $response_code . '): ' . $response_body . ' Headers: ' . print_r($response_headers, true));
            return false;
        }
    }

    // Retrieve all listings for the office
    public function get_all_listings() {
        $options = get_option('entegral_sync_settings');
        $username = !empty($options['office_username']) ? $options['office_username'] : '';
        $password = !empty($options['office_password']) ? $options['office_password'] : '';
        $source_id = !empty($options['source_id']) ? $options['source_id'] : '';

        $endpoint = $this->api_base . 'listings';
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Accept' => 'application/json',
                'SourceID' => $source_id
            ),
            'method' => 'GET',
            'timeout' => 30
        );
        $this->log_message("Fetching all listings: URL={$endpoint}, Headers=" . print_r($args['headers'], true));
        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) {
            $this->log_message('Get listings failed: ' . $response->get_error_message());
            return false;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code == 200) {
            $this->log_message('Listings retrieved: ' . $response_body);
            return json_decode($response_body, true);
        } else {
            $this->log_message('Get listings failed (Code: ' . $response_code . '): ' . $response_body);
            return false;
        }
    }

    // Retrieve a single listing by ID
    public function get_listing($id) {
        $options = get_option('entegral_sync_settings');
        $username = !empty($options['office_username']) ? $options['office_username'] : '';
        $password = !empty($options['office_password']) ? $options['office_password'] : '';
        $source_id = !empty($options['source_id']) ? $options['source_id'] : '';

        $endpoint = $this->api_base . 'listings/' . urlencode($id);
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Accept' => 'application/json',
                'SourceID' => $source_id
            ),
            'method' => 'GET',
            'timeout' => 30
        );
        $this->log_message("Fetching listing ID {$id}: URL={$endpoint}, Headers=" . print_r($args['headers'], true));
        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) {
            $this->log_message('Get listing failed: ' . $response->get_error_message());
            return false;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code == 200) {
            $this->log_message('Listing retrieved: ' . $response_body);
            return json_decode($response_body, true);
        } else {
            $this->log_message('Get listing failed (Code: ' . $response_code . '): ' . $response_body);
            return false;
        }
    }

    // Retrieve a listing's logs by ID
    public function get_listing_logs($id) {
        $options = get_option('entegral_sync_settings');
        $username = !empty($options['office_username']) ? $options['office_username'] : '';
        $password = !empty($options['office_password']) ? $options['office_password'] : '';
        $source_id = !empty($options['source_id']) ? $options['source_id'] : '';

        $endpoint = $this->api_base . 'listings/' . urlencode($id) . '/logs';
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Accept' => 'application/json',
                'SourceID' => $source_id
            ),
            'method' => 'GET',
            'timeout' => 30
        );
        $this->log_message("Fetching listing logs for ID {$id}: URL={$endpoint}, Headers=" . print_r($args['headers'], true));
        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) {
            $this->log_message('Get listing logs failed: ' . $response->get_error_message());
            return false;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code == 200) {
            $this->log_message('Listing logs retrieved: ' . $response_body);
            return json_decode($response_body, true);
        } else {
            $this->log_message('Get listing logs failed (Code: ' . $response_code . '): ' . $response_body);
            return false;
        }
    }

    // Sync properties to Entegral
    public function sync_properties() {
        // Ensure office is set up
        $this->office_id = get_option('entegral_office_id');
        if (!$this->office_id) {
            if (!$this->setup_office()) {
                $this->log_message('Aborting property sync due to office setup failure');
                return false;
            }
        }

        $properties = $this->get_houzez_properties();
        if (!empty($properties)) {
            $this->log_message('Found ' . count($properties) . ' properties to sync');
        } else {
            $this->log_message('No properties found to sync');
            return false;
        }

        $success = true;
        foreach ($properties as $property) {
            $result = $this->send_to_entegral($property);
            if ($result) {
                $this->log_message('Success: Property ID ' . $property['clientPropertyID'] . ' synced');
            } else {
                $success = false;
                $this->log_message('Failed: Property ID ' . $property['clientPropertyID'] . ' not synced');
            }
        }

        return $success;
    }
}

// Initialize the plugin
$entegral_sync = new Entegral_Sync_Houzez();

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('houzez_entegral_sync_cron');
});

?>