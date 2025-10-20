<?php
/**
 * Модуль: Отображение цен
 * Описание: Форматирование и отображение цен с учетом единиц измерения
 * Зависимости: product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Определение типа продажи товара
 */
function get_product_sale_type($product_id) {
    // Проверяем категории для погонных метров
    if (is_running_meter_category($product_id)) {
        return 'running_meter';
    }
    
    // Проверяем категории для квадратных метров
    if (is_square_meter_category($product_id)) {
        return 'square_meter';
    }
    
    // Проверяем флаги товара
    if (get_post_meta($product_id, '_sold_by_area', true) === 'yes') {
        return 'square_meter';
    }
    
    if (get_post_meta($product_id, '_sold_by_length', true) === 'yes') {
        return 'running_meter';
    }
    
    // Проверяем по единице измерения
    $unit = get_post_meta($product_id, '_custom_unit', true);
    if ($unit === 'м.п.') {
        return 'running_meter';
    }
    if ($unit === 'м2') {
        return 'square_meter';
    }
    
    return 'standard';
}

/**
 * Проверка категории погонных метров
 */
function is_running_meter_category($product_id) {
    return has_term([266, 271], 'product_cat', $product_id);
}

/**
 * Проверка категории квадратных метров
 */
function is_square_meter_category($product_id) {
    return has_term([270, 267, 268], 'product_cat', $product_id);
}

/**
 * Форматирование цены с единицей измерения
 */
add_filter('woocommerce_get_price_html', 'format_price_with_unit', 10, 2);
function format_price_with_unit($price_html, $product) {
    if (!$product) {
        return $price_html;
    }
    
    $product_id = $product->get_id();
    $sale_type = get_product_sale_type($product_id);
    $unit = get_post_meta($product_id, '_custom_unit', true);
    
    // Определяем суффикс единицы измерения
    $suffix = '';
    switch ($sale_type) {
        case 'square_meter':
            $suffix = '/м²';
            break;
        case 'running_meter':
            $suffix = '/м.п.';
            break;
        default:
            if ($unit) {
                $suffix = '/' . $unit;
            }
            break;
    }
    
    if ($suffix && is_shop() || is_product_category() || is_product()) {
        // Добавляем суффикс к каждой цене в HTML
        $price_html = preg_replace(
            '/(<\/bdi>)/i',
            '$1<small class="unit-suffix">' . esc_html($suffix) . '</small>',
            $price_html
        );
    }
    
    return $price_html;
}

/**
 * Отображение цены за единицу в карточке товара
 */
add_action('woocommerce_single_product_summary', 'display_unit_price_info', 11);
function display_unit_price_info() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    $sale_type = get_product_sale_type($product_id);
    
    if ($sale_type === 'standard') {
        return;
    }
    
    $price = $product->get_price();
    $multiplier = get_final_multiplier($product_id);
    
    if ($multiplier != 1.0) {
        $price = $price * $multiplier;
    }
    
    $unit_label = '';
    switch ($sale_type) {
        case 'square_meter':
            $unit_label = 'за м²';
            break;
        case 'running_meter':
            $unit_label = 'за погонный метр';
            break;
    }
    
    if ($unit_label) {
        echo '<div class="unit-price-info" style="margin: 10px 0; padding: 10px; background: #f0f8ff; border-radius: 6px;">';
        echo '<span style="color: #666;">Цена:</span> ';
        echo '<strong style="color: #2271b1; font-size: 18px;">' . wc_price($price) . '</strong> ';
        echo '<span style="color: #666;">' . esc_html($unit_label) . '</span>';
        echo '</div>';
    }
}

/**
 * Добавление информации о площади упаковки
 */
add_action('woocommerce_single_product_summary', 'display_package_area', 12);
function display_package_area() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    $title = $product->get_title();
    $area = extract_area_with_qty($title, $product_id);
    
    if (!$area) {
        return;
    }
    
    // Определяем единицу упаковки
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf = has_term($leaf_ids, 'product_cat', $product_id);
    
    $unit = $is_leaf ? 'листа' : 'упаковки';
    
    echo '<div class="package-area-info" style="margin: 10px 0; padding: 10px; background: #e8f4f8; border-radius: 6px;">';
    echo '<span style="color: #666;">Площадь ' . $unit . ':</span> ';
    echo '<strong style="color: #2271b1;">' . number_format($area, 2, ',', ' ') . ' м²</strong>';
    echo '</div>';
}

