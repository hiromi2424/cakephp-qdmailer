<?php
/**
 * Qdmailer Component
 * 
 * This is a component to send email using
 * the Qdmail Library (http://hal456.net/qdmail ).
 * Basic usage is almost the same as built-in QdmailComponent.
 * 
 * Copyright 2008, Spok in japan , tokyo
 * hal456.net/qdmail    :  http://hal456.net/qdmail/
 * & CPA-LAB/Technical  :  http://www.cpa-lab.com/tech/
 * Licensed under The MIT License License
 *
 * CakePHP(tm) :  Rapid Development Framework (http://www.cakephp.org)
 * Copyright 2005-2008, Cake Software Foundation, Inc. (http://www.cakefoundation.org)
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Takayuki Miwa <i@tkyk.name>
 * @link http://wp.serpere.info
 * @package Qdmailer
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

App::import('Vendor', 'qdmail');

class QdmailerComponent extends QdmailComponent
{
  /**
   * If you use qmail, set this variable to true.
   * You can use Configure::write('Qdmailer.is_qmail', bool)
   * in the bootstrap.php.
   * 
   * I strongly recommend you to set it explicitly, because
   * if you leave it as null Qdmail executes the sendmail command
   * each time you instantiate this class to determine whether
   * you are using qmail or not. It not only bloats the maillog
   * but also slows down your app.
   * 
   */
  //var $is_qmail = null;

  /**
   * Loads global configurations from the Configure class,
   * and merges them with defaults in the first load.
   * 
   * @static
   * @return array
   */
  function _getGlobalConfig()
  {
    static $setDefault = false;
    if(!$setDefault) {
      $crr = Configure::read('Qdmailer');
      Configure::write('Qdmailer',
		       am(array('logPath' => LOGS,
				'errorlogPath' => LOGS,
				'errorDisplay' => Configure::read('debug') > 0), 
			  is_array($crr) ? $crr : array()));
      $setDefault = true;
    }
    return Configure::read('Qdmailer');
  }

  /**
   * Returns whether the local MTA is qmail or not.
   * This refers to the Configure to skip the auto-detection.
   * 
   * @override
   * @return boolean
   */
  function isQmail()
  {
    $is_qmail = Configure::read('Qdmailer.is_qmail');
    if(!is_null($is_qmail)) {
      $this->is_qmail = $is_qmail;
    }
    if(!is_null(Configure::read('Qdmailer'))) {
      Configure::delete('Qdmailer.is_qmail');
    }

    return parent::isQmail();
  }

  /**
   * initialize callback.
   * 
   * @param object $controller
   * @param array  $settings  passed from the controller.
   */
  function initialize(&$controller, $settings=array())
  {
    $this->option(am(self::_getGlobalConfig(), $settings));
  }

  /**
   * Supersedes the parent's implementation to prevent
   * setting log_path and errorlog_path to COMPONENTS.
   * 
   * @override
   * @param object $controller
   */
  function startup(&$controller)
  {
    $this->Controller =& $controller;
  }

  /**
   * Loads and instantiates the View.
   * 
   * This code is copied from the EmailComponent class.
   * 
   * @copyright     Copyright 2005-2008, Cake Software Foundation, Inc. (http://www.cakefoundation.org)
   * @return object View
   * @access protected
   */
  function &_loadView()
  {
    $viewClass = $this->Controller->view;
    if ($viewClass != 'View') {
      if (strpos($viewClass, '.') !== false) {
	list($plugin, $viewClass) = explode('.', $viewClass);
      }
      $viewClass = $viewClass . 'View';
      App::import('View', $this->Controller->view);
    }
    $View = new $viewClass($this->Controller, false);
    return $View;
  }

  /**
   * Overrides parent's method to load qdsmtp.php from app/vendors
   * and share the log locations with Qdmail(er).
   * 
   */
  function &smtpObject()
  {
    App::import('Vendor', 'qdsmtp');
    $smtp =& parent::smtpObject();
    $smtp->logFilename($this->logPath(). $smtp->name. '.log');
    $smtp->errorlogFilename($this->errorlogPath() . $smtp->name .'_error.log' );
    $smtp->error_display = $this->errorDisplay();
    return $smtp;
  }

  /**
   * Overrides QdmailComponent::cakeRender.
   *
   */
  function cakeRender($content, $type, $org_charset = null , $target_charset = null)
  {
    if(is_null($target_charset)) {
      $target_charset = $this->charset_content;
    }
    if(is_null($org_charset)){
      $org_charset = Configure::read('App.encoding');
    }
    $type = strtolower($type);

    $View =& $this->_loadView();
    $View->layout = $this->layout;

    $content = $View->element('email' . DS . $type . DS . $this->template, array('content' => $content), true);
    $View->layoutPath = 'email' . DS . $type;
    $content = $View->renderLayout($content);

    $mess = $this->qd_convert_encoding($content, $target_charset, $org_charset);
    return array($mess, $target_charset, $org_charset);
  }
}

