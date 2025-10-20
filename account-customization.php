<?php
/**
 * Модуль: Кастомизация личного кабинета
 * Описание: Настройки личного кабинета WooCommerce, меню, страницы
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Удаление пункта "Загрузки" из меню личного кабинета
 */
add_filter('woocommerce_account_menu_items', 'remove_my_account_downloads', 999);
function remove_my_account_downloads($items) {
    unset($items['downloads']);
    return $items;
}

/**
 * Переименование пункта меню "Адреса" в "Адрес доставки"
 */
add_filter('woocommerce_account_menu_items', function($items) {
    if (isset($items['edit-address'])) {
        $items['edit-address'] = 'Адрес доставки';
    }
    return $items;
});

/**
 * Убрать заголовок "Платёжный адрес", оставить только "Адрес доставки"
 */
add_filter('woocommerce_my_account_my_address_title', function($title, $address_type) {
    if ($address_type === 'billing') {
        return '';
    }
    if ($address_type === 'shipping') {
        return 'Адрес доставки';
    }
    return $title;
}, 10, 2);

/**
 * Скрыть блок платёжного адреса на странице адресов
 */
add_filter('woocommerce_my_account_get_addresses', function($addresses, $customer_id) {
    unset($addresses['billing']);
    return $addresses;
}, 10, 2);

/**
 * Добавление endpoint "Корзина" в меню личного кабинета
 */
add_action('init', function() {
    add_rewrite_endpoint('cart', EP_ROOT | EP_PAGES);
});

add_action('woocommerce_account_cart_endpoint', function() {
    echo do_shortcode('[woocommerce_cart]');
});

add_filter('woocommerce_account_menu_items', function($items) {
    $new_items = array();
    foreach ($items as $key => $value) {
        $new_items[$key] = $value;
        if ($key === 'dashboard') {
            $new_items['cart'] = 'Корзина';
        }
    }
    return $new_items;
});

/**
 * Кастомизация дашборда личного кабинета
 */
add_action('woocommerce_account_dashboard', function() {
    $current_user = wp_get_current_user();
    echo '<p>Добро пожаловать, ' . esc_html($current_user->display_name) . '!</p>';
});

/**
 * Кастомизация страницы заказов
 */
add_action('woocommerce_account_orders_endpoint', function() {
    // Дополнительный вывод на странице заказов
});

/**
 * Скрытие пункта "Выйти" из основного меню
 */
add_filter('woocommerce_account_menu_items', function($items) {
    if (isset($items['customer-logout'])) {
        unset($items['customer-logout']);
    }
    return $items;
});

/**
 * Добавление кнопки "Выйти" в конец дашборда
 */
add_filter('woocommerce_account_menu_items', function($items) {
    $logout_url = wc_get_account_endpoint_url('customer-logout');
    // Можно добавить кастомный вывод кнопки выхода
    return $items;
});

/**
 * Валидация регистрации - запрет на цифры в имени и фамилии
 */
add_filter('woocommerce_registration_errors', function($errors, $username, $email) {
    if (isset($_POST['billing_first_name'])) {
        $first_name = sanitize_text_field($_POST['billing_first_name']);
        if (preg_match('/\d/', $first_name)) {
            $errors->add('first_name_error', 'Имя не должно содержать цифры');
        }
    }
    
    if (isset($_POST['billing_last_name'])) {
        $last_name = sanitize_text_field($_POST['billing_last_name']);
        if (preg_match('/\d/', $last_name)) {
            $errors->add('last_name_error', 'Фамилия не должна содержать цифры');
        }
    }
    
    return $errors;
}, 10, 3);

/**
 * Кастомизация полей оформления заказа
 */
add_filter('woocommerce_checkout_fields', function($fields) {
    // Удаление поля "Компания"
    unset($fields['billing']['billing_company']);
    unset($fields['shipping']['shipping_company']);
    
    // Удаление поля "Адрес 2"
    unset($fields['billing']['billing_address_2']);
    unset($fields['shipping']['shipping_address_2']);
    
    // Изменение labels
    if (isset($fields['billing']['billing_first_name'])) {
        $fields['billing']['billing_first_name']['label'] = 'Имя';
    }
    
    if (isset($fields['billing']['billing_last_name'])) {
        $fields['billing']['billing_last_name']['label'] = 'Фамилия';
    }
    
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['label'] = 'Телефон';
    }
    
    return $fields;
});

/**
 * Отключение обязательности адреса доставки
 */
add_filter('woocommerce_cart_needs_shipping_address', '__return_true');

/**
 * Кастомизация формы редактирования аккаунта
 */
add_action('woocommerce_edit_account_form', function() {
    // Дополнительные поля в форме аккаунта
});

/**
 * Сохранение дополнительных данных аккаунта
 */
add_action('woocommerce_save_account_details', function($user_id) {
    // Сохранение дополнительных полей
});