/**
 * Форматирование цены в корзине
 */
add_filter('woocommerce_cart_item_price', 'format_cart_item_price', 10, 3);
function format_cart_item_price($price_html, $cart_item, $cart_item_key) {
    $product_id = $cart_item['product_id'];
    $sale_type = get_product_sale_type($product_id);
    
    // Для товаров с кастомными расчетами показываем итоговую цену
    if (isset($cart_item['custom_area_calc'])) {
        $data = $cart_item['custom_area_calc'];
        $unit_price = $data['total_price'] / $data['packs'];
        return wc_price($unit_price);
    }
    
    if (isset($cart_item['custom_multiplier_calc'])) {
        $data = $cart_item['custom_multiplier_calc'];
        $unit_price = $data['price'] / $data['quantity'];
        return wc_price($unit_price);
    }
    
    if (isset($cart_item['custom_running_meter_calc'])) {
        $data = $cart_item['custom_running_meter_calc'];
        $unit_price = $data['price'] / $data['quantity'];
        return wc_price($unit_price);
    }
    
    if (isset($cart_item['custom_square_meter_calc'])) {
        $data = $cart_item['custom_square_meter_calc'];
        $unit_price = $data['price'] / $data['quantity'];
        return wc_price($unit_price);
    }
    
    return $price_html;
}

/**
 * Форматирование подытога в корзине
 */
add_filter('woocommerce_cart_item_subtotal', 'format_cart_item_subtotal', 10, 3);
function format_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    // Для товаров с кастомными расчетами показываем полную стоимость
    if (isset($cart_item['custom_area_calc'])) {
        $total = $cart_item['custom_area_calc']['grand_total'] ?? $cart_item['custom_area_calc']['total_price'];
        return wc_price($total);
    }
    
    if (isset($cart_item['custom_multiplier_calc'])) {
        $total = $cart_item['custom_multiplier_calc']['grand_total'] ?? $cart_item['custom_multiplier_calc']['price'];
        return wc_price($total);
    }
    
    if (isset($cart_item['custom_running_meter_calc'])) {
        $total = $cart_item['custom_running_meter_calc']['grand_total'] ?? $cart_item['custom_running_meter_calc']['price'];
        return wc_price($total);
    }
    
    if (isset($cart_item['custom_square_meter_calc'])) {
        $total = $cart_item['custom_square_meter_calc']['grand_total'] ?? $cart_item['custom_square_meter_calc']['price'];
        return wc_price($total);
    }
    
    return $subtotal;
}

/**
 * CSS для единиц измерения
 */
add_action('wp_head', 'add_unit_display_styles');
function add_unit_display_styles() {
    ?>
    <style>
    .unit-suffix {
        font-size: 0.85em;
        color: #666;
        font-weight: normal;
        margin-left: 3px;
    }
    
    .unit-price-info {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .package-area-info {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    @media (max-width: 768px) {
        .unit-suffix {
            font-size: 0.75em;
        }
    }
    </style>
    <?php
}

/**
 * Отображение единицы измерения в миникорзине
 */
add_filter('woocommerce_widget_cart_item_quantity', 'format_mini_cart_quantity', 10, 3);
function format_mini_cart_quantity($quantity_html, $cart_item, $cart_item_key) {
    $product_id = $cart_item['product_id'];
    $sale_type = get_product_sale_type($product_id);
    
    $quantity = $cart_item['quantity'];
    
    $unit = '';
    switch ($sale_type) {
        case 'square_meter':
            $unit = ' м²';
            break;
        case 'running_meter':
            $unit = ' м.п.';
            break;
        default:
            $custom_unit = get_post_meta($product_id, '_custom_unit', true);
            if ($custom_unit) {
                $unit = ' ' . $custom_unit;
            }
            break;
    }
    
    if ($unit) {
        $quantity_html = preg_replace(
            '/(<span class="quantity">.*?)(<\/span>)/i',
            '$1' . esc_html($unit) . '$2',
            $quantity_html
        );
    }
    
    return $quantity_html;
}

/**
 * Хук для корректного отображения в мини-корзине
 */
add_action('woocommerce_before_mini_cart', 'mini_cart_unit_styles');
function mini_cart_unit_styles() {
    ?>
    <style>
    .woocommerce-mini-cart-item .quantity {
        font-size: 0.9em;
    }
    </style>
    <?php
}