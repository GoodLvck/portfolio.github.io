<?php
/**
 * @package Nicepage Website Builder
 * @author Nicepage https://www.nicepage.com
 * @copyright Copyright (c) 2016 - 2019 Nicepage
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
 */

namespace NP\Editor;

defined('_JEXEC') or die;

use \NicepageHelpersNicepage;
use \JRegistry, \JLoader, \JHelperTags, \JTable, \JFactory, \JFile, \JURI, \JFolder, \JString, \JFilterOutput;

JLoader::register('Nicepage_Data_Mappers', JPATH_ADMINISTRATOR . '/components/com_nicepage/tables/mappers.php');

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

/**
 * Class PageSaver
 */
class PageSaver
{
    private $_data;
    private $_pageId;
    private $_pageTitle;
    private $_opt;
    private $_settings;

    private $_saveAndPublish;
    private $_isPreview;
    private $_isAutoSave;

    /**
     * PageSaver constructor.
     *
     * @param JInput $data Data to save.
     */
    public function __construct($data)
    {
        $this->_data = $data;

        $this->_pageId = $data->get('id', '');
        $this->_opt = $data->get('data', '', 'RAW');
        $this->_settings = $data->get('settings', '', 'RAW');

        $this->_saveAndPublish = ($data->get('saveAndPublish', '') == 'true' || $data->get('saveAndPublish', '')  == '1') ? true : false;
        $this->_isPreview = ($data->get('isPreview', '') == 'true' || $data->get('isPreview', '')  == '1') ? true : false;
        $this->_isAutoSave = !$this->_saveAndPublish ? true : false;
    }

    /**
     * Check data for save.
     *
     * @return bool
     */
    public function check()
    {
        if (!$this->_pageId || !$this->_opt || !$this->_settings) {
            return false;
        }
        return true;
    }

    /**
     * Get result data
     * 
     * @return array
     */
    public function getResult()
    {
        return array(
            'pageId'    => $this->_pageId,
            'pageTitle' => $this->_pageTitle,
            'isPreview' => $this->_isPreview
        );
    }

    /**
     * Save page data
     */
    public function save()
    {
        $this->saveData();
        $this->saveBackToTop();
        $this->saveSiteSettings();
        $this->saveCustomFonts();
        $this->saveModalPopups();
    }

    /**
     * Save backtotop html
     */
    public function saveBackToTop()
    {
        $backToTopPublishHtml = $this->_data->get('backToTopPublishHtml', '', 'RAW');
        if ($backToTopPublishHtml) {
            ConfigSaver::saveConfig(array('backToTop' => $backToTopPublishHtml));
        }
    }

    /**
     * Save site settings
     */
    public function saveSiteSettings()
    {
        ConfigSaver::saveSiteSettings($this->_settings, true);
    }

    /**
     * Save page custom fonts
     */
    public function saveCustomFonts()
    {
        $customFontsCss = $this->_data->get('customFontsCss', '', 'RAW');
        if ($customFontsCss) {
            $customFontsFilePath = dirname(JPATH_ADMINISTRATOR) . '/images/nicepage-fonts/fonts_' . $this->_pageId . '.css';
            JFile::write($customFontsFilePath, $customFontsCss);
        }
    }

    /**
     * Save page modal popup dialogs
     */
    public function saveModalPopups()
    {
        $publishDialogs = $this->_data->get('publishDialogs', '', 'RAW');
        if ($publishDialogs) {
            ConfigSaver::saveConfig(array('publishDialogs' => json_encode($publishDialogs)));
        }
    }

