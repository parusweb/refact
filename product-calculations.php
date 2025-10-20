<?php
/**
 * Модуль: Расчеты для товаров
 * Описание: Функции для расчета площади, цен и множителей товаров
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Извлечение площади из названия товара с учетом количества штук в упаковке
 * 
 * @param string $title Название товара
 * @param int|null $product_id ID товара
 * @return float|null Площадь в м² или null
 */
function extract_area_with_qty($title, $product_id = null) {
    $t = mb_strtolower($title, 'UTF-8');
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xC2\xA0", ' ', $t);

    // Паттерны для поиска площади
    $patterns = [
        '/\(?\s*(\d+(?:[.,-]\d+)?)\s*[мm](?:2|²)\b/u',
        '/\((\d+(?:[.,-]\d+)?)\s*[мm](?:2|²)\s*\/\s*\d+\s*(?:лист|упак|шт)\)/u',
        '/(\d+(?:[.,-]\d+)?)\s*[мm](?:2|²)\s*\/\s*(?:упак|лист|шт)\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $t, $m)) {
            $num = str_replace([',', '-'], '.', $m[1]);
            return (float) $num;
        }
    }

    // Паттерн для размеров в формате ширина*длина*высота с указанием упаковки
    if (preg_match('/(\d+)\*(\d+)\*(\d+).*?(\d+)\s*штуп/u', $t, $m)) {
        $width_mm = intval($m[1]);
        $length_mm = intval($m[2]);
        $height_mm = intval($m[3]);
        $qty = intval($m[4]);
        
        // Находим два наибольших размера (ширина и длина, исключаем толщину)
        $sizes = [$width_mm, $length_mm, $height_mm];
        rsort($sizes);
        $width = $sizes[0];
        $length = $sizes[1];
        
        if ($width > 0 && $length > 0) {
            $area_m2 = ($width / 1000) * ($length / 1000) * $qty;
            return round($area_m2, 3);
        }
    }

    // Паттерн ширина*длина с количеством
    if (preg_match('/(\d+)\*(\d+)\s*(?:мм|mm).*?(\d+)\s*(?:шт|лист)/u', $t, $m)) {
        $w = intval($m[1]);
        $l = intval($m[2]);
        $qty = intval($m[3]);
        if ($w > 0 && $l > 0) {
            return round(($w / 1000) * ($l / 1000) * $qty, 3);
        }
    }

    // Паттерн для одного листа в мм
    if (preg_match('/(\d+)\s*[хx*×]\s*(\d+)\s*(?:мм|mm)/u', $t, $m)) {
        $w = intval($m[1]);
        $l = intval($m[2]);
        if ($w > 0 && $l > 0) {
            return round(($w / 1000) * ($l / 1000), 3);
        }
    }

    return null;
}

/**
 * Получение множителя цены для товара
 * 
 * @param int $product_id ID товара
 * @return float Множитель цены
 */
function get_price_multiplier($product_id) {
    if (!$product_id) {
        return 1.0;
    }
    
    $multiplier = get_post_meta($product_id, '_price_multiplier', true);
    
    if ($multiplier === '' || $multiplier === false) {
        return 1.0;
    }
    
    $multiplier = floatval($multiplier);
    return $multiplier > 0 ? $multiplier : 1.0;
}

/**
 * Получение множителя категории товара
 * 
 * @param int $product_id ID товара
 * @return float Множитель категории
 */
function get_category_multiplier($product_id) {
    if (!$product_id) {
        return 1.0;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        return 1.0;
    }
    
    $category_ids = $product->get_category_ids();
    if (empty($category_ids)) {
        return 1.0;
    }
    
    // Берем первую категорию
    $category_id = $category_ids[0];
    $multiplier = get_term_meta($category_id, 'category_price_multiplier', true);
    
    if ($multiplier === '' || $multiplier === false) {
        return 1.0;
    }
    
    $multiplier = floatval($multiplier);
    return $multiplier > 0 ? $multiplier : 1.0;
}

/**
 * Получение итогового множителя с учетом категории и товара
 * 
 * @param int $product_id ID товара
 * @return float Итоговый множитель
 */
function get_final_multiplier($product_id) {
    $product_multiplier = get_price_multiplier($product_id);
    $category_multiplier = get_category_multiplier($product_id);
    
    return $product_multiplier * $category_multiplier;
}

/**
 * Применение множителя категории к цене
 * 
 * @param float $price Цена
 * @param WC_Product $product Товар
 * @return float Цена с множителем
 */
function apply_category_multiplier($price, $product) {
    if (!$price || !$product) {
        return $price;
    }
    
    $multiplier = get_final_multiplier($product->get_id());
    
    if ($multiplier != 1.0) {
        $price = $price * $multiplier;
    }
    
    return $price;
}

/**
 * Применение множителя к обычной цене товара
 */
add_filter('woocommerce_product_get_regular_price', function($price, $product) {
    return apply_category_multiplier($price, $product);
}, 10, 2);

/**
 * Применение множителя к цене со скидкой
 */
add_filter('woocommerce_product_get_sale_price', function($price, $product) {
    return apply_category_multiplier($price, $product);
}, 10, 2);

/**
 * Применение множителя к обычной цене вариации
 */
add_filter('woocommerce_product_variation_get_regular_price', function($price, $product) {
    return apply_category_multiplier($price, $product);
}, 10, 2);

/**
 * Применение множителя к цене со скидкой вариации
 */
add_filter('woocommerce_product_variation_get_sale_price', function($price, $product) {
    return apply_category_multiplier($price, $product);
}, 10, 2);
