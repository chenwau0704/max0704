<?php
/**
 * Plugin Name: One Click Simple Shop
 * Description: 商用基礎版購物外掛（商品、庫存、購物車、結帳、訂單、稅金、運費、付款方式）。
 * Version: 2.0.0
 * Author: GPT-5.3-Codex
 */

if (!defined('ABSPATH')) {
    exit;
}

class One_Click_Simple_Shop {
    private const CART_KEY = 'ocss_cart';
    private const ACTION_NONCE = 'ocss_cart_action';
    private const ACTION_NONCE_FIELD = 'ocss_nonce';
    private const SETTINGS_KEY = 'ocss_settings';

    public function __construct() {
        add_action('init', [$this, 'register_product_cpt']);
        add_action('init', [$this, 'register_order_cpt']);
        add_action('init', [$this, 'maybe_start_session'], 1);
        add_action('init', [$this, 'handle_actions']);

        add_shortcode('ocss_shop', [$this, 'render_shop']);
        add_shortcode('ocss_cart', [$this, 'render_cart']);
        add_shortcode('ocss_checkout', [$this, 'render_checkout']);

        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_ocss_product', [$this, 'save_product_meta']);
        add_action('admin_init', [$this, 'handle_admin_actions']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
    }

    public static function activate(): void {
        $pages = [
            'Shop' => '[ocss_shop]',
            'Cart' => '[ocss_cart]',
            'Checkout' => '[ocss_checkout]',
        ];

        foreach ($pages as $title => $shortcode) {
            $existing = get_page_by_title($title);
            if ($existing instanceof WP_Post) {
                continue;
            }

            wp_insert_post([
                'post_title' => $title,
                'post_content' => $shortcode,
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
        }

        if (!get_option(self::SETTINGS_KEY)) {
            update_option(self::SETTINGS_KEY, [
                'currency' => 'USD',
                'shipping_fee' => '0',
                'tax_rate' => '0',
                'bank_info' => 'Bank: 000-000-000\nAccount Name: Your Company',
            ]);
        }

        flush_rewrite_rules();
    }

    public function register_product_cpt(): void {
        register_post_type('ocss_product', [
            'labels' => [
                'name' => 'Products',
                'singular_name' => 'Product',
                'add_new_item' => 'Add New Product',
                'edit_item' => 'Edit Product',
            ],
            'public' => true,
            'menu_icon' => 'dashicons-cart',
            'supports' => ['title', 'editor', 'thumbnail'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'products'],
            'show_in_rest' => true,
        ]);
    }

    public function register_order_cpt(): void {
        register_post_type('ocss_order', [
            'labels' => [
                'name' => 'Orders',
                'singular_name' => 'Order',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=ocss_product',
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title'],
        ]);
    }

    public function add_admin_pages(): void {
        add_submenu_page(
            'edit.php?post_type=ocss_product',
            'Shop Setup',
            'Shop Setup',
            'manage_options',
            'ocss-setup',
            [$this, 'render_admin_setup']
        );

        add_submenu_page(
            'edit.php?post_type=ocss_product',
            'Shop Settings',
            'Shop Settings',
            'manage_options',
            'ocss-settings',
            [$this, 'render_settings_page']
        );
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'ocss_product_data',
            'Product Data',
            [$this, 'render_product_meta'],
            'ocss_product',
            'side',
            'default'
        );

        add_meta_box(
            'ocss_order_data',
            'Order Details',
            [$this, 'render_order_meta'],
            'ocss_order',
            'normal',
            'default'
        );
    }

    public function render_product_meta(WP_Post $post): void {
        wp_nonce_field('ocss_save_product_meta', 'ocss_product_meta_nonce');
        $price = get_post_meta($post->ID, '_ocss_price', true);
        $stock = get_post_meta($post->ID, '_ocss_stock', true);

        echo '<p><label>Price (USD)</label><br>';
        echo '<input type="number" step="0.01" min="0" name="ocss_price" value="' . esc_attr((string) $price) . '" style="width:100%"></p>';

        echo '<p><label>Stock Qty</label><br>';
        echo '<input type="number" step="1" min="0" name="ocss_stock" value="' . esc_attr((string) $stock) . '" style="width:100%"></p>';
    }

    public function render_order_meta(WP_Post $post): void {
        $customer_name = get_post_meta($post->ID, '_ocss_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_ocss_customer_email', true);
        $customer_phone = get_post_meta($post->ID, '_ocss_customer_phone', true);
        $customer_address = get_post_meta($post->ID, '_ocss_customer_address', true);
        $payment_method = get_post_meta($post->ID, '_ocss_payment_method', true);
        $status = get_post_meta($post->ID, '_ocss_order_status', true);
        $items = get_post_meta($post->ID, '_ocss_order_items', true);
        $totals = get_post_meta($post->ID, '_ocss_order_totals', true);

        $items = is_array($items) ? $items : [];
        $totals = is_array($totals) ? $totals : ['subtotal' => 0, 'tax' => 0, 'shipping' => 0, 'grand_total' => 0];

        echo '<p><strong>Customer:</strong> ' . esc_html((string) $customer_name) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html((string) $customer_email) . '</p>';
        echo '<p><strong>Phone:</strong> ' . esc_html((string) $customer_phone) . '</p>';
        echo '<p><strong>Address:</strong><br>' . nl2br(esc_html((string) $customer_address)) . '</p>';
        echo '<p><strong>Payment:</strong> ' . esc_html((string) $payment_method) . '</p>';
        echo '<p><strong>Status:</strong> ' . esc_html((string) $status) . '</p>';

        echo '<hr><p><strong>Items</strong></p><ul>';
        foreach ($items as $item) {
            $line = sprintf(
                '%s x %d = %s %s',
                (string) ($item['name'] ?? ''),
                (int) ($item['qty'] ?? 0),
                $this->get_currency_symbol(),
                number_format((float) ($item['subtotal'] ?? 0), 2)
            );
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul>';

        echo '<p><strong>Subtotal:</strong> ' . esc_html($this->money((float) ($totals['subtotal'] ?? 0))) . '</p>';
        echo '<p><strong>Tax:</strong> ' . esc_html($this->money((float) ($totals['tax'] ?? 0))) . '</p>';
        echo '<p><strong>Shipping:</strong> ' . esc_html($this->money((float) ($totals['shipping'] ?? 0))) . '</p>';
        echo '<p><strong>Grand Total:</strong> ' . esc_html($this->money((float) ($totals['grand_total'] ?? 0))) . '</p>';

        if (current_user_can('edit_post', $post->ID)) {
            echo '<hr>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('ocss_update_order_status', 'ocss_status_nonce');
            echo '<input type="hidden" name="action" value="ocss_update_order_status">';
            echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $post->ID) . '">';
            echo '<label><strong>Update Status</strong></label><br>';
            echo '<select name="new_status" style="margin:8px 0;width:220px">';
            foreach (['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'] as $s) {
                echo '<option value="' . esc_attr($s) . '" ' . selected($status, $s, false) . '>' . esc_html(ucfirst($s)) . '</option>';
            }
            echo '</select><br>';
            echo '<button class="button button-primary" type="submit">Save Status</button>';
            echo '</form>';
        }
    }

    public function save_product_meta(int $post_id): void {
        if (!isset($_POST['ocss_product_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ocss_product_meta_nonce'])), 'ocss_save_product_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $price = isset($_POST['ocss_price']) ? (float) wp_unslash($_POST['ocss_price']) : 0;
        $stock = isset($_POST['ocss_stock']) ? max(0, (int) wp_unslash($_POST['ocss_stock'])) : 0;

        update_post_meta($post_id, '_ocss_price', max(0, $price));
        update_post_meta($post_id, '_ocss_stock', $stock);
    }

    public function handle_admin_actions(): void {
        add_action('admin_post_ocss_update_order_status', [$this, 'update_order_status']);
    }

    public function update_order_status(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        if (!isset($_POST['ocss_status_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ocss_status_nonce'])), 'ocss_update_order_status')) {
            wp_die('Invalid nonce');
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : 'pending';

        $allowed = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
        if (!in_array($new_status, $allowed, true)) {
            $new_status = 'pending';
        }

        if ($order_id > 0 && get_post_type($order_id) === 'ocss_order') {
            update_post_meta($order_id, '_ocss_order_status', $new_status);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=ocss_order'));
        exit;
    }

    public function render_admin_setup(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap"><h1>One Click Shop Setup</h1>';
        echo '<p>Auto-created pages on activation: <strong>Shop</strong>, <strong>Cart</strong>, <strong>Checkout</strong>.</p>';
        echo '<ul>';
        echo '<li><code>[ocss_shop]</code> 商品列表</li>';
        echo '<li><code>[ocss_cart]</code> 購物車</li>';
        echo '<li><code>[ocss_checkout]</code> 結帳</li>';
        echo '</ul>';
        echo '<p>商用建議：先到 <strong>Shop Settings</strong> 設定稅率、運費、匯款資訊。</p>';
        echo '</div>';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['ocss_save_settings'])) {
            check_admin_referer('ocss_save_settings_nonce', 'ocss_settings_nonce');

            $settings = [
                'currency' => isset($_POST['currency']) ? sanitize_text_field(wp_unslash($_POST['currency'])) : 'USD',
                'shipping_fee' => isset($_POST['shipping_fee']) ? (string) (float) wp_unslash($_POST['shipping_fee']) : '0',
                'tax_rate' => isset($_POST['tax_rate']) ? (string) (float) wp_unslash($_POST['tax_rate']) : '0',
                'bank_info' => isset($_POST['bank_info']) ? sanitize_textarea_field(wp_unslash($_POST['bank_info'])) : '',
            ];

            update_option(self::SETTINGS_KEY, $settings);
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $settings = $this->get_settings();

        echo '<div class="wrap"><h1>Shop Settings</h1>';
        echo '<form method="post">';
        wp_nonce_field('ocss_save_settings_nonce', 'ocss_settings_nonce');

        echo '<table class="form-table">';
        echo '<tr><th>Currency</th><td><input name="currency" type="text" value="' . esc_attr($settings['currency']) . '" class="regular-text" placeholder="USD"></td></tr>';
        echo '<tr><th>Shipping Fee</th><td><input name="shipping_fee" type="number" min="0" step="0.01" value="' . esc_attr($settings['shipping_fee']) . '"></td></tr>';
        echo '<tr><th>Tax Rate (%)</th><td><input name="tax_rate" type="number" min="0" step="0.01" value="' . esc_attr($settings['tax_rate']) . '"></td></tr>';
        echo '<tr><th>Bank Transfer Info</th><td><textarea name="bank_info" rows="5" class="large-text">' . esc_textarea($settings['bank_info']) . '</textarea></td></tr>';
        echo '</table>';

        echo '<p><button class="button button-primary" type="submit" name="ocss_save_settings" value="1">Save Settings</button></p>';
        echo '</form></div>';
    }

    public function enqueue_styles(): void {
        $css = '.ocss-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}.ocss-card{border:1px solid #ddd;padding:16px;border-radius:10px;background:#fff}.ocss-price{font-size:20px;font-weight:700}.ocss-btn{display:inline-block;padding:10px 14px;background:#111;color:#fff;border:none;border-radius:8px;text-decoration:none;cursor:pointer}.ocss-btn-danger{background:#a00}.ocss-table{width:100%;border-collapse:collapse}.ocss-table th,.ocss-table td{border-bottom:1px solid #ddd;padding:10px;text-align:left}.ocss-total{font-size:20px;font-weight:700;margin-top:10px}.ocss-alert{padding:10px 14px;border-radius:8px;background:#e8f7e8;margin:12px 0}.ocss-help{font-size:13px;color:#555}';
        wp_register_style('ocss-inline-style', false);
        wp_enqueue_style('ocss-inline-style');
        wp_add_inline_style('ocss-inline-style', $css);
    }

    public function maybe_start_session(): void {
        if (!session_id()) {
            session_start();
        }
    }

    private function get_settings(): array {
        $defaults = [
            'currency' => 'USD',
            'shipping_fee' => '0',
            'tax_rate' => '0',
            'bank_info' => '',
        ];

        $settings = get_option(self::SETTINGS_KEY, []);
        if (!is_array($settings)) {
            return $defaults;
        }

        return wp_parse_args($settings, $defaults);
    }

    private function get_currency_symbol(): string {
        $currency = strtoupper((string) $this->get_settings()['currency']);
        $symbols = ['USD' => '$', 'TWD' => 'NT$', 'JPY' => '¥', 'EUR' => '€'];
        return $symbols[$currency] ?? $currency . ' ';
    }

    private function money(float $amount): string {
        return $this->get_currency_symbol() . number_format($amount, 2);
    }

    private function get_cart(): array {
        $cart = $_SESSION[self::CART_KEY] ?? [];
        return is_array($cart) ? $cart : [];
    }

    private function set_cart(array $cart): void {
        $_SESSION[self::CART_KEY] = $cart;
    }

    private function action_nonce_ok(): bool {
        if (!isset($_POST[self::ACTION_NONCE_FIELD])) {
            return false;
        }

        return wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST[self::ACTION_NONCE_FIELD])),
            self::ACTION_NONCE
        ) === 1;
    }

    private function get_checkout_url(): string {
        $checkout_page = get_page_by_title('Checkout');
        if ($checkout_page instanceof WP_Post) {
            return get_permalink($checkout_page->ID) ?: home_url('/');
        }

        return home_url('/');
    }

    private function is_valid_product(int $product_id): bool {
        return get_post_type($product_id) === 'ocss_product' && get_post_status($product_id) === 'publish';
    }

    private function get_product_stock(int $product_id): int {
        return max(0, (int) get_post_meta($product_id, '_ocss_stock', true));
    }

    private function get_product_price(int $product_id): float {
        return max(0, (float) get_post_meta($product_id, '_ocss_price', true));
    }

    private function decrease_stock(int $product_id, int $qty): void {
        $stock = $this->get_product_stock($product_id);
        $new_stock = max(0, $stock - $qty);
        update_post_meta($product_id, '_ocss_stock', $new_stock);
    }

    public function handle_actions(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['ocss_action']) || !$this->action_nonce_ok()) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['ocss_action']));

        if ($action === 'add_to_cart') {
            $this->action_add_to_cart();
        } elseif ($action === 'remove_from_cart') {
            $this->action_remove_from_cart();
        } elseif ($action === 'checkout') {
            $this->action_checkout();
        }
    }

    private function action_add_to_cart(): void {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $qty = isset($_POST['qty']) ? max(1, absint($_POST['qty'])) : 1;

        if (!$this->is_valid_product($product_id)) {
            wp_safe_redirect(add_query_arg('ocss_notice', 'invalid', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $stock = $this->get_product_stock($product_id);
        $cart = $this->get_cart();
        $current = $cart[$product_id] ?? 0;

        if (($current + $qty) > $stock) {
            wp_safe_redirect(add_query_arg('ocss_notice', 'stock_low', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $cart[$product_id] = $current + $qty;
        $this->set_cart($cart);

        wp_safe_redirect(add_query_arg('ocss_notice', 'added', wp_get_referer() ?: home_url('/')));
        exit;
    }

    private function action_remove_from_cart(): void {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $cart = $this->get_cart();
        unset($cart[$product_id]);
        $this->set_cart($cart);

        wp_safe_redirect(add_query_arg('ocss_notice', 'removed', wp_get_referer() ?: home_url('/')));
        exit;
    }

    private function action_checkout(): void {
        $cart_data = $this->get_cart_data();
        if (empty($cart_data['rows'])) {
            wp_safe_redirect(add_query_arg('ocss_notice', 'invalid', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $name = isset($_POST['ocss_name']) ? sanitize_text_field(wp_unslash($_POST['ocss_name'])) : '';
        $email = isset($_POST['ocss_email']) ? sanitize_email(wp_unslash($_POST['ocss_email'])) : '';
        $phone = isset($_POST['ocss_phone']) ? sanitize_text_field(wp_unslash($_POST['ocss_phone'])) : '';
        $address = isset($_POST['ocss_address']) ? sanitize_textarea_field(wp_unslash($_POST['ocss_address'])) : '';
        $payment_method = isset($_POST['ocss_payment']) ? sanitize_text_field(wp_unslash($_POST['ocss_payment'])) : 'cod';

        $allowed_payments = ['cod', 'bank_transfer'];
        if (!in_array($payment_method, $allowed_payments, true)) {
            $payment_method = 'cod';
        }

        if ($name === '' || $email === '' || $phone === '' || $address === '') {
            wp_safe_redirect(add_query_arg('ocss_notice', 'missing', wp_get_referer() ?: home_url('/')));
            exit;
        }

        foreach ($cart_data['rows'] as $row) {
            $stock = $this->get_product_stock((int) $row['product_id']);
            if ($row['qty'] > $stock) {
                wp_safe_redirect(add_query_arg('ocss_notice', 'stock_low', wp_get_referer() ?: home_url('/')));
                exit;
            }
        }

        $order_no = 'OCSS-' . gmdate('Ymd-His') . '-' . wp_rand(1000, 9999);
        $order_id = wp_insert_post([
            'post_type' => 'ocss_order',
            'post_status' => 'publish',
            'post_title' => $order_no,
        ]);

        if (!$order_id || is_wp_error($order_id)) {
            wp_safe_redirect(add_query_arg('ocss_notice', 'system_error', wp_get_referer() ?: home_url('/')));
            exit;
        }

        update_post_meta($order_id, '_ocss_order_no', $order_no);
        update_post_meta($order_id, '_ocss_customer_name', $name);
        update_post_meta($order_id, '_ocss_customer_email', $email);
        update_post_meta($order_id, '_ocss_customer_phone', $phone);
        update_post_meta($order_id, '_ocss_customer_address', $address);
        update_post_meta($order_id, '_ocss_payment_method', $payment_method);
        update_post_meta($order_id, '_ocss_order_status', 'pending');
        update_post_meta($order_id, '_ocss_order_items', $cart_data['rows']);
        update_post_meta($order_id, '_ocss_order_totals', $cart_data['totals']);

        foreach ($cart_data['rows'] as $row) {
            $this->decrease_stock((int) $row['product_id'], (int) $row['qty']);
        }

        $this->send_order_emails($order_no, $name, $email, $phone, $address, $payment_method, $cart_data);

        $this->set_cart([]);

        wp_safe_redirect(add_query_arg('ocss_notice', 'ordered', $this->get_checkout_url()));
        exit;
    }

    private function send_order_emails(string $order_no, string $name, string $email, string $phone, string $address, string $payment_method, array $cart_data): void {
        $payment_label = $payment_method === 'bank_transfer' ? 'Bank Transfer' : 'Cash on Delivery';
        $settings = $this->get_settings();
        $items_text = [];

        foreach ($cart_data['rows'] as $row) {
            $items_text[] = sprintf('%s x %d = %s', $row['name'], $row['qty'], $this->money((float) $row['subtotal']));
        }

        $totals = $cart_data['totals'];
        $summary = "Subtotal: {$this->money((float) $totals['subtotal'])}\nTax: {$this->money((float) $totals['tax'])}\nShipping: {$this->money((float) $totals['shipping'])}\nGrand Total: {$this->money((float) $totals['grand_total'])}";

        $admin_subject = '[New Order] ' . $order_no;
        $admin_message = "Order: {$order_no}\nCustomer: {$name}\nEmail: {$email}\nPhone: {$phone}\nAddress: {$address}\nPayment: {$payment_label}\n\nItems:\n" . implode("\n", $items_text) . "\n\n{$summary}";
        wp_mail(get_option('admin_email'), $admin_subject, $admin_message);

        $customer_subject = '[Order Received] ' . $order_no;
        $customer_message = "Hi {$name},\n\nWe received your order.\nOrder No: {$order_no}\nPayment: {$payment_label}\n\nItems:\n" . implode("\n", $items_text) . "\n\n{$summary}\n";

        if ($payment_method === 'bank_transfer' && !empty($settings['bank_info'])) {
            $customer_message .= "\nBank transfer info:\n{$settings['bank_info']}\n";
        }

        $customer_message .= "\nThank you!";
        wp_mail($email, $customer_subject, $customer_message);
    }

    private function render_notice(): void {
        if (!isset($_GET['ocss_notice'])) {
            return;
        }

        $notice = sanitize_text_field(wp_unslash($_GET['ocss_notice']));
        $messages = [
            'added' => '已加入購物車 ✅',
            'removed' => '已移除商品 ✅',
            'ordered' => '訂單建立成功 ✅',
            'missing' => '請填寫完整結帳資料。',
            'stock_low' => '庫存不足，請調整數量。',
            'invalid' => '商品或訂單資料無效。',
            'system_error' => '系統忙碌中，請稍後再試。',
        ];

        if (isset($messages[$notice])) {
            echo '<p class="ocss-alert"><strong>' . esc_html($messages[$notice]) . '</strong></p>';
        }
    }

    private function get_cart_data(): array {
        $rows = [];
        $subtotal = 0.0;

        foreach ($this->get_cart() as $product_id => $qty) {
            if (!$this->is_valid_product((int) $product_id)) {
                continue;
            }

            $price = $this->get_product_price((int) $product_id);
            $stock = $this->get_product_stock((int) $product_id);
            if ($qty <= 0 || $stock <= 0) {
                continue;
            }

            $qty = min((int) $qty, $stock);
            $line_subtotal = $price * $qty;
            $subtotal += $line_subtotal;

            $rows[] = [
                'product_id' => (int) $product_id,
                'name' => get_the_title((int) $product_id),
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $line_subtotal,
            ];
        }

        $settings = $this->get_settings();
        $tax_rate = max(0, (float) $settings['tax_rate']);
        $shipping = max(0, (float) $settings['shipping_fee']);
        $tax = $subtotal * ($tax_rate / 100);
        $grand_total = $subtotal + $tax + $shipping;

        return [
            'rows' => $rows,
            'totals' => [
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'grand_total' => $grand_total,
            ],
        ];
    }

    public function render_shop(): string {
        $query = new WP_Query([
            'post_type' => 'ocss_product',
            'posts_per_page' => 24,
            'post_status' => 'publish',
        ]);

        ob_start();
        $this->render_notice();

        echo '<div class="ocss-grid">';

        if (!$query->have_posts()) {
            echo '<p>目前沒有商品，請到後台新增。</p>';
        }

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $price = $this->get_product_price((int) $id);
            $stock = $this->get_product_stock((int) $id);

            echo '<div class="ocss-card">';
            echo '<h3>' . esc_html(get_the_title()) . '</h3>';
            echo '<div>' . wp_kses_post(wp_trim_words(get_the_content(), 24)) . '</div>';
            echo '<p class="ocss-price">' . esc_html($this->money($price)) . '</p>';
            echo '<p class="ocss-help">庫存：' . esc_html((string) $stock) . '</p>';

            if ($stock > 0) {
                echo '<form method="post">';
                wp_nonce_field(self::ACTION_NONCE, self::ACTION_NONCE_FIELD);
                echo '<input type="hidden" name="ocss_action" value="add_to_cart">';
                echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $id) . '">';
                echo '<input type="number" name="qty" min="1" max="' . esc_attr((string) $stock) . '" value="1" style="width:80px;margin-right:8px">';
                echo '<button class="ocss-btn" type="submit">加入購物車</button>';
                echo '</form>';
            } else {
                echo '<p><strong>已售完</strong></p>';
            }

            echo '</div>';
        }

        wp_reset_postdata();
        echo '</div>';

        return (string) ob_get_clean();
    }

    public function render_cart(): string {
        $cart = $this->get_cart_data();
        $rows = $cart['rows'];
        $totals = $cart['totals'];

        ob_start();
        $this->render_notice();
        echo '<h2>購物車</h2>';

        if (empty($rows)) {
            echo '<p>購物車是空的。</p>';
            return (string) ob_get_clean();
        }

        echo '<table class="ocss-table"><thead><tr><th>商品</th><th>單價</th><th>數量</th><th>小計</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $row['name']) . '</td>';
            echo '<td>' . esc_html($this->money((float) $row['price'])) . '</td>';
            echo '<td>' . esc_html((string) $row['qty']) . '</td>';
            echo '<td>' . esc_html($this->money((float) $row['subtotal'])) . '</td>';
            echo '<td><form method="post">';
            wp_nonce_field(self::ACTION_NONCE, self::ACTION_NONCE_FIELD);
            echo '<input type="hidden" name="ocss_action" value="remove_from_cart">';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $row['product_id']) . '">';
            echo '<button class="ocss-btn ocss-btn-danger" type="submit">移除</button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<p class="ocss-total">小計：' . esc_html($this->money((float) $totals['subtotal'])) . '</p>';
        echo '<p class="ocss-total">稅金：' . esc_html($this->money((float) $totals['tax'])) . '</p>';
        echo '<p class="ocss-total">運費：' . esc_html($this->money((float) $totals['shipping'])) . '</p>';
        echo '<p class="ocss-total">總計：' . esc_html($this->money((float) $totals['grand_total'])) . '</p>';
        echo '<p><a class="ocss-btn" href="' . esc_url($this->get_checkout_url()) . '">前往結帳</a></p>';

        return (string) ob_get_clean();
    }

    public function render_checkout(): string {
        $cart = $this->get_cart_data();
        $rows = $cart['rows'];
        $totals = $cart['totals'];
        $settings = $this->get_settings();

        ob_start();
        $this->render_notice();
        echo '<h2>結帳</h2>';

        if (empty($rows)) {
            echo '<p>購物車是空的。</p>';
            return (string) ob_get_clean();
        }

        echo '<p class="ocss-total">總計：' . esc_html($this->money((float) $totals['grand_total'])) . '</p>';

        echo '<form method="post">';
        wp_nonce_field(self::ACTION_NONCE, self::ACTION_NONCE_FIELD);
        echo '<input type="hidden" name="ocss_action" value="checkout">';

        echo '<p><label>姓名<br><input type="text" name="ocss_name" required style="width:100%"></label></p>';
        echo '<p><label>Email<br><input type="email" name="ocss_email" required style="width:100%"></label></p>';
        echo '<p><label>電話<br><input type="text" name="ocss_phone" required style="width:100%"></label></p>';
        echo '<p><label>地址<br><textarea name="ocss_address" required style="width:100%"></textarea></label></p>';

        echo '<p><label>付款方式<br>';
        echo '<select name="ocss_payment" style="width:100%">';
        echo '<option value="cod">貨到付款 (COD)</option>';
        echo '<option value="bank_transfer">銀行匯款</option>';
        echo '</select></label></p>';

        echo '<p class="ocss-help">匯款資訊將在確認信中寄送。<br>' . nl2br(esc_html((string) $settings['bank_info'])) . '</p>';

        echo '<button class="ocss-btn" type="submit">送出訂單</button>';
        echo '</form>';

        return (string) ob_get_clean();
    }
}

new One_Click_Simple_Shop();
