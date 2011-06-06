<?php 
/*
Plugin Name: Reply To
Version: auto
Description: This plugin allows you to add Twitter-like reply links to comments.
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: Mistic
Author URI: http://www.strangeplanet.fr
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $page;

define('REPLYTO_DIR' , basename(dirname(__FILE__)));
define('REPLYTO_PATH' , PHPWG_PLUGINS_PATH . REPLYTO_DIR . '/');

include_once(REPLYTO_PATH.'reply_to.inc.php');
load_language('plugin.lang', REPLYTO_PATH);

// add link and parse on picture page
if (script_basename() == 'picture')
{
  add_event_handler('loc_begin_picture', 'replyto_add_link');
}
// parse on comments page (picture section)
else if (script_basename() == 'comments' AND !isset($_GET['display_mode']))
{
  add_event_handler('render_comment_content', 'replyto_parse_picture', 10);
}
// add link and parse on album page (compatibility with Comment on Albums)
else if (script_basename() == 'index')
{
  add_event_handler('loc_begin_index', 'replyto_add_link');
}
// parse on comments page (album section)
else if (script_basename() == 'comments' AND $_GET['display_mode'] == 'albums')
{
  add_event_handler('render_comment_content', 'replyto_parse_album', 10);
}
    
?>