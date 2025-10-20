<?php
// === Функция очистки имени файла цвета ===
function pm_clean_color_filename($filename) {
    // Убираем расширение
    $filename = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $filename);
    
    // Убираем суффиксы типа -180, -1, -kopiya, _180 и т.д.
    $filename = preg_replace('/[-_](180|kopiya|copy|1)$/i', '', $filename);
    
    // Шаблоны для извлечения только кода цвета
    $patterns = [
        '/^img[_-]?(\d+)[-_].*$/i' => '$1',           // img_6607-kopiya-1 -> 6607
        '/^(\d+)[-_]\d+$/i' => '$1',                  // 5074-1 -> 5074
        '/^[a-z]+[_-]?[a-z]*[_-]?(\d+)[-_]\d*$/i' => '$1', // osk_tm_3032_1 -> 3032
        '/^([a-z]+)_dlya_pokraski[_-](\d+)$/i' => '$1_$2', // vosk_dlya_pokraski_18 -> vosk_18
        '/^([a-z]+[_-]\d+[a-z0-9]+)[-_]\d+$/i' => '$1'     // ncss_2005y80r-1 -> ncss_2005y80r
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $filename)) {
            $filename = preg_replace($pattern, $replacement, $filename);
            break;
        }
    }
    
    // Финальная очистка
    $filename = preg_replace('/[-_]+/', '_', $filename);
    $filename = trim($filename, '-_');
    
    return $filename;
}

