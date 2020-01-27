<?php
/**
 * @package silverstripe-sitemap
 */
class SitemapPage extends Page {

	public static $db = array (
		'PagesToDisplay' => "Enum('All, ChildrenOf, Selected', 'All')"
	);

	public static $has_one = array (
		'ParentPage' => 'SiteTree'
	);

	public static $many_many = array (
		'PagesToShow' => 'SiteTree'
	);

	public static $icon = array('sitemap/images/sitemap', 'file');

	/**
	 * @return FieldSet
	 */
	public function getCMSFields() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::javascript('sitemap/javascript/SitemapPageAdmin.js');

		$fields = parent::getCMSFields();

		$fields->findOrMakeTab('Root.Sitemap', $this->fieldLabel('Sitemap'));
		$fields->addFieldsToTab('Root.Sitemap', array(
			new HeaderField($this->fieldLabel('PagesToDisplay'), 2),
			new OptionSetField('PagesToDisplay', '', array (
				'All'        => $this->fieldLabel('AllPages'),
				'ChildrenOf' => $this->fieldLabel('ChildrenOf'),
				'Selected'   => $this->fieldLabel('Selected')
			)),
			new TreeDropdownField('ParentPageID', '', 'SiteTree'),
			new TreeMultiselectField('PagesToShow', '', 'SiteTree')
		));
		return $fields;
	}

	/**
	 * @return array
	 */
	public function fieldLabels($includerelations = true) {
		return array_merge(parent::fieldLabels($includerelations), array (
			'Sitemap'        => _t('SitemapPage.SITEMAP', 'Sitemap'),
			'PagesToDisplay' => _t('SitemapPage.PAGESTOSHOW', 'Pages To Show In The Sitemap'),
			'AllPages'       => _t('SitemapPage.ALLPAGES', 'Display all pages which are displayed in the menu.'),
			'ChildrenOf'     => _t('SitemapPage.CHILDRENOF', 'Display the children of a specific page.'),
			'Selected'       => _t('SitemapPage.SELECTED', 'Display only the selected pages.')
		));
	}

	/**
	 * @return string
	 */
	public function getSitemap(ArrayList $set = null) {
		if(!$set) $set = $this->getRootPages();


		if($set && count($set)) {
			$sitemap = '<ul>';

			foreach($set as $page) {
				if($page->ShowInMenus && $page->ID != $this->ID && $page->canView()) {
					$sitemap .= sprintf (
						'<li><a href="%s" title="%s">%s</a>',
						$page->XML_val('Link'),
						$page->XML_val('MenuTitle'),
						$page->XML_val('MenuTitle')
					);

					if($children = $page->Children()) {
						$sitemap .= $this->getSitemap($children);
					}

					$sitemap .= '</li>';
				}
			}

			return $sitemap .'</ul>';
		}
	}

	public function getSitemapXml($lang) {

		$sitemap = '';

		$sitemap .= $this->getXmlForWebsitePage($lang);

		$sitemap .= $this->getXmlForCustomPage($lang);

		return $sitemap;

    }

    protected function getXmlForWebsitePage($lang, ArrayList $set = null, $priority = 1, $segments = array())
    {
	    i18n::set_locale($lang);

	    if(!$set) $set = $this->getRootPages();

	    if($set && count($set)) {
		    $sitemap ='';
		    foreach($set as $page) {
			    if($page->ShowInMenus && $page->ID != $this->ID && $page->canView()) {

				    $childrenSegments = $segments;

				    if(strpos($page->XML_val('Link'), 'http://') !== 0 && strpos($page->XML_val('Link'), 'https://') !== 0) {

					    $childrenSegments[] = $page->XML_val('URLSegment_' . $lang);
					    $sitemap .= '
    <url>
        <loc>https://wwf.be/'.substr($lang, 0,2).'/' . implode('/',$childrenSegments) . '/</loc>
        <changefreq>daily</changefreq>
        <priority>' . $priority . '</priority>
    </url>
                          ';

				    }

				    if($children = $page->Children()) {
					    $priority -= 0.1;
					    $sitemap .= $this->getXmlForWebsitePage($lang, $children, $priority, $childrenSegments);
					    $priority += 0.1;
				    }
			    }
		    }

		    return $sitemap;

	    }
    }

    protected function getXmlForCustomPage($lang)
    {
	   //TODO are custom page here.

	    return '';
    }

	/**
	 * @return DataObjectSet
	 */
	public function getRootPages() {
		switch($this->PagesToDisplay) {
			case 'ChildrenOf':
				return DataObject::get(
					'SiteTree',
					sprintf('"ParentID" = %d AND "ShowInMenus" = 1', $this->ParentPageID)
				);
			case 'Selected':
				//return $this->PagesToShow($showInMenus);
				return $this->PagesToShow();
			default:
				return DataObject::get('SiteTree', '"ParentID" = 0 AND "ShowInMenus" = 1');
		}
	}

	/**
	 * Creates a default {@link SitemapPage} object if one does not currently exist.
	 */
	public function requireDefaultRecords() {
		if(!$sitemap = DataObject::get_one('SitemapPage')) {
			$sitemap = new SitemapPage();

			$sitemap->Title   = _t('SitemapPage.SITEMAP', 'Sitemap');
			$sitemap->Content = sprintf (
				'<p>%s</p>',
				_t('SitemapPage.DEFAULTCONTENT','This page displays a sitemap of the pages in your site.')
			);

			$sitemap->write();
			$sitemap->doPublish();

			if(method_exists('DB', 'alteration_message')) {
				DB::alteration_message('Created default Sitemap page.', 'created');
			} else {
				Database::alteration_message('Created default Sitemap page.', 'created');
			}
		}

		parent::requireDefaultRecords();
	}

}

/**
 * @package silverstripe-sitemap
 */
class SitemapPage_Controller extends Page_Controller {

    private static $allowed_actions = array(
        'index',
        'sitemapXml',
    );

    public function sitemapXml() {
        $sitemap = new SitemapPage;

$sitemapHead =
'<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $sitemapFoot = '
</urlset>
        ';
        $a = file_put_contents('../sitemap-fr.xml', $sitemapHead . $sitemap->getSitemapXml('fr_BE') . $sitemapFoot);
        $b = file_put_contents('../sitemap-nl.xml', $sitemapHead . $sitemap->getSitemapXml('nl_BE') . $sitemapFoot);

        var_dump($a,$b);

    }
}
