<?php
/**
 * Модуль: Category Helpers
 * Описание: Функции для работы с категориями товаров
 * Зависимости: нет
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Проверка категорий для услуг покраски
 * Категории: 87-93 (пиломатериалы), 190,191,127,94 (листовые), 265-271 (столярные)
 */
function is_in_painting_categories($product_id) {
    $painting_cats = array_merge(
        range(87, 93),  // Пиломатериалы
        array(190, 191, 127, 94),  // Листовые материалы
        range(265, 271)  // Столярные изделия
    );
    
    return has_term($painting_cats, 'product_cat', $product_id);
}

/**
 * Проверка категорий с множителем цены
 */
function is_in_multiplier_categories($product_id) {
    // Те же категории что и для покраски
    return is_in_painting_categories($product_id);
}

/**
 * Проверка категорий квадратных метров
 * Категории: 270, 267, 268
 */
function is_square_meter_category($product_id) {
    return has_term(array(270, 267, 268), 'product_cat', $product_id);
}

/**
 * Проверка категорий погонных метров
 * Категории: 266, 271
 */
function is_running_meter_category($product_id) {
    return has_term(array(266, 271), 'product_cat', $product_id);
}

/**
 * Проверка принадлежности к категории с учетом иерархии
 */
function product_in_category($product_id, $category_id) {
    $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    
    if (is_wp_error($terms) || empty($terms)) {
        return false;
    }
    
    // Прямая проверка
    if (in_array($category_id, $terms)) {
        return true;
    }
    
    // Проверка родительских категорий
    foreach ($terms as $term_id) {
        if (term_is_ancestor_of($category_id, $term_id, 'product_cat')) {
            return true;
        }
    }
    
    return false;
}

/**
 * Определение типа калькулятора для товара
 */
function get_calculator_type($product_id) {
    // Проверяем фальшбалки (категория 266)
    if (product_in_category($product_id, 266)) {
        $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
        if (is_array($shapes_data) && !empty($shapes_data)) {
            foreach ($shapes_data as $shape_info) {
                if (!empty($shape_info['enabled'])) {
                    return 'falsebalk';
                }
            }
        }
    }
    
    // Проверяем погонные метры
    if (is_running_meter_category($product_id)) {
        return 'running_meter';
    }
    
    // Проверяем квадратные метры
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
    
    // Проверяем наличие размеров в названии (123*456)
    $title = get_the_title($product_id);
    if (preg_match('/\d+\s*[х×*]\s*\d+/', $title)) {
        return 'dimensions';
    }
    
    return 'none';
}

/**
 * Получение единицы измерения товара на основе категории
 */
function get_category_based_unit($product_id) {
    if (is_square_meter_category($product_id)) {
        return 'м²';
    }
    
    if (is_running_meter_category($product_id)) {
        return 'м.п.';
    }
    
    // Проверяем листовые категории (190, 191, 127, 94)
    if (has_term(array(190, 191, 127, 94), 'product_cat', $product_id)) {
        return 'лист';
    }
    
    // Пробуем получить из метаданных товара
    $custom_unit = get_post_meta($product_id, '_custom_unit', true);
    if ($custom_unit) {
        return $custom_unit;
    }
    
    return 'шт';
}

/**
 * Получение форм склонения единицы измерения
 */
function get_unit_declension_forms($product_id) {
    $unit = get_category_based_unit($product_id);
    
    $forms = array(
        'м²' => array('м²', 'м²', 'м²'),
        'м.п.' => array('м.п.', 'м.п.', 'м.п.'),
        'лист' => array('лист', 'листа', 'листов'),
        'упаковка' => array('упаковка', 'упаковки', 'упаковок'),
        'шт' => array('штука', 'штуки', 'штук'),
    );
    
    return isset($forms[$unit]) ? $forms[$unit] : array('шт', 'шт', 'шт');
}

/**
 * Правильное склонение для количества
 */
function get_quantity_with_unit($quantity, $product_id) {
    $forms = get_unit_declension_forms($product_id);
    
    $cases = array(2, 0, 1, 1, 1, 2);
    $form_index = ($quantity % 100 > 4 && $quantity % 100 < 20) 
        ? 2 
        : $cases[min($quantity % 10, 5)];
    
    return $quantity . ' ' . $forms[$form_index];
}

/**
 * Получение всех родительских категорий товара
 */
function get_product_ancestor_categories($product_id) {
    $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    $all_categories = array();
    
    if (is_wp_error($categories) || empty($categories)) {
        return $all_categories;
    }
    
    foreach ($categories as $cat_id) {
        $all_categories[] = $cat_id;
        $ancestors = get_ancestors($cat_id, 'product_cat');
        $all_categories = array_merge($all_categories, $ancestors);
    }
    
    return array_unique($all_categories);
}

/**
 * Проверка нужен ли калькулятор для товара
 */
function needs_calculator($product_id) {
    return is_in_painting_categories($product_id) || 
           is_in_multiplier_categories($product_id) ||
           get_calculator_type($product_id) !== 'none';
}

/**
 * Получение настроек калькулятора для категории
 */
function get_category_calculator_settings($product_id) {
    $calc_type = get_calculator_type($product_id);
    
    $settings = array(
        'type' => $calc_type,
        'show_painting' => is_in_painting_categories($product_id),
        'has_multiplier' => is_in_multiplier_categories($product_id),
        'unit' => get_category_based_unit($product_id),
        'is_falsebalk' => $calc_type === 'falsebalk',
    );
    
    return $settings;
}