// === Функция получения схем покраски товара ===
function pm_get_product_paint_schemes($product_id) {
    error_log('=== PM Paint Schemes DEBUG START for product #' . $product_id . ' ===');
    
    // 1. Проверяем индивидуальные схемы товара
    $schemes = get_field('custom_schemes', $product_id);
    if (!empty($schemes)) {
        error_log('✓ Found PRODUCT schemes: ' . count($schemes) . ' schemes');
        error_log('  Scheme names: ' . implode(', ', array_column($schemes, 'scheme_name')));
        error_log('=== PM Paint Schemes DEBUG END ===');
        return $schemes;
    }
    error_log('✗ No product-level schemes found');

    // 2. Получаем ВСЕ категории товара
    $terms = get_the_terms($product_id, 'product_cat');
    
    if (!$terms || is_wp_error($terms)) {
        error_log('✗ No categories found');
        error_log('=== PM Paint Schemes DEBUG END ===');
        return [];
    }
    
    error_log('Product categories: ' . count($terms) . ' categories');
    foreach ($terms as $term) {
        error_log('  - Category #' . $term->term_id . ': "' . $term->name . '" (parent: ' . $term->parent . ')');
    }
    
    // 3. ВАЖНО: Сортируем категории - СНАЧАЛА дочерние (с parent), ПОТОМ родительские (parent=0)
    usort($terms, function($a, $b) {
        // Если у $a есть parent, а у $b нет - $a идёт первым
        if ($a->parent > 0 && $b->parent == 0) return -1;
        // Если у $b есть parent, а у $a нет - $b идёт первым  
        if ($b->parent > 0 && $a->parent == 0) return 1;
        // Если оба с parent или оба без - сортируем по ID (меньший ID = более глубокая вложенность обычно)
        return $b->term_id - $a->term_id; // Больший ID обычно = более новая/конкретная категория
    });
    
    error_log('Categories order after sort:');
    foreach ($terms as $term) {
        error_log('  - #' . $term->term_id . ': "' . $term->name . '" (parent: ' . $term->parent . ')');
    }
    
    // 4. Проверяем категории в новом порядке (дочерние первыми!)
    foreach ($terms as $term) {
        error_log('→ Checking category #' . $term->term_id . ': "' . $term->name . '" (parent: ' . $term->parent . ')');
        
        // Проверяем поле 'schemes'
        $schemes = get_field('schemes', 'product_cat_' . $term->term_id);
        error_log('  Checking field "schemes" for product_cat_' . $term->term_id);
        if ($schemes !== false && $schemes !== null) {
            error_log('  Field "schemes" exists, value type: ' . gettype($schemes));
            if (is_array($schemes)) {
                error_log('  Array size: ' . count($schemes));
                foreach ($schemes as $idx => $scheme) {
                    error_log('    Scheme[' . $idx . ']: name="' . ($scheme['scheme_name'] ?? 'EMPTY') . '", colors=' . count($scheme['scheme_colors'] ?? []));
                }
            }
        } else {
            error_log('  Field "schemes" is null or false');
        }
        
        if (!empty($schemes)) {
            error_log('  ✓ Found in "schemes" field: ' . count($schemes) . ' schemes');
            error_log('  ✓ USING THIS CATEGORY!');
            error_log('=== PM Paint Schemes DEBUG END ===');
            return $schemes;
        }
        
        // Проверяем поле 'custom_schemes'
        $schemes = get_field('custom_schemes', 'product_cat_' . $term->term_id);
        error_log('  Checking field "custom_schemes" for product_cat_' . $term->term_id);
        if ($schemes !== false && $schemes !== null) {
            error_log('  Field "custom_schemes" exists, value type: ' . gettype($schemes));
            if (is_array($schemes)) {
                error_log('  Array size: ' . count($schemes));
                foreach ($schemes as $idx => $scheme) {
                    error_log('    Scheme[' . $idx . ']: name="' . ($scheme['scheme_name'] ?? 'EMPTY') . '", colors=' . count($scheme['scheme_colors'] ?? []));
                }
            }
        } else {
            error_log('  Field "custom_schemes" is null or false');
        }
        
        if (!empty($schemes)) {
            error_log('  ✓ Found in "custom_schemes" field: ' . count($schemes) . ' schemes');
            error_log('  ✓ USING THIS CATEGORY!');
            error_log('=== PM Paint Schemes DEBUG END ===');
            return $schemes;
        }
        
        error_log('  ✗ No schemes in category #' . $term->term_id);
    }
    
    // 5. Если не нашли в прямых категориях, проверяем родительские
    error_log('→ Checking parent categories...');
    foreach ($terms as $term) {
        $parent_id = $term->parent;
        while ($parent_id) {
            $parent_term = get_term($parent_id, 'product_cat');
            error_log('  → Checking PARENT category #' . $parent_id . ': "' . $parent_term->name . '"');
            
            $schemes = get_field('schemes', 'product_cat_' . $parent_id);
            if (!empty($schemes)) {
                error_log('    ✓ Found in PARENT "schemes": ' . count($schemes) . ' schemes');
                foreach ($schemes as $idx => $scheme) {
                    error_log('      Scheme[' . $idx . ']: name="' . ($scheme['scheme_name'] ?? 'EMPTY') . '", colors=' . count($scheme['scheme_colors'] ?? []));
                }
                error_log('    ✓ USING PARENT CATEGORY!');
                error_log('=== PM Paint Schemes DEBUG END ===');
                return $schemes;
            }
            
            $schemes = get_field('custom_schemes', 'product_cat_' . $parent_id);
            if (!empty($schemes)) {
                error_log('    ✓ Found in PARENT "custom_schemes": ' . count($schemes) . ' schemes');
                foreach ($schemes as $idx => $scheme) {
                    error_log('      Scheme[' . $idx . ']: name="' . ($scheme['scheme_name'] ?? 'EMPTY') . '", colors=' . count($scheme['scheme_colors'] ?? []));
                }
                error_log('    ✓ USING PARENT CATEGORY!');
                error_log('=== PM Paint Schemes DEBUG END ===');
                return $schemes;
            }
            
            error_log('    ✗ No schemes in parent #' . $parent_id);
            $parent_id = $parent_term->parent;
        }
    }

    error_log('✗ NO SCHEMES FOUND for product #' . $product_id);
    error_log('=== PM Paint Schemes DEBUG END ===');
    return [];
}

