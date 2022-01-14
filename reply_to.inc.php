<?php
defined('REPLYTO_PATH') or die('Hacking attempt!');

define('REPLYTO_REGEX', '#\[reply=([0-9]+)\]([^\[\]]*)\[/reply\]#i');

/**
 * Add anchors and reply button to comments blocks
 */
function replyto_add_link()
{
  global $pwg_loaded_plugins, $template, $page, $conf, $lang;
  $template->assign('REPLYTO_PATH', REPLYTO_PATH);

  // comment form has different id
  $edittingComment = isset($_GET['action']) and $_GET['action'] == 'edit_comment';
  $commentsPage = isset($page['body_id']) and $page['body_id'] == 'theCommentsPage';
  if ($edittingComment or $commentsPage) {
    $template->assign('replyto_form_name', 'editComment');
  } else {
    $template->assign('replyto_form_name', 'addComment');
  }


  $indexSection = (script_basename() == 'index' and isset($page['section']));
  $commentsOnAlbums = isset($pwg_loaded_plugins['Comments_on_Albums']);
  $sectionPage = ($page['section'] == 'categories' and isset($page['category']));
  $albumPageCondition = $indexSection and $commentsOnAlbums and $sectionPage;


  /* COMMENTS page */
  if (script_basename() == 'comments') {
    if (!is_a_guest() or $conf['comments_forall']) {
      $comments = $template->get_template_vars('comments');
      if (!count($comments)) {
        return;
      }

      // generates urls to picture or albums with necessary url params
      foreach ($comments as $tpl_var)
      {
        $replyto_links[ $tpl_var['ID'] ] = add_url_params(
          $tpl_var['U_PICTURE'],
          array(
            'rt' => $tpl_var['ID'],
            'rta' =>$tpl_var['AUTHOR'],
            )
          ).'#commentform';
      }

      $template->assign('replyto_links', $replyto_links);
      $template->set_prefilter('comment_list', 'replyto_add_link_comments_prefilter');
    }
  }
  /* PICTURE page */
  else if (script_basename() == 'picture') {
    add_event_handler('user_comment_insertion', 'replyto_parse_picture_mail');

    if (!is_a_guest() or $conf['comments_forall']) {
      $template->set_prefilter('comment_list', 'replyto_add_link_prefilter');
    }
  }
  /* ALBUM page */
  else if ($albumPageCondition) {
    add_event_handler('user_comment_insertion', 'replyto_parse_album_mail');

    if (!is_a_guest() or $conf['comments_forall']) {
      $template->set_prefilter('comments_on_albums', 'replyto_add_link_prefilter');
    }
  }


  /* we come from comments.php page */
  if (!empty($_GET['rt']) and !empty($_GET['rta'])) {
    $template->func_combine_script(array('id'=>'insertAtCaret', 'path'=>REPLYTO_PATH.'insertAtCaret.js'));
    $template->block_footer_script(array('require'=>'insertAtCaret'), 'replyTo("'.$_GET['rt'].'", "'.$_GET['rta'].'");');
  }
}

/**
 * reply buttons on comments.php page
 */
function replyto_add_link_comments_prefilter($content)
{
  // style
  $content.= '
{html_style}
.replyTo {
  display:none;
  background:url({$ROOT_URL}{$REPLYTO_PATH}reply.png) left top no-repeat;
  height:16px;
  margin-left:20px;
  padding-left:20px;
}
li.commentElement:hover .replyTo {
  display:inline;
}
.replyTo:hover {
  background-position:left -16px;
}
{/html_style}';

  // button
  $search = '<span class="commentDate">{$comment.DATE}</span>';
  $replyHtml = '<a href="{$replyto_links[$comment.ID]}" class="replyTo">{\'Reply\'|@translate}</a>';
  $replace = $search . $replyHtml;

  return str_replace($search, $replace, $content);
}

/**
 * reply buttons on picture.php and index.php pages
 */
function replyto_add_link_prefilter($content)
{
  // script & style
  $content.= '
{combine_script id=\'insertAtCaret\' require=\'jquery\' path=$REPLYTO_PATH|cat:\'insertAtCaret.js\'}

{footer_script require=\'insertAtCaret\'}
function replyTo(commentID, author) {
  jQuery("#{$replyto_form_name} textarea").insertAtCaret("[reply=" + commentID + "]" + author + "[/reply] ").focus();
}
{/footer_script}

{html_style}
.replyTo {
  display:none;
  background:url({$ROOT_URL}{$REPLYTO_PATH}reply.png) left top no-repeat;
  height:16px;
  margin-left:20px;
  padding-left:20px;
}
li.commentElement:hover .replyTo {
  display:inline;
}
.replyTo:hover {
  background-position:left -16px;
}
{/html_style}';

  // button
  $search[1] = '<span class="commentDate">{$comment.DATE}</span>';
  $replace[1] = $search[1].'<a href="#commentform" class="replyTo" onclick="replyTo(\'{$comment.ID}\', \'{$comment.AUTHOR}\');">{\'Reply\'|@translate}</a>';

  // anchors
  $search[2] = '<li class="commentElement';
  $replace[2] = '<a name="comment-{$comment.ID}"></a>'.$search[2];

  $search[3] = '<div id="commentAdd">';
  $replace[3] = $search[3].'<a name="commentform"></a>';

  return str_replace($search, $replace, $content);
}


/**
 * Replace reply tag by a link with absolute url
 */
function replyto_parse($comment, $in_album = false)
{
  if (preg_match(REPLYTO_REGEX, $comment, $matches)) {
    /* try to parse a ReplyTo tag link for picture page */
    if (!$in_album) {
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
      if (pwg_db_num_rows($result)) {
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

        $replace = '@ <a href="'.$image['url'].'#comment-$1">$2</a> :';
      } else {
        $replace = '';
      }
    }
    /* try to parse a ReplyTo tag link for an album */
    else if ( $in_album == 'album') {
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
      if (pwg_db_num_rows($result)) {
        $category = pwg_db_fetch_assoc($result);

        // link to the album
        $category['url'] = make_index_url(array(
          'category' => $category,
        ));

        $replace = '@ <a href="'.$category['url'].'#comment-$1">$2</a> :';
      } else {
        $replace = '';
      }
    }

    return preg_replace(REPLYTO_REGEX, $replace, $comment);
  } else {
    return $comment;
  }
}

/**
 * Replace BBcode tag by a link in notification mails
 */
function replyto_parse_picture_mail($comment)
{
  $comment['content'] = replyto_parse($comment['content']);
  return $comment;
}

function replyto_parse_album_mail($comment)
{
  $comment['content'] = replyto_parse($comment['content'], 'album');
  return $comment;
}
