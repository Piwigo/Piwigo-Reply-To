<?php 
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Add anchors and reply button to comments blocks
 */
function replyto_add_link()
{
  global $pwg_loaded_plugins, $template, $page, $conf;
  $template->assign('REPLYTO_PATH', REPLYTO_PATH);
  
  // comment form has different id
  if (
    (isset($_GET['action']) AND $_GET['action'] == 'edit_comment') OR 
    (isset($page['body_id']) AND $page['body_id'] == 'theCommentsPage')
    ) 
  {
    $template->assign('replyto_form_name', 'editComment');
  } 
  else 
  {
    $template->assign('replyto_form_name', 'addComment');
  }
    
  // must re-check our location + some additional tests
  if (script_basename() == 'picture')
  {
    add_event_handler('render_comment_content', 'replyto_parse_picture', 60);
    add_event_handler('user_comment_insertion', 'replyto_parse_picture_mail');
    if ( !is_a_guest() OR $conf['comments_forall'] )
    {
      $template->set_prefilter('picture', 'replyto_add_link_prefilter');
    }
  } 
  else if 
    (
      script_basename() == 'index' AND 
      isset($pwg_loaded_plugins['Comments_on_Albums']) AND 
      $page['section'] == 'categories' AND isset($page['category'])
    )
  {
    add_event_handler('render_comment_content', 'replyto_parse_album', 60);
    add_event_handler('user_comment_insertion', 'replyto_parse_album_mail');
    if ( !is_a_guest() OR $conf['comments_forall'] )
    {
      $template->set_prefilter('comments_on_albums', 'replyto_add_link_prefilter');
    }
  }
}

function replyto_add_link_prefilter($content, &$smarty)
{
  // script
  $search[0] = '<ul class="thumbnailCategories">';
  $replace[0] = '
{combine_script id=\'insertAtCaret\' require=\'jquery\' path=$REPLYTO_PATH|@cat:\'insertAtCaret.js\'}

{footer_script require=\'insertAtCaret\'}
function replyto(commentID, author) {ldelim}
  jQuery("#{$replyto_form_name} textarea").insertAtCaret("[reply=" + commentID + "]" + author + "[/reply] ");
}
{/footer_script}

{html_head}
<style type="text/css">
  .replyTo {ldelim}
    display:inline-block;
    width:16px;
    height:16px;
    background:url({$REPLYTO_PATH}reply.png) center top no-repeat;
  }
  .replyTo:hover {ldelim}
    background-position:center -16px;
  }
</style>
{/html_head}'
.$search[0];

  // button
  $search[1] = '<span class="author">';
  $replace[1] = '
{if not isset($comment.IN_EDIT)}
<div class="actions" style="float:right;">
  <a href="#commentform" title="{\'reply to this comment\'|@translate}" class="replyTo" onclick="replyto(\'{$comment.ID}\', \'{$comment.AUTHOR}\');">&nbsp;</a>
</div>
{/if}'
.$search[1];
  
  // anchor
  $search[2] = '<div class="thumbnailCategory';
  $replace[2] = '
<a name="comment-{$comment.ID}"></a>'
.$search[2];

  $search[3] = '<legend>{\'Add a comment\'|@translate}</legend>';
  $replace[3] = $search[3].'
<a name="commentform"></a>';

  return str_replace($search, $replace, $content);
}


/**
 * Replace BBcode tag by a link with absolute url
 */
function replyto_parse_picture($comment)
{
  if (preg_match('#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i', $comment, $matches))
  {
    // picture informations
    $query = '
SELECT
  img.id,
  img.file,
  cat.category_id
FROM ' . IMAGES_TABLE . ' AS img
INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS cat
  ON cat.image_id = img.id
INNER JOIN ' . COMMENTS_TABLE . ' AS com
  ON com.image_id = img.id
WHERE com.id = ' . $matches[1] . '
;';
    $result = pwg_query($query);
    
    // make sure the target comment exists
    if (pwg_db_num_rows($result))
    {
      $image = pwg_db_fetch_assoc($result);

      // retrieving category informations
      $query = '
SELECT 
  id, 
  name, 
  permalink, 
  uppercats
FROM ' . CATEGORIES_TABLE . '
WHERE id = ' . $image['category_id'] . '
;';
      $image['cat'] = pwg_db_fetch_assoc(pwg_query($query));

      // link to the full size picture
      $image['url'] = make_picture_url(array(
        'category' => $image['cat'],
        'image_id' => $image['id'],
        'image_file' => $image['file'],
      ));		  
      
      $search = "#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i";
      $replace = '@ <a href="'.get_absolute_root_url().$image['url'].'#comment-$1">$2</a> :';
    }
    else
    {
      $search = "#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i";
      $replace = '';
    }
    
    return preg_replace($search, $replace, $comment);
  }
  else
  {
    return $comment;
  }
}

function replyto_parse_album($comment)
{
  if (preg_match('#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i', $comment, $matches))
  {  
    // check if the comment is really an album comment 
    // (both comments_on_albums script and default comments script are executed... 
    //  with the same 'render_comment_content' event)
    ## THIS CAN INDUCE ERRORS, MUST FIND A BETTER WAY
    $query = '
SELECT id
FROM ' . COA_TABLE . '
WHERE content = "' . $comment .'"
;';
    if (!pwg_db_num_rows(pwg_query($query)))
    {
      return $comment;
    }
    
    // retrieving category informations
    $query = '
SELECT
  cat.id, 
  cat.name, 
  cat.permalink, 
  cat.uppercats
FROM ' . COA_TABLE . ' AS com
INNER JOIN ' . CATEGORIES_TABLE . ' AS cat
  ON cat.id = com.category_id
WHERE com.id = ' . $matches[1] . '
;';
    $result = pwg_query($query);
    
    // make sure the target comment exists
    if (pwg_db_num_rows($result))
    {
      $category = pwg_db_fetch_assoc($result);

      // link to the album
      $category['url'] = make_index_url(array(
        'category' => $category,
      ));		  
    
      $search = "#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i";
      $replace = '@ <a href="'.get_absolute_root_url().$category['url'].'#comment-$1">$2</a> :';
    }
    else
    {
      $search = "#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i";
      $replace = '';
    }
    
    return preg_replace($search, $replace, $comment);
  }
  else
  {
    return $comment;
  }
}

/**
 * Replace BBcode tag by a link in notification mails
 */
function replyto_parse_picture_mail($comment)
{
  $comment['content'] = replyto_parse_picture($comment['content']);
  var_dump($comment);
  return $comment;
}

function replyto_parse_album_mail($comment)
{
  $comment['content'] = replyto_parse_album($comment['content']);
  return $comment;
}

?>