    /**
     * Save page data options
     */
    public function saveData()
    {
        $publishHeaderFooter = ConfigSaver::saveHeaderFooter($this->_data, $this->_saveAndPublish, $this->_isPreview);

        // properties
        $publishHtml    = isset($this->_opt['publishHtml']) ? $this->_opt['publishHtml'] : '';
        $publishNicePageCss = isset($this->_opt['publishNicePageCss']) ? $this->_opt['publishNicePageCss'] : '';

        $html           = isset($this->_opt['html']) ? $this->_opt['html'] : '';
        $head           = isset($this->_opt['head']) ? $this->_opt['head'] : '';
        $bodyClass      = isset($this->_opt['bodyClass']) ? $this->_opt['bodyClass'] : '';
        $bodyStyle      = isset($this->_opt['bodyStyle']) ? $this->_opt['bodyStyle'] : '';
        $bodyDataBg     = isset($this->_opt['bodyDataBg']) ? $this->_opt['bodyDataBg'] : '';
        $introImgStruct = isset($this->_opt['introImgStruct']) ? $this->_opt['introImgStruct'] : '';

        $homeUrl = dirname(dirname(JURI::current()));
        $html = str_replace($homeUrl, '[[site_path_editor]]', $html);
        $publishPageParts = str_replace(
            $homeUrl . '/',
            '[[site_path_live]]',
            array(
                'publishHtml'   => $publishHtml,
                'head'          => $head,
                'bodyStyle'     => $bodyStyle,
                'bodyDataBg'     => $bodyDataBg,
                'introImgStruct' => $introImgStruct
            )
        );

        $pageCssUsedIds = '';
        if ($this->_saveAndPublish) {
            list($siteStyleCssParts, $pageCssUsedIds, $headerFooterCssUsedIds) = NicepageHelpersNicepage::processAllColors($publishNicePageCss, $publishHtml, $publishHeaderFooter);
            ConfigSaver::saveConfig(array('siteStyleCssParts' => $siteStyleCssParts, 'headerFooterCssUsedIds' => $headerFooterCssUsedIds, 'siteStyleCss' => ''/*old property*/));
        }

        $this->_pageTitle = $this->_data->get('title', '', 'RAW');
        $pageIntro = $this->_data->get('introHtml', '', 'RAW');

        // seo options
        $titleInBrowser = $this->_data->get('titleInBrowser', '', 'RAW');
        $keywords       = $this->_data->get('keywords', '', 'RAW');
        $description    = $this->_data->get('description', '', 'RAW');
        $metaGeneratorContent = $this->_data->get('metaGeneratorContent', '', 'RAW');
        $canonical = $this->_data->get('canonical', '', 'RAW');

        $article = null;
        if ($this->_pageId == '-1') {
            $article = self::createPost(
                array(
                    'title' => $this->_pageTitle,
                    'intro' => $pageIntro,
                    'full' => '<!--np_fulltext-->' . $publishPageParts['publishHtml'] . '<!--/np_fulltext-->',
                    'seoOptions' => array(
                        'title' => $titleInBrowser,
                        'keywords' => $keywords,
                        'description' => $description
                    )
                )
            );
            $this->_pageId = $article->id;

            $session = JFactory::getSession();
            $registry = $session->get('registry');
            $registry->set('com_content.edit.article.id', $article->id);
        } else {
            $contentMapper = \Nicepage_Data_Mappers::get('content');
            $res = $contentMapper->find(array('id' => $this->_pageId));
            if (count($res) > 0 && !$this->_isPreview) {
                $article = $res[0];
                $article->introtext = $pageIntro;
                $article->fulltext = '<!--np_fulltext-->' . $publishPageParts['publishHtml'] . '<!--/np_fulltext-->';
                $attribs = self::stringToParams($article->attribs ? $article->attribs : '{}');
                $attribs['article_page_title'] = $titleInBrowser;
                $article->attribs = self::paramsToString($attribs);

                $article->metakey = $keywords;
                $article->metadesc = $description;

                $this->_setTags($article);

                $contentMapper->save($article);
            }
        }

        $fonts = isset($this->_opt['fonts']) ? $this->_opt['fonts'] : '';
        if ($fonts) {
            $fonts = preg_replace('/[\"\']fonts.css[\"\']/',  '[[site_path_live]]components/com_nicepage/assets/css/fonts/fonts.css', $fonts);
            $fonts = preg_replace('/[\"\']page-fonts.css[\"\']/', '[[site_path_live]]components/com_nicepage/assets/css/fonts/page-' . $this->_pageId . '-fonts.css', $fonts);
            $fonts = preg_replace('/[\"\']header-footer-fonts.css[\"\']/', '[[site_path_live]]components/com_nicepage/assets/css/fonts/header-footer-fonts.css', $fonts);
        }
        $this->saveLocalGoogleFonts($this->_data->get('fontsData', '', 'RAW'), $this->_pageId);

        $backlink       = isset($this->_opt['backlink']) ? $this->_opt['backlink'] : '';
        $hideHeader     = isset($this->_opt['hideHeader']) ? filter_var($this->_opt['hideHeader'], FILTER_VALIDATE_BOOLEAN) : false;
        $hideFooter     = isset($this->_opt['hideFooter']) ? filter_var($this->_opt['hideFooter'], FILTER_VALIDATE_BOOLEAN) : false;
        $hideBackToTop     = isset($this->_opt['hideBackToTop']) ? filter_var($this->_opt['hideBackToTop'], FILTER_VALIDATE_BOOLEAN) : false;

        $siteStyleCss   = $this->_data->get('siteStyleCss', '', 'RAW');
        $metaTags       = $this->_data->get('metaTags', '', 'RAW');
        $customHeadHtml = $this->_data->get('customHeadHtml', '', 'RAW');

        $pageFormsData = $this->_data->get('pageFormsData', '', 'RAW');
        $dialogs = $this->_data->get('dialogs', '', 'RAW');

        $props = array(
            'html' => $html,
            'publishHtml' => $publishPageParts['publishHtml'],
            'pageCssUsedIds' => $pageCssUsedIds,
            'backlink' => $backlink,
            'metaGeneratorContent' => $metaGeneratorContent,
            'canonical' => $canonical,
            'head' => $publishPageParts['head'],
            'bodyClass' => $bodyClass,
            'bodyStyle' => $publishPageParts['bodyStyle'],
            'bodyDataBg' => $publishPageParts['bodyDataBg'],
            'fonts' => $fonts,
            'siteStyleCss' => $siteStyleCss,
            'keywords' => $keywords,
            'description' => $description,
            'metaTags' => $metaTags,
            'customHeadHtml' => $customHeadHtml,
            'titleInBrowser' => $titleInBrowser,
            'introImgStruct' => $publishPageParts['introImgStruct'],
            'hideHeader' => $hideHeader,
            'hideFooter' => $hideFooter,
            'hideBackToTop' => $hideBackToTop,
            'formsData' => $pageFormsData,
            'dialogs' => $dialogs,
        );

        $getCmsValue = array(
            'theme-template' => 'default',
            'np-template-header-footer-from-plugin' => 'landing',
            'np-template-header-footer-from-theme' => 'landing_with_header_footer'
        );
        $pageTypeKey = $this->_data->get('pageType', 'np-template-header-footer-from-plugin', 'RAW');
        if (!array_key_exists($pageTypeKey, $getCmsValue)) {
            $pageTypeKey = 'np-template-header-footer-from-plugin';
        }
        $pageType = $getCmsValue[$pageTypeKey];
        $props['pageView'] = $pageType;

        $newData = array(
            'preview_props' => $this->_isPreview ? $props : '',
            'autosave_props' => $this->_isAutoSave ? $props : '',
        );
        $page = NicepageHelpersNicepage::getSectionsTable();
        if ($page->load(array('page_id' => $this->_pageId))) {
            if (!$this->_isPreview && !$this->_isAutoSave) {
                $newData['props'] = $props;
            }
        } else {
            $newData[$page->getKeyName()] = null; //create new record
            $newData = array(
                'page_id' => $this->_pageId,
                'props'   => $props,
            );
        }
        $page->save($newData);
    }

