<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php
$allowedUnits = ['px', 'em', 'vh', 'vw'];
$heightValue = isset($spacerHeight) ? max(1, intval($spacerHeight)) : 1;
$spacerUnit = in_array($spacerUnit, $allowedUnits) ? $spacerUnit : 'px';
$height = $heightValue . $spacerUnit;

// Check if in edit mode
$isEditMode = $controller->getCollectionObject()->isEditMode();

// Check if spacerHighlight checkbox is ticked
$shouldHighlight = isset($spacerHighlight) && $spacerHighlight; 
?>

<div class="spacer" style="height: <?= htmlspecialchars($height, ENT_QUOTES, 'UTF-8') ?>;  
	 <?= ($c && $c->isEditMode() && $shouldHighlight) ? 'background-image: repeating-linear-gradient(45deg,rgba(17, 17, 17, 0.3), rgba(17, 17, 17, 0.3) 10px, rgba(255, 215, 0, 0.3) 10px, rgba(255, 215, 0, 0.3) 20px); border: 1px solid #ffd700;' : '' ?>">
    <?php if ($c && $c->isEditMode() && $shouldHighlight && !($heightValue < 16 && $spacerUnit === 'px')) : ?>
        <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                     color: #000000; font-size: 16px; display: block; text-align: center; font-weight:bold; ">
            SPACER (<?= htmlspecialchars($height, ENT_QUOTES, 'UTF-8') ?>)
        </span>
    <?php endif; ?>
</div>
