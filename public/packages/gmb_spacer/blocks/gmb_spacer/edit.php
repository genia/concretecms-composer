<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php
// Ensure values are properly assigned
$allowedUnits = ['px', 'em', 'vh', 'vw'];
$spacerHeight = isset($spacerHeight) ? max(1, intval($spacerHeight)) : 1;
$spacerUnit = in_array($spacerUnit, $allowedUnits) ? $spacerUnit : 'px';
?>

<div class="form-group">
    <?= $form->label('spacerHeight', t('Height of the space:')) ?>
    <div class="input-group">
        <?= $form->number('spacerHeight', $spacerHeight, ['id' => 'spacerHeightText', 'step' => '1', 'min' => '1']) ?>
        <div class="input-group-addon">
            <?= $form->select('spacerUnit', ['px' => 'px', 'em' => 'em', 'vh' => 'vh', 'vw' => 'vw'], $spacerUnit) ?>
        </div>
    </div>
</div>

<div class="form-group">
    <?= $form->label('slider', t('Adjust Height:')) ?>
    <input type="range" id="spacerHeightSlider" value="<?= (int) max($spacerHeight, 1) ?>" min="1" max="200" step="1"
        oninput="document.getElementById('spacerHeightText').value = this.value">
</div>

<div class="form-group form-check">
    <label class="form-check-label">
        <?php echo $form->checkbox('spacerHighlight', 1, (bool) ($spacerHighlight ?? false), ['class' => 'form-check-input']); ?>
        <?php echo t('Enable Spacer Highlight in Edit'); ?>
    </label>
</div>

<script>
document.getElementById('spacerHeightSlider').addEventListener('input', function() {
    document.getElementById('spacerHeightText').value = Math.max(1, this.value);
});
document.getElementById('spacerHeightText').addEventListener('input', function() {
    document.getElementById('spacerHeightSlider').value = Math.max(1, this.value);
});
</script>