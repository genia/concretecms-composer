<?php defined('C5_EXECUTE') or exit('Access Denied.'); ?>


<?php
if (isset($error)) {
    echo '<div class="alert alert-error">'.$error.'</div>';
}
if (! isset($query) || ! is_string($query)) {
    $query = '';
}
?>

<?php // Search Only Focus?>
<form class="ccm-search-block-form" action="<?php echo \Concrete\Core\Support\Facade\Url::to($resultTarget); ?>" method="get">
	
	<div class="form-group">
		<?php if (empty($query)) {
		    echo $form->hidden('search_paths[]', h($baseSearchPath, ENT_COMPAT, APP_CHARSET));
		} elseif (\Concrete\Core\Http\Request::request('search_paths') && is_array(\Concrete\Core\Http\Request::request('search_paths'))) {
		    foreach (\Concrete\Core\Http\Request::request('search_paths') as $search_path) {
		        if (is_string($search_path)) {
		            echo $form->hidden('search_paths[]', h($search_path, ENT_COMPAT, APP_CHARSET));
		        }
		    }
		} ?>
		
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
		
		<?php if ($allowUserOptions) { ?>
			<div>
				<h5><?php echo t('Advanced Search Options'); ?></h5>
				<input type="radio" name="options" value="ALL" <?php echo $searchAll ? 'checked' : null; ?>/>
				<span class="enlilSearchTemplatesOptionSpan"><?php echo t('Search All Sites'); ?></span>
				<input type="radio" name="options" value="CURRENT" <?php echo $searchAll ? null : 'checked'; ?>/>
				<span><?php echo t('Search Current Site'); ?></span>
			</div>
		<?php } ?>
	</div>
</form>

<?php // Focus?>
<?php if (empty($query)) { ?>
	<script>
		$(document).ready(function() {  $('.enlilSearchTemplatesFocus-<?php echo $this->block->getBlockID(); ?>').focus();  });
	</script>
<?php }
