<?php
/**
 * @version     sw.build.version
 * @copyright   Copyright (C) 2019 - 2024 Sven Schultschik. All rights reserved
 * @license     GPL-3.0-or-later
 * @author      Sven Schultschik (extensions@schultschik.de)
 * @link        extensions.schultschik.de
 */

namespace SchuWeb\Plugin\SchuWeb_Sitemap\Weblinks\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\Component\Weblinks\Site\Helper\RouteHelper;
use Joomla\Database\ParameterType;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use SchuWeb\Component\Sitemap\Site\Event\MenuItemPrepareEvent;
use SchuWeb\Component\Sitemap\Site\Event\TreePrepareEvent;


class Weblinks extends CMSPlugin implements SubscriberInterface
{
    /**
     * @since 5.2.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onGetMenus' => 'onGetMenus',
            'onGetTree'  => 'onGetTree',
        ];
    }

    /**
     * This function is called before a menu item is printed. We use it to set the
     * proper uniqueid for the item
     *
     * @param   MenuItemPrepareEvent  Event object
     *
     * @return void
     * @since  5.2.0
     */
    public function onGetMenus(MenuItemPrepareEvent $event)
    {
        $menu_item  = $event->getMenuItem();
        $link_query = parse_url($menu_item->link);
        if (!isset($link_query['query'])) {
            return;
        }

        parse_str(html_entity_decode($link_query['query']), $link_vars);
        $view = ArrayHelper::getValue($link_vars, 'view', '');

        switch ($view) {
            case 'weblink':
                $id = intval(ArrayHelper::getValue($link_vars, 'id', 0));
                if ($id) {
                    $menu_item->uid        = "com_weblinksi{$id}";
                    $menu_item->expandible = false;
                }
                break;
            case 'categories':
                $menu_item->uid = 'com_weblinkscategories';
                $menu_item->expandible = true;
                break;
            case 'category':
                $catid = intval(ArrayHelper::getValue($link_vars, 'id', 0));
                $menu_item->uid = "com_weblinksc{$catid}";
                $menu_item->expandible = true;
                break;
        }
    }

    /**
     * Expands a com_content menu item
     *
     * @param   TreePrepareEvent  Event object
     *
     * @return void
     * @since  5.2.0
     */
    public function onGetTree(TreePrepareEvent $event)
	//static function getTree(&$sitemap, &$parent, &$params)
	{
        $sitemap = $event->getSitemap();
        $parent  = $event->getNode();

        if ($parent->option != "com_weblinks")
            return null;

        //Image sitemap does not make sense for weblinks
        if ($sitemap->isImagesitemap())
            return null;

		$link_query = parse_url($parent->link);
		parse_str(html_entity_decode($link_query['query']), $link_vars);
		$view = ArrayHelper::getValue($link_vars, 'view', 0);

		if ($view == 'category') {
			$catid = intval(ArrayHelper::getValue($link_vars, 'id', 0));
		} elseif ($view == 'categories') {
			$catid = 0;
		} else { // Only expand category menu items
			return;
		}

		$include_links = $this->params->get('include_links', 1);
		$include_links = ($include_links == 1
			|| ($include_links == 2 && $sitemap->isXmlsitemap())
			|| ($include_links == 3 && !$sitemap->isXmlsitemap()));
		$params['include_links'] = $include_links;

		$priority = ArrayHelper::getValue($params, 'cat_priority', $parent->priority);
		$changefreq = ArrayHelper::getValue($params, 'cat_changefreq', $parent->changefreq);
		if ($priority == '-1')
			$priority = $parent->priority;
		if ($changefreq == '-1')
			$changefreq = $parent->changefreq;

		$params['cat_priority'] = $priority;
		$params['cat_changefreq'] = $changefreq;

		$priority = ArrayHelper::getValue($params, 'link_priority', $parent->priority);
		$changefreq = ArrayHelper::getValue($params, 'link_changefreq', $parent->changefreq);
		if ($priority == '-1')
			$priority = $parent->priority;

		if ($changefreq == '-1')
			$changefreq = $parent->changefreq;

		$params['link_priority'] = $priority;
		$params['link_changefreq'] = $changefreq;

		$options = array();
		$options['countItems'] = false;
		$options['catid'] = rand();
		$categories = Categories::getInstance('Weblinks', $options);
		$category = $categories->get($catid ?: 'root', true);

		$weblinks_params = ComponentHelper::getParams('com_weblinks');

		$params['count_clicks'] = $weblinks_params->get('count_clicks', "1");

		self::getCategoryTree($sitemap, $parent, $params, $category);
	}