// === Добавление скрипта в footer для динамического блока схем ===
add_action('wp_footer', function() {
    if (!is_product()) return;
    global $product;
    
    // ОБНОВЛЕНО: проверяем через is_in_painting_categories или если функция не существует, через расширенную проверку
    $product_id = $product->get_id();
    $can_show_colors = false;
    
    if (function_exists('is_in_painting_categories')) {
        $can_show_colors = is_in_painting_categories($product_id);
    } elseif (function_exists('is_in_target_categories')) {
        $can_show_colors = is_in_target_categories($product_id);
    } else {
        // Fallback: проверяем категории напрямую
        $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (!is_wp_error($product_categories) && !empty($product_categories)) {
            $target_categories = array_merge(
                range(87, 93),      // пиломатериалы
                [190, 191, 127, 94], // листовые материалы
                range(265, 271)      // столярные изделия
            );
            foreach ($product_categories as $cat_id) {
                if (in_array($cat_id, $target_categories)) {
                    $can_show_colors = true;
                    break;
                }
                // Проверяем дочерние категории
                foreach ($target_categories as $target_cat_id) {
                    if (function_exists('cat_is_ancestor_of') && cat_is_ancestor_of($target_cat_id, $cat_id)) {
                        $can_show_colors = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if (!$can_show_colors) return;

$schemes = pm_get_product_paint_schemes($product->get_id());
if (!is_array($schemes)) {
    $schemes = [];
}    
    // Отладка: логируем что получили
    error_log('PM Paint Schemes DEBUG for product ' . $product->get_id() . ':');
error_log('Raw schemes count: ' . (is_countable($schemes) ? count($schemes) : 0));
    if (!empty($schemes)) {
        foreach ($schemes as $index => $scheme) {
$scheme_name = (is_array($scheme) && !empty($scheme['scheme_name'])) ? $scheme['scheme_name'] : 'EMPTY';
$colors_count = (is_array($scheme) && isset($scheme['scheme_colors']) && is_countable($scheme['scheme_colors'])) ? count($scheme['scheme_colors']) : 0;
error_log('  Scheme #' . $index . ': name="' . $scheme_name . '", colors=' . $colors_count);

        }
    }
    
    // Фильтруем схемы: убираем те, у которых нет имени или цветов
    $schemes = array_filter($schemes, function($scheme) {
        $has_name = !empty($scheme['scheme_name']);
        $has_colors = !empty($scheme['scheme_colors']) && is_array($scheme['scheme_colors']);
        
        if (!$has_name) {
            error_log('PM Paint Schemes: Skipping scheme with empty name');
        }
        if (!$has_colors) {
            error_log('PM Paint Schemes: Skipping scheme "' . ($scheme['scheme_name'] ?? 'EMPTY') . '" - no colors');
        }
        
        return $has_name && $has_colors;
    });
    
    if (empty($schemes)) {
        error_log('PM Paint Schemes: No valid schemes after filtering');
        return;
    }
    
    error_log('PM Paint Schemes: Using ' . count($schemes) . ' valid schemes');
    
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('PM Paint Schemes: Script loaded');
        
        const checkForPaintingBlock = setInterval(function() {
            const paintingBlock = document.getElementById('painting-services-block');
            if (paintingBlock) {
                console.log('PM Paint Schemes: Painting block found');
                clearInterval(checkForPaintingBlock);
                addPaintSchemesToBlock(paintingBlock);
            }
        }, 200);

        function addPaintSchemesToBlock(paintingBlock) {
            let schemesBlock = document.getElementById('paint-schemes-block');
            let schemes = <?php echo json_encode($schemes); ?>;

            console.log('PM Paint Schemes: Adding schemes to block', schemes);

            // ВАЖНО: Если блок УЖЕ существует (создан PHP), полностью очищаем его
            if (schemesBlock) {
                console.log('PM Paint Schemes: Block already exists, clearing it');
                schemesBlock.remove(); // Удаляем старый блок
            }
            
            // Создаем новый блок
            const html = createSchemesHTML();
            paintingBlock.insertAdjacentHTML('beforeend', html);
            schemesBlock = document.getElementById('paint-schemes-block');
            console.log('PM Paint Schemes: Fresh schemes block created');

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
                
                <!-- Блок предпросмотра выбранного цвета -->
                <div id="color-preview-container" style="display:none; margin-bottom:20px; padding:20px; background:#f5f5f5; border-radius:8px; border:2px solid #4CAF50;">
                    <div style="position:relative; margin-bottom:15px; text-align:center;">
                        <!-- Галочка поверх изображения -->
                        <div style="position:relative; display:inline-block;">
                            <div style="border:3px solid #4CAF50; border-radius:8px; padding:5px; background:#fff; box-shadow:0 4px 12px rgba(76, 175, 80, 0.3);">
                                <img id="color-preview-image" src="" alt="" style="height:200px; object-fit:cover; display:block; border-radius:4px;">
                            </div>
                            <!-- Зеленая галочка -->
                            <div style="position:absolute; top:-10px; right:-10px; width:50px; height:50px; background:#4CAF50; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 8px rgba(0,0,0,0.2); border:3px solid #fff;">
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <p id="color-preview-scheme" style="margin:10px 0; font-weight:600; font-size:16px; color:#333; text-align:center;"></p>
                    <p id="color-preview-code" style="margin:10px 0; font-size:18px; font-weight:700; color:#4CAF50; text-align:center;"></p>
                    
                    <!-- Кнопка "Выбрать другой цвет" -->
                    <div style="text-align:center; margin-top:15px;">
                        <button type="button" id="change-color-btn" style="padding:10px 20px; background:#fff; border:2px solid #0073aa; color:#0073aa; border-radius:5px; cursor:pointer; font-weight:600; transition:all 0.3s;">
                            Выбрать другой цвет
                        </button>
                    </div>
                </div>
                
                <!-- Блоки с палитрой цветов -->
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

        // НОВАЯ функция для консистентной генерации slug
        function normalizeSchemeSlug(scheme) {
            let slug = scheme.scheme_slug;
            // Генерируем slug если он пустой или undefined
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
            // Убираем расширение
            filename = filename.replace(/\.(jpg|jpeg|png|webp|gif)$/i, '');
            
            // ВАЖНО: убираем _180 и другие суффиксы
            filename = filename.replace(/[-_](180|kopiya|copy|1)$/i, '');
            
            // Применяем паттерны
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
            
            // Финальная очистка
            filename = filename.replace(/[-_]+/g, '_').replace(/^[-_]|[-_]$/g, '');
            
            return filename;
        }

        function updateSchemeOptions(schemes) {
            const select = document.getElementById('pm_scheme_select');
            if (!select) return;
            select.innerHTML = '<option value="">Выберите схему</option>';
            
            let validCount = 0;
            schemes.forEach(s => {
                // Проверяем что у схемы есть имя
                if (!s.scheme_name || s.scheme_name.trim() === '') {
                    console.warn('PM Paint Schemes: Skipping scheme with empty name', s);
                    return;
                }
                
                const slug = normalizeSchemeSlug(s);
                const opt = document.createElement('option');
                opt.value = slug;
                opt.textContent = s.scheme_name;
                opt.dataset.name = s.scheme_name;
                select.appendChild(opt);
                validCount++;
            });
            
            console.log('PM Paint Schemes: Added', validCount, 'scheme options');
        }

        function updateColorBlocks(schemes) {
            const container = document.getElementById('color-blocks-container');
            if (!container) return;
            container.innerHTML = '';

            let validBlocksCount = 0;
            schemes.forEach(scheme => {
                // Проверяем что схема валидна
                if (!scheme.scheme_name || scheme.scheme_name.trim() === '') {
                    console.warn('PM Paint Schemes: Skipping scheme with empty name', scheme);
                    return;
                }
                
                const slug = normalizeSchemeSlug(scheme);
                const name = scheme.scheme_name;
                const colors = scheme.scheme_colors || [];
                
                console.log('PM Paint Schemes: Creating block for scheme:', name, 'with slug:', slug, 'colors:', colors.length);
                
                if (!colors.length) {
                    console.warn('PM Paint Schemes: No colors for scheme:', name);
                    return;
                }

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
                validBlocksCount++;
            });
            
            console.log('PM Paint Schemes: Created', validBlocksCount, 'valid color blocks');
        }

        function findMatchingScheme(schemes, serviceName) {
            if (!serviceName) return null;
            
            // Фильтруем схемы с пустыми именами
            const validSchemes = schemes.filter(s => s.scheme_name && s.scheme_name.trim() !== '');
            
            if (validSchemes.length === 0) {
                console.warn('PM Paint Schemes: No valid schemes to match against');
                return null;
            }
            
            // Очищаем название услуги от цены и лишних символов
            let cleanServiceName = serviceName
                .replace(/\s*\(\+.*?\)$/g, '') // Убираем (+750 ₽/м²)
                .replace(/\s*\+.*$/g, '')       // Убираем +750 ₽/м²
                .toLowerCase()
                .replace(/[^\wа-яё\s]/gi, '')
                .trim();
            
            if (!cleanServiceName) return null;

            console.log('PM Paint Schemes: Looking for scheme matching:', cleanServiceName);
            
            // Убираем из названия услуги специфичные слова типа "столешницы", "изделия" и т.д.
            const wordsToRemove = ['столешницы', 'столешницу', 'изделия', 'изделие', 'доски', 'доску', 'материала', 'наличника', 'наличник'];
            let simplifiedServiceName = cleanServiceName;
            wordsToRemove.forEach(word => {
                const regex = new RegExp('\\b' + word + '\\b', 'gi');
                simplifiedServiceName = simplifiedServiceName.replace(regex, '').replace(/\s+/g, ' ').trim();
            });
            
            console.log('PM Paint Schemes: Simplified service name:', simplifiedServiceName);

            // 1. Точное совпадение
            let found = validSchemes.find(s => {
                let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                return cleanSchemeName === cleanServiceName;
            });
            
            if (found) {
                console.log('PM Paint Schemes: Found exact match!');
                return found;
            }
            
            // 2. Совпадение упрощенного названия
            found = validSchemes.find(s => {
                let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                return cleanSchemeName === simplifiedServiceName;
            });
            
            if (found) {
                console.log('PM Paint Schemes: Found simplified match!');
                return found;
            }
            
            // 3. Совпадение без слова "покраска"
            let serviceWithoutPokraska = simplifiedServiceName.replace(/покр?аска\s*/gi, '').trim();
            found = validSchemes.find(s => {
                let schemeWithoutPokraska = s.scheme_name.toLowerCase()
                    .replace(/[^\wа-яё\s]/gi, '')
                    .replace(/покр?аска\s*/gi, '')
                    .trim();
                return schemeWithoutPokraska === serviceWithoutPokraska;
            });
            
            if (found) {
                console.log('PM Paint Schemes: Found match without "pokraska"!');
                return found;
            }
            
            // 4. Совпадение по ключевым словам (первые 2-3 значимых слова)
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
                    
                    // Проверяем совпадение хотя бы 2 ключевых слов
                    let matchCount = 0;
                    for (let word of serviceWords) {
                        if (schemeWords.some(sw => sw.includes(word) || word.includes(sw))) {
                            matchCount++;
                        }
                    }
                    
                    return matchCount >= Math.min(2, serviceWords.length);
                });
                
                if (found) {
                    console.log('PM Paint Schemes: Found match by keywords!');
                    return found;
                }
            }
            
            console.log('PM Paint Schemes: NO MATCH FOUND. Available schemes:', validSchemes.map(s => s.scheme_name));
            console.log('PM Paint Schemes: Tried to match:', cleanServiceName, '→', simplifiedServiceName);
            
            return null;
        }

        function showColors(slug) {
            console.log('PM Paint Schemes: showColors called with slug:', slug);
            
            let foundBlock = false;
            document.querySelectorAll('.pm-paint-colors').forEach(block => {
                const blockScheme = block.dataset.scheme;
                
                if (blockScheme === slug) {
                    block.style.display = 'block';
                    foundBlock = true;
                    console.log('PM Paint Schemes: Showing block for scheme:', slug);
                } else {
                    block.style.display = 'none';
                }
            });
            
            if (!foundBlock) {
                console.error('PM Paint Schemes: No color block found for slug:', slug);
                console.log('PM Paint Schemes: Available blocks:', Array.from(document.querySelectorAll('.pm-paint-colors')).map(b => b.dataset.scheme));
            }
            
            // Сбрасываем выбор цвета
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
            
            // Скрываем превью и показываем палитру при смене схемы
            const previewContainer = document.getElementById('color-preview-container');
            const colorBlocksContainer = document.getElementById('color-blocks-container');
            
            if (previewContainer) {
                previewContainer.style.display = 'none';
            }
            
            if (colorBlocksContainer) {
                colorBlocksContainer.style.display = 'block';
            }
            
            // Сбрасываем скрытые поля
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
            
            // Скрываем превью
            const previewContainer = document.getElementById('color-preview-container');
            if (previewContainer) {
                previewContainer.style.display = 'none';
            }
            
            console.log('PM Paint Schemes: Scheme selection reset');
        }

        function setupSchemeHandlers(allSchemes) {
            const serviceSelect = document.getElementById('painting_service_select');
            const schemesBlock = document.getElementById('paint-schemes-block');
            const schemeSelect = document.getElementById('pm_scheme_select');
            const form = document.querySelector('form.cart');

            console.log('PM Paint Schemes: Setting up handlers', {
                serviceSelect: !!serviceSelect,
                schemesBlock: !!schemesBlock,
                schemeSelect: !!schemeSelect
            });

            // Добавляем скрытые поля в форму
            if (form) {
                if (!form.querySelector('#pm_selected_scheme_name')) {
                    form.insertAdjacentHTML('beforeend', `
                        <input type="hidden" id="pm_selected_scheme_name" name="pm_selected_scheme_name" value="">
                        <input type="hidden" id="pm_selected_scheme_slug" name="pm_selected_scheme_slug" value="">
                        <input type="hidden" id="pm_selected_color_image" name="pm_selected_color_image" value="">
                        <input type="hidden" id="pm_selected_color_filename" name="pm_selected_color_filename" value="">
                    `);
                }
            }

            // Обработчик выбора услуги покраски
            if (serviceSelect) {
                serviceSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const serviceName = selectedOption.text;

                    console.log('PM Paint Schemes: Service changed to:', serviceName);

                    if (this.value && serviceName) {
                        const matchingScheme = findMatchingScheme(allSchemes, serviceName);

                        if (matchingScheme) {
                            try {
                                const schemeSlug = normalizeSchemeSlug(matchingScheme);
                                
                                console.log('PM Paint Schemes: Found matching scheme:', matchingScheme.scheme_name, 'slug:', schemeSlug);
                                
                                // Скрываем селектор схем
                                document.getElementById('scheme-selector').style.display = 'none';
                                
                                // Пересоздаем блоки цветов только для этой схемы
                                updateColorBlocks([matchingScheme]);
                                
                                // Устанавливаем скрытые поля
                                document.getElementById('pm_selected_scheme_name').value = matchingScheme.scheme_name;
                                document.getElementById('pm_selected_scheme_slug').value = schemeSlug;
                                
                                // Показываем блок схем
                                schemesBlock.style.display = 'block';
                                
                                // ВАЖНО: Показываем цвета ПОСЛЕ того как блоки созданы
                                setTimeout(() => {
                                    showColors(schemeSlug);
                                    
                                    // Проверка что блок действительно показан
                                    const colorBlock = document.querySelector(`.pm-paint-colors[data-scheme="${schemeSlug}"]`);
                                    if (colorBlock) {
                                        console.log('PM Paint Schemes: Color block found and shown');
                                    } else {
                                        console.error('PM Paint Schemes: Color block NOT FOUND for slug:', schemeSlug);
                                        console.log('Available blocks:', Array.from(document.querySelectorAll('.pm-paint-colors')).map(b => b.dataset.scheme));
                                    }
                                }, 100);
                                
                            } catch (error) {
                                console.error('PM Paint Schemes: Error showing scheme', error);
                                schemesBlock.style.display = 'none';
                                resetSchemeSelection();
                            }
                        } else {
                            console.log('PM Paint Schemes: No matching scheme, showing selector');
                            // Несколько схем - показываем селектор
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

            // Обработчик выбора схемы из селекта
            if (schemeSelect) {
                schemeSelect.addEventListener('change', function() {
                    const selectedSlug = this.value;
                    const selectedName = this.options[this.selectedIndex].dataset.name || '';
                    
                    console.log('PM Paint Schemes: Scheme selected:', selectedName, 'slug:', selectedSlug);
                    
                    if (selectedSlug) {
                        document.getElementById('pm_selected_scheme_name').value = selectedName;
                        document.getElementById('pm_selected_scheme_slug').value = selectedSlug;
                        showColors(selectedSlug);
                    } else {
                        // Сброс если выбрана пустая опция
                        document.querySelectorAll('.pm-paint-colors').forEach(b => b.style.display = 'none');
                        document.getElementById('pm_selected_scheme_name').value = '';
                        document.getElementById('pm_selected_scheme_slug').value = '';
                    }
                });
            }

            // Обработчик выбора цвета
            document.addEventListener('change', function(e) {
                if (e.target.name === 'pm_selected_color') {
                    console.log('PM Paint Schemes: Color selected');
                    
                    // Обновляем визуальное выделение
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
                    
                    // Показываем превью выбранного цвета
                    const selectedImg = e.target.nextElementSibling;
                    if (selectedImg) {
                        const previewContainer = document.getElementById('color-preview-container');
                        const previewImage = document.getElementById('color-preview-image');
                        const previewScheme = document.getElementById('color-preview-scheme');
                        const previewCode = document.getElementById('color-preview-code');
                        
                        previewImage.src = selectedImg.src;
                        previewImage.alt = e.target.dataset.filename;
                        
                        // Название схемы
                        const schemeName = e.target.dataset.scheme;
                        previewScheme.textContent = schemeName;
                        
                        // Код цвета
                        const colorCode = e.target.dataset.filename;
                        previewCode.textContent = 'Код: ' + colorCode;
                        
                        // Показываем превью
                        previewContainer.style.display = 'block';
                        
                        // ВАЖНО: Скрываем палитру цветов
                        const colorBlocksContainer = document.getElementById('color-blocks-container');
                        if (colorBlocksContainer) {
                            colorBlocksContainer.style.display = 'none';
                        }
                        
                        // Скрываем селектор схем (если он виден)
                        const schemeSelector = document.getElementById('scheme-selector');
                        if (schemeSelector && schemeSelector.style.display !== 'none') {
                            // Сохраняем состояние селектора схем для восстановления
                            previewContainer.dataset.schemeSelectorWasVisible = 'true';
                        }
                        
                        // Сохраняем данные в скрытые поля
                        document.getElementById('pm_selected_color_image').value = selectedImg.src;
                        document.getElementById('pm_selected_color_filename').value = colorCode;
                        
                        console.log('PM Paint Schemes: Color selected, palette hidden, preview shown');
                    }
                }
            });

            // Обработчик кнопки "Выбрать другой цвет"
            document.addEventListener('click', function(e) {
                if (e.target.id === 'change-color-btn') {
                    console.log('PM Paint Schemes: Change color button clicked');
                    
                    const previewContainer = document.getElementById('color-preview-container');
                    const colorBlocksContainer = document.getElementById('color-blocks-container');
                    const schemeSelector = document.getElementById('scheme-selector');
                    
                    // Скрываем превью
                    if (previewContainer) {
                        previewContainer.style.display = 'none';
                    }
                    
                    // Показываем палитру цветов
                    if (colorBlocksContainer) {
                        colorBlocksContainer.style.display = 'block';
                    }
                    
                    // Восстанавливаем селектор схем, если он был виден
                    if (schemeSelector && previewContainer.dataset.schemeSelectorWasVisible === 'true') {
                        schemeSelector.style.display = 'block';
                    }
                    
                    // Сбрасываем выбор цвета
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
                    
                    // Очищаем скрытые поля
                    document.getElementById('pm_selected_color_image').value = '';
                    document.getElementById('pm_selected_color_filename').value = '';
                    
                    console.log('PM Paint Schemes: Color selection reset, palette shown');
                }
            });
        }
    });
    </script>
    <?php
}, 25);

// === Передача данных в корзину ===
add_filter('woocommerce_add_cart_item_data', 'pm_add_paint_data_to_cart', 10, 3);
function pm_add_paint_data_to_cart($cart_item_data, $product_id, $variation_id) {

    // Обрабатываем имя файла цвета с фильтрацией
    if (!empty($_POST['pm_selected_color_filename'])) {
        $cleaned_filename = pm_clean_color_filename($_POST['pm_selected_color_filename']);
        $cart_item_data['pm_selected_color'] = $cleaned_filename;
        $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
    } elseif (!empty($_POST['pm_selected_color'])) {
        // Если пришло полное значение "Схема — код", извлекаем код
        $color_value = sanitize_text_field($_POST['pm_selected_color']);
        if (strpos($color_value, ' — ') !== false) {
            $parts = explode(' — ', $color_value);
            $cleaned_filename = pm_clean_color_filename(end($parts));
        } else {
            $cleaned_filename = pm_clean_color_filename($color_value);
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
        $image_url = esc_url_raw($_POST['pm_selected_color_image']);
        $cart_item_data['pm_selected_color_image'] = $image_url;
    }
    
    return $cart_item_data;
}

// === Сохранение в заказ ===
add_action('woocommerce_checkout_create_order_line_item', 'pm_add_paint_data_to_order', 10, 4);
function pm_add_paint_data_to_order($item, $cart_item_key, $values, $order) {

    if (!empty($values['pm_selected_scheme_name'])) {
        $scheme_display = $values['pm_selected_scheme_name'];
        
        // Добавляем код цвета к схеме, если есть
        if (!empty($values['pm_selected_color'])) {
            $scheme_display .= ' — ' . $values['pm_selected_color'];
        }
        
        //$item->add_meta_data('Схема покраски', $scheme_display, true);
        //error_log('PM Paint Schemes: Scheme added to order - ' . $scheme_display);
    }
    
    if (!empty($values['pm_selected_color_image'])) {
        $item->add_meta_data('_pm_color_image_url', $values['pm_selected_color_image'], true);
    }
    
    if (!empty($values['pm_selected_color'])) {
        $item->add_meta_data('Код цвета', $values['pm_selected_color'], true);
    }
}

// === Отображение изображения цвета в заказе (админка и email) ===
add_filter('woocommerce_order_item_display_meta_key', 'pm_rename_color_meta_key', 10, 3);
function pm_rename_color_meta_key($display_key, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        return 'Образец цвета';
    }
    return $display_key;
}

add_filter('woocommerce_order_item_display_meta_value', 'pm_display_color_image_in_order', 10, 3);
function pm_display_color_image_in_order($display_value, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        $image_url = $meta->value;
        return '<img src="' . esc_url($image_url) . '" style="width:60px; height:60px; object-fit:cover; border:2px solid #ddd; border-radius:4px; display:block; margin-top:5px;">';
    }
    return $display_value;
}

// === Интеграция с услугами покраски из f1.php ===
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    // Проверяем наличие услуги покраски
    if (!empty($_POST['painting_service_key'])) {
        // Получаем выбранный цвет для услуги покраски
        if (!empty($cart_item_data['pm_selected_color'])) {
            $color = $cart_item_data['pm_selected_color'];
            
            // Обновляем название услуги покраски с учетом цвета
            $sources = ['custom_area_calc', 'custom_dimensions', 'card_pack_purchase', 'standard_pack_purchase'];
            
            foreach ($sources as $key) {
                if (!empty($cart_item_data[$key]['painting_service'])) {
                    $cart_item_data[$key]['painting_service']['color_code'] = $color;
                    $cart_item_data[$key]['painting_service']['name_with_color'] = 
                        $cart_item_data[$key]['painting_service']['name'] . ' "' . $color . '"';
                }
            }
        }
    }
    
    return $cart_item_data;
}, 20, 3);