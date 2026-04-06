<?php
/**
 * Plugin Name: One Click Simple Shop
 * Description: Minimal shopping site features for WordPress: products, cart, checkout (email order), and shortcodes.
 * Version: 1.1.0
 * Author: GPT-5.3-Codex
 */

if (!defined('ABSPATH')) {
    exit;
}

class One_Click_Simple_Shop {
    private const CART_KEY = 'ocss_cart';
    private const NONCE_ACTION = 'ocss_cart_action';
    private const NONCE_FIELD = 'ocss_nonce';

    public function __construct() {
        add_action('init', [$this, 'register_product_cpt']);
        add_action('init', [$this, 'register_order_cpt']);
        add_action('init', [$this, 'maybe_start_session'], 1);
        add_action('init', [$this, 'handle_actions']);
        add_shortcode('ocss_shop', [$this, 'render_shop']);
        add_shortcode('ocss_cart', [$this, 'render_cart']);
        add_shortcode('ocss_checkout', [$this, 'render_checkout']);
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('add_meta_boxes', [$this, 'add_price_metabox']);
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
        add_action('save_post_ocss_product', [$this, 'save_price_metabox']);
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
            'capability_type' => 'post',
        ]);
    }

    public function add_price_metabox(): void {
        add_meta_box(
            'ocss_price_box',
            'Product Price',
            [$this, 'render_price_metabox'],
            'ocss_product',
            'side',
            'default'
        );
    }

    public function add_order_meta_box(): void {
        add_meta_box(
            'ocss_order_box',
            'Order Details',
            [$this, 'render_order_meta_box'],
            'ocss_order',
            'normal',
            'default'
        );
    }

    public function render_order_meta_box(WP_Post $post): void {
        $name = get_post_meta($post->ID, '_ocss_customer_name', true);
        $email = get_post_meta($post->ID, '_ocss_customer_email', true);
        $phone = get_post_meta($post->ID, '_ocss_customer_phone', true);
        $address = get_post_meta($post->ID, '_ocss_customer_address', true);
        $total = (float) get_post_meta($post->ID, '_ocss_order_total', true);
        $items = get_post_meta($post->ID, '_ocss_order_items', true);
        $items = is_array($items) ? $items : [];

        echo '<p><strong>Customer:</strong> ' . esc_html((string) $name) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html((string) $email) . '</p>';
        echo '<p><strong>Phone:</strong> ' . esc_html((string) $phone) . '</p>';
        echo '<p><strong>Address:</strong><br>' . nl2br(esc_html((string) $address)) . '</p>';
        echo '<hr>';
        echo '<p><strong>Items</strong></p>';
        echo '<ul>';
        foreach ($items as $item) {
            $line = sprintf(
                '%s x %d = $%s',
                (string) ($item['name'] ?? ''),
                (int) ($item['qty'] ?? 0),
                number_format((float) ($item['subtotal'] ?? 0), 2)
            );
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul>';
        echo '<p><strong>Total:</strong> $' . esc_html(number_format($total, 2)) . '</p>';
    }

    public function render_price_metabox(WP_Post $post): void {
        wp_nonce_field('ocss_save_price', 'ocss_price_nonce');
        $price = get_post_meta($post->ID, '_ocss_price', true);
        echo '<label for="ocss_price">Price (USD)</label>';
        echo '<input type="number" step="0.01" min="0" id="ocss_price" name="ocss_price" value="' . esc_attr((string) $price) . '" style="width:100%;margin-top:8px;" />';
    }

    public function save_price_metabox(int $post_id): void {
        if (!isset($_POST['ocss_price_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ocss_price_nonce'])), 'ocss_save_price')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $price = isset($_POST['ocss_price']) ? (float) wp_unslash($_POST['ocss_price']) : 0;
        update_post_meta($post_id, '_ocss_price', max(0, $price));
    }

    public function add_admin_page(): void {
        add_submenu_page(
            'edit.php?post_type=ocss_product',
            'Shop Setup',
            'Shop Setup',
            'manage_options',
            'ocss-setup',
            [$this, 'render_admin_setup']
        );
    }

    public function render_admin_setup(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap"><h1>One Click Shop Setup</h1>';
        echo '<p>Activation auto-creates pages: Shop, Cart, Checkout.</p>';
        echo '<p>If needed, paste these shortcodes manually:</p>';
        echo '<ul>';
        echo '<li><code>[ocss_shop]</code> -> Shop page</li>';
        echo '<li><code>[ocss_cart]</code> -> Cart page</li>';
        echo '<li><code>[ocss_checkout]</code> -> Checkout page</li>';
        echo '</ul>';
        echo '<p>Orders are emailed to: <strong>' . esc_html(get_option('admin_email')) . '</strong></p>';
        echo '</div>';
    }

    public function enqueue_styles(): void {
        $css = '.ocss-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}.ocss-card{border:1px solid #ddd;padding:16px;border-radius:10px;background:#fff}.ocss-price{font-size:20px;font-weight:700}.ocss-btn{display:inline-block;padding:10px 14px;background:#111;color:#fff;border:none;border-radius:8px;text-decoration:none;cursor:pointer}.ocss-btn-danger{background:#a00}.ocss-table{width:100%;border-collapse:collapse}.ocss-table th,.ocss-table td{border-bottom:1px solid #ddd;padding:10px;text-align:left}.ocss-total{font-size:22px;font-weight:700;margin-top:14px}.ocss-alert{padding:10px 14px;border-radius:8px;background:#e8f7e8;margin:12px 0}';
        wp_register_style('ocss-inline-style', false);
        wp_enqueue_style('ocss-inline-style');
        wp_add_inline_style('ocss-inline-style', $css);
    }

    public function maybe_start_session(): void {
        if (!session_id()) {
            session_start();
        }
    }

    private function get_cart(): array {
        $cart = $_SESSION[self::CART_KEY] ?? [];
        return is_array($cart) ? $cart : [];
    }

    private function set_cart(array $cart): void {
        $_SESSION[self::CART_KEY] = $cart;
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

    private function verify_action_nonce(): bool {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return false;
        }

        return wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION) === 1;
    }

    public function handle_actions(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['ocss_action']) || !$this->verify_action_nonce()) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['ocss_action']));

        if ($action === 'add_to_cart') {
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $qty = isset($_POST['qty']) ? max(1, absint($_POST['qty'])) : 1;

            if (!$this->is_valid_product($product_id)) {
                wp_safe_redirect(add_query_arg('ocss_notice', 'invalid', wp_get_referer() ?: home_url('/')));
                exit;
            }

            $cart = $this->get_cart();
            $cart[$product_id] = ($cart[$product_id] ?? 0) + $qty;
            $this->set_cart($cart);
            wp_safe_redirect(add_query_arg('ocss_notice', 'added', wp_get_referer() ?: home_url('/')));
            exit;
        }

        if ($action === 'remove_from_cart') {
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $cart = $this->get_cart();
            unset($cart[$product_id]);
            $this->set_cart($cart);
            wp_safe_redirect(add_query_arg('ocss_notice', 'removed', wp_get_referer() ?: home_url('/')));
            exit;
        }

        if ($action === 'checkout') {
            $this->process_checkout();
        }
    }

    private function render_notice(): void {
        if (!isset($_GET['ocss_notice'])) {
            return;
        }

        $notice = sanitize_text_field(wp_unslash($_GET['ocss_notice']));
        $messages = [
            'added' => 'Added to cart ✅',
            'removed' => 'Removed from cart ✅',
            'ordered' => 'Order placed successfully ✅',
            'invalid' => 'Invalid product.',
        ];

        if (isset($messages[$notice])) {
            echo '<p class="ocss-alert"><strong>' . esc_html($messages[$notice]) . '</strong></p>';
        }
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
            echo '<p>No products yet. Add products in WP Admin > Products.</p>';
        }

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $price = (float) get_post_meta($id, '_ocss_price', true);

            echo '<div class="ocss-card">';
            echo '<h3>' . esc_html(get_the_title()) . '</h3>';
            echo '<div>' . wp_kses_post(wp_trim_words(get_the_content(), 20)) . '</div>';
            echo '<p class="ocss-price">$' . esc_html(number_format($price, 2)) . '</p>';
            echo '<form method="post">';
            wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
            echo '<input type="hidden" name="ocss_action" value="add_to_cart" />';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $id) . '" />';
            echo '<input type="number" name="qty" value="1" min="1" style="width:70px;margin-right:8px;" />';
            echo '<button class="ocss-btn" type="submit">Add to Cart</button>';
            echo '</form>';
            echo '</div>';
        }

        wp_reset_postdata();
        echo '</div>';

        return (string) ob_get_clean();
    }

    private function get_cart_rows(): array {
        $rows = [];
        $total = 0.0;

        foreach ($this->get_cart() as $product_id => $qty) {
            if (!$this->is_valid_product((int) $product_id)) {
                continue;
            }

            $price = (float) get_post_meta($product_id, '_ocss_price', true);
            $subtotal = $price * $qty;
            $total += $subtotal;
            $rows[] = [
                'product_id' => (int) $product_id,
                'name' => get_the_title($product_id),
                'qty' => (int) $qty,
                'subtotal' => $subtotal,
            ];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function render_cart(): string {
        $cart_data = $this->get_cart_rows();

        ob_start();
        $this->render_notice();

        echo '<h2>Your Cart</h2>';

        if (empty($cart_data['rows'])) {
            echo '<p>Cart is empty.</p>';
            return (string) ob_get_clean();
        }

        echo '<table class="ocss-table"><thead><tr><th>Product</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead><tbody>';
        foreach ($cart_data['rows'] as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $row['name']) . '</td>';
            echo '<td>' . esc_html((string) $row['qty']) . '</td>';
            echo '<td>$' . esc_html(number_format((float) $row['subtotal'], 2)) . '</td>';
            echo '<td>';
            echo '<form method="post">';
            wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
            echo '<input type="hidden" name="ocss_action" value="remove_from_cart" />';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $row['product_id']) . '" />';
            echo '<button class="ocss-btn ocss-btn-danger" type="submit">Remove</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="ocss-total">Total: $' . esc_html(number_format((float) $cart_data['total'], 2)) . '</p>';
        echo '<p><a class="ocss-btn" href="' . esc_url($this->get_checkout_url()) . '">Go to Checkout</a></p>';

        return (string) ob_get_clean();
    }

    public function render_checkout(): string {
        $cart_data = $this->get_cart_rows();

        ob_start();
        $this->render_notice();

        echo '<h2>Checkout</h2>';

        if (empty($cart_data['rows'])) {
            echo '<p>Your cart is empty.</p>';
            return (string) ob_get_clean();
        }

        echo '<p class="ocss-total">Order total: $' . esc_html(number_format((float) $cart_data['total'], 2)) . '</p>';
        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<input type="hidden" name="ocss_action" value="checkout" />';
        echo '<p><label>Name<br><input type="text" name="ocss_name" required style="width:100%"></label></p>';
        echo '<p><label>Email<br><input type="email" name="ocss_email" required style="width:100%"></label></p>';
        echo '<p><label>Phone<br><input type="text" name="ocss_phone" required style="width:100%"></label></p>';
        echo '<p><label>Address<br><textarea name="ocss_address" required style="width:100%"></textarea></label></p>';
        echo '<button class="ocss-btn" type="submit">Place Order</button>';
        echo '</form>';

        return (string) ob_get_clean();
    }

    private function process_checkout(): void {
        $cart_data = $this->get_cart_rows();
        if (empty($cart_data['rows'])) {
            wp_safe_redirect(add_query_arg('ocss_notice', 'invalid', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $name = isset($_POST['ocss_name']) ? sanitize_text_field(wp_unslash($_POST['ocss_name'])) : '';
        $email = isset($_POST['ocss_email']) ? sanitize_email(wp_unslash($_POST['ocss_email'])) : '';
        $phone = isset($_POST['ocss_phone']) ? sanitize_text_field(wp_unslash($_POST['ocss_phone'])) : '';
        $address = isset($_POST['ocss_address']) ? sanitize_textarea_field(wp_unslash($_POST['ocss_address'])) : '';

        if ($name === '' || $email === '' || $phone === '' || $address === '') {
            wp_safe_redirect(add_query_arg('ocss_notice', 'invalid', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $lines = [];
        foreach ($cart_data['rows'] as $row) {
            $lines[] = $row['name'] . ' x ' . $row['qty'] . ' = $' . number_format((float) $row['subtotal'], 2);
        }

        $order_id = wp_insert_post([
            'post_type' => 'ocss_order',
            'post_status' => 'publish',
            'post_title' => 'Order ' . current_time('mysql'),
        ]);

        if ($order_id && !is_wp_error($order_id)) {
            update_post_meta($order_id, '_ocss_customer_name', $name);
            update_post_meta($order_id, '_ocss_customer_email', $email);
            update_post_meta($order_id, '_ocss_customer_phone', $phone);
            update_post_meta($order_id, '_ocss_customer_address', $address);
            update_post_meta($order_id, '_ocss_order_items', $cart_data['rows']);
            update_post_meta($order_id, '_ocss_order_total', (float) $cart_data['total']);
        }

        $subject = 'New Shop Order - ' . current_time('mysql');
        $message = "Customer: {$name}\nEmail: {$email}\nPhone: {$phone}\nAddress: {$address}\n\nItems:\n" . implode("\n", $lines) . "\n\nTotal: $" . number_format((float) $cart_data['total'], 2);
        if ($order_id && !is_wp_error($order_id)) {
            $message .= "\n\nOrder ID: #" . $order_id;
        }

        wp_mail(get_option('admin_email'), $subject, $message);
        $this->set_cart([]);

        wp_safe_redirect(add_query_arg('ocss_notice', 'ordered', wp_get_referer() ?: home_url('/')));
        exit;
    }
}

new One_Click_Simple_Shop();
