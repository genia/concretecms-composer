<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php
use Concrete\Core\Support\Facade\Url;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Validation\CSRF\Token;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductImage;
use Concrete\Package\CommunityStore\Src\CommunityStore\Utilities\Multilingual as CSMultilingual;

$app = Application::getFacadeApplication();
$csm = $app->make(CSMultilingual::class);

// Get token from controller (initialized in on_start)
$token = isset($controller) && isset($controller->token) ? $controller->token : new Token();
$productID = isset($productID) ? (int)$productID : 0;
$isNew = ($productID === 0 || $productID === 'new');
$product = null;
$errors = isset($errors) ? $errors : [];

if (!$isNew) {
    $product = Product::getByID($productID);
    if (!$product) {
        $isNew = true;
    }
}

// Get product attributes
$productCategory = $app->make('Concrete\Package\CommunityStore\Attribute\Category\ProductCategory');
$typeAttr = $productCategory->getAttributeKeyByHandle('type');
$metalAttr = $productCategory->getAttributeKeyByHandle('metal');
$stoneAttr = $productCategory->getAttributeKeyByHandle('stone');

// Get current attribute values
$typeValue = $product ? $product->getAttribute('type') : null;
$metalValue = $product ? $product->getAttribute('metal') : null;
$stoneValue = $product ? $product->getAttribute('stone') : null;

// Get attribute options
$getAttributeOptions = function($ak) {
    if (!$ak) return [];
    $controller = $ak->getController();
    $akSettings = $ak->getAttributeKeySettings();
    if (!$akSettings) return [];
    $optionList = $akSettings->getOptionList();
    if (!$optionList) return [];
    $options = [];
    foreach ($optionList->getOptions() as $option) {
        $options[] = [
            'value' => $option->getSelectAttributeOptionID(),
            'label' => $option->getSelectAttributeOptionValue()
        ];
    }
    return $options;
};

$typeOptions = $getAttributeOptions($typeAttr);
$metalOptions = $getAttributeOptions($metalAttr);
$stoneOptions = $getAttributeOptions($stoneAttr);

// Get all product images (primary + alternates)
$productImages = [];
$primaryImageID = 0;
if ($product) {
    // Get primary image
    $imgObj = $product->getImageObj();
    if ($imgObj) {
        $primaryImageID = $imgObj->getFileID();
        $productImages[] = [
            'fID' => $imgObj->getFileID(),
            'file' => $imgObj,
            'isPrimary' => true
        ];
    }
    
    // Get alternate images
    $altImageObjects = ProductImage::getImageObjectsForProduct($product);
    foreach ($altImageObjects as $altImg) {
        if ($altImg && $altImg->getFileID() != $primaryImageID) {
            $productImages[] = [
                'fID' => $altImg->getFileID(),
                'file' => $altImg,
                'isPrimary' => false
            ];
        }
    }
}

// If no images, create one empty slot
if (empty($productImages)) {
    $productImages[] = [
        'fID' => 0,
        'file' => null,
        'isPrimary' => true
    ];
}

// Get multilingual values
$productNameEN = $product ? $product->getName() : '';
$productNameRU = '';
$productDescEN = $product ? $product->getDesc() : '';
$productDescRU = '';

if ($product) {
    // Query Translation entity directly to get Russian translations
    $em = $app->make('Doctrine\ORM\EntityManager');
    $productID = $product->getID();
    
    // Get Russian product name
    $qb = $em->createQueryBuilder();
    $query = $qb->select('t')
        ->from('Concrete\Package\CommunityStore\Src\CommunityStore\Multilingual\Translation', 't')
        ->where('t.entityType = :type')
        ->andWhere('t.locale = :locale')
        ->andWhere('t.pID = :pid')
        ->setParameter('type', 'productName')
        ->setParameter('locale', 'ru_RU')
        ->setParameter('pid', $productID)
        ->setMaxResults(1)
        ->getQuery();
    $nameTranslation = $query->getOneOrNullResult();
    if ($nameTranslation) {
        $productNameRU = $nameTranslation->getTranslatedText() ?: '';
    }
    
    // Get Russian product description
    $qb = $em->createQueryBuilder();
    $query = $qb->select('t')
        ->from('Concrete\Package\CommunityStore\Src\CommunityStore\Multilingual\Translation', 't')
        ->where('t.entityType = :type')
        ->andWhere('t.locale = :locale')
        ->andWhere('t.pID = :pid')
        ->setParameter('type', 'productDescription')
        ->setParameter('locale', 'ru_RU')
        ->setParameter('pid', $productID)
        ->setMaxResults(1)
        ->getQuery();
    $descTranslation = $query->getOneOrNullResult();
    if ($descTranslation) {
        // Description uses extendedText (isLongText = true)
        $productDescRU = $descTranslation->getExtendedText() ?: '';
    }
}
?>

