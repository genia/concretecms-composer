<?php defined('C5_EXECUTE') or exit('Access Denied.'); ?>


<?php
if (isset($error)) {
    echo '<div class="alert alert-error">'.$error.'</div>';
}
if (! isset($query) || ! is_string($query)) {
    $query = '';
}
?>

<?php // Search X Focus?>
<form class="ccm-search-block-form" action="<?php echo \Concrete\Core\Support\Facade\Url::to($resultTarget); ?>" method="get">
	
	<div class="form-group">
		<?php // Input Height - Elemental ONLY
        $inputStyle = '';
if (\Concrete\Core\Page\Page::getCurrentPage()->getCollectionThemeObject()->getThemeHandle() == 'elemental') {
    $inputStyle = 'height: 3em;';
}
if (! empty($title)) {
    echo '<h3>'.$form->label('query', t($title), ['class' => 'control-label']).'</h3>';
} ?>
		<?php if (! empty($buttonText)) { ?>
			<div class="input-group">
				<?php echo $form->text('query', $query, ['class' => 'form-control enlilSearchTemplatesFocus-'.$this->block->getBlockID(), 'style' => $inputStyle]); ?>
				<span class="input-group-btn input-group-button ms-3">
					<?php echo $form->submit('submit', h($buttonText), ['class' => 'btn btn-default btn-secondary ccm-search-block-submit']); ?>
				</span>
			</div>
		<?php } else { ?>
			<?php echo $form->text('query', $query, ['class' => 'form-control enlilSearchTemplatesFocus-'.$this->block->getBlockID(), 'style' => $inputStyle]); ?>
		<?php } ?>
	</div>
</form>

<?php // Focus?>
<?php if (empty($query)) { ?>
	<script>
		$(document).ready(function() {  $('.enlilSearchTemplatesFocus-<?php echo $this->block->getBlockID(); ?>').focus();  });
	</script>
<?php }
