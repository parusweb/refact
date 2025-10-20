<?php
/**
 * Модуль: Метаданные товаров
 * Описание: Кастомные поля товаров в админ-панели WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Добавление поля множителя цены в настройки товара
 */
add_action('woocommerce_product_options_pricing', 'add_price_multiplier_field');
function add_price_multiplier_field() {
    global $post;
    
    echo '<div class="options_group">';
    
    woocommerce_wp_text_input([
        'id' => '_price_multiplier',
        'label' => 'Множитель цены',
        'description' => 'Умножает цену товара на указанное значение (например, 1.2 увеличит цену на 20%)',
        'desc_tip' => true,
        'type' => 'number',
        'custom_attributes' => [
            'step' => '0.01',
            'min' => '0'
        ],
        'value' => get_post_meta($post->ID, '_price_multiplier', true)
    ]);
    
    echo '</div>';
}

/**
 * Сохранение множителя цены товара
 */
add_action('woocommerce_process_product_meta', 'save_price_multiplier_field');
function save_price_multiplier_field($post_id) {
    $multiplier = isset($_POST['_price_multiplier']) ? sanitize_text_field($_POST['_price_multiplier']) : '';
    
    if ($multiplier !== '') {
        update_post_meta($post_id, '_price_multiplier', $multiplier);
    } else {
        delete_post_meta($post_id, '_price_multiplier');
    }
}

/**
 * Добавление колонки с множителем в список товаров
 */
add_filter('manage_edit-product_columns', 'add_multiplier_column');
function add_multiplier_column($columns) {
    $new_columns = [];
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        // Добавляем колонку после названия
        if ($key === 'name') {
            $new_columns['price_multiplier'] = 'Множитель';
        }
    }
    
    return $new_columns;
}

/**
 * Вывод значения множителя в списке товаров
 */
add_action('manage_product_posts_custom_column', 'show_multiplier_column_content', 10, 2);
function show_multiplier_column_content($column, $post_id) {
    if ($column === 'price_multiplier') {
        $multiplier = get_post_meta($post_id, '_price_multiplier', true);
        
        if ($multiplier && $multiplier != 1) {
            echo '<span style="color: #2271b1; font-weight: 600;">×' . esc_html($multiplier) . '</span>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}

/**
 * Добавление общих полей товара
 */
add_action('woocommerce_product_options_general_product_data', 'add_custom_product_fields');
function add_custom_product_fields() {
    global $post;
    
    echo '<div class="options_group">';
    
    // Поле для единицы измерения
    woocommerce_wp_select([
        'id' => '_custom_unit',
        'label' => 'Единица измерения',
        'options' => [
            '' => 'Не указано',
            'м2' => 'м² (квадратный метр)',
            'м.п.' => 'м.п. (погонный метр)',
            'шт' => 'шт (штука)',
            'упак' => 'упак (упаковка)',
            'комплект' => 'комплект',
            'л' => 'л (литр)',
            'кг' => 'кг (килограмм)'
        ],
        'value' => get_post_meta($post->ID, '_custom_unit', true)
    ]);
    
    // Поле для коэффициента пересчета
    woocommerce_wp_text_input([
        'id' => '_unit_conversion_factor',
        'label' => 'Коэффициент пересчета',
        'description' => 'Для автоматического пересчета количества (например, для погонных метров)',
        'desc_tip' => true,
        'type' => 'number',
        'custom_attributes' => [
            'step' => '0.001',
            'min' => '0'
        ],
        'value' => get_post_meta($post->ID, '_unit_conversion_factor', true)
    ]);
    
    // Флаг продажи по площади
    woocommerce_wp_checkbox([
        'id' => '_sold_by_area',
        'label' => 'Продается по площади',
        'description' => 'Товар продается в м², цена рассчитывается автоматически',
        'value' => get_post_meta($post->ID, '_sold_by_area', true) ? 'yes' : 'no'
    ]);
    
    // Флаг продажи по длине
    woocommerce_wp_checkbox([
        'id' => '_sold_by_length',
        'label' => 'Продается по длине',
        'description' => 'Товар продается в погонных метрах',
        'value' => get_post_meta($post->ID, '_sold_by_length', true) ? 'yes' : 'no'
    ]);
    
    echo '</div>';
}

/**
 * Сохранение кастомных полей товара
 */
add_action('woocommerce_process_product_meta', 'save_custom_product_fields');
function save_custom_product_fields($post_id) {
    // Единица измерения
    $unit = isset($_POST['_custom_unit']) ? sanitize_text_field($_POST['_custom_unit']) : '';
    update_post_meta($post_id, '_custom_unit', $unit);
    
    // Коэффициент пересчета
    $conversion = isset($_POST['_unit_conversion_factor']) ? sanitize_text_field($_POST['_unit_conversion_factor']) : '';
    update_post_meta($post_id, '_unit_conversion_factor', $conversion);
    
    // Флаг продажи по площади
    $sold_by_area = isset($_POST['_sold_by_area']) ? 'yes' : 'no';
    update_post_meta($post_id, '_sold_by_area', $sold_by_area);
    
    // Флаг продажи по длине
    $sold_by_length = isset($_POST['_sold_by_length']) ? 'yes' : 'no';
    update_post_meta($post_id, '_sold_by_length', $sold_by_length);
}

/**
 * Добавление быстрого редактирования для множителя
 */
add_action('woocommerce_product_quick_edit_end', 'add_quick_edit_multiplier');
function add_quick_edit_multiplier() {
    ?>
    <div class="inline-edit-group">
        <label class="alignleft">
            <span class="title">Множитель цены</span>
            <span class="input-text-wrap">
                <input type="number" step="0.01" min="0" name="_price_multiplier" class="text" value="">
            </span>
        </label>
    </div>
    <?php
}

/**
 * JavaScript для быстрого редактирования
 */
add_action('admin_footer', 'add_quick_edit_script');
function add_quick_edit_script() {
    global $current_screen;
    
    if ('edit-product' !== $current_screen->id) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#the-list').on('click', '.editinline', function() {
            var post_id = $(this).closest('tr').attr('id').replace('post-', '');
            var multiplier = $('#post-' + post_id + ' .column-price_multiplier').text().trim();
            
            if (multiplier && multiplier !== '—') {
                multiplier = multiplier.replace('×', '');
                $('input[name="_price_multiplier"]').val(multiplier);
            } else {
                $('input[name="_price_multiplier"]').val('');
            }
        });
    });
    </script>
    <?php
}

/**
 * Сохранение быстрого редактирования
 */
add_action('woocommerce_product_quick_edit_save', 'save_quick_edit_multiplier');
function save_quick_edit_multiplier($product) {
    if (isset($_POST['_price_multiplier'])) {
        $multiplier = sanitize_text_field($_POST['_price_multiplier']);
        
        if ($multiplier !== '') {
            update_post_meta($product->get_id(), '_price_multiplier', $multiplier);
        } else {
            delete_post_meta($product->get_id(), '_price_multiplier');
        }
    }
}
