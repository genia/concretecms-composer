<?php
/**
 * Boxed Filters Template for Community Store Product Filter
 * 
 * Displays product attributes as either:
 * - Boxed checkboxes (visual pill/tag style)
 * - Multi-select dropdowns
 * 
 * Set display mode per attribute via data attributes or globally below.
 */
defined('C5_EXECUTE') or die("Access Denied.");

use Concrete\Package\CommunityStore\Src\CommunityStore\Utilities\Price;

$csm = $app->make('cs/helper/multilingual');

// Default display mode: 'boxed' for pill-style checkboxes, 'multiselect' for dropdowns
$defaultDisplayMode = 'boxed';
?>

<div class="store-product-filter-block store-filter-boxed">
    <form class="<?= ($updateType == 'auto' ? 'store-product-filter-block-auto' : ''); ?>" <?= ($jumpAnchor ? 'id="filter-'. $bID .'"' : ''); ?>>

        <?php if (!empty($filterData)): ?>
            <?php foreach ($filterData as $akhandle => $data): ?>
                <div class="store-filter-group mb-4">
                    <h4 class="store-filter-title">
                        <?php if ($data['type'] == 'attr'): 
                            $ak = $attributes[$akhandle];
                        ?>
                            <?= h($csm->t($data['label'] ? $data['label'] : $ak->getAttributeKeyName(), 'productAttributeName', null, $ak->getAttributeKeyID())); ?>
                        <?php elseif ($data['type'] == 'price'): ?>
                            <?= t($data['label'] ? $data['label'] : t('Price')); ?>
                        <?php endif; ?>
                    </h4>

                    <div class="store-filter-options">
                        <?php if ($data['type'] == 'attr'): ?>
                            <?php
                            $optiondata = $data['data'];
                            $matchingType = $attrFilterTypes[$akhandle]['matchingType'];
                            $invalidHiding = $attrFilterTypes[$akhandle]['invalidHiding'];
                            $attrType = $ak->getAttributeType()->getAttributeTypeHandle();
                            
                            // Determine display mode - can be customized per attribute type
                            // 'select' type attributes work well as multi-selects
                            // 'boolean' and 'text' work well as boxed checkboxes
                            $displayMode = ($attrType === 'select' && count($optiondata) > 6) ? 'multiselect' : $defaultDisplayMode;
                            ?>
                            
                            <?php if ($displayMode === 'multiselect'): ?>
                                <!-- Multi-select dropdown -->
                                <select multiple class="store-filter-multiselect form-select" name="<?= $akhandle; ?>[]" data-matching="<?= $matchingType; ?>">
                                    <?php foreach ($optiondata as $option => $count): 
                                        $checked = isset($selectedAttributes[$akhandle]) && in_array($option, $selectedAttributes[$akhandle]);
                                        $disabled = false;
                                        
                                        if (!$checked && $count == 0 && $matchingType == 'and') {
                                            $disabled = true;
                                            if ($invalidHiding == 'hide') {
                                                continue;
                                            }
                                        }
                                    ?>
                                        <option value="<?= h($option); ?>" 
                                                <?= $checked ? 'selected' : ''; ?> 
                                                <?= $disabled ? 'disabled' : ''; ?>>
                                            <?php
                                            if ('boolean' == $attrType) {
                                                $checkboxSettings = $ak->getAttributeKeySettings();
                                                $checkboxLabel = method_exists($checkboxSettings, 'getCheckboxLabel') ? $checkboxSettings->getCheckboxLabel() : '';
                                                echo h($csm->t(empty($checkboxLabel) ? $option : $checkboxLabel, 'productAttributeLabel'));
                                            } else {
                                                echo h($csm->t($option, 'productAttributeValue'));
                                            }
                                            if ($showTotals && ($matchingType == 'and' || ($matchingType == 'or' && !key_exists($akhandle, $selectedAttributes)))) {
                                                echo " ($count)";
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <!-- Boxed checkboxes (pill/tag style) -->
                                <div class="store-filter-boxed-options">
                                    <?php foreach ($optiondata as $option => $count): 
                                        $checked = isset($selectedAttributes[$akhandle]) && in_array($option, $selectedAttributes[$akhandle]);
                                        $disabled = false;
                                        $show = true;
                                        
                                        if (!$checked && $count == 0 && $matchingType == 'and') {
                                            $disabled = true;
                                            if ($invalidHiding == 'hide') {
                                                $show = false;
                                            }
                                        }
                                        
                                        if (!$show) continue;
                                    ?>
                                        <label class="store-filter-box <?= $checked ? 'active' : ''; ?> <?= $disabled ? 'disabled' : ''; ?>">
                                            <input type="checkbox" 
                                                   data-matching="<?= $matchingType; ?>"
                                                   <?= $disabled ? 'disabled="disabled"' : ''; ?>
                                                   <?= $checked ? 'checked="checked"' : ''; ?>
                                                   value="<?= h($option); ?>" 
                                                   name="<?= $akhandle; ?>[]" />
                                            <span class="store-filter-box-label">
                                                <?php
                                                if ('boolean' == $attrType) {
                                                    $checkboxSettings = $ak->getAttributeKeySettings();
                                                    $checkboxLabel = method_exists($checkboxSettings, 'getCheckboxLabel') ? $checkboxSettings->getCheckboxLabel() : '';
                                                    echo h($csm->t(empty($checkboxLabel) ? $option : $checkboxLabel, 'productAttributeLabel'));
                                                } else {
                                                    echo h($csm->t($option, 'productAttributeValue'));
                                                }
                                                ?>
                                                <?php if ($showTotals && ($matchingType == 'and' || ($matchingType == 'or' && !key_exists($akhandle, $selectedAttributes)))): ?>
                                                    <span class="store-filter-count">(<?= $count; ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($data['type'] == 'price'): ?>
                            <!-- Price range slider -->
                            <?php if ($minPrice != $maxPrice): ?>
                                <div data-role="rangeslider" class="store-filter-price-slider">
                                    <input type="hidden" class="js-range-slider" name="price" value=""
                                           data-type="double"
                                           data-min="<?= $minPrice; ?>"
                                           data-max="<?= $maxPrice; ?>"
                                           data-from="<?= $minPriceSelected; ?>"
                                           data-to="<?= $maxPriceSelected; ?>"
                                           data-input-values-separator="-"
                                           data-skin="round"
                                           data-prefix="<?= \Concrete\Core\Support\Facade\Config::get('community_store.symbol'); ?>"
                                           data-force-edges="true" />
                                </div>
                                <script>
                                    $(document).ready(function () {
                                        $(".js-range-slider").ionRangeSlider({
                                            <?php if ($updateType == 'auto'): ?>
                                            onFinish: function() {
                                                communityStore.submitProductFilter($('.js-range-slider'));
                                            }
                                            <?php endif; ?>
                                        });
                                    });
                                </script>
                            <?php else: ?>
                                <span class="store-filter-price-single"><?= Price::format($minPrice); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($updateType == 'button'): ?>
            <div class="store-filter-actions">
                <button type="submit" class="store-btn-filter btn btn-primary">
                    <?= ($filterButtonText ? t($filterButtonText) : t('Filter')); ?>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($displayClear && (!empty($selectedAttributes) || $priceFiltering)): ?>
            <div class="store-filter-actions">
                <button type="submit" class="store-btn-filter-clear btn btn-secondary">
                    <?= ($clearButtonText ? t($clearButtonText) : t('Clear Filters')); ?>
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<style>
/* Boxed Filters Styling - Inline Layout */
.store-filter-boxed {
    padding: 12px 15px;
    background: #f9f9f9;
    border-radius: 6px;
}

.store-filter-boxed form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 15px;
}

.store-filter-group {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0;
}

.store-filter-title {
    font-size: 0.7rem;
    font-weight: 600;
    margin-bottom: 0;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.store-filter-options {
    display: inline-flex;
    align-items: center;
}

/* Boxed Checkboxes - Pill/Tag Style */
.store-filter-boxed-options {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
}

.store-filter-box {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    font-size: 0.7rem;
}

.store-filter-box:hover {
    border-color: #999;
    background: #f5f5f5;
}

.store-filter-box.active {
    background: #333;
    border-color: #333;
    color: #fff;
}

.store-filter-box.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f0f0f0;
}

.store-filter-box input[type="checkbox"] {
    display: none; /* Hide the actual checkbox */
}

.store-filter-box-label {
    display: flex;
    align-items: center;
    gap: 4px;
}

.store-filter-count {
    font-size: 0.6rem;
    opacity: 0.7;
}

/* Multi-select Dropdown */
.store-filter-multiselect {
    width: auto;
    min-width: 120px;
    min-height: auto;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 4px;
    font-size: 0.7rem;
}

.store-filter-multiselect option {
    padding: 4px 8px;
    border-radius: 2px;
    margin-bottom: 1px;
}

.store-filter-multiselect option:checked {
    background: #333;
    color: #fff;
}

.store-filter-multiselect option:disabled {
    opacity: 0.5;
}

/* Price Slider */
.store-filter-price-slider {
    padding: 0;
    min-width: 150px;
}

/* Filter Actions - inline with groups */
.store-filter-actions {
    display: inline-flex;
    margin: 0;
}

.store-filter-actions .btn {
    padding: 4px 12px;
    font-size: 0.7rem;
    margin-bottom: 0;
}

.store-btn-filter {
    background: #333;
    border-color: #333;
}

.store-btn-filter:hover {
    background: #555;
    border-color: #555;
}

.store-btn-filter-clear {
    background: transparent;
    border: 1px solid #999;
    color: #666;
}

.store-btn-filter-clear:hover {
    background: #f5f5f5;
    border-color: #666;
    color: #333;
}

/* Responsive */
@media (max-width: 768px) {
    .store-filter-boxed {
        padding: 10px;
    }
    
    .store-filter-boxed form {
        gap: 10px;
    }
    
    .store-filter-group {
        flex-wrap: wrap;
    }
    
    .store-filter-box {
        padding: 3px 8px;
        font-size: 0.65rem;
    }
    
    .store-filter-title {
        font-size: 0.65rem;
    }
}
</style>

<script>
$(document).ready(function() {
    // Handle boxed checkbox visual state
    $('.store-filter-box input[type="checkbox"]').on('change', function() {
        var $box = $(this).closest('.store-filter-box');
        if ($(this).is(':checked')) {
            $box.addClass('active');
        } else {
            $box.removeClass('active');
        }
    });
    
    // Handle multi-select auto-submit if enabled
    <?php if ($updateType == 'auto'): ?>
    $('.store-filter-multiselect').on('change', function() {
        communityStore.submitProductFilter($(this));
    });
    <?php endif; ?>
});
</script>

