<?php defined('C5_EXECUTE') or exit('Access Denied.'); ?>


<?php
$page = Page::getCurrentPage();
if ($page->isEditMode()) { ?>

	<div class="ccm-edit-mode-disabled-item">
		<p>Enlil Search Templates<br>Results Only</p>
	</div>
	
<?php } else {

    if (isset($error)) {
        echo '<div class="alert alert-error">'.$error.'</div>';
    }
    if (! isset($query) || ! is_string($query)) {
        $query = '';
    }
    ?>
	
	<?php // Results Only?>
	<div class="enlilSearchTemplatesResults">
		
		<?php if (! empty($title)) { ?>
			<h3><?php echo h($title) ?></h3>
		<?php }

		if (! empty($do_search)) {
		    if (empty($results)) { ?>
				<h4><?php echo t('No Results Found.') ?></h4>
			<?php } else {

			    $th = \Concrete\Core\Support\Facade\Application::getFacadeApplication()->make('helper/text'); ?>
				
				<div id="searchResults">
					<?php foreach ($results as $r) {
					    $currentPageBody = $this->controller->highlightedExtendedMarkup($r->getPageIndexContent(), $query); ?>
						<div class="searchResult">
							<h3><a href="<?php echo $r->getCollectionLink() ?>"><?php echo $r->getCollectionName() ?></a></h3>
							<p>
								<?php
					            if ($r->getCollectionDescription()) {
					                echo $this->controller->highlightedMarkup($th->shortText($r->getCollectionDescription()), $query).'<br/>';
					            }
					            if ($currentPageBody) { // Lets remove breaks where $currentPageBody is empty!
					                echo $currentPageBody.'<br/>';
					            }
					    ?>
								<a href="<?php echo $r->getCollectionLink() ?>" class="pageLink"><?php echo $this->controller->highlightedMarkup($r->getCollectionLink(), $query) ?></a>
							</p>
						</div>
					<?php } ?>
				</div>
				
				<?php
                $pages = $pagination->getCurrentPageResults();
			    if ($pagination->haveToPaginate()) {
			        $showPagination = true;
			        echo $pagination->renderDefaultView();
			    }
			}
		} ?>
		
	</div>
	
<?php }