    /**
     * Save local google fonts
     *
     * @param JInput $fontsData Data parameters
     * @param string $pageId    Page id
     *
     * @return array|void
     */
    public function saveLocalGoogleFonts($fontsData, $pageId) {
        if (!$fontsData) {
            return;
        }

        $fontsFolder = dirname(JPATH_ADMINISTRATOR) . '/components/com_nicepage/assets/css/fonts';
        if (!JFolder::exists($fontsFolder)) {
            if (!JFolder::create($fontsFolder)) {
                return;
            }
        }

        $fontsFiles = isset($fontsData['files']) ? $fontsData['files'] : array();
        foreach ($fontsFiles as $fontFile) {
            $fontData = json_decode($fontFile, true);
            if (!$fontData) {
                continue;
            }
            switch($fontData['fileName']) {
            case 'fonts.css':
                JFile::write($fontsFolder . '/fonts.css', str_replace('fonts/', '', $fontData['content']));
                break;
            case 'page-fonts.css':
                JFile::write($fontsFolder . '/page-' . $pageId .'-fonts.css', str_replace('fonts/', '', $fontData['content']));
                JFile::write($fontsFolder . '/header-footer-fonts.css', str_replace('fonts/', '', $fontData['content']));
                break;
            case 'downloadedFonts.json':
                JFile::write($fontsFolder . '/downloadedFonts.json', $fontData['content']);
                break;
            default:
                $content = '';
                $bytes = $fontData['content'];
                foreach ($bytes as $chr) {
                    $content .= chr($chr);
                }
                JFile::write($fontsFolder . '/' . $fontData['fileName'], $content);
            }
        }
    }

