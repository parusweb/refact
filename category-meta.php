<?php
/**
 * Модуль: Метаданные категорий
 * Описание: Кастомные поля для категорий товаров
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Добавление полей при создании категории
 */
add_action('product_cat_add_form_fields', 'add_category_multiplier_field');
function add_category_multiplier_field() {
    ?>
    <div class="form-field">
        <label for="category_price_multiplier">Множитель цены категории</label>
        <input type="number" step="0.01" min="0" name="category_price_multiplier" id="category_price_multiplier" value="">
        <p class="description">Умножает цену всех товаров категории на указанное значение (например, 1.2 увеличит цену на 20%)</p>
    </div>
    
    <div class="form-field">
        <label for="category_icon">Иконка категории</label>
        <input type="text" name="category_icon" id="category_icon" value="" placeholder="dashicons-products">
        <p class="description">Класс иконки Dashicons (например: dashicons-hammer, dashicons-admin-home)</p>
    </div>
    
    <div class="form-field">
        <label for="category_color">Цвет категории</label>
        <input type="text" name="category_color" id="category_color" value="" class="color-picker">
        <p class="description">Цвет для отображения категории</p>
    </div>
    <?php
}

/**
 * Добавление полей при редактировании категории
 */
add_action('product_cat_edit_form_fields', 'edit_category_multiplier_field', 10, 1);
function edit_category_multiplier_field($term) {
    $multiplier = get_term_meta($term->term_id, 'category_price_multiplier', true);
    $icon = get_term_meta($term->term_id, 'category_icon', true);
    $color = get_term_meta($term->term_id, 'category_color', true);
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="category_price_multiplier">Множитель цены категории</label>
        </th>
        <td>
            <input type="number" step="0.01" min="0" name="category_price_multiplier" id="category_price_multiplier" value="<?php echo esc_attr($multiplier); ?>">
            <p class="description">Умножает цену всех товаров категории на указанное значение</p>
        </td>
    </tr>
    
    <tr class="form-field">
        <th scope="row">
            <label for="category_icon">Иконка категории</label>
        </th>
        <td>
            <input type="text" name="category_icon" id="category_icon" value="<?php echo esc_attr($icon); ?>" placeholder="dashicons-products">
            <p class="description">Класс иконки Dashicons</p>
        </td>
    </tr>
    
    <tr class="form-field">
        <th scope="row">
            <label for="category_color">Цвет категории</label>
        </th>
        <td>
            <input type="text" name="category_color" id="category_color" value="<?php echo esc_attr($color); ?>" class="color-picker">
            <p class="description">Цвет для отображения категории</p>
        </td>
    </tr>
    <?php
}

/**
 * Сохранение полей категории при создании
 */
add_action('created_product_cat', 'save_category_custom_fields', 10, 1);
function save_category_custom_fields($term_id) {
    if (isset($_POST['category_price_multiplier'])) {
        $multiplier = sanitize_text_field($_POST['category_price_multiplier']);
        update_term_meta($term_id, 'category_price_multiplier', $multiplier);
    }
    
    if (isset($_POST['category_icon'])) {
        $icon = sanitize_text_field($_POST['category_icon']);
        update_term_meta($term_id, 'category_icon', $icon);
    }
    
    if (isset($_POST['category_color'])) {
        $color = sanitize_hex_color($_POST['category_color']);
        update_term_meta($term_id, 'category_color', $color);
    }
}

/**
 * Сохранение полей категории при редактировании
 */
add_action('edited_product_cat', 'save_category_custom_fields', 10, 1);

/**
 * Добавление колонки множителя в список категорий
 */
add_filter('manage_edit-product_cat_columns', 'add_category_multiplier_column');
function add_category_multiplier_column($columns) {
    $new_columns = [];
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'name') {
            $new_columns['price_multiplier'] = 'Множитель';
            $new_columns['category_icon'] = 'Иконка';
        }
    }
    
    return $new_columns;
}

/**
 * Вывод значений в колонках категорий
 */
add_filter('manage_product_cat_custom_column', 'show_category_multiplier_column', 10, 3);
function show_category_multiplier_column($content, $column_name, $term_id) {
    if ($column_name === 'price_multiplier') {
        $multiplier = get_term_meta($term_id, 'category_price_multiplier', true);
        
        if ($multiplier && $multiplier != 1) {
            $content = '<span style="color: #2271b1; font-weight: 600;">×' . esc_html($multiplier) . '</span>';
        } else {
            $content = '<span style="color: #999;">—</span>';
        }
    }
    
    if ($column_name === 'category_icon') {
        $icon = get_term_meta($term_id, 'category_icon', true);
        $color = get_term_meta($term_id, 'category_color', true);
        
        if ($icon) {
            $style = $color ? 'color: ' . esc_attr($color) . ';' : '';
            $content = '<span class="dashicons ' . esc_attr($icon) . '" style="' . $style . '"></span>';
        } else {
            $content = '<span style="color: #999;">—</span>';
        }
    }
    
    return $content;
}

/**
 * Подключение color picker для категорий
 */
add_action('admin_enqueue_scripts', 'enqueue_category_scripts');
function enqueue_category_scripts($hook) {
    if ($hook !== 'edit-tags.php' && $hook !== 'term.php') {
        return;
    }
    
    global $taxnow;
    if ($taxnow !== 'product_cat') {
        return;
    }
    
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    
    wp_add_inline_script('wp-color-picker', '
        jQuery(document).ready(function($) {
            $(".color-picker").wpColorPicker();
        });
    ');
}

/**
 * Добавление метабокса с информацией о категории на странице товара
 */
add_action('add_meta_boxes', 'add_category_info_metabox');
function add_category_info_metabox() {
    add_meta_box(
        'category_multiplier_info',
        'Информация о множителях',
        'render_category_info_metabox',
        'product',
        'side',
        'low'
    );
}

/**
 * Вывод метабокса с информацией о множителях
 */
function render_category_info_metabox($post) {
    $product_multiplier = get_post_meta($post->ID, '_price_multiplier', true);
    $product = wc_get_product($post->ID);
    
    if (!$product) {
        echo '<p>Информация недоступна</p>';
        return;
    }
    
    $category_ids = $product->get_category_ids();
    
    echo '<div style="padding: 10px 0;">';
    
    // Множитель товара
    if ($product_multiplier && $product_multiplier != 1) {
        echo '<p><strong>Множитель товара:</strong> <span style="color: #2271b1;">×' . esc_html($product_multiplier) . '</span></p>';
    }
    
    // Множители категорий
    if (!empty($category_ids)) {
        echo '<p><strong>Категории:</strong></p><ul style="margin-left: 20px;">';
        
        foreach ($category_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            $cat_multiplier = get_term_meta($cat_id, 'category_price_multiplier', true);
            
            echo '<li>' . esc_html($term->name);
            
            if ($cat_multiplier && $cat_multiplier != 1) {
                echo ' <span style="color: #2271b1;">(×' . esc_html($cat_multiplier) . ')</span>';
            }
            
            echo '</li>';
        }
        
        echo '</ul>';
    }
    
    // Итоговый множитель
    $final_multiplier = get_final_multiplier($post->ID);
    if ($final_multiplier != 1) {
        echo '<hr style="margin: 10px 0;">';
        echo '<p><strong>Итоговый множитель:</strong> <span style="color: #46b450; font-size: 14px; font-weight: 600;">×' . esc_html($final_multiplier) . '</span></p>';
    }
    
    echo '</div>';
}