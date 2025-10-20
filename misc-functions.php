<?php
/**
 * Модуль: Прочие функции
 * Описание: Различные вспомогательные функции
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Добавление поддержки WebP
 */
add_filter('mime_types', 'add_webp_support');
function add_webp_support($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}

/**
 * Русские окончания для чисел
 * 
 * @param int $number Число
 * @param array $forms Формы слова [один, два, пять]
 * @return string Слово с правильным окончанием
 */
function get_russian_plural_form($number, $forms) {
    $cases = [2, 0, 1, 1, 1, 2];
    return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}

/**
 * Форматирование номера телефона
 * 
 * @param string $phone Номер телефона
 * @return string Отформатированный номер
 */
function format_phone_number($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 11 && $phone[0] === '8') {
        $phone = '7' . substr($phone, 1);
    }
    
    if (strlen($phone) === 11) {
        return sprintf('+%s (%s) %s-%s-%s',
            substr($phone, 0, 1),
            substr($phone, 1, 3),
            substr($phone, 4, 3),
            substr($phone, 7, 2),
            substr($phone, 9, 2)
        );
    }
    
    return $phone;
}

/**
 * Получение склонения для единиц измерения
 */
function get_unit_declension($count, $unit) {
    $forms = [
        'шт' => ['штука', 'штуки', 'штук'],
        'м2' => ['квадратный метр', 'квадратных метра', 'квадратных метров'],
        'м.п.' => ['погонный метр', 'погонных метра', 'погонных метров'],
        'л' => ['литр', 'литра', 'литров'],
        'кг' => ['килограмм', 'килограмма', 'килограммов'],
        'упак' => ['упаковка', 'упаковки', 'упаковок']
    ];
    
    if (isset($forms[$unit])) {
        return get_russian_plural_form($count, $forms[$unit]);
    }
    
    return $unit;
}

/**
 * Безопасное получение значения из массива
 */
function array_get($array, $key, $default = null) {
    if (!is_array($array)) {
        return $default;
    }
    
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }
    
    return $default;
}

/**
 * Проверка является ли значение валидным числом
 */
function is_valid_number($value) {
    return is_numeric($value) && $value > 0;
}

/**
 * Форматирование площади
 */
function format_area($area) {
    if (!is_valid_number($area)) {
        return '0 м²';
    }
    
    return number_format($area, 2, ',', ' ') . ' м²';
}

/**
 * Форматирование размеров
 */
function format_dimensions($width, $length, $height = null) {
    $dims = number_format($width, 2) . ' × ' . number_format($length, 2);
    
    if ($height) {
        $dims .= ' × ' . number_format($height, 2);
    }
    
    return $dims . ' м';
}

/**
 * Получение ID товара из различных источников
 */
function get_product_id_from_context($product = null) {
    if (is_numeric($product)) {
        return intval($product);
    }
    
    if (is_a($product, 'WC_Product')) {
        return $product->get_id();
    }
    
    if (is_a($product, 'WP_Post')) {
        return $product->ID;
    }
    
    global $post;
    if ($post && $post->post_type === 'product') {
        return $post->ID;
    }
    
    return 0;
}

/**
 * Логирование для отладки (только для админов)
 */
function parusweb_debug_log($message, $data = null) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        $log_message = '[ParusWeb] ' . $message;
        
        if ($data !== null) {
            $log_message .= ': ' . print_r($data, true);
        }
        
        error_log($log_message);
    }
}

/**
 * Получение настройки плагина
 */
function get_parusweb_option($option, $default = false) {
    return get_option('parusweb_' . $option, $default);
}

/**
 * Обновление настройки плагина
 */
function update_parusweb_option($option, $value) {
    return update_option('parusweb_' . $option, $value);
}

/**
 * Проверка активности модуля
 */
function is_parusweb_module_active($module_id) {
    $enabled_modules = get_option('parusweb_enabled_modules', []);
    return in_array($module_id, $enabled_modules);
}

/**
 * Удаление пустых значений из массива
 */
function array_filter_empty($array) {
    return array_filter($array, function($value) {
        return !empty($value);
    });
}


/**
 * Округление до нужного количества знаков
 */
function round_price($price, $decimals = 2) {
    return round(floatval($price), $decimals);
}


/**
 * Форматирование даты на русском
 */
function format_russian_date($timestamp, $format = 'd F Y') {
    $months = [
        'January' => 'января',
        'February' => 'февраля',
        'March' => 'марта',
        'April' => 'апреля',
        'May' => 'мая',
        'June' => 'июня',
        'July' => 'июля',
        'August' => 'августа',
        'September' => 'сентября',
        'October' => 'октября',
        'November' => 'ноября',
        'December' => 'декабря'
    ];
    
    $date = date($format, $timestamp);
    
    return str_replace(array_keys($months), array_values($months), $date);
}

/**
 * Проверка является ли пользователь оптовым покупателем
 */
function is_wholesale_customer($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    return get_user_meta($user_id, 'is_wholesale_customer', true) === 'yes';
}

/**
 * Добавление параметров к URL
 */
function add_query_args_to_url($url, $args) {
    return add_query_arg($args, $url);
}

/**
 * Очистка кеша товара
 */
function clear_product_cache($product_id) {
    wp_cache_delete($product_id, 'product');
    wp_cache_delete('product-' . $product_id, 'products');
    
    wc_delete_product_transients($product_id);
}

/**
 * Получение всех категорий товара включая родительские
 */
function get_all_product_categories($product_id) {
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return [];
    }
    
    $category_ids = $product->get_category_ids();
    $all_categories = [];
    
    foreach ($category_ids as $cat_id) {
        $all_categories[] = $cat_id;
        
        // Получаем родительские категории
        $ancestors = get_ancestors($cat_id, 'product_cat');
        $all_categories = array_merge($all_categories, $ancestors);
    }
    
    return array_unique($all_categories);
}

/**
 * Проверка минимального/максимального количества товара
 */
function validate_product_quantity($product_id, $quantity) {
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return false;
    }
    
    $min_qty = get_post_meta($product_id, '_min_quantity', true);
    $max_qty = get_post_meta($product_id, '_max_quantity', true);
    
    if ($min_qty && $quantity < $min_qty) {
        return false;
    }
    
    if ($max_qty && $quantity > $max_qty) {
        return false;
    }
    
    return true;
}

add_filter('woocommerce_account_menu_items', function($items) {
    unset($items['cart']); // для меню аккаунта
    return $items;
}, 999);

add_filter('wp_nav_menu_items', function($items, $args) {
    // убираем "Cart" из всех меню
    $items = preg_replace('/<li[^>]*><a[^>]*href="[^"]*cart[^"]*"[^>]*>.*?<\/a><\/li>/i', '', $items);
    return $items;
}, 10, 2);