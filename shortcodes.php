<?php
/**
 * Модуль: Шорткоды
 * Описание: Пользовательские шорткоды для вывода контента
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Шорткод для вывода калькулятора площади
 * Использование: [area_calculator product_id="123"]
 */
add_shortcode('area_calculator', 'shortcode_area_calculator');
function shortcode_area_calculator($atts) {
    
    $atts = shortcode_atts([
        'product_id' => 0
    ], $atts);
    
    $product_id = intval($atts['product_id']);
    
    if (!$product_id) {
        return '<p>Укажите ID товара</p>';
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return '<p>Товар не найден</p>';
    }
    
    $price = $product->get_price();
    
    ob_start();
    ?>
    <div class="parusweb-shortcode-calculator">
        <h3>Калькулятор для: <?php echo esc_html($product->get_title()); ?></h3>
        
        <div class="calculator-inputs">
            <input type="number" class="calc-width" placeholder="Ширина, м" step="0.01" min="0">
            <span>×</span>
            <input type="number" class="calc-length" placeholder="Длина, м" step="0.01" min="0">
        </div>
        
        <div class="calculator-result">
            <div>Площадь: <strong class="result-area">0 м²</strong></div>
            <div>Цена: <strong class="result-price"><?php echo wc_price(0); ?></strong></div>
        </div>
        
        <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="button">Добавить в корзину</a>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        const price = <?php echo $price; ?>;
        
        $('.calc-width, .calc-length').on('input', function() {
            const width = parseFloat($('.calc-width').val()) || 0;
            const length = parseFloat($('.calc-length').val()) || 0;
            const area = width * length;
            const total = area * price;
            
            $('.result-area').text(area.toFixed(2) + ' м²');
            $('.result-price').html('<?php echo get_woocommerce_currency_symbol(); ?>' + total.toFixed(2));
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * Шорткод для вывода товаров с множителем
 * Использование: [products_with_multiplier category="123" limit="10"]
 */
add_shortcode('products_with_multiplier', 'shortcode_products_with_multiplier');
function shortcode_products_with_multiplier($atts) {
    
    $atts = shortcode_atts([
        'category' => '',
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC'
    ], $atts);
    
    $args = [
        'post_type' => 'product',
        'posts_per_page' => intval($atts['limit']),
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
        'meta_query' => [
            [
                'key' => '_price_multiplier',
                'compare' => 'EXISTS'
            ]
        ]
    ];
    
    if (!empty($atts['category'])) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => intval($atts['category'])
            ]
        ];
    }
    
    $products = new WP_Query($args);
    
    if (!$products->have_posts()) {
        return '<p>Товары не найдены</p>';
    }
    
    ob_start();
    ?>
    <div class="parusweb-products-grid">
        <?php while ($products->have_posts()): $products->the_post(); ?>
            <?php
            global $product;
            $multiplier = get_final_multiplier(get_the_ID());
            $percent = $multiplier > 1 ? '+' . round(($multiplier - 1) * 100) . '%' : '';
            ?>
            <div class="product-item">
                <a href="<?php the_permalink(); ?>">
                    <?php echo $product->get_image(); ?>
                </a>
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <div class="price">
                    <?php echo $product->get_price_html(); ?>
                    <?php if ($percent): ?>
                        <span class="multiplier-badge"><?php echo $percent; ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="button">В корзину</a>
            </div>
        <?php endwhile; ?>
    </div>
    <?php
    wp_reset_postdata();
    
    return ob_get_clean();
}

/**
 * Шорткод для вывода категорий с иконками
 * Использование: [product_categories parent="0" columns="4"]
 */