	static function getCategoryTree(&$sitemap, &$parent, &$params, &$category)
	{
		$children = $category->getChildren();

		foreach ($children as $cat) {
			$node = new \stdclass;
			$node->id = $parent->id;
			$id = $node->uid = $parent->uid . 'c' . $cat->id;
			$node->name = $cat->title;
			$node->link = RouteHelper::getCategoryRoute($cat);
			$node->priority = $params['cat_priority'];
			$node->changefreq = $params['cat_changefreq'];
			$node->browserNav = $parent->browserNav;
			$node->modified = $cat->modified_time;

            if ($sitemap->isNewssitemap()){
                $node->modified = $cat->created_time;
            }

			$node->expandible = true;

			if (!isset($parent->subnodes))
				$parent->subnodes = new \stdClass();

			$node->params = &$parent->params;

			$parent->subnodes->$id = $node;

			self::getCategoryTree($sitemap, $parent->subnodes->$id, $params, $cat);
		}

		if ($params['include_links']) {
			/** @var DatabaseDriver $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);

			$query = $db->getQuery(true);
            $now   = Factory::getDate()->toSql();
			$query->select(
				array(
					$db->qn('id'),
					$db->qn('title'),
					$db->qn('url'),
					$db->qn('params'),
					$db->qn('modified'),
                    $db->qn('created')
				)
			)
				->from($db->qn('#__weblinks'))
				->setLimit(ArrayHelper::getValue($params, 'max_links', null, 'INT'))
				->order($db->escape('ordering') . ' ' . $db->escape('ASC'))
                ->where($db->qn('catid') . ' = ' . $db->q($category->id))
                ->where($db->qn('state') . ' = 1')
                ->extendWhere(
                    'AND',
                    [
                        $db->qn('publish_up') . ' IS NULL',
                        $db->qn('publish_up') . ' <= :nowDate1',
                    ],
                    'OR'
                )
                ->extendWhere(
                    'AND',
                    [
                        $db->qn('publish_down') . ' IS NULL',
                        $db->qn('publish_down') . ' >= :nowDate2',
                    ],
                    'OR'
                )
                ->bind([':nowDate1', ':nowDate2'], $now, ParameterType::STRING);

            if ($sitemap->isNewssitemap()){
                $query->where($db->qn('created') .' > DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -2 DAY)');
            }

			$db->setQuery($query);

			$links = $db->loadObjectList();

			foreach ($links as $link) {
				$item_params = new Registry;
				$item_params->loadString($link->params);

				$node = new \stdclass;
				$node->id = $parent->id;
				$id = $node->uid = $parent->uid . 'i' . $link->id;
				$node->name = $link->title;

				// Find the Itemid
				$Itemid = intval(preg_replace('/.*Itemid=([0-9]+).*/', '$1', RouteHelper::getWeblinkRoute($link->id, $category->id)));

				if ($item_params->get('count_clicks', $params['count_clicks']) == "1") {
					$node->link = 'index.php?option=com_weblinks&task=weblink.go&id=' . $link->id . '&Itemid=' . ($Itemid ?: $parent->id);
				} else {
					$node->link = $link->url;
				}
				$node->priority = $params['link_priority'];
				$node->changefreq = $params['link_changefreq'];

				$node->browserNav = $parent->browserNav;
				$node->modified = $link->modified;

                if ($sitemap->isNewssitemap()){
                    $node->modified = $link->created;
                }

				$node->expandible = false;

				if (!isset($parent->subnodes))
					$parent->subnodes = new \stdClass();

				$parent->subnodes->$id = $node;
			}
		}
	}
}