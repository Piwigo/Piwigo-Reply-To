<?php 
/*
Plugin Name: Reply To
Version: auto
Description: This plugin allows you to add Twitter-like reply links to comments.
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=556
Author: Mistic
Author URI: http://www.strangeplanet.fr
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $page;

define('REPLYTO_DIR' , basename(dirname(__FILE__)));
define('REPLYTO_PATH' , PHPWG_PLUGINS_PATH . REPLYTO_DIR . '/');

add_event_handler('init', 'replyto_init');

function replyto_init()
{
  include_once(REPLYTO_PATH.'reply_to.inc.php');
  load_language('plugin.lang', REPLYTO_PATH);

  add_event_handler('render_comment_content', 'replyto_parse', 60, 2);
  if (script_basename() == 'comments') add_event_handler('loc_after_page_header', 'replyto_add_link');
  else add_event_handler('loc_end_section_init', 'replyto_add_link');
}
    
?>