add_shortcode('product_categories', 'shortcode_product_categories');
function shortcode_product_categories($atts) {
    
    $atts = shortcode_atts([
        'parent' => 0,
        'columns' => 4,
        'hide_empty' => true
    ], $atts);
    
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'parent' => intval($atts['parent']),
        'hide_empty' => $atts['hide_empty'] === 'true'
    ]);
    
    if (empty($categories) || is_wp_error($categories)) {
        return '<p>Категории не найдены</p>';
    }
    
    $columns = intval($atts['columns']);
    
    ob_start();
    ?>
    <div class="parusweb-categories-grid columns-<?php echo $columns; ?>">
        <?php foreach ($categories as $category): ?>
            <?php
            $icon = get_term_meta($category->term_id, 'category_icon', true);
            $color = get_term_meta($category->term_id, 'category_color', true);
            $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
            ?>
            <div class="category-item">
                <a href="<?php echo get_term_link($category); ?>">
                    <?php if ($thumbnail_id): ?>
                        <?php echo wp_get_attachment_image($thumbnail_id, 'medium'); ?>
                    <?php elseif ($icon): ?>
                        <span class="category-icon dashicons <?php echo esc_attr($icon); ?>" 
                              style="<?php echo $color ? 'color: ' . esc_attr($color) : ''; ?>"></span>
                    <?php endif; ?>
                    
                    <h3><?php echo esc_html($category->name); ?></h3>
                    
                    <?php if ($category->description): ?>
                        <p><?php echo esc_html($category->description); ?></p>
                    <?php endif; ?>
                    
                    <span class="count"><?php echo $category->count; ?> товаров</span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Шорткод для вывода информации о товаре
 * Использование: [product_info id="123" fields="title,price,area"]
 */
add_shortcode('product_info', 'shortcode_product_info');
function shortcode_product_info($atts) {
    
    $atts = shortcode_atts([
        'id' => 0,
        'fields' => 'title,price'
    ], $atts);
    
    $product_id = intval($atts['id']);
    
    if (!$product_id) {
        return '<p>Укажите ID товара</p>';
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return '<p>Товар не найден</p>';
    }
    
    $fields = array_map('trim', explode(',', $atts['fields']));
    
    ob_start();
    ?>
    <div class="parusweb-product-info">
        <?php foreach ($fields as $field): ?>
            <?php
            switch ($field) {
                case 'title':
                    echo '<div class="field-title"><strong>Название:</strong> ' . esc_html($product->get_title()) . '</div>';
                    break;
                    
                case 'price':
                    echo '<div class="field-price"><strong>Цена:</strong> ' . $product->get_price_html() . '</div>';
                    break;
                    
                case 'area':
                    $area = extract_area_with_qty($product->get_title(), $product_id);
                    if ($area) {
                        echo '<div class="field-area"><strong>Площадь:</strong> ' . number_format($area, 2) . ' м²</div>';
                    }
                    break;
                    
                case 'sku':
                    echo '<div class="field-sku"><strong>Артикул:</strong> ' . esc_html($product->get_sku()) . '</div>';
                    break;
                    
                case 'stock':
                    $stock = $product->get_stock_quantity();
                    $status = $product->is_in_stock() ? 'В наличии' : 'Нет в наличии';
                    echo '<div class="field-stock"><strong>Наличие:</strong> ' . $status;
                    if ($stock) {
                        echo ' (' . $stock . ')';
                    }
                    echo '</div>';
                    break;
                    
                case 'description':
                    echo '<div class="field-description"><strong>Описание:</strong> ' . wp_kses_post($product->get_short_description()) . '</div>';
                    break;
            }
            ?>
        <?php endforeach; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Шорткод для вывода списка товаров по ID
 * Использование: [products_by_ids ids="1,2,3,4"]
 */
add_shortcode('products_by_ids', 'shortcode_products_by_ids');
function shortcode_products_by_ids($atts) {
    
    $atts = shortcode_atts([
        'ids' => '',
        'columns' => 4
    ], $atts);
    
    if (empty($atts['ids'])) {
        return '<p>Укажите ID товаров</p>';
    }
    
    $ids = array_map('intval', explode(',', $atts['ids']));
    $columns = intval($atts['columns']);
    
    ob_start();
    ?>
    <div class="parusweb-products-grid columns-<?php echo $columns; ?>">
        <?php foreach ($ids as $product_id): ?>
            <?php
            $product = wc_get_product($product_id);
            if (!$product) continue;
            ?>
            <div class="product-item">
                <a href="<?php echo get_permalink($product_id); ?>">
                    <?php echo $product->get_image(); ?>
                </a>
                <h3><a href="<?php echo get_permalink($product_id); ?>"><?php echo $product->get_title(); ?></a></h3>
                <div class="price"><?php echo $product->get_price_html(); ?></div>
                <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="button">В корзину</a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    
    return ob_get_clean();
}
