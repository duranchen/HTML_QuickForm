<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Alexey Borzov <borz_off@cs.msu.su>                           |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'HTML/QuickForm/Renderer.php';

/**
 * A concrete renderer for HTML_QuickForm, using HTML_Template_ITX
 * 
 * @author Alexey Borzov <borz_off@cs.msu.su>
 * @access public
 */
class HTML_QuickForm_Renderer_ITX extends HTML_QuickForm_Renderer
{
   /**
    * An HTML_Template_ITX instance
    * @var object
    */
    var $_tpl = null;

   /**
    * The errors that were not shown near concrete fields go here
    * @var array
    */
    var $_errors = array();

   /**
    * Show the block with required note?
    * @var bool
    */
    var $_showRequired = false;

   /**
    * A separator for group elements
    * @var mixed
    */
    var $_groupSeparator = null;

   /**
    * The current element index inside a group
    * @var integer
    */
    var $_groupElementIdx = 0;

   /**
    * Blocks to use for different elements  
    * @var array
    */
    var $_elementBlocks = array();

   /**
    * Block to use for headers
    * @var string
    */
    var $_headerBlock = null;


   /**
    * Constructor
    *
    * @param object     An HTML_Template_ITX object to use
    */
    function HTML_QuickForm_Renderer_ITX(&$tpl)
    {
        $this->HTML_QuickForm_Renderer();
        $this->_tpl =& $tpl;
        $this->_tpl->setCurrentBlock('qf_main_loop');
    }


    function finishForm(&$form)
    {
        // display errors above form
        if (!empty($this->_errors) && $this->_tpl->blockExists('qf_error_loop')) {
            foreach ($this->_errors as $error) {
                $this->_tpl->setVariable('qf_error', $error);
                $this->_tpl->parse('qf_error_loop');
            }
        }
        // show required note
        if ($this->_showRequired) {
            $this->_tpl->setVariable('qf_required_note', $form->getRequiredNote());
        }
        // assign form attributes
        $this->_tpl->setVariable('qf_attributes', $form->getAttributesString());
        // assign javascript validation rules
        $this->_tpl->setVariable('qf_javascript', $form->getValidationScript());
    }
      

    function renderHeader(&$header)
    {
        $blockName = $this->_matchBlock($header);
        if ('qf_header' == $blockName && isset($this->_headerBlock)) {
            $blockName = $this->_headerBlock;
        }
        $this->_tpl->setVariable('qf_header', $header->toHtml());
        $this->_tpl->parse($blockName);
        $this->_tpl->parse('qf_main_loop');
    }


    function renderElement(&$element, $required, $error)
    {
        $blockName = $this->_matchBlock($element);
        // are we inside a group?
        if ('qf_main_loop' != $this->_tpl->getCurrentBlock()) {
            if (0 != $this->_groupElementIdx && $this->_tpl->placeholderExists('qf_separator', $blockName)) {
                if (is_array($this->_groupSeparator)) {
                    $this->_tpl->setVariable('qf_separator', $this->_groupSeparator[($this->_groupElementIdx - 1) % count($this->_groupSeparator)]);
                } else {
                    $this->_tpl->setVariable('qf_separator', (string)$this->_groupSeparator);
                }
            }
            $this->_groupElementIdx++;

        } elseif(!empty($error)) {
            // show the error message or keep it for later use
            if ($this->_tpl->blockExists($blockName . '_error')) {
                $this->_tpl->setVariable('qf_error', $error);
            } else {
                $this->_errors[] = $error;
            }
        }
        // show an '*' near the required element
        if ($required) {
            $this->_showRequired = true;
            if ($this->_tpl->blockExists($blockName . '_required')) {
                $this->_tpl->touchBlock($blockName . '_required');
            }
        }
        // render the element itself with its label
        $this->_tpl->setVariable('qf_element', $element->toHtml());
        if ($this->_tpl->placeholderExists('qf_label', $blockName)) {
            $this->_tpl->setVariable('qf_label', $element->getLabel());
        }
        $this->_tpl->parse($blockName);
        $this->_tpl->parseCurrentBlock();
    }
   

    function renderHidden(&$element)
    {
        $this->_tpl->setVariable('qf_hidden', $element->toHtml());
        $this->_tpl->parse('qf_hidden_loop');
    }


    function startGroup(&$group, $required, $error)
    {
        $blockName = $this->_matchBlock($group);
        $this->_tpl->setCurrentBlock($blockName . '_loop');
        $this->_groupElementIdx = 0;
        $this->_groupSeparator  = empty($group->_separator)? '&nbsp;': $group->_separator;
        // show an '*' near the required element
        if ($required) {
            $this->_showRequired = true;
            if ($this->_tpl->blockExists($blockName . '_required')) {
                $this->_tpl->touchBlock($blockName . '_required');
            }
        }
        // show the error message or keep it for later use
        if (!empty($error)) {
            if ($this->_tpl->blockExists($blockName . '_error')) {
                $this->_tpl->setVariable('qf_error', $error);
            } else {
                $this->_errors[] = $error;
            }
        }
        $this->_tpl->setVariable('qf_group_label', $group->getLabel());
    }


    function finishGroup(&$group)
    {
        $this->_tpl->parse($this->_matchBlock($group));
        $this->_tpl->setCurrentBlock('qf_main_loop');
        $this->_tpl->parseCurrentBlock();
    }


   /**
    * Returns the name of a block to use for element rendering
    * 
    * If a name was not explicitly set via setElementBlock(), it tries
    * the names '{prefix}_{element type}' and '{prefix}_{element}', where
    * prefix is either 'qf' or the name of the current group's block
    * 
    * @param object     An HTML_QuickForm_element object
    * @access private
    * @return string    block name
    */
    function _matchBlock(&$element)
    {
        $name = $element->getName();
        $type = $element->getType();
        if (isset($this->_elementBlocks[$name]) && $this->_tpl->blockExists($this->_elementBlocks[$name])) {
            if (('group' == $type) || ($this->_elementBlocks[$name] . '_loop' != $this->_tpl->getCurrentBlock())) {
                return $this->_elementBlocks[$name];
            }
        }
        if ('group' != $type && 'qf_main_loop' != $this->_tpl->getCurrentBlock()) {
            $prefix = substr($this->_tpl->getCurrentBlock(), 0, -5); // omit '_loop' postfix
        } else {
            $prefix = 'qf';
        }
        if ($this->_tpl->blockExists($prefix . '_' . $type)) {
            return $prefix . '_' . $type;
        } else {
            return $prefix . '_element';
        }
    }


   /**
    * Sets the block to use for element rendering
    * 
    * @param mixed      element name or array ('element name' => 'block name')
    * @param string     block name if $elementName is not an array
    * @access public
    * @return void
    */
    function setElementBlock($elementName, $blockName = null)
    {
        if (is_array($elementName)) {
            $this->_elementBlocks = array_merge($this->_elementBlocks, $elementName);
        } else {
            $this->_elementBlocks[$elementName] = $blockName;
        }
    }


   /**
    * Sets the name of a block to use for header rendering
    *
    * @param string     block name
    * @access public
    * @return void
    */
    function setHeaderBlock($blockName)
    {
        $this->_headerBlock = $blockName;
    }
}
?>