<div class="container mt-4 mb-4">
    <div class="row">
        <div class="col-12">
            <h1><?= $isNew ? t('Add New Product') : t('Edit Product') ?></h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?= $view->action('save') ?>" enctype="multipart/form-data" id="product-edit-form">
                <?= $token->output('save_product') ?>
                <input type="hidden" name="productID" value="<?= $productID ?>">
                
                <div class="row">
                    <!-- Left Column: Images -->
                    <div class="col-12 col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><?= t('Product Image(s)') ?></h5>
                            </div>
                            <div class="card-body text-center">

                                <!-- Navigation and Image Counter -->
                                <div class="mt-3" id="image-navigation" <?= count($productImages) <= 1 ? 'style="display: none;"' : '' ?>>
                                    <div class="d-flex justify-content-center align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="prev-image" style="margin-right: 10px;">
                                            <i class="fa fa-chevron-left"></i>
                                        </button>
                                        <span id="image-counter">1 / <?= count($productImages) ?></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="next-image" style="margin-left: 10px;">
                                            <i class="fa fa-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="product-images-container">
                                    <?php foreach ($productImages as $index => $imgData): ?>
                                        <div class="product-image-slot" data-slot-index="<?= $index ?>" <?= $index > 0 ? 'style="display: none;"' : '' ?>>
                                            <div id="image-preview-<?= $index ?>" class="mb-3 position-relative">
                                                <?php if ($imgData['file']): ?>
                                                    <img src="<?= $imgData['file']->getThumbnailURL('product_detail') ?>" 
                                                         alt="<?= h($productNameEN) ?>" 
                                                         class="img-fluid" 
                                                         style="max-width: 100%; max-height: 300px;">
                                                <?php else: ?>
                                                    <div class="bg-light p-5 text-muted">
                                                        <i class="fa fa-image fa-3x mb-2"></i><br>
                                                        <?= t('No image') ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($imgData['isPrimary']): ?>
                                                    <span class="badge bg-primary position-absolute top-0 end-0 m-2 primary-badge"><?= t('Primary') ?></span>
                                                <?php elseif ($imgData['file']): ?>
                                                    <a href="#" class="badge bg-secondary position-absolute top-0 end-0 m-2 make-primary-link" data-fid="<?= $imgData['fID'] ?>"><?= t('Make Primary') ?></a>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <label class="btn btn-sm btn-primary">
                                                    <i class="fa fa-upload"></i> <?= t('Upload New Image') ?>
                                                    <input type="file" name="product_image_<?= $index ?>" accept="image/*" style="display: none;" class="image-upload" data-slot-index="<?= $index ?>">
                                                </label>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <button type="button" class="btn btn-sm btn-secondary select-existing-image" data-slot-index="<?= $index ?>">
                                                    <i class="fa fa-folder-open"></i> <?= t('Select Existing') ?>
                                                </button>
                                            </div>
                                            
                                            <input type="hidden" name="product_image_id[]" class="product-image-id" data-slot-index="<?= $index ?>" value="<?= $imgData['fID'] ?>">
                                            
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-danger remove-image" data-slot-index="<?= $index ?>">
                                                    <i class="fa fa-trash"></i> <?= t('Remove Image') ?>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>                                
                                
                                <!-- Add Image Button -->
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-success" id="add-image-slot">
                                        <i class="fa fa-plus"></i> <?= t('Add Image') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Product Details -->
                    <div class="col-12 col-md-8">
                        <!-- Price -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><?= t('Price') ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="product_price"><?= t('Price') ?> *</label>
                                    <input type="number" 
                                           step="0.01" 
                                           min="0" 
                                           name="product_price" 
                                           id="product_price" 
                                           class="form-control" 
                                           value="<?= $product ? h($product->getPrice()) : '' ?>" 
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Multilingual Content -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><?= t('Product Information') ?></h5>
                                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#lang-en" role="tab">
                                            <?= t('English') ?>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#lang-ru" role="tab">
                                            <?= t('Russian') ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- English Tab -->
                                    <div class="tab-pane fade show active" id="lang-en" role="tabpanel">
                                        <div class="form-group mb-3">
                                            <label for="product_name_en"><?= t('Product Name') ?> *</label>
                                            <input type="text" 
                                                   name="product_name_en" 
                                                   id="product_name_en" 
                                                   class="form-control" 
                                                   value="<?= h($productNameEN) ?>" 
                                                   required>
                                        </div>
                                        <div class="form-group">
                                            <label for="product_desc_en"><?= t('Short Description') ?></label>
                                            <textarea name="product_desc_en" 
                                                      id="product_desc_en" 
                                                      class="form-control" 
                                                      rows="3"><?= h($productDescEN) ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Russian Tab -->
                                    <div class="tab-pane fade" id="lang-ru" role="tabpanel">
                                        <div class="form-group mb-3">
                                            <label for="product_name_ru"><?= t('Product Name (Russian)') ?></label>
                                            <input type="text" 
                                                   name="product_name_ru" 
                                                   id="product_name_ru" 
                                                   class="form-control" 
                                                   value="<?= h($productNameRU) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="product_desc_ru"><?= t('Short Description (Russian)') ?></label>
                                            <textarea name="product_desc_ru" 
                                                      id="product_desc_ru" 
                                                      class="form-control" 
                                                      rows="3"><?= h($productDescRU) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attributes -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><?= t('Attributes') ?></h5>
                            </div>
                            <div class="card-body">
                                <!-- Type (Single Choice) -->
                                <?php if ($typeAttr && !empty($typeOptions)): ?>
                                    <div class="form-group mb-3">
                                        <label for="attr_type"><?= t('Type') ?></label>
                                        <select name="attr_type" id="attr_type" class="form-control">
                                            <option value=""><?= t('-- Select Type --') ?></option>
                                            <?php foreach ($typeOptions as $option): ?>
                                                <?php
                                                $selected = false;
                                                if ($typeValue) {
                                                    if (is_object($typeValue)) {
                                                        // SelectValue object
                                                        $selectedOptions = $typeValue->getSelectedOptions();
                                                        foreach ($selectedOptions as $selOpt) {
                                                            if ($selOpt->getSelectAttributeOptionID() == $option['value']) {
                                                                $selected = true;
                                                                break;
                                                            }
                                                        }
                                                    } elseif (is_array($typeValue)) {
                                                        $selected = in_array($option['value'], $typeValue);
                                                    }
                                                }
                                                ?>
                                                <option value="<?= $option['value'] ?>" <?= $selected ? 'selected' : '' ?>>
                                                    <?= h($option['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Metal (Single Choice) -->
                                <?php if ($metalAttr && !empty($metalOptions)): ?>
                                    <div class="form-group mb-3">
                                        <label for="attr_metal"><?= t('Metal') ?></label>
                                        <select name="attr_metal" id="attr_metal" class="form-control">
                                            <option value=""><?= t('-- Select Metal --') ?></option>
                                            <?php foreach ($metalOptions as $option): ?>
                                                <?php
                                                $selected = false;
                                                if ($metalValue) {
                                                    if (is_object($metalValue)) {
                                                        $selectedOptions = $metalValue->getSelectedOptions();
                                                        foreach ($selectedOptions as $selOpt) {
                                                            if ($selOpt->getSelectAttributeOptionID() == $option['value']) {
                                                                $selected = true;
                                                                break;
                                                            }
                                                        }
                                                    } elseif (is_array($metalValue)) {
                                                        $selected = in_array($option['value'], $metalValue);
                                                    }
                                                }
                                                ?>
                                                <option value="<?= $option['value'] ?>" <?= $selected ? 'selected' : '' ?>>
                                                    <?= h($option['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Stone (Multiple Choice) -->
                                <?php if ($stoneAttr && !empty($stoneOptions)): ?>
                                    <div class="form-group">
                                        <label><?= t('Stone') ?></label>
                                        <div class="form-check-group">
                                            <?php
                                            $selectedStoneIDs = [];
                                            if ($stoneValue) {
                                                if (is_object($stoneValue)) {
                                                    $selectedOptions = $stoneValue->getSelectedOptions();
                                                    foreach ($selectedOptions as $selOpt) {
                                                        $selectedStoneIDs[] = $selOpt->getSelectAttributeOptionID();
                                                    }
                                                } elseif (is_array($stoneValue)) {
                                                    $selectedStoneIDs = $stoneValue;
                                                }
                                            }
                                            ?>
                                            <?php foreach ($stoneOptions as $option): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           name="attr_stone[]" 
                                                           id="stone_<?= $option['value'] ?>" 
                                                           value="<?= $option['value'] ?>"
                                                           <?= in_array($option['value'], $selectedStoneIDs) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="stone_<?= $option['value'] ?>">
                                                        <?= h($option['label']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?= Url::to('/') ?>" class="btn btn-secondary">
                                <i class="fa fa-arrow-left"></i> <?= t('Back') ?>
                            </a>
                            <div>
                                <?php if ($product && $product->getID()): ?>
                                    <button type="button" class="btn btn-danger me-2" id="delete-product-btn">
                                        <i class="fa fa-trash"></i> <?= t('Delete Product') ?>
                                    </button>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> <?= t('Save Product') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Multiple image management
    var currentImageIndex = 0;
    var uploadInProgress = {};
    
    // Update image counter
    function updateImageCounter() {
        var total = $('.product-image-slot').length;
        if (total > 1) {
            $('#image-counter').text((currentImageIndex + 1) + ' / ' + total);
            $('#image-navigation').show();
        } else {
            $('#image-counter').text('1 / 1');
            $('#image-navigation').hide();
        }
    }
    
    // Show specific image slot
    function showImageSlot(index) {
        $('.product-image-slot').hide();
        $('.product-image-slot[data-slot-index="' + index + '"]').show();
        currentImageIndex = index;
        updateImageCounter();
    }
    
    // Update Make Primary link for a slot after an image is added
    function updateMakePrimaryLink(slotIndex, fileID) {
        var $slot = $('.product-image-slot[data-slot-index="' + slotIndex + '"]');
        var $preview = $slot.find('[id^="image-preview-"]');
        
        // Remove any existing badge/link
        $preview.find('.primary-badge, .make-primary-link').remove();
        
        // Only add Make Primary link if this is not the primary slot (index 0) and has an image
        if (slotIndex > 0 && fileID && fileID != '0') {
            $preview.append('<a href="#" class="badge bg-secondary position-absolute top-0 end-0 m-2 make-primary-link" data-fid="' + fileID + '"><?= t("Make Primary") ?></a>');
        } else if (slotIndex == 0 && fileID && fileID != '0') {
            $preview.append('<span class="badge bg-primary position-absolute top-0 end-0 m-2 primary-badge"><?= t("Primary") ?></span>');
        }
    }
    
    // Navigation
    $('#prev-image').on('click', function() {
        var total = $('.product-image-slot').length;
        if (total > 1) {
            var newIndex = (currentImageIndex - 1 + total) % total;
            showImageSlot(newIndex);
        }
    });
    
    $('#next-image').on('click', function() {
        var total = $('.product-image-slot').length;
        if (total > 1) {
            var newIndex = (currentImageIndex + 1) % total;
            showImageSlot(newIndex);
        }
    });
    
    // Add new image slot
    $('#add-image-slot').on('click', function() {
        var newIndex = $('.product-image-slot').length;
        var $newSlot = $('.product-image-slot:first').clone();
        $newSlot.attr('data-slot-index', newIndex);
        $newSlot.find('.product-image-id').attr('data-slot-index', newIndex).val(0);
        $newSlot.find('.image-upload').attr('name', 'product_image_' + newIndex).attr('data-slot-index', newIndex).val('');
        $newSlot.find('.select-existing-image').attr('data-slot-index', newIndex);
        $newSlot.find('.remove-image').attr('data-slot-index', newIndex);
        // Find and update the preview area ID
        var $preview = $newSlot.find('[id^="image-preview-"]');
        $preview.attr('id', 'image-preview-' + newIndex);
        // Reset preview to blank state
        $preview.html('<div class="bg-light p-5 text-muted"><i class="fa fa-image fa-3x mb-2"></i><br><?= t("No image") ?></div>');
        $newSlot.find('.badge').remove(); // Remove primary badge from new slots
        $newSlot.hide();
        $('#product-images-container').append($newSlot);
        showImageSlot(newIndex);
    });
    
    // Remove image slot
    $(document).on('click', '.remove-image', function() {
        var slotIndex = $(this).data('slot-index');
        var $slot = $('.product-image-slot[data-slot-index="' + slotIndex + '"]');
        var total = $('.product-image-slot').length;
        
        if (total <= 1) {
            // Can't remove the last slot, just clear it
            $slot.find('.product-image-id').val(0);
            $slot.find('#image-preview-' + slotIndex).html('<div class="bg-light p-5 text-muted"><i class="fa fa-image fa-3x mb-2"></i><br><?= t("No image") ?></div>');
            $slot.find('.image-upload').val('');
        } else {
            // Remove the slot
            $slot.remove();
            // Reindex remaining slots
            var newIndex = 0;
            $('.product-image-slot').each(function() {
                var oldIndex = $(this).data('slot-index');
                if (oldIndex != slotIndex) {
                    $(this).attr('data-slot-index', newIndex);
                    $(this).find('.product-image-id').attr('data-slot-index', newIndex);
                    $(this).find('.image-upload').attr('name', 'product_image_' + newIndex).attr('data-slot-index', newIndex);
                    $(this).find('.select-existing-image').attr('data-slot-index', newIndex);
                    $(this).find('.remove-image').attr('data-slot-index', newIndex);
                    $(this).find('[id^="image-preview-"]').attr('id', 'image-preview-' + newIndex);
                    newIndex++;
                }
            });
            // Show first slot if we removed the current one
            if (currentImageIndex >= newIndex) {
                currentImageIndex = newIndex - 1;
            }
            showImageSlot(currentImageIndex);
        }
    });
    
    // Image upload with AJAX (supports chunked uploads) - for each slot
    $(document).on('change', '.image-upload', function(e) {
        var slotIndex = $(this).data('slot-index');
        var file = e.target.files[0];
        if (!file) return;
        
        // Show preview immediately
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#image-preview-' + slotIndex).html('<img src="' + e.target.result + '" class="img-fluid" style="max-width: 100%; max-height: 300px;">');
        };
        reader.readAsDataURL(file);
        
        // Upload file via ConcreteCMS file upload endpoint (supports chunked uploads)
        if (uploadInProgress[slotIndex]) {
            alert('<?= t("An upload is already in progress. Please wait.") ?>');
            return;
        }
        
        uploadInProgress[slotIndex] = true;
        var $uploadBtn = $(this).closest('label');
        var originalText = $uploadBtn.html();
        $uploadBtn.html('<i class="fa fa-spinner fa-spin"></i> <?= t("Uploading...") ?>').prop('disabled', true);
        
        // Use ConcreteCMS file upload endpoint which handles chunked uploads automatically
        var uploadUrl = '<?= \Concrete\Core\Support\Facade\Url::to("/ccm/system/file/upload") ?>';
        var tokenValue = '<?= $token->generate() ?>';
        
        // Check if we need chunked upload (file larger than 2MB)
        var chunkSize = 2 * 1024 * 1024; // 2MB chunks
        var needsChunking = file.size > chunkSize;
        
        console.log('Uploading file:', file.name, 'Size:', file.size, 'Type:', file.type, 'Needs chunking:', needsChunking);
        
        if (needsChunking) {
            // Upload in chunks using Dropzone.js format
            uploadFileInChunks(file, uploadUrl, tokenValue, chunkSize, $uploadBtn, originalText, slotIndex);
        } else {
            // Upload normally (single request)
            uploadFileSingle(file, uploadUrl, tokenValue, $uploadBtn, originalText, slotIndex);
        }
    });
    
    function uploadFileSingle(file, uploadUrl, tokenValue, $uploadBtn, originalText, slotIndex) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('fID', 0);
        formData.append('ccm_token', tokenValue);
        
        $.ajax({
            url: uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentComplete = (e.loaded / e.total) * 100;
                            $uploadBtn.html('<i class="fa fa-spinner fa-spin"></i> <?= t("Uploading...") ?> ' + Math.round(percentComplete) + '%');
                        }
                    }, false);
                }
                return xhr;
            },
            success: function(response, textStatus, xhr) {
                handleUploadSuccess(response, textStatus, xhr, $uploadBtn, originalText, slotIndex);
            },
            error: function(xhr, status, error) {
                handleUploadError(xhr, status, error, $uploadBtn, originalText, slotIndex);
            }
        });
    };
    
    function uploadFileInChunks(file, uploadUrl, tokenValue, chunkSize, $uploadBtn, originalText, slotIndex) {
        // Generate unique ID for this file upload (Dropzone.js format)
        var dzuuid = 'dz-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        var totalChunks = Math.ceil(file.size / chunkSize);
        var currentChunk = 0;
        var uploadedChunks = [];
        
        console.log('Starting chunked upload:', {
            dzuuid: dzuuid,
            totalChunks: totalChunks,
            chunkSize: chunkSize,
            fileSize: file.size
        });
        
        function uploadChunk(chunkIndex) {
            var start = chunkIndex * chunkSize;
            var end = Math.min(start + chunkSize, file.size);
            var chunk = file.slice(start, end);
            
            var formData = new FormData();
            formData.append('file', chunk, file.name);
            formData.append('fID', 0);
            formData.append('ccm_token', tokenValue);
            formData.append('dzuuid', dzuuid);
            formData.append('dzchunkindex', chunkIndex);
            formData.append('dztotalchunkcount', totalChunks);
            
            var percentComplete = ((chunkIndex + 1) / totalChunks) * 100;
            $uploadBtn.html('<i class="fa fa-spinner fa-spin"></i> <?= t("Uploading chunk") ?> ' + (chunkIndex + 1) + '/' + totalChunks + ' (' + Math.round(percentComplete) + '%)');
            
            $.ajax({
                url: uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response, textStatus, xhr) {
                    uploadedChunks.push(chunkIndex);
                    console.log('Chunk ' + chunkIndex + ' uploaded successfully');
                    
                    // Check if this was the last chunk
                    // The backend will return the file when all chunks are received
                    if (uploadedChunks.length === totalChunks) {
                        // All chunks uploaded, backend should have combined them
                        // The response from the last chunk should contain the file
                        console.log('All chunks uploaded, processing final response');
                        handleUploadSuccess(response, textStatus, xhr, $uploadBtn, originalText, slotIndex);
                    } else {
                        // Upload next chunk
                        uploadChunk(chunkIndex + 1);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Chunk ' + chunkIndex + ' upload failed:', xhr, status, error);
                    handleUploadError(xhr, status, error, $uploadBtn, originalText, slotIndex);
                }
            });
        }
        
        // Start uploading chunks
        uploadChunk(0);
    }
    
    function handleUploadSuccess(response, textStatus, xhr, $uploadBtn, originalText, slotIndex) {
        uploadInProgress[slotIndex] = false;
        $uploadBtn.html(originalText).prop('disabled', false);
        
        console.log('Upload success callback - Status:', textStatus);
        console.log('Upload success - HTTP Status:', xhr.status);
        console.log('Upload success - Full response:', response);
        console.log('Upload success - Response type:', typeof response);
        console.log('Upload success - Response keys:', response ? Object.keys(response) : 'null');
        
        // Handle string responses (might be HTML or plain text)
        if (typeof response === 'string') {
            console.log('Response is string, trying to parse as JSON...');
            try {
                response = JSON.parse(response);
                console.log('Parsed response:', response);
            } catch(e) {
                console.error('Failed to parse string response as JSON:', e);
                alert('<?= t("Upload failed: Invalid response format") ?>');
                $('#image-preview-' + slotIndex).html('<div class="bg-light p-5 text-muted text-danger"><i class="fa fa-exclamation-triangle fa-3x mb-2"></i><br><?= t("Upload failed") ?><br><small><?= t("Invalid response format") ?></small></div>');
                $('.product-image-id[data-slot-index="' + slotIndex + '"]').val('');
                $('.image-upload[data-slot-index="' + slotIndex + '"]').val('');
                return;
            }
        }
        
        // Check for errors in FileEditResponse format first
        if (response && response.errors) {
            var errorMsg = '';
            if (Array.isArray(response.errors) && response.errors.length > 0) {
                errorMsg = response.errors.join(', ');
            } else if (typeof response.errors === 'object') {
                if (response.errors.list && Array.isArray(response.errors.list)) {
                    errorMsg = response.errors.list.map(function(e) {
                        return e.message || e;
                    }).join(', ');
                } else if (response.errors.toString) {
                    errorMsg = response.errors.toString();
                } else {
                    errorMsg = JSON.stringify(response.errors);
                }
            } else if (typeof response.errors === 'string') {
                errorMsg = response.errors;
            }
            
            if (errorMsg) {
                console.error('Error in FileEditResponse:', errorMsg, 'Full response:', response);
                alert('<?= t("Upload failed:") ?> ' + errorMsg);
                $('#image-preview-' + slotIndex).html('<div class="bg-light p-5 text-muted text-danger"><i class="fa fa-exclamation-triangle fa-3x mb-2"></i><br><?= t("Upload failed") ?><br><small>' + errorMsg + '</small></div>');
                $('.product-image-id[data-slot-index="' + slotIndex + '"]').val('');
                $('.image-upload[data-slot-index="' + slotIndex + '"]').val('');
                return;
            }
        }
        
        // Also check for legacy error format
        if (response && response.error) {
            var errorMsg = '';
            if (typeof response.error === 'string') {
                errorMsg = response.error;
            } else if (typeof response.error === 'boolean' && response.error === true) {
                errorMsg = response.message || response.errorMessage || '<?= t("Upload was rejected by server") ?>';
            } else if (response.error.message) {
                errorMsg = response.error.message;
            } else if (response.errorMessage) {
                errorMsg = response.errorMessage;
            } else if (response.message) {
                errorMsg = response.message;
            } else {
                errorMsg = JSON.stringify(response.error);
            }
            
            if (errorMsg) {
                console.error('Error in legacy format:', errorMsg, 'Full response:', response);
                alert('<?= t("Upload failed:") ?> ' + errorMsg);
                $('#image-preview-' + slotIndex).html('<div class="bg-light p-5 text-muted text-danger"><i class="fa fa-exclamation-triangle fa-3x mb-2"></i><br><?= t("Upload failed") ?><br><small>' + errorMsg + '</small></div>');
                $('.product-image-id[data-slot-index="' + slotIndex + '"]').val('');
                $('.image-upload[data-slot-index="' + slotIndex + '"]').val('');
                return;
            }
        }
        
        // Extract file ID from FileEditResponse format
        var fileID = null;
        if (response && response.files && response.files.length > 0) {
            var fileObj = response.files[0];
            if (fileObj.fID) {
                fileID = fileObj.fID;
            } else if (fileObj.id) {
                fileID = fileObj.id;
            }
        } else if (response && response.fID) {
            fileID = response.fID;
        } else if (response && typeof response === 'object' && response.id) {
            fileID = response.id;
        } else if (response && response.file && response.file.fID) {
            fileID = response.file.fID;
        }
        
        console.log('Extracted file ID:', fileID);
        console.log('Full response structure:', JSON.stringify(response, null, 2));
        
        if (fileID) {
            // Check for duplicate by checksum via our custom endpoint
            $.ajax({
                url: '<?= $view->action("check_duplicate_image") ?>',
                type: 'POST',
                data: {
                    fID: fileID,
                    ccm_token: '<?= $token->generate("check_duplicate") ?>'
                },
                success: function(dupResponse) {
                    if (dupResponse && dupResponse.existing_fID) {
                        fileID = dupResponse.existing_fID;
                        console.log('Duplicate image found, using existing file ID: ' + fileID);
                    }
                    $('.product-image-id[data-slot-index="' + slotIndex + '"]').val(fileID);
                    console.log('Image uploaded successfully, file ID: ' + fileID);
                    // Update preview with uploaded image
                    ConcreteFileManager.getFileDetails(fileID, function(r) {
                        if (r.files && r.files[0]) {
                            var file = r.files[0];
                            var $temp = $('<div>').html(file.resultsThumbnailImg || '');
                            var $img = $temp.find('img');
                            if ($img.length > 0) {
                                $('#image-preview-' + slotIndex).html('<img src="' + $img.attr('src') + '" class="img-fluid" style="max-width: 100%; max-height: 300px;">');
                            } else if (file.url) {
                                $('#image-preview-' + slotIndex).html('<img src="' + file.url + '" class="img-fluid" style="max-width: 100%; max-height: 300px;">');
                            }
                            // Add Make Primary link for non-primary slots
                            updateMakePrimaryLink(slotIndex, fileID);
                        }
                    });
                },
                error: function() {
                    // If duplicate check fails, just use the uploaded file
                    $('.product-image-id[data-slot-index="' + slotIndex + '"]').val(fileID);
                }
            });
        } else {
            alert('<?= t("Upload failed: Unknown error. Please check the file size and try again.") ?>');
            $('#image-preview-' + slotIndex).html('<div class="bg-light p-5 text-muted text-danger"><i class="fa fa-exclamation-triangle fa-3x mb-2"></i><br><?= t("Upload failed") ?></div>');
            $('.product-image-id[data-slot-index="' + slotIndex + '"]').val('');
            $('.image-upload[data-slot-index="' + slotIndex + '"]').val('');
            console.error('Upload response:', response);
        }
    }
    
    function handleUploadError(xhr, status, error, $uploadBtn, originalText, slotIndex) {
        uploadInProgress[slotIndex] = false;
        $uploadBtn.html(originalText).prop('disabled', false);
        
        console.error('Upload error - XHR:', xhr);
        console.error('Upload error - Status:', status);
        console.error('Upload error - Error:', error);
        console.error('Upload error - Response Text:', xhr.responseText);
        console.error('Upload error - Response JSON:', xhr.responseJSON);
        console.error('Upload error - Status Code:', xhr.status);
        
        var errorMsg = '<?= t("Upload failed") ?>';
        var errorDetails = '';
        
        // Try to extract error message from response
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                // FileEditResponse format
                if (Array.isArray(xhr.responseJSON.errors)) {
                    errorDetails = xhr.responseJSON.errors.join(', ');
                } else if (typeof xhr.responseJSON.errors === 'string') {
                    errorDetails = xhr.responseJSON.errors;
                } else {
                    errorDetails = JSON.stringify(xhr.responseJSON.errors);
                }
            } else if (xhr.responseJSON.error) {
                if (typeof xhr.responseJSON.error === 'string') {
                    errorDetails = xhr.responseJSON.error;
                } else if (typeof xhr.responseJSON.error === 'object' && xhr.responseJSON.error.message) {
                    errorDetails = xhr.responseJSON.error.message;
                } else if (typeof xhr.responseJSON.error === 'boolean') {
                    errorDetails = '<?= t("Upload was rejected by server") ?>';
                } else {
                    errorDetails = JSON.stringify(xhr.responseJSON.error);
                }
            } else if (xhr.responseJSON.message) {
                errorDetails = xhr.responseJSON.message;
            } else {
                errorDetails = JSON.stringify(xhr.responseJSON);
            }
        } else if (xhr.responseText) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.errors) {
                    errorDetails = Array.isArray(response.errors) ? response.errors.join(', ') : response.errors;
                } else if (response.error) {
                    errorDetails = typeof response.error === 'string' ? response.error : JSON.stringify(response.error);
                } else if (response.message) {
                    errorDetails = response.message;
                } else {
                    errorDetails = xhr.responseText.substring(0, 200);
                }
            } catch(e) {
                errorDetails = xhr.responseText.substring(0, 200);
            }
        }
        
        if (!errorDetails) {
            if (xhr.status === 0) {
                errorDetails = '<?= t("Network error or request cancelled") ?>';
            } else if (xhr.status === 413) {
                errorDetails = '<?= t("File too large") ?>';
            } else if (xhr.status === 403) {
                errorDetails = '<?= t("Access denied") ?>';
            } else if (xhr.status === 500) {
                errorDetails = '<?= t("Server error") ?>';
            } else {
                errorDetails = error || '<?= t("Unknown error") ?>';
            }
        }
        
        errorMsg += ': ' + errorDetails;
        
        alert(errorMsg);
        $('#image-preview-' + slotIndex).html('<div class="bg-light p-5 text-muted text-danger"><i class="fa fa-exclamation-triangle fa-3x mb-2"></i><br><?= t("Upload failed") ?><br><small>' + errorDetails + '</small></div>');
        $('.product-image-id[data-slot-index="' + slotIndex + '"]').val('');
        $('.image-upload[data-slot-index="' + slotIndex + '"]').val('');
    }
    
    // Select existing image - for each slot
    $(document).on('click', '.select-existing-image', function() {
        var slotIndex = $(this).data('slot-index');
        ConcreteFileManager.launchDialog(function(data) {
            ConcreteFileManager.getFileDetails(data.fID, function(r) {
                if (r.files && r.files[0]) {
                    var file = r.files[0];
                    $('.product-image-id[data-slot-index="' + slotIndex + '"]').val(data.fID);
                    
                    // Extract image URL from resultsThumbnailImg (usually HTML)
                    var imageUrl = '';
                    if (file.resultsThumbnailImg) {
                        // Create a temporary element to parse the HTML
                        var $temp = $('<div>').html(file.resultsThumbnailImg);
                        var $img = $temp.find('img');
                        if ($img.length > 0) {
                            imageUrl = $img.attr('src');
                        } else {
                            // If no img tag, try using it as a direct URL
                            imageUrl = file.resultsThumbnailImg;
                        }
                    }
                    
                    // Fallback: use file.url or construct relative path
                    if (!imageUrl) {
                        if (file.url) {
                            imageUrl = file.url;
                        } else {
                            // Construct relative URL
                            imageUrl = '/index.php/tools/files/get_file?fID=' + data.fID;
                        }
                    }
                    
                    // Update preview
                    $('#image-preview-' + slotIndex).html('<img src="' + imageUrl + '" class="img-fluid" style="max-width: 100%; max-height: 300px;">');
                    // Add Make Primary link for non-primary slots
                    updateMakePrimaryLink(slotIndex, data.fID);
                }
            });
        }, {
            filters: [{"field": "type", "type": 1}]
        });
    });
    
    // Make Primary - click handler
    $(document).on('click', '.make-primary-link', function(e) {
        e.preventDefault();
        var $link = $(this);
        var $slot = $link.closest('.product-image-slot');
        var slotIndex = $slot.data('slot-index');
        var fID = $link.data('fid');
        
        // Find the current primary slot (index 0)
        var $primarySlot = $('.product-image-slot[data-slot-index="0"]');
        
        // Get the current primary image ID
        var primaryFID = $primarySlot.find('.product-image-id').val();
        
        // Swap the file IDs in the hidden inputs
        $primarySlot.find('.product-image-id').val(fID);
        $slot.find('.product-image-id').val(primaryFID);
        
        // Swap the visual content
        var primaryHtml = $primarySlot.find('[id^="image-preview-"]').html();
        var currentHtml = $slot.find('[id^="image-preview-"]').html();
        
        $primarySlot.find('[id^="image-preview-"]').html(currentHtml);
        $slot.find('[id^="image-preview-"]').html(primaryHtml);
        
        // Update the badges/links
        // The new primary slot gets the Primary badge
        $primarySlot.find('.make-primary-link').replaceWith('<span class="badge bg-primary position-absolute top-0 end-0 m-2 primary-badge"><?= t("Primary") ?></span>');
        
        // The old primary slot (now in current slot position) gets a Make Primary link if it has an image
        if (primaryFID && primaryFID != '0') {
            $slot.find('.primary-badge').replaceWith('<a href="#" class="badge bg-secondary position-absolute top-0 end-0 m-2 make-primary-link" data-fid="' + primaryFID + '"><?= t("Make Primary") ?></a>');
        } else {
            $slot.find('.primary-badge').remove();
        }
        
        // Navigate to show the primary slot
        showImageSlot(0);
    });
    
    // Initialize image counter
    updateImageCounter();
    
    // Delete product
    $('#delete-product-btn').on('click', function() {
        if (confirm('<?= t("Are you sure you want to delete this product? This action cannot be undone.") ?>')) {
            var form = $('<form>', {
                'method': 'POST',
                'action': '<?= $view->action("delete") ?>'
            });
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'productID',
                'value': '<?= $productID ?>'
            }));
            form.append('<?= $token->output("delete_product") ?>');
            $('body').append(form);
            form.submit();
        }
    });
});
</script>

<style>
@media (max-width: 768px) {
    .card {
        margin-bottom: 1rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
}
</style>
