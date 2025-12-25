<?php  
namespace Concrete\Package\GmbSpacer\Block\GmbSpacer;

use Concrete\Core\Block\BlockController;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController  
{
    protected $btTable = 'btGmbSpacer';
    protected $btInterfaceWidth = "300";
    protected $btInterfaceHeight = "300";
    protected $btDefaultSet = 'basic';

    public function getBlockTypeDescription()  
    {
        return t("Add spacer between blocks without coding.");
    }

    public function getBlockTypeName()  
    {
        return t("Spacer");
    }
	
    public function edit()
    {
        $db = $this->app->make('database')->connection();
        $row = $db->fetchAssoc('SELECT spacerHeight, spacerUnit, spacerHighlight FROM btGmbSpacer WHERE bID = ?', [$this->bID]);
    
        $this->set('spacerHeight', isset($row['spacerHeight']) ? max(1, intval($row['spacerHeight'])) : 1);
        $this->set('spacerUnit', in_array($row['spacerUnit'], ['px', 'em', 'vh', 'vw']) ? $row['spacerUnit'] : 'px');
        $this->set('spacerHighlight', isset($row['spacerHighlight']) ? (bool)$row['spacerHighlight'] : false);
    }

	public function save($data)  
	{
		$allowedUnits = ['px', 'em', 'vh', 'vw'];
		$args = [
			'spacerHeight' => max(1, intval($data['spacerHeight'] ?? 1)),
			'spacerUnit' => in_array($data['spacerUnit'] ?? '', $allowedUnits) ? $data['spacerUnit'] : 'px',
			'spacerHighlight' => !empty($data['spacerHighlight']) ? 1 : 0,
		];
	
		$db = $this->app->make('database')->connection();
		$existingEntry = $db->fetchAssoc('SELECT bID FROM btGmbSpacer WHERE bID = ?', [$this->bID]);
	
		if ($existingEntry) {
			$db->update('btGmbSpacer', $args, ['bID' => $this->bID]);
		} else {
			$args['bID'] = $this->bID;
			$db->insert('btGmbSpacer', $args);
		}
	}

    public function delete()  
    {
        // Secure block deletion
        $db = $this->app->make('database')->connection();
        $db->executeQuery('DELETE FROM btGmbSpacer WHERE bID = ?', [$this->bID]);

        parent::delete();
    }
}