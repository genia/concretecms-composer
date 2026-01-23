<?php
namespace Application\Controller\SinglePage\Store\Product;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Core\File\File;
use Concrete\Core\File\Importer;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Url;
use Concrete\Core\Routing\Redirect;
use Concrete\Core\Validation\CSRF\Token;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Concrete\Package\CommunityStore\Src\CommunityStore\Utilities\Multilingual as CSMultilingual;
use Concrete\Package\CommunityStore\Src\CommunityStore\Multilingual\Translation;
use Concrete\Core\Support\Facade\Log;

class Edit extends PageController
{
    public $token;
    
    public function on_start()
    {
        parent::on_start();
        $this->token = new Token();
    }
    
    /**
     * Check if uploaded file is a duplicate by checksum
     */
    public function check_duplicate_image()
    {
        // Check if user is admin
        $u = new \Concrete\Core\User\User();
        $isAdmin = false;
        if ($u->isRegistered()) {
            $ui = $u->getUserInfoObject();
            if ($ui) {
                $adminGroup = \Concrete\Core\User\Group\Group::getByName('Administrators');
                if ($adminGroup && $ui->inGroup($adminGroup)) {
                    $isAdmin = true;
                } elseif ($u->getUserID() == 1) {
                    $isAdmin = true; // Super admin
                }
            }
        }
        
        if (!$isAdmin) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Access denied')
            ], 403);
        }
        
        if (!$this->token->validate('check_duplicate')) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Invalid token')
            ], 403);
        }
        
        $fID = (int)$this->post('fID');
        if ($fID <= 0) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Invalid file ID')
            ], 400);
        }
        
        try {
            $file = File::getByID($fID);
            if (!$file || $file->isError()) {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => t('File not found')
                ], 404);
            }
            
            $fileVersion = $file->getApprovedVersion();
            if (!$fileVersion) {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => t('File version not found')
                ], 404);
            }
            
            $filePath = $fileVersion->getFile();
            if (!file_exists($filePath)) {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => t('File does not exist on disk')
                ], 404);
            }
            
            $fileContent = @file_get_contents($filePath);
            if ($fileContent === false) {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => t('Failed to read file')
                ], 500);
            }
            
            $checksum = md5($fileContent);
            $fileSize = strlen($fileContent);
            
            // Check if a file with this checksum already exists (excluding current file)
            $existingFile = $this->findExistingFileByChecksum($checksum, $fileSize, $fID);
            if ($existingFile) {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'existing_fID' => $existingFile->getFileID(),
                    'message' => t('Duplicate file found')
                ]);
            }
            
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'message' => t('No duplicate found')
            ]);
            
        } catch (\Exception $e) {
            Log::addError('Check duplicate image exception: ' . $e->getMessage());
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Error checking for duplicate: %s', $e->getMessage())
            ], 500);
        }
    }
    
    /**
     * Handle AJAX image upload (supports chunked uploads)
     * Note: This method is kept for backward compatibility but we now use the system file upload endpoint
     */
    public function upload_image()
    {
        // Check if user is admin
        $u = new \Concrete\Core\User\User();
        $isAdmin = false;
        if ($u->isRegistered()) {
            $ui = $u->getUserInfoObject();
            if ($ui) {
                $adminGroup = \Concrete\Core\User\Group\Group::getByName('Administrators');
                if ($adminGroup && $ui->inGroup($adminGroup)) {
                    $isAdmin = true;
                } elseif ($u->getUserID() == 1) {
                    $isAdmin = true; // Super admin
                }
            }
        }
        
        if (!$isAdmin) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Access denied')
            ], 403);
        }
        
        if (!$this->token->validate('upload_image')) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Invalid token')
            ], 403);
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
                UPLOAD_ERR_FORM_SIZE => t('The uploaded file exceeds the MAX_FILE_SIZE directive'),
                UPLOAD_ERR_PARTIAL => t('The uploaded file was only partially uploaded'),
                UPLOAD_ERR_NO_FILE => t('No file was uploaded'),
                UPLOAD_ERR_NO_TMP_DIR => t('Missing a temporary folder'),
                UPLOAD_ERR_CANT_WRITE => t('Failed to write file to disk'),
                UPLOAD_ERR_EXTENSION => t('A PHP extension stopped the file upload'),
            ];
            $uploadError = isset($_FILES['file']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
            $errorMsg = isset($errorMessages[$uploadError]) ? $errorMessages[$uploadError] : t('Unknown upload error: %s', $uploadError);
            
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => $errorMsg
            ], 400);
        }
        
        try {
            // Check for duplicates by checksum
            $imageData = file_get_contents($_FILES['file']['tmp_name']);
            if ($imageData === false) {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => t('Failed to read uploaded file')
                ], 400);
            }
            
            $checksum = md5($imageData);
            $fileSize = strlen($imageData);
            
            // Check if a file with this checksum already exists
            $existingFile = $this->findExistingFileByChecksum($checksum, $fileSize);
            if ($existingFile) {
                Log::addInfo('Found existing image with matching checksum, reusing file ID: ' . $existingFile->getFileID());
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'fID' => $existingFile->getFileID(),
                    'message' => t('File already exists, reusing existing file')
                ]);
            }
            
            // Upload the new file
            $importer = new Importer();
            $uploadedFileVersion = $importer->import($_FILES['file']['tmp_name'], $_FILES['file']['name']);
            
            if ($uploadedFileVersion instanceof \Concrete\Core\Entity\File\Version) {
                $uploadedFile = $uploadedFileVersion->getFile();
                if ($uploadedFile && !$uploadedFile->isError()) {
                    return new \Symfony\Component\HttpFoundation\JsonResponse([
                        'fID' => $uploadedFile->getFileID(),
                        'message' => t('File uploaded successfully')
                    ]);
                } else {
                    return new \Symfony\Component\HttpFoundation\JsonResponse([
                        'error' => t('File object has an error')
                    ], 500);
                }
            } else {
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => t('Importer did not return a valid file version')
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::addError('Image upload exception: ' . $e->getMessage());
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => t('Upload failed: %s', $e->getMessage())
            ], 500);
        }
    }
    
    public function view($productID = null)
    {
        // Check if user is admin
        $u = new \Concrete\Core\User\User();
        $isAdmin = false;
        if ($u->isRegistered()) {
            $ui = $u->getUserInfoObject();
            if ($ui) {
                $adminGroup = \Concrete\Core\User\Group\Group::getByName('Administrators');
                if ($adminGroup && $ui->inGroup($adminGroup)) {
                    $isAdmin = true;
                } elseif ($u->getUserID() == 1) {
                    $isAdmin = true; // Super admin
                }
            }
        }
        
        if (!$isAdmin) {
            return Redirect::to('/');
        }
        
        if ($productID === 'new' || $productID === null) {
            $productID = 0;
        } else {
            $productID = (int)$productID;
        }
        
        $this->set('productID', $productID);
    }
    
    public function save()
    {
        // Check if user is admin
        $u = new \Concrete\Core\User\User();
        $isAdmin = false;
        if ($u->isRegistered()) {
            $ui = $u->getUserInfoObject();
            if ($ui) {
                $adminGroup = \Concrete\Core\User\Group\Group::getByName('Administrators');
                if ($adminGroup && $ui->inGroup($adminGroup)) {
                    $isAdmin = true;
                } elseif ($u->getUserID() == 1) {
                    $isAdmin = true; // Super admin
                }
            }
        }
        
        if (!$isAdmin) {
            return Redirect::to('/');
        }
        
        if (!$this->token->validate('save_product')) {
            $this->error->add(t('Invalid token'));
            return $this->view($this->post('productID'));
        }
        
        $app = Application::getFacadeApplication();
        $csm = $app->make(CSMultilingual::class);
        $errors = [];
        
        $productID = (int)$this->post('productID');
        $isNew = ($productID === 0);
        
        // Validate required fields
        $productNameEN = trim($this->post('product_name_en'));
        $productPrice = $this->post('product_price');
        
        if (empty($productNameEN)) {
            $errors[] = t('Product name (English) is required');
        }
        
        if (empty($productPrice) || $productPrice < 0) {
            $errors[] = t('Product price is required and must be 0 or greater');
        }
        
        if (!empty($errors)) {
            $this->set('errors', $errors);
            $this->set('productID', $productID);
            return $this->view($productID);
        }
        
        try {
            // Handle image upload - now done via AJAX, so we only need the file ID
            $imageID = (int)$this->post('product_image_id');
            
            // Legacy support: if file was uploaded via form (not AJAX), handle it
            if (isset($_FILES['product_image']) && !empty($_FILES['product_image']['tmp_name'])) {
                $uploadError = $_FILES['product_image']['error'];
                
                // Check for upload errors
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
                        UPLOAD_ERR_FORM_SIZE => t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
                        UPLOAD_ERR_PARTIAL => t('The uploaded file was only partially uploaded'),
                        UPLOAD_ERR_NO_FILE => t('No file was uploaded'),
                        UPLOAD_ERR_NO_TMP_DIR => t('Missing a temporary folder'),
                        UPLOAD_ERR_CANT_WRITE => t('Failed to write file to disk'),
                        UPLOAD_ERR_EXTENSION => t('A PHP extension stopped the file upload'),
                    ];
                    $errorMsg = isset($errorMessages[$uploadError]) ? $errorMessages[$uploadError] : t('Unknown upload error: %s', $uploadError);
                    $errors[] = t('Image upload failed: %s', $errorMsg);
                    Log::addError('Image upload error: ' . $errorMsg . ' (error code: ' . $uploadError . ')');
                } elseif ($uploadError === UPLOAD_ERR_OK) {
                    // New file uploaded - check for duplicates by checksum first
                    $tmpPath = $_FILES['product_image']['tmp_name'];
                    
                    if (!file_exists($tmpPath)) {
                        $errors[] = t('Uploaded file temporary path does not exist');
                        Log::addError('Uploaded file temporary path does not exist: ' . $tmpPath);
                    } else {
                        $imageData = @file_get_contents($tmpPath);
                        if ($imageData === false || empty($imageData)) {
                            $errors[] = t('Failed to read uploaded image file');
                            Log::addError('Failed to read uploaded image file from: ' . $tmpPath);
                        } else {
                            $checksum = md5($imageData);
                            $fileSize = strlen($imageData);
                            
                            // Check if a file with this checksum already exists
                            $existingFile = $this->findExistingFileByChecksum($checksum, $fileSize);
                            if ($existingFile) {
                                Log::addInfo('Found existing image with matching checksum, reusing file ID: ' . $existingFile->getFileID());
                                $imageID = $existingFile->getFileID();
                            } else {
                                // No duplicate found, upload the new file
                                try {
                                    $importer = new Importer();
                                    $uploadedFileVersion = $importer->import($tmpPath, $_FILES['product_image']['name']);
                                    
                                    if ($uploadedFileVersion instanceof \Concrete\Core\Entity\File\Version) {
                                        $uploadedFile = $uploadedFileVersion->getFile();
                                        if ($uploadedFile && !$uploadedFile->isError()) {
                                            $imageID = $uploadedFile->getFileID();
                                            Log::addInfo('Uploaded new image, file ID: ' . $imageID);
                                        } else {
                                            $errorMsg = $uploadedFile ? 'File object has error' : 'File object is null';
                                            $errors[] = t('Failed to upload image: %s', $errorMsg);
                                            Log::addError('Image upload failed: ' . $errorMsg);
                                        }
                                    } else {
                                        $errors[] = t('Failed to upload image: Importer did not return a valid file version');
                                        Log::addError('Image upload failed: Importer returned: ' . gettype($uploadedFileVersion));
                                    }
                                } catch (\Exception $e) {
                                    $errors[] = t('Failed to upload image: %s', $e->getMessage());
                                    Log::addError('Image upload exception: ' . $e->getMessage());
                                    Log::addError('Stack trace: ' . $e->getTraceAsString());
                                }
                            }
                        }
                    }
                }
            }
            
            // Get or create product
            if ($isNew) {
                // Create new product
                $data = [
                    'pSKU' => '',
                    'pName' => $productNameEN,
                    'pDesc' => trim($this->post('product_desc_en')),
                    'pPrice' => (float)$productPrice,
                    'pActive' => 1,
                    'pQtyUnlim' => 1, // Unlimited quantity by default
                    'pNoQty' => '', // Not "no quantity" - quantity is tracked
                    'pAllowDecimalQty' => false, // No decimal quantities by default
                    'pCostPrice' => '', // Explicitly set to empty string to prevent null
                    'pWholesalePrice' => '', // Explicitly set to empty string to prevent null
                    'pfID' => $imageID > 0 ? $imageID : 0, // Set image ID in data array
                ];
                
                $product = Product::saveProduct($data);
                if (!$product) {
                    throw new \Exception(t('Failed to create product'));
                }
                
                // Set image ID after product creation (in case pfID in data array didn't work)
                if ($imageID > 0) {
                    $file = File::getByID($imageID);
                    if ($file && !$file->isError()) {
                        $product->setImageId($imageID);
                        $product->save(); // Save again to persist the image
                    }
                }
            } else {
                // Update existing product
                $product = Product::getByID($productID);
                if (!$product) {
                    throw new \Exception(t('Product not found'));
                }
                
                $product->setName($productNameEN);
                $product->setDescription(trim($this->post('product_desc_en')));
                $product->setPrice((float)$productPrice);
                
                // Set image ID
                if ($imageID > 0) {
                    $file = File::getByID($imageID);
                    if ($file && !$file->isError()) {
                        $product->setImageId($imageID);
                    }
                } else {
                    // Remove image if explicitly cleared
                    $product->setImageId(0);
                }
            }
            
            // Save product
            $product->save();
            
            // Process multilingual translations (always process, even if empty, to allow clearing)
            $productNameRU = trim($this->post('product_name_ru', ''));
            $productDescRU = trim($this->post('product_desc_ru', ''));
            
            error_log('=== Translation Save Debug ===');
            error_log('Product ID: ' . $product->getID());
            error_log('Product Name RU (raw): ' . var_export($this->post('product_name_ru'), true));
            error_log('Product Name RU (trimmed): ' . var_export($productNameRU, true));
            error_log('Product Desc RU (raw): ' . var_export($this->post('product_desc_ru'), true));
            error_log('Product Desc RU (trimmed): ' . var_export($productDescRU, true));
            
            Log::addInfo('=== Translation Save Debug ===');
            Log::addInfo('Product ID: ' . $product->getID());
            Log::addInfo('Product Name RU (raw): ' . var_export($this->post('product_name_ru'), true));
            Log::addInfo('Product Name RU (trimmed): ' . var_export($productNameRU, true));
            Log::addInfo('Product Desc RU (raw): ' . var_export($this->post('product_desc_ru'), true));
            Log::addInfo('Product Desc RU (trimmed): ' . var_export($productDescRU, true));
            
            $this->saveMultilingualTranslations($product, $productNameRU, $productDescRU);
            
            // Process attributes
            $this->processAttributes($product);
            
            // Save again to ensure all changes are persisted
            $product->save();
            
            // Generate product page if it doesn't exist (for click handler to work)
            // Check if product already has a page (same logic as import script)
            if (!$product->getPageID()) {
                // Generate the product page - this will create the detail page
                $product->generatePage();
            }
            
            // Redirect to edit page
            $this->set('productID', $product->getID());
            return $this->view($product->getID());
            
        } catch (\Exception $e) {
            Log::addError('Product save error: ' . $e->getMessage());
            $errors[] = t('An error occurred while saving the product: %s', $e->getMessage());
            $this->set('errors', $errors);
            $this->set('productID', $productID);
            return $this->view($productID);
        }
    }
    
    /**
     * Save multilingual translations
     */
    private function saveMultilingualTranslations($product, $productNameRU, $productDescRU)
    {
        try {
            error_log('saveMultilingualTranslations called');
            Log::addInfo('saveMultilingualTranslations called');
            $em = $this->app->make('Doctrine\ORM\EntityManager');
            $productID = $product->getID();
            error_log('EntityManager obtained, Product ID: ' . $productID);
            Log::addInfo('EntityManager obtained, Product ID: ' . $productID);
            
            // Save Russian product name (always save, even if empty, to allow clearing)
            if (!empty($productNameRU)) {
                error_log('Saving productName translation, text: ' . $productNameRU);
                Log::addInfo('Saving productName translation, text: ' . $productNameRU);
                $this->saveTranslation($em, $productID, 'productName', 'ru_RU', $productNameRU, false);
            } else {
                error_log('Skipping productName translation (empty)');
                Log::addInfo('Skipping productName translation (empty)');
            }
            
            // Save Russian product description (always save, even if empty, to allow clearing)
            // Note: description uses isLongText = true in import script
            if (!empty($productDescRU)) {
                error_log('Saving productDescription translation, text: ' . $productDescRU);
                Log::addInfo('Saving productDescription translation, text: ' . $productDescRU);
                $this->saveTranslation($em, $productID, 'productDescription', 'ru_RU', $productDescRU, true);
            } else {
                error_log('Skipping productDescription translation (empty)');
                Log::addInfo('Skipping productDescription translation (empty)');
            }
            
            error_log('saveMultilingualTranslations completed successfully');
            Log::addInfo('saveMultilingualTranslations completed successfully');
            
        } catch (\Exception $e) {
            error_log('Error saving multilingual translations: ' . $e->getMessage());
            error_log('Translation error details: ' . $e->getTraceAsString());
            Log::addError('Error saving multilingual translations: ' . $e->getMessage());
            Log::addError('Translation error details: ' . $e->getTraceAsString());
            throw $e; // Re-throw to see the error
        }
    }
    
    /**
     * Save a translation
     */
    private function saveTranslation($em, $productID, $entityType, $locale, $text, $isLongText)
    {
        $logMsg = "saveTranslation called: productID={$productID}, entityType={$entityType}, locale={$locale}, isLongText=" . ($isLongText ? 'true' : 'false') . ", text length=" . strlen($text);
        error_log($logMsg);
        Log::addInfo($logMsg);
        
        try {
            // Use QueryBuilder like the import script does
            $qb = $em->createQueryBuilder();
            $query = $qb->select('t')
                ->from('Concrete\Package\CommunityStore\Src\CommunityStore\Multilingual\Translation', 't')
                ->where('t.entityType = :type')
                ->andWhere('t.locale = :locale')
                ->andWhere('t.pID = :pid')
                ->setParameter('type', $entityType)
                ->setParameter('locale', $locale)
                ->setParameter('pid', $productID)
                ->setMaxResults(1)
                ->getQuery();

            error_log('Query built, executing...');
            Log::addInfo('Query built, executing...');
            $existing = $query->getResult();
            $foundCount = count($existing);
            error_log('Query executed, found ' . $foundCount . ' existing translations');
            Log::addInfo('Query executed, found ' . $foundCount . ' existing translations');

            if (!empty($existing)) {
                $translation = $existing[0];
                error_log('Using existing translation');
                Log::addInfo('Using existing translation');
            } else {
                $translation = new Translation();
                error_log('Creating new Translation object');
                Log::addInfo('Creating new Translation object');
                $translation->setProductID($productID);
                $translation->setEntityType($entityType);
                $translation->setLocale($locale);
                $initMsg = 'Translation object initialized with productID=' . $productID . ', entityType=' . $entityType . ', locale=' . $locale;
                error_log($initMsg);
                Log::addInfo($initMsg);
            }

            if ($isLongText) {
                $translation->setExtendedText($text);
                $setMsg = 'Set extendedText, length: ' . strlen($text);
                error_log($setMsg);
                Log::addInfo($setMsg);
            } else {
                $translation->setTranslatedText($text);
                $setMsg = 'Set translatedText, length: ' . strlen($text);
                error_log($setMsg);
                Log::addInfo($setMsg);
            }

            error_log('Calling translation->save()...');
            Log::addInfo('Calling translation->save()...');
            $translation->save();
            $successMsg = "Translation saved successfully for product ID {$productID}, type: {$entityType}, locale: {$locale}";
            error_log($successMsg);
            Log::addInfo($successMsg);
            
        } catch (\Exception $e) {
            $errorMsg = 'Error in saveTranslation: ' . $e->getMessage();
            error_log($errorMsg);
            error_log('Exception class: ' . get_class($e));
            error_log('Stack trace: ' . $e->getTraceAsString());
            Log::addError($errorMsg);
            Log::addError('Exception class: ' . get_class($e));
            Log::addError('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Delete a product
     */
    public function delete()
    {
        // Check if user is admin
        $u = new \Concrete\Core\User\User();
        $isAdmin = false;
        if ($u->isRegistered()) {
            $ui = $u->getUserInfoObject();
            if ($ui) {
                $adminGroup = \Concrete\Core\User\Group\Group::getByName('Administrators');
                if ($adminGroup && $ui->inGroup($adminGroup)) {
                    $isAdmin = true;
                } elseif ($u->getUserID() == 1) {
                    $isAdmin = true; // Super admin
                }
            }
        }
        
        if (!$isAdmin) {
            return Redirect::to('/');
        }
        
        if (!$this->token->validate('delete_product')) {
            $this->error->add(t('Invalid token'));
            $this->set('productID', $this->post('productID'));
            return $this->view($this->post('productID'));
        }
        
        $productID = (int)$this->post('productID');
        if ($productID > 0) {
            $product = Product::getByID($productID);
            if ($product) {
                try {
                    // Clear the main image reference before deletion to preserve the file
                    // The image file itself should not be deleted, only the product reference
                    $product->setImageId(0);
                    $product->save();
                    
                    // Now remove the product (this will remove associations but not the image files)
                    $product->remove();
                    return Redirect::to('/');
                } catch (\Exception $e) {
                    Log::addError('Error deleting product: ' . $e->getMessage());
                    $this->error->add(t('An error occurred while deleting the product: %s', $e->getMessage()));
                    $this->set('productID', $productID);
                    return $this->view($productID);
                }
            } else {
                $this->error->add(t('Product not found.'));
            }
        } else {
            $this->error->add(t('Invalid product ID.'));
        }
        
        return Redirect::to('/');
    }
    
    /**
     * Process product attributes
     */
    private function processAttributes($product)
    {
        try {
            $productCategory = $this->app->make('Concrete\Package\CommunityStore\Attribute\Category\ProductCategory');
            
            // Process Type (single choice)
            $typeAttr = $productCategory->getAttributeKeyByHandle('type');
            if ($typeAttr) {
                $typeValue = $this->post('attr_type');
                if (!empty($typeValue)) {
                    $this->setSelectAttributeValue($product, $typeAttr, [(int)$typeValue]);
                } else {
                    $product->setAttribute('type', null);
                }
            }
            
            // Process Metal (single choice)
            $metalAttr = $productCategory->getAttributeKeyByHandle('metal');
            if ($metalAttr) {
                $metalValue = $this->post('attr_metal');
                if (!empty($metalValue)) {
                    $this->setSelectAttributeValue($product, $metalAttr, [(int)$metalValue]);
                } else {
                    $product->setAttribute('metal', null);
                }
            }
            
            // Process Stone (multiple choice)
            $stoneAttr = $productCategory->getAttributeKeyByHandle('stone');
            if ($stoneAttr) {
                $stoneValues = $this->post('attr_stone');
                if (!empty($stoneValues) && is_array($stoneValues)) {
                    $stoneIDs = array_map('intval', $stoneValues);
                    $this->setSelectAttributeValue($product, $stoneAttr, $stoneIDs);
                } else {
                    $product->setAttribute('stone', null);
                }
            }
            
        } catch (\Exception $e) {
            Log::addWarning('Error processing product attributes: ' . $e->getMessage());
        }
    }
    
    /**
     * Set a select attribute value on a product
     */
    private function setSelectAttributeValue($product, $ak, $optionIDs)
    {
        try {
            $em = $this->app->make('Doctrine\ORM\EntityManager');
            
            // Get the select attribute settings
            $controller = $ak->getController();
            $akSettings = $ak->getAttributeKeySettings();
            
            if (!$akSettings) {
                Log::addWarning("No settings found for select attribute: " . $ak->getAttributeKeyHandle());
                return;
            }
            
            // Get the option list
            $optionList = $akSettings->getOptionList();
            if (!$optionList) {
                Log::addWarning("No option list found for select attribute: " . $ak->getAttributeKeyHandle());
                return;
            }
            
            $selectedOptions = [];
            $existingOptions = $optionList->getOptions();
            
            foreach ($optionIDs as $optionID) {
                // Find existing option
                foreach ($existingOptions as $option) {
                    if ($option->getSelectAttributeOptionID() == $optionID) {
                        $selectedOptions[] = $option;
                        break;
                    }
                }
            }
            
            if (empty($selectedOptions)) {
                // Clear attribute if no options selected
                $product->setAttribute($ak->getAttributeKeyHandle(), null);
                return;
            }
            
            // Set the attribute value on the product using array of option objects
            // This matches the approach used in the import script
            $product->setAttribute($ak->getAttributeKeyHandle(), $selectedOptions);
            
        } catch (\Exception $e) {
            Log::addWarning('Error setting select attribute value: ' . $e->getMessage());
        }
    }
    
        /**
         * Find an existing file by checksum and file size
         * This allows detecting duplicate images even if they have different filenames
         * @param string $checksum MD5 checksum of the file content
         * @param int $fileSize File size in bytes
         * @param int $excludeFID Optional file ID to exclude from search
         * @return File|false File object if found, false otherwise
         */
        private function findExistingFileByChecksum($checksum, $fileSize, $excludeFID = 0)
    {
        try {
            $db = $this->app->make('database')->connection();
            
            // Query for files with matching file size (as a first filter)
            $query = "SELECT f.fID, fv.fvFilename FROM Files f 
                      INNER JOIN FileVersions fv ON f.fID = fv.fID 
                      WHERE fv.fvIsApproved = 1 
                      AND fv.fvSize = ?
                      ORDER BY fv.fvID DESC";
            
            $results = $db->fetchAll($query, [$fileSize]);
            
                // Check each file with matching size by calculating its checksum
                foreach ($results as $row) {
                    // Skip excluded file
                    if ($excludeFID > 0 && (int)$row['fID'] === $excludeFID) {
                        continue;
                    }
                    
                    $file = File::getByID($row['fID']);
                    if ($file && !$file->isError()) {
                    $fileVersion = $file->getApprovedVersion();
                    if ($fileVersion) {
                        $filePath = $fileVersion->getFile();
                        if (file_exists($filePath)) {
                            $fileContent = @file_get_contents($filePath);
                            if ($fileContent && md5($fileContent) === $checksum) {
                                Log::addInfo('Found duplicate image by content checksum: ' . $fileVersion->getFilename() . ' (file ID: ' . $file->getFileID() . ')');
                                return $file;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's an error, just continue and upload new file
            Log::addWarning('Error checking for existing file by checksum: ' . $e->getMessage());
        }
        
        return false;
    }
}