    /**
     * @param array $data Data parameters
     *
     * @return mixed
     */
    public static function createPost($data = array())
    {
        $content = isset($data['intro']) ? $data['intro'] : '';
        $fulltext = isset($data['full']) ? $data['full'] : '';
        $defaultSeoOptions = array(
            'title' => '',
            'keywords' => '',
            'description' => ''
        );
        $seoOptions = isset($data['seoOptions']) ? array_merge($defaultSeoOptions, $data['seoOptions']) : $defaultSeoOptions;

        $images = '';
        if (isset($data['images'])) {
            foreach ($data['images'] as $img) {
                $images .= '<img src="' . $img .'">' . PHP_EOL;
            }
        }
        $content = $images . $content;

        $contentMapper = \Nicepage_Data_Mappers::get('content');
        $article = $contentMapper->create();
        $article->catid = self::getCategoryByName('Uncategorised');

        list($title, $alias) = self::generateNewTitle($article->catid, $data);

        $article->title = $title;
        $article->alias = $alias;
        $article->introtext = $content;
        $article->fulltext = $fulltext;
        if (isset($data['state'])) {
            $article->state = $data['state'];
        }
        $article->attribs = self::paramsToString(
            array (
                'show_title' => '',
                'link_titles' => '',
                'show_intro' => '0',
                'show_category' => '',
                'link_category' => '',
                'show_parent_category' => '',
                'link_parent_category' => '',
                'show_author' => '',
                'link_author' => '',
                'show_create_date' => '',
                'show_modify_date' => '',
                'show_publish_date' => '',
                'show_item_navigation' => '',
                'show_icons' => '',
                'show_print_icon' => '',
                'show_email_icon' => '',
                'show_vote' => '',
                'show_hits' => '',
                'show_noauth' => '',
                'alternative_readmore' => '',
                'article_layout' => '',
                'article_page_title' => $seoOptions['title']
            )
        );
        $article->metadata = self::paramsToString(array('robots' => '', 'author' => '', 'rights' => '', 'xreference' => '', 'tags' => ''));
        $article->metakey = $seoOptions['keywords'];
        $article->metadesc = $seoOptions['description'];
        $status = $contentMapper->save($article);
        if (is_string($status)) {
            trigger_error($status, E_USER_ERROR);
        }

        return $article;
    }

    /**
     * @param int   $catId      Category id
     * @param array $data       Data
     * @param bool  $checkTitle Validate title
     * @param bool  $checkAlias Validate title
     *
     * @return array
     */
    public static function generateNewTitle($catId, $data, $checkTitle = true, $checkAlias = true)
    {
        $title = isset($data['title']) && $data['title'] ? strip_tags($data['title']) : (isset($data['subpage']) ? 'SubPage' : 'Page');
        $alias = isset($data['alias']) && $data['alias'] ? $data['alias'] : '';

        $table = JTable::getInstance('Content');
        if ($checkTitle) {
            while ($table->load(array('title' => $title, 'catid' => $catId))) {
                $title = JString::increment($title);
            }
        }

        if (!$alias) {
            if (JFactory::getConfig()->get('unicodeslugs') == 1) {
                $alias = JFilterOutput::stringURLUnicodeSlug($title);
            } else {
                $alias = JFilterOutput::stringURLSafe($title);
            }
        }
        if ($checkAlias) {
            while ($table->load(array('alias' => $alias, 'catid' => $catId))) {
                $alias = JString::increment($alias, 'dash');
            }
        }
        if (!$alias) {
            $date = new JDate();
            $alias = $date->format('Y-m-d-H-i-s');
        }

        return array($title, $alias);
    }

    /**
     * @param string $name Category name
     *
     * @return mixed
     */
    public static function getCategoryByName($name)
    {
        $categoryMapper = \Nicepage_Data_Mappers::get('category');
        $res = $categoryMapper->find(array('title' => $name, 'extension' => 'com_content'));

        if (count($res) > 0) {
            return $res[0]->id;
        }

        $categoryObj = $categoryMapper->create();
        $categoryObj->title = $name;
        $categoryObj->extension = 'com_content';
        $categoryObj->metadata = self::paramsToString(array('robots' => '', 'author' => '', 'tags' => ''));
        $status = $categoryMapper->save($categoryObj);
        if (is_string($status)) {
            trigger_error($status, E_USER_ERROR);
        }
        return $categoryObj->id;
    }

    /**
     * @param array $params Parameters
     *
     * @return mixed
     */
    public static function paramsToString($params)
    {
        $registry = new JRegistry();
        $registry->loadArray($params);
        return $registry->toString();
    }

    /**
     * @param string $string Parameters string
     *
     * @return mixed
     */
    public static function stringToParams($string)
    {
        $registry = new JRegistry();
        $registry->loadString($string);
        return $registry->toArray();
    }

    /**
     * Set tags for article
     *
     * @param object $article Current article object
     */
    private function _setTags(&$article)
    {
        if (class_exists('JHelperTags')) {
            $article->tagsHelper = new JHelperTags;
            $article->tagsHelper->typeAlias = 'com_content.article';
            $article->tagsHelper->tags = explode(',', $article->tagsHelper->getTagIds($article->id, 'com_content.article'));
        }
    }
}