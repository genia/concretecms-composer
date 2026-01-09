<?php
/**
 * Inline Filters Override for Community Store Product Filter
 * Displays product attributes inline with reduced font sizes
 */
defined('C5_EXECUTE') or die("Access Denied.");

use Concrete\Package\CommunityStore\Src\CommunityStore\Utilities\Price;

$csm = $app->make('cs/helper/multilingual');

// Check if price filter is included to load ionRangeSlider
$hasPriceFilter = false;
if (!empty($filterData)) {
    foreach ($filterData as $data) {
        if ($data['type'] == 'price') {
            $hasPriceFilter = true;
            break;
        }
    }
}

// Load ionRangeSlider assets if price filter is present
if ($hasPriceFilter) {
    $packagePath = \Concrete\Core\Support\Facade\Url::to('/packages/community_store/blocks/community_product_filter');
    ?>
    <link rel="stylesheet" href="<?= $packagePath; ?>/css/ion.rangeSlider.min.css">
    <script src="<?= $packagePath; ?>/js/ion.rangeSlider.min.js"></script>
    <?php
}
?>

<div class="store-product-filter-block store-filter-inline">
    <form class="<?= ($updateType == 'auto' ? 'store-product-filter-block-auto' : ''); ?>" <?= ($jumpAnchor ? 'id="filter-'. $bID .'"' : ''); ?>>

        <?php if (!empty($filterData)): ?>
            <?php foreach ($filterData as $akhandle => $data): ?>
                <div class="store-filter-group">
                    <span class="store-filter-title">
                        <?php if ($data['type'] == 'attr'): 
                            $ak = $attributes[$akhandle];
                        ?>
                            <?= h($csm->t($data['label'] ? $data['label'] : $ak->getAttributeKeyName(), 'productAttributeName', null, $ak->getAttributeKeyID())); ?>
                        <?php elseif ($data['type'] == 'price'): ?>
                            <?= t($data['label'] ? $data['label'] : t('Price')); ?>
                        <?php endif; ?>
                    </span>

                    <div class="store-filter-options">
                        <?php if ($data['type'] == 'attr'): ?>
                            <?php
                            $optiondata = $data['data'];
                            $matchingType = $attrFilterTypes[$akhandle]['matchingType'];
                            $invalidHiding = $attrFilterTypes[$akhandle]['invalidHiding'];
                            $attrType = $ak->getAttributeType()->getAttributeTypeHandle();
                            ?>
                            
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
                            
                        <?php elseif ($data['type'] == 'price'): ?>
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
                <button type="submit" class="store-btn-filter btn btn-primary btn-sm">
                    <?= ($filterButtonText ? t($filterButtonText) : t('Filter')); ?>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($displayClear && (!empty($selectedAttributes) || $priceFiltering)): ?>
            <div class="store-filter-actions">
                <button type="submit" class="store-btn-filter-clear btn btn-secondary btn-sm">
                    <?= ($clearButtonText ? t($clearButtonText) : t('Clear')); ?>
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

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
    
    <?php if ($updateType == 'auto'): ?>
    // Handle auto-submit for checkboxes
    $('.store-filter-box input[type="checkbox"]').on('change', function() {
        communityStore.submitProductFilter($(this));
    });
    <?php endif; ?>
});
</script>

