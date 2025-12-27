<?php
/**
 * Plugin Name: Bitumen Export Calculator Pro
 * Version: 2.2.1
 * Author: Narjes Alipour
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bitumen_Export_Calculator {

    private $option_name = 'bec_options';
    private static $shortcode_present = false;

    public function __construct() {
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        add_shortcode('bitumen_calculator', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);

        add_action('wp_ajax_bec_calculate', [$this, 'handle_calculation']);
        add_action('wp_ajax_nopriv_bec_calculate', [$this, 'handle_calculation']);
    }

    public function enqueue_frontend_scripts() {
        if (self::$shortcode_present) {
            wp_enqueue_style(
                'bec-frontend-css',
                plugin_dir_url(__FILE__) . 'css/bec-frontend.css',
                [],
                '2.2.1'
            );

            wp_enqueue_script(
                'bec-frontend-js',
                plugin_dir_url(__FILE__) . 'js/bec-frontend.js',
                ['jquery'],
                '2.2.1',
                true
            );

            wp_localize_script('bec-frontend-js', 'bec_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bec_nonce_action'),
                'min_quantity_alert' => __('Please enter a valid quantity (Minimum 100 MT).', 'bit-calc'),
                'destination_alert' => __('Please select a destination.', 'bit-calc'),
                'calculating_text' => __('Calculating...', 'bit-calc'),
                'calculate_text' => __('Calculate Price', 'bit-calc'),
            ]);
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Bitumen Calculator', 'bit-calc'),
            __('Bitumen Calc', 'bit-calc'),
            'manage_options',
            'bitumen_calc_pro',
            [$this, 'admin_page_html'],
            'dashicons-chart-line'
        );
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name, [$this, 'sanitize_options']);

        add_settings_section('bec_main_section', __('Configuration Parameters', 'bit-calc'), null, 'bitumen_calc_pro');

        add_settings_field('bec_types', __('Bitumen Types & Prices (Name:Price)', 'bit-calc'), [$this, 'render_textarea_field'], 'bitumen_calc_pro', 'bec_main_section', ['id' => 'types', 'desc' => __('Enter one per line. Format: Name:Price (e.g., Bitumen 60/70:350)', 'bit-calc')]);
        add_settings_field('bec_destinations', __('Destinations & Shipping Cost (Name:Cost)', 'bit-calc'), [$this, 'render_textarea_field'], 'bitumen_calc_pro', 'bec_main_section', ['id' => 'destinations', 'desc' => __('Enter one per line. Format: Name:Cost (e.g., Mombasa:45)', 'bit-calc')]);
        add_settings_field('bec_jumbo_cost', __('Jumbo Packaging Cost (Per Ton)', 'bit-calc'), [$this, 'render_input_field'], 'bitumen_calc_pro', 'bec_main_section', ['id' => 'jumbo_cost']);
        add_settings_field('bec_drum_cost', __('Drum Packaging Cost (Per Ton)', 'bit-calc'), [$this, 'render_input_field'], 'bitumen_calc_pro', 'bec_main_section', ['id' => 'drum_cost']);
        add_settings_field('bec_currency_symbol', __('Currency Symbol', 'bit-calc'), [$this, 'render_text_field'], 'bitumen_calc_pro', 'bec_main_section', ['id' => 'currency_symbol', 'default' => '$']);
    }

    public function sanitize_options($input) {
        $new_input = [];
        $new_input['types'] = sanitize_textarea_field($input['types']);
        $new_input['destinations'] = sanitize_textarea_field($input['destinations']);
        $new_input['jumbo_cost'] = floatval($input['jumbo_cost']);
        $new_input['drum_cost'] = floatval($input['drum_cost']);
        if (!empty($input['currency_symbol'])) {
            $new_input['currency_symbol'] = sanitize_text_field($input['currency_symbol']);
        }
        return $new_input;
    }

    public function render_input_field($args) {
        $options = get_option($this->option_name);
        $val = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo "<input type='number' step='0.01' name='{$this->option_name}[{$args['id']}]' value='$val' class='regular-text'>";
    }

    public function render_text_field($args) {
        $options = get_option($this->option_name);
        $default = isset($args['default']) ? $args['default'] : '';
        $val = isset($options[$args['id']]) ? $options[$args['id']] : $default;
        echo "<input type='text' name='{$this->option_name}[{$args['id']}]' value='" . esc_attr($val) . "' class='regular-text'>";
    }

    public function render_textarea_field($args) {
        $options = get_option($this->option_name);
        $val = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo "<textarea name='{$this->option_name}[{$args['id']}]' rows='5' cols='50' class='large-text code'>$val</textarea>";
        echo "<p class='description'>{$args['desc']}</p>";
    }

    public function admin_page_html() {
        ?>
        <div class="wrap">
            <h1><?php _e('Bitumen Export Calculator Settings', 'bit-calc'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('bitumen_calc_pro');
                submit_button(__('Save Settings', 'bit-calc'));
                ?>
            </form>
        </div>
        <?php
    }

    private function parse_config_string($string) {
        $lines = explode("\n", $string);
        $data = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) == 2) {
                $name = trim($parts[0]);
                $price = floatval(trim($parts[1]));
                if ($name && $price >= 0) {
                    $data[$name] = $price;
                }
            }
        }
        return $data;
    }

    public function render_shortcode() {
        self::$shortcode_present = true;
        $options = get_option($this->option_name);
        $types = $this->parse_config_string(isset($options['types']) ? $options['types'] : '');
        $destinations = $this->parse_config_string(isset($options['destinations']) ? $options['destinations'] : '');

        ob_start();
        ?>
        <div id="bec-wrapper" class="woodmart-style-box">
            <h3><?php _e('Export Cost Calculator', 'bit-calc'); ?></h3>
            
            <div class="bec-field-group">
                <label><?php _e('Order Quantity (MT)', 'bit-calc'); ?> <span class="required">*</span></label>
                <input type="number" id="bec-qty" min="100" value="100" placeholder="<?php esc_attr_e('Min 100 MT', 'bit-calc'); ?>">
                <small><?php _e('Minimum order: 100 Metric Tons', 'bit-calc'); ?></small>
                <div id="bec-qty-error" class="bec-error"></div>
            </div>

            <div class="bec-field-group">
                <label><?php _e('Bitumen Grade', 'bit-calc'); ?></label>
                <select id="bec-type">
                    <?php foreach($types as $name => $price): ?>
                        <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="bec-dest-error" class="bec-error"></div>
            </div>

            <div class="bec-field-group">
                <label><?php _e('Packaging', 'bit-calc'); ?></label>
                <select id="bec-pack">
                    <option value="jumbo"><?php _e('Jumbo Bag', 'bit-calc'); ?></option>
                    <option value="drum"><?php _e('New Steel Drum', 'bit-calc'); ?></option>
                </select>
            </div>

            <div class="bec-field-group bec-field-group-last">
                <label><?php _e('Destination Port', 'bit-calc'); ?></label>
                <select id="bec-dest">
                    <option value=""><?php _e('-- Select Destination --', 'bit-calc'); ?></option>
                    <?php foreach($destinations as $name => $cost): ?>
                        <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button id="bec-calc-btn" class="button alt"><?php _e('Calculate Price', 'bit-calc'); ?></button>
            <div id="bec-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_calculation() {
        check_ajax_referer('bec_nonce_action', 'security');

        $qty = floatval($_POST['qty']);
        $type_name = sanitize_text_field($_POST['type']);
        $pack_type = sanitize_text_field($_POST['pack']);
        $dest_name = sanitize_text_field($_POST['dest']);

        $options = get_option($this->option_name);
        $types = $this->parse_config_string($options['types']);
        $destinations = $this->parse_config_string($options['destinations']);
        
        $jumbo_cost = floatval($options['jumbo_cost']);
        $drum_cost = floatval($options['drum_cost']);

        if (!isset($types[$type_name])) wp_send_json_error(__('Invalid Bitumen Type', 'bit-calc'));
        if (!isset($destinations[$dest_name])) wp_send_json_error(__('Invalid Destination', 'bit-calc'));

        $base_price_per_ton = $types[$type_name];
        $freight_cost_per_ton = $destinations[$dest_name];
        $pack_cost_per_ton = ($pack_type === 'jumbo') ? $jumbo_cost : $drum_cost;

        $bitumen_cost = $base_price_per_ton * $qty;
        $packaging_cost = $pack_cost_per_ton * $qty;
        $shipping_cost = $freight_cost_per_ton * $qty;
        $total_price = $bitumen_cost + $packaging_cost + $shipping_cost;
        $unit_price = $base_price_per_ton + $pack_cost_per_ton + $freight_cost_per_ton;

        $currency_symbol = isset($options['currency_symbol']) ? $options['currency_symbol'] : '$';
        $html = '<div class="bec-cost-breakdown">';
        $html .= '<p>' . __('Bitumen Cost:', 'bit-calc') . ' <span>' . esc_html($currency_symbol) . number_format($bitumen_cost, 2) . '</span></p>';
        $html .= '<p>' . __('Packaging Cost:', 'bit-calc') . ' <span>' . esc_html($currency_symbol) . number_format($packaging_cost, 2) . '</span></p>';
        $html .= '<p>' . __('Shipping Cost:', 'bit-calc') . ' <span>' . esc_html($currency_symbol) . number_format($shipping_cost, 2) . '</span></p>';
        $html .= '<hr>';
        $html .= '<p class="bec-total"><strong>' . __('Estimated Total (CFR):', 'bit-calc') . '</strong> <span class="bec-total-price">' . esc_html($currency_symbol) . number_format($total_price, 2) . '</span></p>';
        $html .= '<p class="bec-unit-price">' . sprintf(__('Details: %s %s/MT', 'bit-calc'), esc_html($currency_symbol), number_format($unit_price, 2)) . '</p>';
        $html .= '</div>';

        wp_send_json_success(['html' => $html]);
    }
}

new Bitumen_Export_Calculator();
