<?php

namespace Concrete\Package\GmbSpacer;

use Concrete\Core\Package\Package;
use Concrete\Core\Block\BlockType\BlockType;

/**
 * The ProgePack package controller.
 */
class Controller extends Package 
{
    /**
     * The package handle.
     *
     * @var string
     */
    protected $pkgHandle = 'gmb_spacer';

    /**
     * The package version.
     *
     * @var string
     */
    protected $pkgVersion = '1.0.0';

    /**
     * The minimum concrete5 version.
     *
     * @var string
     */
    protected $appVersionRequired = '9.0.0';

    /**
     * {@inheritdoc}
     */
    public function getPackageName()
    {
        return t('Simple Spacer');
    }

    /**
     * {@inheritdoc}
     */
    public function getPackageDescription()
    {
        return t('Add a spacer to your website.');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        $pkg = parent::install();

        //install block
        BlockType::installBlockTypeFromPackage('gmb_spacer', $pkg);
    }

	public function uninstall(){
		parent::uninstall();
        
	}
}
