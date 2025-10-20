<?php
/**
 * Модуль: Отображение цен
 * Описание: Форматирование и отображение цен с учетом единиц измерения
 * Зависимости: product-calculations, category-helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Форматирование цены с единицей измерения
 */
add_filter('woocommerce_get_price_html', 'parusweb_format_price_with_unit', 10, 2);
function parusweb_format_price_with_unit($price_html, $product) {
    if (!$product) {
        return $price_html;
    }
    
    $product_id = $product->get_id();
    
    // Получаем единицу измерения
    if (function_exists('get_category_based_unit')) {
        $unit = get_category_based_unit($product_id);
    } else {
        $unit = get_post_meta($product_id, '_custom_unit', true);
    }
    
    // Определяем суффикс
    $suffix = '';
    
    if ($unit === 'м²') {
        $suffix = '/м²';
    } elseif ($unit === 'м.п.') {
        $suffix = '/м.п.';
    } elseif (function_exists('is_square_meter_category') && is_square_meter_category($product_id)) {
        $suffix = '/м²';
    } elseif (function_exists('is_running_meter_category') && is_running_meter_category($product_id)) {
        $suffix = '/м.п.';
    } elseif ($unit && $unit !== 'шт') {
        $suffix = '/' . $unit;
    }
    
    // Добавляем суффикс
    if ($suffix && !empty($price_html)) {
        // Убираем старые суффиксы если они есть
        $price_html = preg_replace('/\/(м²|м\.п\.|шт|лист)/', '', $price_html);
        
        // Добавляем суффикс перед закрывающим тегом
        $price_html = preg_replace(
            '/(<\/bdi>|<\/span>)(?!.*<\/bdi>|<\/span>)/i',
            '<small class="unit-suffix">' . $suffix . '</small>$1',
            $price_html,
            1
        );
    }
    
    return $price_html;
}

/**
 * Отображение информации о площади упаковки
 */
add_action('woocommerce_single_product_summary', 'parusweb_display_package_area', 12);
function parusweb_display_package_area() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    $title = $product->get_name();
    
    // Проверяем нужна ли информация о площади
    if (!function_exists('extract_area_with_qty')) {
        return;
    }
    
    $area_data = extract_area_with_qty($title, $product_id);
    
    if (empty($area_data['area']) || $area_data['area'] <= 0) {
        return;
    }
    
    $area = $area_data['area'];
    $qty = isset($area_data['qty']) ? $area_data['qty'] : 1;
    
    // Определяем единицу (упаковка или лист)
    $leaf_parent_id = 190;
    $leaf_children = array(191, 127, 94);
    $leaf_ids = array_merge(array($leaf_parent_id), $leaf_children);
    $is_leaf = has_term($leaf_ids, 'product_cat', $product_id);
    
    $unit = $is_leaf ? 'листа' : 'упаковки';
    
    echo '<div class="package-area-info" style="margin: 10px 0; padding: 10px; background: #e8f4f8; border-radius: 6px;">';
    echo '<span style="color: #666;">Площадь ' . esc_html($unit) . ':</span> ';
    echo '<strong style="color: #2271b1;">' . number_format($area, 2, ',', ' ') . ' м²</strong>';
    echo '</div>';
}

/**
 * Отображение цены за единицу в карточке товара
 */
add_action('woocommerce_single_product_summary', 'parusweb_display_unit_price_info', 11);
function parusweb_display_unit_price_info() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем тип товара
    $unit = '';
    if (function_exists('get_category_based_unit')) {
        $unit = get_category_based_unit($product_id);
    }
    
    // Показываем только для специфических единиц
    if ($unit !== 'м²' && $unit !== 'м.п.') {
        return;
    }
    
    $price = $product->get_price();
    
    // Применяем множитель если есть
    if (function_exists('get_final_multiplier')) {
        $multiplier = get_final_multiplier($product_id);
        if ($multiplier != 1.0) {
            $price = $price * $multiplier;
        }
    }
    
    $unit_label = ($unit === 'м²') ? 'за м²' : 'за погонный метр';
    
    echo '<div class="unit-price-info" style="margin: 10px 0; padding: 10px; background: #f0f8ff; border-radius: 6px;">';
    echo '<span style="color: #666;">Цена:</span> ';
    echo '<strong style="color: #2271b1; font-size: 18px;">' . wc_price($price) . '</strong> ';
    echo '<span style="color: #666;">' . esc_html($unit_label) . '</span>';
    echo '</div>';
}

/**
 * Форматирование цены в корзине
 */
