<?php
/*------------------------------------------------------------------------

# TZ Portfolio Extension

# ------------------------------------------------------------------------

# author    DuongTVTemPlaza

# copyright Copyright (C) 2012 templaza.com. All Rights Reserved.

# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

# Websites: http://www.templaza.com

# Technical Support:  Forum - http://templaza.com/Forum

-------------------------------------------------------------------------*/

// no direct access
defined('_JEXEC') or die;

require_once JPATH_SITE.'/components/com_tz_portfolio/helpers/route.php';

jimport('joomla.application.component.model');

abstract class modTZ_PortfolioCategoriesHelper
{
    protected static $lookup    = null;
    protected static $sView    = null;
    protected static $sCatIds    = array();

    public static function getList(&$params){
        $crop = $params->get('crop');
        $width = $params->get('width');
        $height = $params->get('height');
        $categoryName   = null;
        $total          = null;
        $catIds         = null;
        if($params -> get('catid'))
            $catIds         = implode(',',$params -> get('catid'));
        if($catIds){
            $categoryName   = strtolower(self::getCategoryName($catIds));
        }
        if($params -> get('show_total',1))
            $total  = ',(SELECT COUNT(*) FROM #__content AS c WHERE c.catid = a.id) AS total';

        if($categoryName == strtolower('Uncategorised'))
            $query  = 'SELECT a.id, a.title, a.alias, a.note, a.published, a.access,'
                .' a.checked_out, a.checked_out_time, a.created_user_id, a.path,'
                .' a.parent_id, a.description, a.params, a.level, a.lft, a.rgt, a.language,'
                .'l.title AS language_title,ag.title AS access_level,'
                .'ua.name AS author_name'
                .$total
                .' FROM #__categories AS a'
                .' LEFT JOIN `#__languages` AS l ON l.lang_code = a.language'
                .' LEFT JOIN #__users AS uc ON uc.id=a.checked_out'
                .' LEFT JOIN #__viewlevels AS ag ON ag.id = a.access'
                .' LEFT JOIN #__users AS ua ON ua.id = a.created_user_id'
                .' WHERE a.extension = \'com_content\' AND (a.published = 1)'
                .' AND NOT a.title="Uncategorised"'
                .' GROUP BY a.id'
                .' ORDER BY a.lft asc';
        else
            $query  = 'SELECT a.id, a.description, a.params, a.title, a.alias, a.note, a.published, a.access,'
                .' a.checked_out, a.checked_out_time, a.created_user_id, a.path,'
                .' a.parent_id, a.level, a.lft, a.rgt, a.language,'
                .'l.title AS language_title,ag.title AS access_level,'
                .'ua.name AS author_name'
                .$total
                .' FROM #__categories AS a'
                .' LEFT JOIN `#__languages` AS l ON l.lang_code = a.language'
                .' LEFT JOIN #__users AS uc ON uc.id=a.checked_out'
                .' LEFT JOIN #__viewlevels AS ag ON ag.id = a.access'
                .' LEFT JOIN #__users AS ua ON ua.id = a.created_user_id'
                .' WHERE a.extension = \'com_content\' AND (a.published = 1)'
                .' AND a.id IN('.$catIds.')'
                .' GROUP BY a.id'
                .' ORDER BY a.lft asc';

        $db     = JFactory::getDbo();
        $db -> setQuery($query);
        if($items   = $db -> loadObjectList()){
            $i=0;
            foreach($items as $item){
                $items[$i] ->link   = JRoute::_(self::getCategoryRoute($item->id));
                $registry = new JRegistry;
                $registry->loadString($item->params);
                $images = $registry->toArray();
                if(isset($images['image']))
                    $imglink = $images['image'];
                if($crop){
                    $items[$i]->images = self::tz_resizeImgcrop($imglink, $width, $height,$crop);
                } else{
                    $items[$i]->images = self::tz_resizeImg($imglink, $width, $height);
                }
                $i++;
            }
            return $items;
        }
        return false;
    }

    public static function getCategoryName($catIds = array()){
        if($catIds && count($catIds) == 1){
            $query  = 'SELECT title FROM #__categories'
                .' WHERE extension="com_content" AND id='.(int)$catIds[0];
            $db     = JFactory::getDbo();
            $db -> setQuery($query);
            if($db -> query()){
                $rows   = $db -> loadObject();

                if($rows)
                    return $rows -> title;
            }
        }
        return false;
    }

    static function tz_resizeImgcrop($imglink, $width, $height,$crop)
    {
        $img = new stdClass();
        $img->src = $imglink;
        $root_url = parse_url(JURI::base());
        if ($height != "") {
            $height1 = '&amp;height=' . $height;
        } else {
            $height1 = "";
        }
        $crop1 = '&amp;cropratio=' . $crop;

        $image = 'modules/mod_tz_portfolio_categories/image.php?width=' . $width . $height1 . $crop1 . '&amp;image=' . $root_url ['path'] . $img->src;


        return $image;

    }

