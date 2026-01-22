<?php
namespace Application\Controller\SinglePage\Dashboard\Store;

use Concrete\Core\Routing\Redirect;
use Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Override of Community Store products controller to add bulk operations
 */
class Products extends \Concrete\Package\CommunityStore\Controller\SinglePage\Dashboard\Store\Products
{
    /**
     * Delete multiple products at once
     */
    public function deleteSelected()
    {
        if (!$this->token->validate('community_store_bulk')) {
            return new JsonResponse([
                'success' => false,
                'message' => t('Invalid token')
            ], 403);
        }
        
        $productIds = $this->request->request->get('productIds', []);
        
        if (empty($productIds) || !is_array($productIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => t('No products selected')
            ], 400);
        }
        
        $deleted = 0;
        $errors = [];
        
        foreach ($productIds as $pID) {
            $pID = (int) $pID;
            if ($pID > 0) {
                try {
                    $product = Product::getByID($pID);
                    if ($product) {
                        $product->remove();
                        $deleted++;
                    }
                } catch (\Exception $e) {
                    $errors[] = t('Failed to delete product %s: %s', $pID, $e->getMessage());
                }
            }
        }
        
        return new JsonResponse([
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors,
            'message' => t('%d product(s) deleted', $deleted)
        ]);
    }
}
