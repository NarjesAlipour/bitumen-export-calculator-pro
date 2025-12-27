<?php
/**
 * Plugin Name: Bitumen Export Calculator Pro
 * Version: 2.3.0
 */

if (!defined('ABSPATH')) exit;

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
            wp_enqueue_style('bec-frontend-css', plugin_dir_url(__FILE__) . 'css/bec-frontend.css', [], '2.3.0');
            wp_enqueue_script('bec-frontend-js', plugin_dir_url(__FILE__) . 'js/bec-frontend.js', ['jquery'], '2.3.0', true);
            $options = get_option($this->option_name);
            wp_localize_script('bec-frontend-js', 'bec_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bec_nonce_action')
            ]);
        }
    }

    public function add_admin_menu() {
        add_menu_page('Bitumen Calc Pro', 'Bitumen Calc', 'manage_options', 'bitumen_calc_pro', [$this, 'admin_page_html'], 'dashicons-chart-line');
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
    }

    public function admin_page_html() {
        $options = get_option($this->option_name); ?>
        <div class="wrap">
            <h1>Bitumen Calculator Settings</h1>
            <form action="options.php" method="post">
                <?php settings_fields($this->option_name); ?>
                <table class="form-table">
                    <tr><th>Currency Symbol</th><td><input type="text" name="bec_options[currency]" value="<?php echo esc_attr($options['currency'] ?? '$'); ?>"></td></tr>
                    <tr><th>Jumbo Cost</th><td><input type="number" step="0.01" name="bec_options[jumbo_cost]" value="<?php echo $options['jumbo_cost'] ?? 0; ?>"></td></tr>
                    <tr><th>Drum Cost</th><td><input type="number" step="0.01" name="bec_options[drum_cost]" value="<?php echo $options['drum_cost'] ?? 0; ?>"></td></tr>
                    <tr><th>Types (Name|Price)</th><td><textarea name="bec_options[types]" rows="5" class="large-text"><?php echo $options['types'] ?? ''; ?></textarea></td></tr>
                    <tr><th>Destinations (Name|Cost)</th><td><textarea name="bec_options[destinations]" rows="5" class="large-text"><?php echo $options['destinations'] ?? ''; ?></textarea></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php }

    private function parse($s) {
        $lines = explode("\n", str_replace("\r", "", $s));
        $d = [];
        foreach ($lines as $l) { $p = explode('|', $l); if (count($p) == 2) $d[trim($p[0])] = floatval(trim($p[1])); }
        return $d;
    }

    public function render_shortcode() {
        self::$shortcode_present = true;
        $opt = get_option($this->option_name);
        $types = $this->parse($opt['types'] ?? '');
        $dests = $this->parse($opt['destinations'] ?? '');
        ob_start(); ?>
        <div id="bec-wrapper">
            <div class="bec-field">
                <label>Qty (100-1000 MT)</label>
                <input type="number" id="bec-qty" min="100" max="1000" value="100">
                <span class="bec-err" id="err-qty"></span>
            </div>
            <div class="bec-field">
                <label>Grade</label>
                <select id="bec-type"><?php foreach($types as $n => $p) echo "<option value='$n'>$n</option>"; ?></select>
            </div>
            <div class="bec-field">
                <label>Packaging</label>
                <select id="bec-pack"><option value="jumbo">Jumbo Bag</option><option value="drum">Steel Drum</option></select>
            </div>
            <div class="bec-field">
                <label>Destination</label>
                <select id="bec-dest"><option value="">-- Select --</option><?php foreach($dests as $n => $c) echo "<option value='$n'>$n</option>"; ?></select>
                <span class="bec-err" id="err-dest"></span>
            </div>
            <button id="bec-calc-btn">Calculate</button>
            <div id="bec-result"></div>
        </div>
        <?php return ob_get_clean();
    }

    public function handle_calculation() {
        check_ajax_referer('bec_nonce_action', 'security');
        $opt = get_option($this->option_name);
        $qty = floatval($_POST['qty']);
        $cur = $opt['currency'] ?? '$';
        
        $base = $this->parse($opt['types'])[$_POST['type']];
        $ship = $this->parse($opt['destinations'])[$_POST['dest']];
        $pack = ($_POST['pack'] === 'jumbo') ? $opt['jumbo_cost'] : $opt['drum_cost'];

        if ($qty > 500) $base *= 0.95; // 5% Discount

        $t_bit = $base * $qty;
        $t_pack = $pack * $qty;
        $t_ship = $ship * $qty;
        $total = $t_bit + $t_pack + $t_ship;

        $html = "<div class='bec-res'>
            <p>Bitumen: {$cur}" . number_format($t_bit, 2) . "</p>
            <p>Packaging: {$cur}" . number_format($t_pack, 2) . "</p>
            <p>Shipping: {$cur}" . number_format($t_ship, 2) . "</p>
            <hr><strong>Total: {$cur}" . number_format($total, 2) . "</strong>
        </div>";
        wp_send_json_success(['html' => $html]);
    }
}
new Bitumen_Export_Calculator();