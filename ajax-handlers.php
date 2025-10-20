<?php
/**
 * Модуль: AJAX обработчики
 * Описание: Обработчики AJAX запросов для калькуляторов и динамических расчетов
 * Зависимости: product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX расчет цены по площади
 */
add_action('wp_ajax_calculate_area_price', 'ajax_calculate_area_price');
add_action('wp_ajax_nopriv_calculate_area_price', 'ajax_calculate_area_price');
function ajax_calculate_area_price() {
    
    check_ajax_referer('parusweb_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $width = isset($_POST['width']) ? floatval($_POST['width']) : 0;
    $length = isset($_POST['length']) ? floatval($_POST['length']) : 0;
    
    if (!$product_id || $width <= 0 || $length <= 0) {
        wp_send_json_error(['message' => 'Некорректные данные']);
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Товар не найден']);
    }
    
    $area = $width * $length;
    $price_per_m2 = $product->get_price();
    $multiplier = get_final_multiplier($product_id);
    
    if ($multiplier != 1.0) {
        $price_per_m2 *= $multiplier;
    }
    
    $total_price = $area * $price_per_m2;
    
    wp_send_json_success([
        'area' => round($area, 2),
        'price_per_m2' => $price_per_m2,
        'total_price' => $total_price,
        'formatted_price' => wc_price($total_price),
        'multiplier' => $multiplier
    ]);
}

/**
 * AJAX расчет цены по длине
 */
add_action('wp_ajax_calculate_length_price', 'ajax_calculate_length_price');
add_action('wp_ajax_nopriv_calculate_length_price', 'ajax_calculate_length_price');
function ajax_calculate_length_price() {
    
    check_ajax_referer('parusweb_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $length = isset($_POST['length']) ? floatval($_POST['length']) : 0;
    
    if (!$product_id || $length <= 0) {
        wp_send_json_error(['message' => 'Некорректные данные']);
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Товар не найден']);
    }
    
    $price_per_meter = $product->get_price();
    $multiplier = get_final_multiplier($product_id);
    
    if ($multiplier != 1.0) {
        $price_per_meter *= $multiplier;
    }
    
    $total_price = $length * $price_per_meter;
    
    wp_send_json_success([
        'length' => round($length, 2),
        'price_per_meter' => $price_per_meter,
        'total_price' => $total_price,
        'formatted_price' => wc_price($total_price),
        'multiplier' => $multiplier
    ]);
}

/**
 * AJAX получение информации о товаре
 */
add_action('wp_ajax_get_product_info', 'ajax_get_product_info');
add_action('wp_ajax_nopriv_get_product_info', 'ajax_get_product_info');
function ajax_get_product_info() {
    
    check_ajax_referer('parusweb_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if (!$product_id) {
        wp_send_json_error(['message' => 'ID товара не указан']);
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Товар не найден']);
    }
    
    $title = $product->get_title();
    $area = extract_area_with_qty($title, $product_id);
    $multiplier = get_final_multiplier($product_id);
    $unit = get_post_meta($product_id, '_custom_unit', true);
    $sold_by_area = get_post_meta($product_id, '_sold_by_area', true);
    $sold_by_length = get_post_meta($product_id, '_sold_by_length', true);
    
    wp_send_json_success([
        'id' => $product_id,
        'title' => $title,
        'price' => $product->get_price(),
        'formatted_price' => $product->get_price_html(),
        'area' => $area,
        'multiplier' => $multiplier,
        'unit' => $unit,
        'sold_by_area' => $sold_by_area === 'yes',
        'sold_by_length' => $sold_by_length === 'yes',
        'in_stock' => $product->is_in_stock(),
        'stock_quantity' => $product->get_stock_quantity()
    ]);
}

/**
 * AJAX проверка наличия товара
 */
add_action('wp_ajax_check_product_stock', 'ajax_check_product_stock');
add_action('wp_ajax_nopriv_check_product_stock', 'ajax_check_product_stock');
function ajax_check_product_stock() {
    
    check_ajax_referer('parusweb_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 1;
    
    if (!$product_id) {
        wp_send_json_error(['message' => 'ID товара не указан']);
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Товар не найден']);
    }
    
    $is_in_stock = $product->is_in_stock();
    $stock_quantity = $product->get_stock_quantity();
    $backorders_allowed = $product->backorders_allowed();
    
    $available = true;
    $message = '';
    
    if (!$is_in_stock && !$backorders_allowed) {
        $available = false;
        $message = 'Товар закончился';
    } elseif ($stock_quantity !== null && $quantity > $stock_quantity && !$backorders_allowed) {
        $available = false;
        $message = 'В наличии только ' . $stock_quantity . ' ' . get_post_meta($product_id, '_custom_unit', true);
    }
    
    wp_send_json_success([
        'available' => $available,
        'in_stock' => $is_in_stock,
        'stock_quantity' => $stock_quantity,
        'message' => $message
    ]);
}

/**
 * AJAX массовое обновление множителей
 */
add_action('wp_ajax_bulk_update_multipliers', 'ajax_bulk_update_multipliers');
function ajax_bulk_update_multipliers() {
    
    check_ajax_referer('parusweb_admin_nonce', 'nonce');
    
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Недостаточно прав']);
    }
    
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
    $multiplier = isset($_POST['multiplier']) ? floatval($_POST['multiplier']) : 1.0;
    
    if (empty($product_ids) || $multiplier <= 0) {
        wp_send_json_error(['message' => 'Некорректные данные']);
    }
    
    $updated = 0;
    
    foreach ($product_ids as $product_id) {
        if (update_post_meta($product_id, '_price_multiplier', $multiplier)) {
            $updated++;
        }
    }
    
    wp_send_json_success([
        'updated' => $updated,
        'total' => count($product_ids),
        'message' => 'Обновлено товаров: ' . $updated
    ]);
}

/**
 * Добавление nonce для AJAX запросов
 */
add_action('wp_enqueue_scripts', 'add_ajax_nonce');
function add_ajax_nonce() {
    wp_localize_script('parusweb-frontend', 'paruswebAjax', [
        'nonce' => wp_create_nonce('parusweb_nonce')
    ]);
}

/**
 * Добавление админского nonce
 */
add_action('admin_enqueue_scripts', 'add_admin_ajax_nonce');
function add_admin_ajax_nonce() {
    wp_localize_script('parusweb-admin', 'paruswebAdminAjax', [
        'nonce' => wp_create_nonce('parusweb_admin_nonce')
    ]);
}
