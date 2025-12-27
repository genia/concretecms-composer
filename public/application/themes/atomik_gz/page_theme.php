<?php
namespace Application\Theme\AtomikGz;

use Concrete\Core\Feature\Features;
use Concrete\Core\Page\Theme\BedrockThemeTrait;
use Concrete\Core\Page\Theme\Color\Color;
use Concrete\Core\Page\Theme\Color\ColorCollection;
use Concrete\Core\Page\Theme\Documentation\DocumentationProviderInterface;
use Concrete\Core\Page\Theme\Theme;

class PageTheme extends Theme
{
    
    use BedrockThemeTrait {
        getColorCollection as getBedrockColorCollection;
        registerAssets as registerBedrockAssets;
    }

    public function registerAssets()
    {
        // Register bedrock assets (jQuery, Bootstrap, Vue, etc.)
        $this->registerBedrockAssets();
        
        // Register Community Store CSS
        $this->requireAsset('css', 'community-store');
    }

    public function getThemeName()
    {
        return t('Atomik GZ');
    }

    public function getThemeDescription()
    {
        return t('A Concrete CMS theme built for version 9.');
    }

    public function getThemeSupportedFeatures()
    {
        return [
            Features::ACCOUNT,
            Features::ACCORDIONS,
            Features::DESKTOP,
            Features::BASICS,
            Features::SOCIAL,
            Features::TYPOGRAPHY,
            Features::DOCUMENTS,
            Features::FAQ,
            Features::PROFILE,
            Features::NAVIGATION,
            Features::IMAGERY,
            Features::FORMS,
            Features::SEARCH,
            Features::STAGING,
            Features::TESTIMONIALS,
            Features::TAXONOMY,
        ];
    }

    /**
     * @return array
     */
    public function getThemeResponsiveImageMap()
    {
        return [
            'xl' => '1200px',
            'lg' => '992px',
            'md' => '768px',
            'sm' => '576px',
            'xs' => '0',
        ];
    }

    /**
     * @return array
     */
    public function getThemeEditorClasses()
    {
        return [
            [
                'title' => t('Display 1'),
                'element' => array('h1','p','div'),
                'attributes' => array('class' => 'display-1')
            ],
            [
                'title' => t('Display 2'),
                'element' => array('h2','p','div'),
                'attributes' => array('class' => 'display-2')
            ],
            [
                'title' => t('Display 3'),
                'element' => array('h3','p','div'),
                'attributes' => array('class' => 'display-3')
            ],
            [
                'title' => t('Display 4'),
                'element' => array('h4','p','div'),
                'attributes' => array('class' => 'display-4')
            ],
            [
                'title' => t('Display 5'),
                'element' => array('h5','p','div'),
                'attributes' => array('class' => 'display-5')
            ],
            [
                'title' => t('Display 6'),
                'element' => array('h6','p','div'),
                'attributes' => array('class' => 'display-6')
            ],
            [
                'title' => t('Lead'),
                'element' => array('p'),
                'attributes' => array('class' => 'lead')
            ],
            [
                'title' => t('Basic Table'),
                'element' => array('table'),
                'attributes' => array('class' => 'table')
            ],
            [
                'title' => t('Striped Table'),
                'element' => array('table'),
                'attributes' => array('class' => 'table table-striped')
            ],
        ];
    }


    public function getDocumentationProvider(): ?DocumentationProviderInterface
    {
        // Documentation provider removed - AtomikDocumentationProvider is hardcoded to Concrete\Theme\Atomik\PageTheme
        return null;
    }

    public function getColorCollection(): ?ColorCollection
    {
        $collection = $this->getBedrockColorCollection();
        $collection->add(new Color('light-accent', t('Light Accent')));
        $collection->add(new Color('accent', t('Accent')));
        $collection->add(new Color('dark-accent', t('Dark Accent')));
        return $collection;
    }


}
