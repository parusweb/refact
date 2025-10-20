<?php
/**
 * Модуль: Вспомогательные функции категорий
 * Описание: Проверка принадлежности товаров к специальным категориям
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Проверка принадлежности товара к категориям покраски
 */
function is_in_painting_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    $target_categories = array_merge(
        range(87, 93),
        [190, 191, 127, 94],
        range(265, 271)
    );
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Проверка целевых категорий (общая)
 */
function is_in_target_categories($product_id) {
    return is_in_painting_categories($product_id);
}

/**
 * Проверка категорий с множителем (265-268, 270-271)
 */
function is_in_multiplier_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    $target_categories = [265, 266, 267, 268, 270, 271];
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Проверка категории квадратных метров
 */
function is_square_meter_category($product_id) {
    return has_term([270, 267, 268], 'product_cat', $product_id);
}

/**
 * Проверка категории погонных метров
 */
function is_running_meter_category($product_id) {
    return has_term([266, 271], 'product_cat', $product_id);
}

/**
 * Проверка принадлежности к категории с учетом иерархии
 */
function product_in_category($product_id, $category_id) {
    $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    if (is_wp_error($terms) || empty($terms)) {
        return false;
    }
    
    if (in_array($category_id, $terms)) {
        return true;
    }
    
    foreach ($terms as $term_id) {
        if (term_is_ancestor_of($category_id, $term_id, 'product_cat')) {
            return true;
        }
    }
    
    return false;
}

/**
 * Получение всех родительских категорий товара
 */
function get_product_ancestor_categories($product_id) {
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    $all_categories = [];
    
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
 * Проверка листовых категорий (листы, а не упаковки)
 */
function is_leaf_category($product_id) {
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    
    return has_term($leaf_ids, 'product_cat', $product_id);
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
    
    if (is_leaf_category($product_id)) {
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
    
    $forms = [
        'м²' => ['м²', 'м²', 'м²'],
        'м.п.' => ['м.п.', 'м.п.', 'м.п.'],
        'лист' => ['лист', 'листа', 'листов'],
        'упаковка' => ['упаковка', 'упаковки', 'упаковок'],
        'шт' => ['штука', 'штуки', 'штук'],
    ];
    
    return $forms[$unit] ?? ['шт', 'шт', 'шт'];
}

/**
 * Правильное склонение для количества
 */
function get_quantity_with_unit($quantity, $product_id) {
    $forms = get_unit_declension_forms($product_id);
    
    $cases = [2, 0, 1, 1, 1, 2];
    $form_index = ($quantity % 100 > 4 && $quantity % 100 < 20) 
        ? 2 
        : $cases[min($quantity % 10, 5)];
    
    return $quantity . ' ' . $forms[$form_index];
}

/**
 * Проверка нужны ли калькуляторы для товара
 */
function needs_calculator($product_id) {
    return is_in_target_categories($product_id) || 
           is_in_multiplier_categories($product_id);
}

/**
 * Определение типа калькулятора для товара
 */
function get_calculator_type($product_id) {
    if (is_running_meter_category($product_id)) {
        // Проверяем фальшбалки отдельно
        if (product_in_category($product_id, 266)) {
            $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_info) {
                    if (!empty($shape_info['enabled'])) {
                        return 'falsebalk';
                    }
                }
            }
        }
        return 'running_meter';
    }
    
    if (is_square_meter_category($product_id)) {
        return 'square_meter';
    }
    
    // Проверяем флаги товара
    if (get_post_meta($product_id, '_sold_by_area', true) === 'yes') {
        return 'area';
    }
    
    if (get_post_meta($product_id, '_sold_by_length', true) === 'yes') {
        return 'running_meter';
    }
    
    // Проверяем наличие размеров в названии
    $title = get_the_title($product_id);
    if (preg_match('/\d+\*\d+/', $title)) {
        return 'dimensions';
    }
    
    return 'none';
}

/**
 * Получение настроек калькулятора для категории
 */
function get_category_calculator_settings($product_id) {
    $calc_type = get_calculator_type($product_id);
    
    $settings = [
        'type' => $calc_type,
        'show_painting' => is_in_painting_categories($product_id),
        'show_faska' => false,
        'unit' => get_category_based_unit($product_id)
    ];
    
    // Проверяем наличие фасок
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    foreach ($categories as $cat_id) {
        $faska_types = get_term_meta($cat_id, 'faska_types', true);
        if (!empty($faska_types)) {
            $settings['show_faska'] = true;
            $settings['faska_types'] = $faska_types;
            break;
        }
    }
    
    return $settings;
}