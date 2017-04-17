<?php
/**
 * contentmulticategories
 *
 * @version 1.0
 * @author Arkadiy (a.sedelnikov@gmail.com)
 * @copyright (C) 2016 Arkadiy (a.sedelnikov@gmail.com)
 * @license GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 **/

// no direct access
defined('_JEXEC') or die;


class plgSystemContentmulticategories extends JPlugin
{
    private $isAdmin, $app, $input;
    private static $items = array();

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage('plg_system_contentmulticategories', __DIR__);
        $this->isAdmin = JFactory::getApplication()->isAdmin();
        $this->app = JFactory::getApplication();
        $this->input = $this->app->input;
    }

    /** Форма в редактировании контента
     * @param $context
     * @param $data
     * @return bool
     */
    function onContentPrepareData($context, $data)
    {
        if(!is_object($data) && !is_array($data)){
            return true;
        }

        $layout = $this->input->getCmd('layout', '');

        if (!($context == 'com_content.article' && $layout == 'edit'))
        {
            return true;
        }

        $articleId = 0;

        if(is_object($data) && !empty($data->id))
        {
            $articleId = (int)$data->id;
        }
        else if(is_array($data) && !empty($data["id"]))
        {
            $articleId = (int)$data["id"];
        }
        $langFilter = $this->params->get('lang_filter', 0);
        $aclFilter = $this->params->get('acl_filter', 0);
        $selectedCats = $this->getSelectedMultiCats($articleId);
        if(!$langFilter && !$aclFilter){
            $options = JHtml::_('category.categories', 'com_content', array('filter.published' => 1));
        }
        else{
            $conf = array('filter.published' => 1);
            if($langFilter){
                $conf['filter.language'] = JFactory::getLanguage()->getTag();
            }

            if($aclFilter){
                $conf['filter.acl'] = 1;
            }

            $options = $this->options($conf);
        }

        $html = '
        <div class="tab-pane" id="contentmulticategories">
            <div class="control-group">
                <label for="contentmulticategories_multi_categories" class="control-label" title="" >'.JText::_('JSELECT').'</label>
                <div class="controls">
                    <select name="contentmulticategories[categories][]" id="contentmulticategories_multi_categories" multiple="multiple" size="10">
                            '.JHtml::_('select.options', $options, 'value', 'text', $selectedCats).'
			        </select>
                </div>
            </div>
        </div>
		';

        echo $html;
        return true;
    }

    private function options($config = array('filter.published' => array(0, 1)))
    {
        $hash = md5(serialize($config));

        if (!isset(static::$items[$hash]))
        {
            $config = (array) $config;
            $db = JFactory::getDbo();
            $query = $db->getQuery(true)
                ->select('a.id, a.title, a.level')
                ->from('#__categories AS a')
                ->where('a.parent_id > 0');

            // Filter on extension.
            $query->where('extension = ' . $db->quote('com_content'));

            // Filter on the published state
            if (isset($config['filter.published']))
            {
                if (is_numeric($config['filter.published']))
                {
                    $query->where('a.published = ' . (int) $config['filter.published']);
                }
                elseif (is_array($config['filter.published']))
                {
                    $config['filter.published'] = ArrayHelper::toInteger($config['filter.published']);
                    $query->where('a.published IN (' . implode(',', $config['filter.published']) . ')');
                }
            }

            // Filter on the language
            if (isset($config['filter.language']))
            {
                if (is_string($config['filter.language']))
                {
                    $query->where('a.language = ' . $db->quote($config['filter.language']));
                }
                elseif (is_array($config['filter.language']))
                {
                    foreach ($config['filter.language'] as &$language)
                    {
                        $language = $db->quote($language);
                    }

                    $query->where('a.language IN (' . implode(',', $config['filter.language']) . ')');
                }
            }

            $query->order('a.lft');

            $db->setQuery($query);
            $items = $db->loadObjectList();

            $userRights = array();
            if(!empty($config['filter.acl'])){
                $userRights = JFactory::getUser()->getAuthorisedCategories('com_content', 'core.create');
            }

            // Assemble the list options.
            static::$items[$hash] = array();

            foreach ($items as &$item)
            {
                $repeat = ($item->level - 1 >= 0) ? $item->level - 1 : 0;
                $item->title = str_repeat('- ', $repeat) . $item->title;
                $disabled = (!empty($config['filter.acl']) && !in_array($item->id, $userRights)) ? true : false;
                static::$items[$hash][] = JHtml::_('select.option', $item->id, $item->title, 'value', 'text', $disabled);
            }
        }

        return static::$items[$hash];
    }

    /** Действия с табами в редактировании контента
     * @return
     */
    public function onBeforeRender()
    {
        $view = $this->input->getCmd('view', '');
        $option = $this->input->getCmd('option', '');
        $layout = $this->input->getCmd('layout', '');

        $isContent = $option == 'com_content' && ( ($this->isAdmin && $view == 'article') || (!$this->isAdmin && $view == 'form') ) && $layout === 'edit';


        if(!$isContent)
        {
            return;
        }

        $document = JFactory::getDocument();
        if($this->isAdmin)
        {
            $document->addScriptDeclaration('
                (function($){
                    $(document).ready(function(){
		            	var tab = $(\'<li class=""><a href="#contentmulticategories" data-toggle="tab">' . JText::_( 'PLG_CMC_VARIANT_MULTICATS' ) . '</a></li>\');
		            	$(\'#myTabTabs\').append(tab);

		            	if($(\'#myTabContent\').length)
		            	{
                            $(\'#contentmulticategories\').appendTo($(\'#myTabContent\'));
                        }
                        else if($(\'div.span10>div.tab-content\').length)
		            	{
                            $(\'#contentmulticategories\').appendTo($(\'div.span10>div.tab-content\'));
                        }
		            });
		        })(jQuery);
		    ');
        }
        else
        {
            $document->addScriptDeclaration('
                (function($){
                    $(document).ready(function(){
		            	var tab = $(\'<li class=""><a href="#contentmulticategories" data-toggle="tab">' . JText::_( 'PLG_CMC_VARIANT_MULTICATS' ) . '</a></li>\');
		            	$(\'ul.nav-tabs\').append(tab);
		            	$(\'#contentmulticategories\').appendTo($(\'div.tab-content\', \'#adminForm\'));
		            });
		        })(jQuery);
		    ');
        }

    }

    /** Сохранение данных
     * @param $context
     * @param $article
     * @param $isNew
     * @return bool
     * @throws Exception
     */
    public function onContentAfterSave($context, $article, $isNew)
    {
        if($context !== 'com_content.article' && $context !== 'com_content.form')
            return true;

        $articleId	= $article->id;

        $data = ( isset($_POST['contentmulticategories']) && is_array($_POST['contentmulticategories']) )
            ? $_POST['contentmulticategories'] : null;

        if ($articleId)
        {
            $multiCats = (!empty($data['categories'])) ? $data['categories'] : array();
            $this->saveMultiCats($multiCats, $articleId);
        }
        return true;
    }

    /** Действия при удалении контента
     * @param	string		The context of the content passed to the plugin (added in 1.6)
     * @param	object		A JTableContent object
     * @since   2.5
     */
    public function onContentAfterDelete($context, $article)
    {

        if ($context != 'com_content.article')
            return true;

        $articleId	= $article->id;

        if ($articleId)
        {
            $this->deleteMultiCats($articleId);
        }

        return true;
    }

    /** Добавление материалов с дополнительными категориями к основным.
     * @param $itemsModel
     * @throws Exception
     */
    public function onGetContentItems(&$itemsModel)
    {
        $view = $this->input->getString('view', '');
        $catid = $this->input->getInt('catid', 0);
        $id = $this->input->getInt('id', 0);

        if($view == 'category')
        {
            $catid = $id;
        }

        $catArticles = $this->getMultiCatsArticles($catid);
        $catArticles = (empty($catArticles)) ? array() : $catArticles;

        if(count($catArticles))
        {
            $itemsModel->setState('filter.category_id', '');
            $itemsModel->setState('filter.article_id.include', true);
            $itemsModel->setState('filter.article_id', $catArticles);
        }
    }

    /** Подмена модели категории контента.
     * @throws Exception
     */
    public function onAfterRoute()
    {
        $option = $this->input->getString('option', '');

        if($this->isAdmin || $option != 'com_content')
        {
            return;
        }

        $suffix = version_compare(JVERSION, '3.5.0', '<') ? '' : '35';
        if(!class_exists('ContentModelCategory'))
            require_once JPATH_ROOT.'/plugins/system/contentmulticategories/classes/category'.$suffix.'.php';
    }

    public function onContentPrepare($context='com_content.article', &$item, &$params, $offset)
    {
        $option = $this->input->getString('option', '');
        if($this->params->get('target', 'default') == 'default' || $this->isAdmin || ($context !='com_content.article' && $context !='com_content.category') || $option != 'com_content')
            return true;


        $view = $this->input->getString('view', '');
        if($view == 'category'){
            $catid = $this->input->getInt('id', 0);
            if($catid != $item->catid){
                $catData = $this->getCatData($catid);
                $item->catid = $catid;
                $item->category_title = $catData->title;
                $item->category_alias = $catData->alias;
                $item->category_access = $catData->access;
                $item->catslug = $catid.':'.$catData->alias;
                $slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;
                $item->readmore_link = JRoute::_(ContentHelperRoute::getArticleRoute($slug, $item->catid, $item->language));
                $this->app->setUserState('contentmulticategories.previous_category_'.$item->id, $catid);
            }
        }
        else if($view == 'article'){
            $catid = $this->app->getUserState('contentmulticategories.previous_category_'.$item->id, 0);
            if($catid <> $item->catid){
                $document = JFactory::getDocument();
                $document->addHeadLink($item->readmore_link, 'canonical');
                $catData = $this->getCatData($catid);
                $item->catid = $catid;
                $item->category_title = $catData->title;
                $item->category_alias = $catData->alias;
                $item->category_access = $catData->access;
                $item->catslug = $catid.':'.$catData->alias;
                $slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;
                $item->readmore_link = JRoute::_(ContentHelperRoute::getArticleRoute($slug, $item->catid, $item->language));

            }
        }
        return true;
    }

    private function getCatData($catid){
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('alias, title, access')->from('#__categories')->where('id = '.$db->quote($catid));
        $result = $db->setQuery($query,0,1)->loadObject();
        return $result;
    }

    private function getSelectedMultiCats($articleId)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('`category_id`')
            ->from('`#__contentmulticategories_categories`')
            ->where('`article_id` = '.$db->quote($articleId));
        $result = $db->setQuery($query)->loadColumn();

        if(!is_array($result) || !count($result))
        {
            return array();
        }
        return $result;
    }

    private function getMultiCatsArticles($categoryId)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('`article_id`')
            ->from('`#__contentmulticategories_categories`')
            ->where('`category_id` = '.$db->quote($categoryId));
        $result1 = $db->setQuery($query)->loadColumn();

        if(!is_array($result1) || !count($result1))
        {
            $result1 = array();
        }

        $query->clear()
            ->select('`id`')
            ->from('`#__content`')
            ->where('`catid` = '.$db->quote($categoryId));
        $result2 = $db->setQuery($query)->loadColumn();

        if(!is_array($result2) || !count($result2))
        {
            $result2 = array();
        }

        $result = array_merge($result1, $result2);
        $result = array_unique($result);

        if(!is_array($result) || !count($result))
        {
            return array();
        }
        return $result;
    }

    private function deleteMultiCats($articleId)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query->delete('`#__contentmulticategories_categories`')
            ->where('`article_id` = '.$db->quote($articleId));
        $db->setQuery($query)->execute();
    }

    private function saveMultiCats($multiCats, $articleId)
    {
        $multiCats = array_unique($multiCats);

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query->delete('`#__contentmulticategories_categories`')
            ->where('`article_id` = '.$db->quote($articleId));
        $db->setQuery($query)->execute();

        if(count($multiCats) > 0)
        {
            foreach($multiCats as $v)
            {
                $v = (int)$v;

                if($v == 0)
                {
                    continue;
                }
                $object = new stdClass();
                $object->category_id = $v;
                $object->article_id = $articleId;
                $db->insertObject('#__contentmulticategories_categories', $object);
            }
        }
    }
}
