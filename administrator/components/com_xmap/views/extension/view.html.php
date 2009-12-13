<?php
/**
* @version             $Id$
 * @copyright   Copyright (C) 2007 - 2009 Joomla! Vargas. All rights reserved.
 * @license             GNU General Public License version 2 or later; see LICENSE.txt
 * @author              Guillermo Vargas (guille@vargas.co.cr)
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * @package		Joomla.Administrator
 * @subpackage	com_content
 */
class XmapViewExtension extends JView
{
	/**
	 * Display the view
	 *
	 * @access	public
	 */
	function display($tpl = null)
	{
		$app	= &JFactory::getApplication();
		$state	= $this->get('State');
		$item	= $this->get('Item');
		$params	= $this->get('Params');
		$form 	= $this->get('Form');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		// Convert dates from UTC
		$offset	= $app->getCfg('offset');

		$form->bind($item);

		$this->assignRef('state',	$state);
		$this->assignRef('extension',	$item);
		$this->assignRef('params',	$params);
		$this->assignRef('form',	$form);

		$this->_setToolbar();
		parent::display($tpl);
		JRequest::setVar('hidemainmenu', true);
	}

	/**
	 * Display the toolbar
	 *
	 * @access	private
	 */
	function _setToolbar()
	{
		$user		= &JFactory::getUser();

		JToolBarHelper::title(JText::_('Xmap_Page_Edit_Extension'), 'article-add.png');
		
		JToolBarHelper::save('extension.save');
		JToolBarHelper::apply('extension.apply');
		JToolBarHelper::cancel('extension.cancel');
		JToolBarHelper::divider();
		JToolBarHelper::help('screen.xmap.extension');
	}
}