add_filter('woocommerce_cart_item_price', 'parusweb_format_cart_item_price', 10, 3);
function parusweb_format_cart_item_price($price_html, $cart_item, $cart_item_key) {
    $product_id = $cart_item['product_id'];
    
    // Для товаров с кастомными расчетами показываем итоговую цену за единицу
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
add_filter('woocommerce_cart_item_subtotal', 'parusweb_format_cart_item_subtotal', 10, 3);
function parusweb_format_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    // Для товаров с кастомными расчетами показываем полную стоимость
    if (isset($cart_item['custom_area_calc'])) {
        $total = isset($cart_item['custom_area_calc']['grand_total']) 
            ? $cart_item['custom_area_calc']['grand_total'] 
            : $cart_item['custom_area_calc']['total_price'];
        return wc_price($total);
    }
    
    if (isset($cart_item['custom_multiplier_calc'])) {
        $total = isset($cart_item['custom_multiplier_calc']['grand_total']) 
            ? $cart_item['custom_multiplier_calc']['grand_total'] 
            : $cart_item['custom_multiplier_calc']['price'];
        return wc_price($total);
    }
    
    if (isset($cart_item['custom_running_meter_calc'])) {
        $total = isset($cart_item['custom_running_meter_calc']['grand_total']) 
            ? $cart_item['custom_running_meter_calc']['grand_total'] 
            : $cart_item['custom_running_meter_calc']['price'];
        return wc_price($total);
    }
    
    if (isset($cart_item['custom_square_meter_calc'])) {
        $total = isset($cart_item['custom_square_meter_calc']['grand_total']) 
            ? $cart_item['custom_square_meter_calc']['grand_total'] 
            : $cart_item['custom_square_meter_calc']['price'];
        return wc_price($total);
    }
    
    return $subtotal;
}

/**
 * Добавление информации о параметрах в корзине
 */
add_filter('woocommerce_get_item_data', 'parusweb_display_custom_item_data', 10, 2);
function parusweb_display_custom_item_data($item_data, $cart_item) {
    // Расчет по площади
    if (isset($cart_item['custom_area_calc'])) {
        $data = $cart_item['custom_area_calc'];
        
        if (!empty($data['width'])) {
            $item_data[] = array(
                'name' => 'Ширина',
                'value' => $data['width'] . ' м'
            );
        }
        
        if (!empty($data['length'])) {
            $item_data[] = array(
                'name' => 'Длина',
                'value' => $data['length'] . ' м'
            );
        }
        
        if (!empty($data['area'])) {
            $item_data[] = array(
                'name' => 'Площадь',
                'value' => number_format($data['area'], 2, ',', ' ') . ' м²'
            );
        }
        
        if (!empty($data['packs'])) {
            $item_data[] = array(
                'name' => 'Количество',
                'value' => $data['packs'] . ' ' . ($data['packs'] > 1 ? 'упаковок' : 'упаковка')
            );
        }
    }
    
    // Расчет по длине (погонные метры)
    if (isset($cart_item['custom_running_meter_calc'])) {
        $data = $cart_item['custom_running_meter_calc'];
        
        if (!empty($data['length'])) {
            $item_data[] = array(
                'name' => 'Длина',
                'value' => $data['length'] . ' м.п.'
            );
        }
        
        if (!empty($data['quantity'])) {
            $item_data[] = array(
                'name' => 'Количество',
                'value' => $data['quantity']
            );
        }
    }
    
    // Расчет по квадратным метрам
    if (isset($cart_item['custom_square_meter_calc'])) {
        $data = $cart_item['custom_square_meter_calc'];
        
        if (!empty($data['square_meters'])) {
            $item_data[] = array(
                'name' => 'Площадь',
                'value' => number_format($data['square_meters'], 2, ',', ' ') . ' м²'
            );
        }
    }
    
    // Данные покраски
    if (isset($cart_item['painting_service'])) {
        $painting = $cart_item['painting_service'];
        
        $item_data[] = array(
            'name' => 'Услуга покраски',
            'value' => $painting['name']
        );
        
        if (!empty($painting['color_code'])) {
            $item_data[] = array(
                'name' => 'Цвет',
                'value' => $painting['color_code']
            );
        }
    }
    
    return $item_data;
}

/**
 * CSS стили для единиц измерения
 */
add_action('wp_head', 'parusweb_unit_display_styles');
function parusweb_unit_display_styles() {
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
        flex-wrap: wrap;
    }
    
    .package-area-info {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .unit-suffix {
            font-size: 0.75em;
        }
        
        .unit-price-info,
        .package-area-info {
            font-size: 14px;
        }
    }
    </style>
    <?php
}