    static function tz_resizeImg($imglink, $width, $height)
    {
        $img = new stdClass();
        $img->src = $imglink;
        $root_url = parse_url(JURI::base());
        if ($height != "") {
            $height1 = '&amp;height=' . $height;
        } else {
            $height1 = "";
        }

        $image = 'modules/mod_tz_portfolio_categories/image.php?width=' . $width . $height1 . '&amp;image=' . $root_url ['path'] . $img->src;


        return $image;

    }

    protected static function _findLink($needles = null)
    {
        $app		= JFactory::getApplication();
        $menus		= $app->getMenu('site');
        $active     = $menus->getActive();

        // Prepare the reverse lookup array.
        if (self::$lookup === null)
        {
            self::$lookup       = array();
            self::$sView        = array();
            self::$sCatIds      = array();

            $component	= JComponentHelper::getComponent('com_tz_portfolio');
            $items		= $menus->getItems('component_id', $component->id);

            $ccomponent = JComponentHelper::getComponent('com_content');
            if($cItems  = $menus -> getItems('component_id',$ccomponent -> id)){
                $items  = array_merge($items,$cItems);
            }

            foreach ($items as $item)
            {
                if (isset($item->query) && isset($item->query['view']))
                {
                    $view = $item->query['view'];

                    if (!isset(self::$lookup[$item -> component][$view])) {
                        self::$lookup[$item -> component][$view] = array();
                    }
                    if (isset($item->query['id'])) {
                        self::$lookup[$item -> component][$view][$item->query['id']] = $item->id;
                        self::$sCatIds[]    = $item->query['id'];
                    } else {
                        $catids		=	$item->params->get('tz_catid');

                        self::$sView['view']    = 'portfolio';
                        self::$sView['Itemid']    = $item -> id;
                        if ($catids) {
                            $catids = array_filter($catids);
                            $catids = array_reverse($catids);
                            if (is_array($catids)) {
                                if(count($catids)){
                                    for ($i =0; $i<count($catids); $i++){
                                        self::$lookup[$item -> component][$view][$catids[$i]] = $item->id;
                                    }
                                }
                            } else {
                                self::$lookup[$item -> component][$view][$catids] = $item->id;
                            }
                        }
                    }

                    if ($active && $active->component == 'com_tz_portfolio') {
                        if (isset($active->query) && isset($active->query['view'])){

                            if (isset($active->query['id'])) {
                                self::$lookup[$item -> component][$active->query['view']][$active->query['id']] = $active->id;
                            }
                        }
                    }elseif($active && $active->component == 'com_content'){
                        if (isset($active->query) && isset($active->query['view'])){

                            if (isset($active->query['id'])) {
                                self::$lookup[$item -> component][$active->query['view']][$active->query['id']] = $active->id;
                            }
                        }
                    }
                }
            }
        }

        if ($needles)
        {

            foreach ($needles as $view => $ids)
            {
                if ((isset(self::$lookup['com_tz_portfolio']) && isset(self::$lookup['com_tz_portfolio'][$view]))
                    || (isset(self::$lookup['com_content']) && isset(self::$lookup['com_content'][$view])) )
                {
                    foreach($ids as $id)
                    {
                        if (isset(self::$lookup['com_tz_portfolio'][$view][(int)$id])) {
                            return 'index.php?option=com_tz_portfolio&view='.$view.'&id='.$id.'&Itemid='.self::$lookup['com_tz_portfolio'][$view][(int) $id];
                        }elseif (isset(self::$lookup['com_content'][$view][(int)$id])) {
                            return 'index.php?option=com_content&view='.$view.'&id='.$id.'&Itemid='.self::$lookup['com_content'][$view][(int) $id];
                        }elseif(self::$sView['view'] == $view && !in_array($id,self::$sCatIds)){
                            return 'index.php?option=com_tz_portfolio&view='.$view.'&Itemid='.self::$sView['Itemid'];
                        }
                    }
                }
            }
        }
        else
        {
            if ($active && $active->component == 'com_tz_portfolio') {
                return $active ->link.'&Itemid='.$active -> id;
            }elseif($active && $active->component == 'com_content'){
                return $active ->link.'&Itemid='.$active -> id;
            }
        }

        return null;
    }

    protected static function getCategoryRoute($catid)
    {
        if ($catid instanceof JCategoryNode)
        {
            $id = $catid->id;
            $category = $catid;
        }
        else
        {
            $id = (int) $catid;
            $category = JCategories::getInstance('Content')->get($id);
        }

        if($id < 1)
        {
            $link = '';
        }
        else
        {
            $needles = array(
                'category' => array($id),
                'portfolio' => array($id),
                'timeline' => array($id)
            );

            if ($item = self::_findLink($needles))
            {
//                $link = 'index.php?Itemid='.$item;
                $link = $item;
            }
            else
            {
                //Create the link
//                $link = 'index.php?option=com_tz_portfolio&amp;view=category&amp;id='.$id;
                $link   = null;
                if($category)
                {
                    $catids = array_reverse($category->getPath());
                    $needles = array(
                        'category' => $catids,
                        'categories' => $catids
                    );

                    if ($item = self::_findLink($needles)) {
                        $link = $item;
                    }
                    elseif ($item = self::_findLink()) {
                        $link = $item;
                    }
                }
            }
        }

        return $link;
    }

}
?>
