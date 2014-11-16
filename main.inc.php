<?php 
/*
Plugin Name: Reply To
Version: auto
Description: This plugin allows you to add Twitter-like reply links to comments.
Plugin URI: auto
Author: Mistic
Author URI: http://www.strangeplanet.fr
*/

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'reply_to')
{
  add_event_handler('init', 'reply_to_error');
  function reply_to_error()
  {
    global $page;
    $page['errors'][] = 'Reply To folder name is incorrect, uninstall the plugin and rename it to "reply_to"';
  }
  return;
}

global $page;

define('REPLYTO_PATH' , PHPWG_PLUGINS_PATH . 'reply_to/');

add_event_handler('init', 'replyto_init');

function replyto_init()
{
  include_once(REPLYTO_PATH.'reply_to.inc.php');
  load_language('plugin.lang', REPLYTO_PATH);

  add_event_handler('render_comment_content', 'replyto_parse', 60, 2);
  if (script_basename() == 'comments')
  {
    add_event_handler('loc_end_comments', 'replyto_add_link', 60);
  }
  else
  {
    add_event_handler('loc_end_section_init', 'replyto_add_link');
  }
}
