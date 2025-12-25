<?php

namespace Concrete\Package\EnlilSearchTemplates;

use Concrete\Core\Package\Package;

class Controller extends Package
{
    protected $pkgHandle = 'enlil_search_templates';

    protected $appVersionRequired = '8.5.5'; // v9 Compatible!

    protected $pkgVersion = '0.9.0.2';

    public function getPackageName()
    {

        return t('Enlil Search Templates');
    }

    public function getPackageDescription()
    {

        return t('Search Block Templates');
    }
}
