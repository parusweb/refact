<?php
/**
 * Paint Schemes Module
 * Модуль управления схемами покраски для WooCommerce
 * 
 * @package ParusWeb_Functions
 * @subpackage Modules
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Получение услуг покраски из ACF для конкретного товара
 */
function get_acf_painting_services($product_id) {
    // Проверяем включены ли услуги покраски глобально
    if (!get_field('painting_services_enabled', 'option')) {
        return array();
    }
    
    // Пробуем получить услуги для конкретного товара
    $services = get_field('dop_uslugi', $product_id);
    
    // Если нет - пробуем получить из категории
    if (!$services || !is_array($services)) {
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        
        if (!is_wp_error($categories) && !empty($categories)) {
            foreach ($categories as $cat_id) {
                $services = get_field('dop_uslugi', 'product_cat_' . $cat_id);
                if ($services && is_array($services)) {
                    break;
                }
            }
        }
    }
    
    // Если всё равно нет - берём глобальные услуги
    if (!$services || !is_array($services)) {
        $services = get_field('global_dop_uslugi', 'option');
    }
    
    return is_array($services) ? $services : array();
}

/**
 * Класс модуля схем покраски
 */
class PW_Paint_Schemes_Module {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_footer', array($this, 'render_paint_schemes_script'), 25);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_paint_data_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'integrate_with_painting_services'), 20, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_paint_data_to_order'), 10, 4);
        add_filter('woocommerce_order_item_display_meta_key', array($this, 'rename_color_meta_key'), 10, 3);
        add_filter('woocommerce_order_item_display_meta_value', array($this, 'display_color_image_in_order'), 10, 3);
    }
    
    /**
     * Очистка имени файла цвета от лишних суффиксов
     * 
     * @param string $filename Исходное имя файла
     * @return string Очищенное имя файла
     */
    public function clean_color_filename($filename) {
        $filename = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $filename);
        $filename = preg_replace('/[-_](180|kopiya|copy|1)$/i', '', $filename);
        
        $patterns = array(
            '/^img[_-]?(\d+)[-_].*$/i' => '$1',
            '/^(\d+)[-_]\d+$/i' => '$1',
            '/^[a-z]+[_-]?[a-z]*[_-]?(\d+)[-_]\d*$/i' => '$1',
            '/^([a-z]+)_dlya_pokraski[_-](\d+)$/i' => '$1_$2',
            '/^([a-z]+[_-]\d+[a-z0-9]+)[-_]\d+$/i' => '$1'
        );
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $filename)) {
                $filename = preg_replace($pattern, $replacement, $filename);
                break;
            }
        }
        
        $filename = preg_replace('/[-_]+/', '_', $filename);
        $filename = trim($filename, '-_');
        
        return $filename;
    }
    
    /**
     * Получение схем покраски для товара
     * 
     * @param int $product_id ID товара
     * @return array Массив схем покраски
     */
    public function get_product_paint_schemes($product_id) {
        $schemes = get_field('custom_schemes', $product_id);
        if (!empty($schemes) && is_array($schemes)) {
            return $schemes;
        }
        
        $terms = get_the_terms($product_id, 'product_cat');
        
        if (!$terms || is_wp_error($terms)) {
            return array();
        }
        
        usort($terms, function($a, $b) {
            if ($a->parent > 0 && $b->parent == 0) return -1;
            if ($b->parent > 0 && $a->parent == 0) return 1;
            return $b->term_id - $a->term_id;
        });
        
        foreach ($terms as $term) {
            $schemes = get_field('schemes', 'product_cat_' . $term->term_id);
            if (!empty($schemes) && is_array($schemes)) {
                return $schemes;
            }
            
            $schemes = get_field('custom_schemes', 'product_cat_' . $term->term_id);
            if (!empty($schemes) && is_array($schemes)) {
                return $schemes;
            }
        }
        
        foreach ($terms as $term) {
            $parent_id = $term->parent;
            while ($parent_id) {
                $parent_term = get_term($parent_id, 'product_cat');
                
                if (is_wp_error($parent_term)) {
                    break;
                }
                
                $schemes = get_field('schemes', 'product_cat_' . $parent_id);
                if (!empty($schemes) && is_array($schemes)) {
                    return $schemes;
                }
                
                $schemes = get_field('custom_schemes', 'product_cat_' . $parent_id);
                if (!empty($schemes) && is_array($schemes)) {
                    return $schemes;
                }
                
                $parent_id = $parent_term->parent;
            }
        }
        
        return array();
    }
    
    /**
     * Проверка возможности отображения цветов для товара
     * 
     * @param int $product_id ID товара
     * @return bool
     */
    private function can_show_colors($product_id) {
        if (function_exists('is_in_painting_categories')) {
            return is_in_painting_categories($product_id);
        }
        
        if (function_exists('is_in_target_categories')) {
            return is_in_target_categories($product_id);
        }
        
        $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        
        if (is_wp_error($product_categories) || empty($product_categories)) {
            return false;
        }
        
        $target_categories = array_merge(
            range(87, 93),
            array(190, 191, 127, 94),
            range(265, 271)
        );
        
        foreach ($product_categories as $cat_id) {
            if (in_array($cat_id, $target_categories)) {
                return true;
            }
            
            foreach ($target_categories as $target_cat_id) {
                if (function_exists('cat_is_ancestor_of') && cat_is_ancestor_of($target_cat_id, $cat_id)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Рендер JavaScript для схем покраски
     */
    public function render_paint_schemes_script() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        
        if (!$this->can_show_colors($product_id)) {
            return;
        }
        
        $schemes = $this->get_product_paint_schemes($product_id);
        
        if (!is_array($schemes)) {
            $schemes = array();
        }
        
        $schemes = array_filter($schemes, function($scheme) {
            return !empty($scheme['scheme_name']) && !empty($scheme['scheme_colors']) && is_array($scheme['scheme_colors']);
        });
        
        if (empty($schemes)) {
            return;
        }
        
        $this->output_javascript($schemes);
    }
    
    /**
     * Вывод JavaScript кода
     * 
     * @param array $schemes Массив схем
     */
    private function output_javascript($schemes) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkForPaintingBlock = setInterval(function() {
                const paintingBlock = document.getElementById('painting-services-block');
                if (paintingBlock) {
                    clearInterval(checkForPaintingBlock);
                    addPaintSchemesToBlock(paintingBlock);
                }
            }, 200);

            function addPaintSchemesToBlock(paintingBlock) {
                let schemesBlock = document.getElementById('paint-schemes-block');
                const schemes = <?php echo wp_json_encode($schemes); ?>;

                if (schemesBlock) {
                    schemesBlock.remove();
                }
                
                const html = createSchemesHTML();
                paintingBlock.insertAdjacentHTML('beforeend', html);
                schemesBlock = document.getElementById('paint-schemes-block');

                updateSchemeOptions(schemes);
                updateColorBlocks(schemes);
                setupSchemeHandlers(schemes);
            }

            function createSchemesHTML() {
                return `
                <div id="paint-schemes-block" style="display:none; margin-top:20px; border-top:1px solid #ddd; padding-top:15px;">
                    <h4>Цвет покраски</h4>
                    <div id="scheme-selector" style="margin-bottom:15px; display:block;">
                        <label style="display: block; margin-bottom: 10px;">
                            Схема покраски:
                            <select id="pm_scheme_select" style="width:100%; padding:5px; background:#fff; margin-top:5px;">
                                <option value="">Выберите схему</option>
                            </select>
                        </label>
                    </div>
                    
                    <div id="color-preview-container" style="display:none; margin-bottom:20px; padding:20px; background:#f5f5f5; border-radius:8px; border:2px solid #4CAF50;">
                        <div style="position:relative; margin-bottom:15px; text-align:center;">
                            <div style="position:relative; display:inline-block;">
                                <div style="border:3px solid #4CAF50; border-radius:8px; padding:5px; background:#fff; box-shadow:0 4px 12px rgba(76, 175, 80, 0.3);">
                                    <img id="color-preview-image" src="" alt="" style="height:200px; object-fit:cover; display:block; border-radius:4px;">
                                </div>
                                <div style="position:absolute; top:-10px; right:-10px; width:50px; height:50px; background:#4CAF50; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 8px rgba(0,0,0,0.2); border:3px solid #fff;">
                                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <p id="color-preview-scheme" style="margin:10px 0; font-weight:600; font-size:16px; color:#333; text-align:center;"></p>
                        <p id="color-preview-code" style="margin:10px 0; font-size:18px; font-weight:700; color:#4CAF50; text-align:center;"></p>
                        
                        <div style="text-align:center; margin-top:15px;">
                            <button type="button" id="change-color-btn" style="padding:10px 20px; background:#fff; border:2px solid #0073aa; color:#0073aa; border-radius:5px; cursor:pointer; font-weight:600; transition:all 0.3s;">
                                Выбрать другой цвет
                            </button>
                        </div>
                    </div>
                    
                    <div id="color-blocks-container"></div>
                </div>
                <style>
                    #pm_scheme_select,
                    input[type="text"],
                    input[type="number"],
                    select {
                        background-color: #ffffff !important;
                    }
                    .pm-color-option {
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                    }
                    .pm-color-option:hover {
                        transform: scale(1.1);
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                        z-index: 10;
                        position: relative;
                    }
                    .pm-color-option img {
                        transition: all 0.2s ease;
                    }
                    .pm-color-option input:checked + img {
                        border: 3px solid #4CAF50;
                        box-shadow: 0 0 0 2px #fff, 0 0 0 4px #4CAF50;
                    }
                    #change-color-btn:hover {
                        background:#0073aa;
                        color:#fff;
                        transform:scale(1.05);
                    }
                </style>
                `;
            }

            function normalizeSchemeSlug(scheme) {
                let slug = scheme.scheme_slug;
                if (!slug || slug === 'undefined' || slug === '') {
                    slug = scheme.scheme_name.toLowerCase()
                        .trim()
                        .replace(/[^\wа-яё0-9\s]/gi, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                }
                return slug;
            }

            function cleanColorFilename(filename) {
                filename = filename.replace(/\.(jpg|jpeg|png|webp|gif)$/i, '');
                filename = filename.replace(/[-_](180|kopiya|copy|1)$/i, '');
                
                const patterns = [
                    [/^img[_-]?(\d+)[-_].*$/i, '$1'],
                    [/^(\d+)[-_]\d+$/i, '$1'],
                    [/^[a-z]+[_-]?[a-z]*[_-]?(\d+)[-_]\d*$/i, '$1'],
                    [/^([a-z]+)_dlya_pokraski[_-](\d+)$/i, '$1_$2'],
                    [/^([a-z]+[_-]\d+[a-z0-9]+)[-_]\d+$/i, '$1']
                ];
                
                for (let [pattern, replacement] of patterns) {
                    if (pattern.test(filename)) {
                        filename = filename.replace(pattern, replacement);
                        break;
                    }
                }
                
                filename = filename.replace(/[-_]+/g, '_').replace(/^[-_]|[-_]$/g, '');
                return filename;
            }

            function updateSchemeOptions(schemes) {
                const select = document.getElementById('pm_scheme_select');
                if (!select) return;
                select.innerHTML = '<option value="">Выберите схему</option>';
                
                schemes.forEach(s => {
                    if (!s.scheme_name || s.scheme_name.trim() === '') return;
                    
                    const slug = normalizeSchemeSlug(s);
                    const opt = document.createElement('option');
                    opt.value = slug;
                    opt.textContent = s.scheme_name;
                    opt.dataset.name = s.scheme_name;
                    select.appendChild(opt);
                });
            }

            function updateColorBlocks(schemes) {
                const container = document.getElementById('color-blocks-container');
                if (!container) return;
                container.innerHTML = '';

                schemes.forEach(scheme => {
                    if (!scheme.scheme_name || scheme.scheme_name.trim() === '') return;
                    
                    const slug = normalizeSchemeSlug(scheme);
                    const name = scheme.scheme_name;
                    const colors = scheme.scheme_colors || [];
                    
                    if (!colors.length) return;

                    let html = `<div class="pm-paint-colors" data-scheme="${slug}" style="display:none; margin-bottom:15px;">
                                    <p><strong>${name}: выберите цвет</strong></p>
                                    <div style="display:flex; flex-wrap:wrap; gap:10px;">`;
                    colors.forEach(c => {
                        const rawFilename = c.url.split('/').pop();
                        const cleanFilename = cleanColorFilename(rawFilename);
                        const value = `${name} — ${cleanFilename}`;
                        html += `<label class="pm-color-option" style="cursor:pointer; border:2px solid transparent; border-radius:6px; overflow:hidden;">
                                    <input type="radio" name="pm_selected_color" 
                                           value="${value}" 
                                           data-filename="${cleanFilename}" 
                                           data-image="${c.url}"
                                           data-scheme="${name}" 
                                           style="display:none;" required>
                                    <img src="${c.url}" alt="${cleanFilename}" title="${cleanFilename}" style="width:50px;height:50px;object-fit:cover; display:block;">
                                </label>`;
                    });
                    html += '</div></div>';
                    container.insertAdjacentHTML('beforeend', html);
                });
            }

            function findMatchingScheme(schemes, serviceName) {
                if (!serviceName) return null;
                
                const validSchemes = schemes.filter(s => s.scheme_name && s.scheme_name.trim() !== '');
                if (validSchemes.length === 0) return null;
                
                let cleanServiceName = serviceName
                    .replace(/\s*\(\+.*?\)$/g, '')
                    .replace(/\s*\+.*$/g, '')
                    .toLowerCase()
                    .replace(/[^\wа-яё\s]/gi, '')
                    .trim();
                
                if (!cleanServiceName) return null;
                
                const wordsToRemove = ['столешницы', 'столешницу', 'изделия', 'изделие', 'доски', 'доску', 'материала', 'наличника', 'наличник'];
                let simplifiedServiceName = cleanServiceName;
                wordsToRemove.forEach(word => {
                    const regex = new RegExp('\\b' + word + '\\b', 'gi');
                    simplifiedServiceName = simplifiedServiceName.replace(regex, '').replace(/\s+/g, ' ').trim();
                });
                
                let found = validSchemes.find(s => {
                    let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                    return cleanSchemeName === cleanServiceName;
                });
                
                if (found) return found;
                
                found = validSchemes.find(s => {
                    let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                    return cleanSchemeName === simplifiedServiceName;
                });
                
                if (found) return found;
                
                let serviceWithoutPokraska = simplifiedServiceName.replace(/покр?аска\s*/gi, '').trim();
                found = validSchemes.find(s => {
                    let schemeWithoutPokraska = s.scheme_name.toLowerCase()
                        .replace(/[^\wа-яё\s]/gi, '')
                        .replace(/покр?аска\s*/gi, '')
                        .trim();
                    return schemeWithoutPokraska === serviceWithoutPokraska;
                });
                
                if (found) return found;
                
                const stopWords = ['покраска', 'покрасить', 'для', 'по', 'в', 'на', 'с', 'из'];
                const serviceWords = simplifiedServiceName.split(/\s+/).filter(word => 
                    word.length > 2 && !stopWords.includes(word)
                ).slice(0, 3);
                
                if (serviceWords.length > 0) {
                    found = validSchemes.find(s => {
                        let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                        const schemeWords = cleanSchemeName.split(/\s+/).filter(word => 
                            word.length > 2 && !stopWords.includes(word)
                        );
                        
                        let matchCount = 0;
                        for (let word of serviceWords) {
                            if (schemeWords.some(sw => sw.includes(word) || word.includes(sw))) {
                                matchCount++;
                            }
                        }
                        
                        return matchCount >= Math.min(2, serviceWords.length);
                    });
                    
                    if (found) return found;
                }
                
                return null;
            }

            function showColors(slug) {
                document.querySelectorAll('.pm-paint-colors').forEach(block => {
                    block.style.display = (block.dataset.scheme === slug) ? 'block' : 'none';
                });
                
                document.querySelectorAll('input[name="pm_selected_color"]').forEach(radio => {
                    radio.checked = false;
                });
                
                document.querySelectorAll('.pm-color-option').forEach(option => {
                    option.style.borderColor = 'transparent';
                    const img = option.querySelector('img');
                    if (img) {
                        img.style.border = '3px solid transparent';
                        img.style.boxShadow = 'none';
                    }
                });
                
                const previewContainer = document.getElementById('color-preview-container');
                const colorBlocksContainer = document.getElementById('color-blocks-container');
                
                if (previewContainer) previewContainer.style.display = 'none';
                if (colorBlocksContainer) colorBlocksContainer.style.display = 'block';
                
                document.getElementById('pm_selected_color_image').value = '';
                document.getElementById('pm_selected_color_filename').value = '';
            }

            function resetSchemeSelection() {
                const schemeSelect = document.getElementById('pm_scheme_select');
                if (schemeSelect) schemeSelect.value = '';
                
                document.getElementById('scheme-selector').style.display = 'block';
                document.getElementById('pm_selected_scheme_name').value = '';
                document.getElementById('pm_selected_scheme_slug').value = '';
                document.getElementById('pm_selected_color_image').value = '';
                document.getElementById('pm_selected_color_filename').value = '';
                
                document.querySelectorAll('.pm-paint-colors').forEach(b => b.style.display = 'none');
                document.querySelectorAll('input[name="pm_selected_color"]').forEach(r => r.checked = false);
                document.querySelectorAll('.pm-color-option').forEach(o => {
                    o.style.borderColor = 'transparent';
                    const img = o.querySelector('img');
                    if (img) {
                        img.style.border = '2px solid transparent';
                        img.style.boxShadow = 'none';
                    }
                });
                
                const previewContainer = document.getElementById('color-preview-container');
                if (previewContainer) previewContainer.style.display = 'none';
            }

            function setupSchemeHandlers(allSchemes) {
                const serviceSelect = document.getElementById('painting_service_select');
                const schemesBlock = document.getElementById('paint-schemes-block');
                const schemeSelect = document.getElementById('pm_scheme_select');
                const form = document.querySelector('form.cart');

                if (form && !form.querySelector('#pm_selected_scheme_name')) {
                    form.insertAdjacentHTML('beforeend', `
                        <input type="hidden" id="pm_selected_scheme_name" name="pm_selected_scheme_name" value="">
                        <input type="hidden" id="pm_selected_scheme_slug" name="pm_selected_scheme_slug" value="">
                        <input type="hidden" id="pm_selected_color_image" name="pm_selected_color_image" value="">
                        <input type="hidden" id="pm_selected_color_filename" name="pm_selected_color_filename" value="">
                    `);
                }

                if (serviceSelect) {
                    serviceSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const serviceName = selectedOption.text;

                        if (this.value && serviceName) {
                            const matchingScheme = findMatchingScheme(allSchemes, serviceName);

                            if (matchingScheme) {
                                const schemeSlug = normalizeSchemeSlug(matchingScheme);
                                document.getElementById('scheme-selector').style.display = 'none';
                                updateColorBlocks([matchingScheme]);
                                document.getElementById('pm_selected_scheme_name').value = matchingScheme.scheme_name;
                                document.getElementById('pm_selected_scheme_slug').value = schemeSlug;
                                schemesBlock.style.display = 'block';
                                setTimeout(() => showColors(schemeSlug), 100);
                            } else {
                                document.getElementById('scheme-selector').style.display = 'block';
                                updateSchemeOptions(allSchemes);
                                updateColorBlocks(allSchemes);
                                schemesBlock.style.display = 'block';
                                resetSchemeSelection();
                            }
                        } else {
                            schemesBlock.style.display = 'none';
                            resetSchemeSelection();
                        }
                    });
                }

                if (schemeSelect) {
                    schemeSelect.addEventListener('change', function() {
                        const selectedSlug = this.value;
                        const selectedName = this.options[this.selectedIndex].dataset.name || '';
                        
                        if (selectedSlug) {
                            document.getElementById('pm_selected_scheme_name').value = selectedName;
                            document.getElementById('pm_selected_scheme_slug').value = selectedSlug;
                            showColors(selectedSlug);
                        } else {
                            document.querySelectorAll('.pm-paint-colors').forEach(b => b.style.display = 'none');
                            document.getElementById('pm_selected_scheme_name').value = '';
                            document.getElementById('pm_selected_scheme_slug').value = '';
                        }
                    });
                }

                document.addEventListener('change', function(e) {
                    if (e.target.name === 'pm_selected_color') {
                        document.querySelectorAll('.pm-color-option').forEach(o => {
                            o.style.borderColor = 'transparent';
                            const img = o.querySelector('img');
                            if (img) {
                                img.style.border = '3px solid transparent';
                                img.style.boxShadow = 'none';
                            }
                        });
                        
                        const selectedOption = e.target.closest('.pm-color-option');
                        selectedOption.style.borderColor = '#4CAF50';
                        
                        const selectedImg = e.target.nextElementSibling;
                        if (selectedImg) {
                            const previewContainer = document.getElementById('color-preview-container');
                            const previewImage = document.getElementById('color-preview-image');
                            const previewScheme = document.getElementById('color-preview-scheme');
                            const previewCode = document.getElementById('color-preview-code');
                            
                            previewImage.src = selectedImg.src;
                            previewImage.alt = e.target.dataset.filename;
                            previewScheme.textContent = e.target.dataset.scheme;
                            previewCode.textContent = 'Код: ' + e.target.dataset.filename;
                            previewContainer.style.display = 'block';
                            
                            const colorBlocksContainer = document.getElementById('color-blocks-container');
                            if (colorBlocksContainer) colorBlocksContainer.style.display = 'none';
                            
                            const schemeSelector = document.getElementById('scheme-selector');
                            if (schemeSelector && schemeSelector.style.display !== 'none') {
                                previewContainer.dataset.schemeSelectorWasVisible = 'true';
                            }
                            
                            document.getElementById('pm_selected_color_image').value = selectedImg.src;
                            document.getElementById('pm_selected_color_filename').value = e.target.dataset.filename;
                        }
                    }
                });

                document.addEventListener('click', function(e) {
                    if (e.target.id === 'change-color-btn') {
                        const previewContainer = document.getElementById('color-preview-container');
                        const colorBlocksContainer = document.getElementById('color-blocks-container');
                        const schemeSelector = document.getElementById('scheme-selector');
                        
                        if (previewContainer) previewContainer.style.display = 'none';
                        if (colorBlocksContainer) colorBlocksContainer.style.display = 'block';
                        
                        if (schemeSelector && previewContainer.dataset.schemeSelectorWasVisible === 'true') {
                            schemeSelector.style.display = 'block';
                        }
                        
                        document.querySelectorAll('input[name="pm_selected_color"]').forEach(radio => {
                            radio.checked = false;
                        });
                        
                        document.querySelectorAll('.pm-color-option').forEach(option => {
                            option.style.borderColor = 'transparent';
                            const img = option.querySelector('img');
                            if (img) {
                                img.style.border = '3px solid transparent';
                                img.style.boxShadow = 'none';
                            }
                        });
                        
                        document.getElementById('pm_selected_color_image').value = '';
                        document.getElementById('pm_selected_color_filename').value = '';
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Добавление данных о покраске в корзину
     * 
     * @param array $cart_item_data Данные элемента корзины
     * @param int $product_id ID товара
     * @param int $variation_id ID вариации
     * @return array
     */
    public function add_paint_data_to_cart($cart_item_data, $product_id, $variation_id) {
        if (!empty($_POST['pm_selected_color_filename'])) {
            $cleaned_filename = $this->clean_color_filename(sanitize_text_field($_POST['pm_selected_color_filename']));
            $cart_item_data['pm_selected_color'] = $cleaned_filename;
            $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
        } elseif (!empty($_POST['pm_selected_color'])) {
            $color_value = sanitize_text_field($_POST['pm_selected_color']);
            if (strpos($color_value, ' — ') !== false) {
                $parts = explode(' — ', $color_value);
                $cleaned_filename = $this->clean_color_filename(end($parts));
            } else {
                $cleaned_filename = $this->clean_color_filename($color_value);
            }
            $cart_item_data['pm_selected_color'] = $cleaned_filename;
            $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
        }
        
        if (!empty($_POST['pm_selected_scheme_name'])) {
            $cart_item_data['pm_selected_scheme_name'] = sanitize_text_field($_POST['pm_selected_scheme_name']);
        }
        
        if (!empty($_POST['pm_selected_scheme_slug'])) {
            $cart_item_data['pm_selected_scheme_slug'] = sanitize_text_field($_POST['pm_selected_scheme_slug']);
        }
        
        if (!empty($_POST['pm_selected_color_image'])) {
            $cart_item_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
        }
        
        return $cart_item_data;
    }
    
    /**
     * Интеграция с услугами покраски
     * 
     * @param array $cart_item_data Данные элемента корзины
     * @param int $product_id ID товара
     * @param int $variation_id ID вариации
     * @return array
     */
    public function integrate_with_painting_services($cart_item_data, $product_id, $variation_id) {
        if (empty($_POST['painting_service_key']) || empty($cart_item_data['pm_selected_color'])) {
            return $cart_item_data;
        }
        
        $color = $cart_item_data['pm_selected_color'];
        $sources = array('custom_area_calc', 'custom_dimensions', 'card_pack_purchase', 'standard_pack_purchase');
        
        foreach ($sources as $key) {
            if (!empty($cart_item_data[$key]['painting_service'])) {
                $cart_item_data[$key]['painting_service']['color_code'] = $color;
                $cart_item_data[$key]['painting_service']['name_with_color'] = 
                    $cart_item_data[$key]['painting_service']['name'] . ' "' . $color . '"';
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Добавление данных в заказ
     * 
     * @param WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param WC_Order $order
     */
    public function add_paint_data_to_order($item, $cart_item_key, $values, $order) {
        if (!empty($values['pm_selected_color_image'])) {
            $item->add_meta_data('_pm_color_image_url', $values['pm_selected_color_image'], true);
        }
        
        if (!empty($values['pm_selected_color'])) {
            $item->add_meta_data('Код цвета', $values['pm_selected_color'], true);
        }
    }
    
    /**
     * Переименование ключа мета-данных
     * 
     * @param string $display_key
     * @param WC_Meta_Data $meta
     * @param WC_Order_Item_Product $item
     * @return string
     */
    public function rename_color_meta_key($display_key, $meta, $item) {
        if ($meta->key === '_pm_color_image_url') {
            return 'Образец цвета';
        }
        return $display_key;
    }
    
    /**
     * Отображение изображения цвета
     * 
     * @param string $display_value
     * @param WC_Meta_Data $meta
     * @param WC_Order_Item_Product $item
     * @return string
     */
    public function display_color_image_in_order($display_value, $meta, $item) {
        if ($meta->key === '_pm_color_image_url') {
            $image_url = $meta->value;
            return '<img src="' . esc_url($image_url) . '" style="width:60px; height:60px; object-fit:cover; border:2px solid #ddd; border-radius:4px; display:block; margin-top:5px;" alt="Образец цвета">';
        }
        return $display_value;
    }
}

/**
 * Глобальные функции-хелперы
 */

/**
 * Получить схемы покраски для товара
 * 
 * @param int $product_id ID товара
 * @return array
 */
function pw_get_product_paint_schemes($product_id) {
    return PW_Paint_Schemes_Module::instance()->get_product_paint_schemes($product_id);
}

/**
 * Очистить имя файла цвета
 * 
 * @param string $filename Имя файла
 * @return string
 */
function pw_clean_color_filename($filename) {
    return PW_Paint_Schemes_Module::instance()->clean_color_filename($filename);
}

// Инициализация модуля
PW_Paint_Schemes_Module::instance();