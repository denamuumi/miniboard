<?php

use Psr\Http\Message\UploadedFileInterface;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Creates a post object that's ready to be saved into database.
 * 
 * @param array $args
 * @param array $params
 * @param array $file
 * @return array
 */
function create_post(array $args, array $params, array $file) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];

  // escape message HTML entities
  $message = funcs_common_clean_field($params['message']);

  // preprocess message reference links (same board)
  $message = preg_replace_callback('/(^&gt;&gt;)([0-9]+)/m', function ($matches) use ($board_cfg) {
    $post = select_post($board_cfg['id'], intval($matches[2]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        return "<a class='reference' href='/{$board_cfg['id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        return "<a class='reference' href='/{$board_cfg['id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);

  // preprocess message reference links (any board)
  $message = preg_replace_callback('/(^&gt;&gt;&gt;)\/([a-z]+)\/([0-9]+)/m', function ($matches) use ($board_cfg) {
    $post = select_post($matches[2], intval($matches[3]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        return "<a class='reference' href='/{$post['board_id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        return "<a class='reference' href='/{$post['board_id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);
  
  // preprocess message quotes
  $message = preg_replace('/(^&gt;)([a-zA-Z0-9,.-;:_ ]+)/m', '<span class="quote">$0</span>', $message);

  // preprocess message bbcode
  $message = preg_replace('/\[(b|i|u|s)\](.*?)\[\/\1\]/ms', '<$1>$2</$1>', $message);
  $message = preg_replace('/\[code\](.*?)\[\/code\]/ms', '<pre>$1</pre>', $message);
  $message = preg_replace('/\[quote\](.*?)\[\/quote\]/ms', '<blockquote>$1</blockquote>', $message);
  $message = preg_replace('/\[quote="(.*?)"\](.*?)\[\/quote\]/ms', '<blockquote>$2</blockquote><p>~ $1 ~</p>', $message);

  // convert message line endings
  $message = nl2br($message, false);

  // strip HTML tags inside <pre></pre>
  $message = preg_replace_callback('/\<pre\>(.*?)\<\/pre\>/ms', function ($matches) {
    return '<pre>' . strip_tags($matches[1]) . '</pre>';
  }, $message);

  // get truncated message
  $message_truncated = $message;
  $message_truncated_flag = funcs_common_truncate_string_linebreak($message_truncated, $board_cfg['truncate'], true);

  return [
    'board_id'            => $board_cfg['id'],
    'parent_id'           => isset($args['thread_id']) && is_numeric($args['thread_id']) ? $args['thread_id'] : 0,
    'name'                => strlen($params['name']) !== 0 ? funcs_common_clean_field($params['name']) : $board_cfg['anonymous'],
    'tripcode'            => null,
    'email'               => funcs_common_clean_field($params['email']),
    'subject'             => funcs_common_clean_field($params['subject']),
    'message'             => $params['message'],
    'message_rendered'    => $message,
    'message_truncated'   => $message_truncated_flag ? $message_truncated : null,
    'password'            => null,
    'file'                => $file['file'],
    'file_hex'            => $file['file_hex'],
    'file_original'       => $file['file_original'],
    'file_size'           => $file['file_size'],
    'file_size_formatted' => $file['file_size_formatted'],
    'image_width'         => $file['image_width'],
    'image_height'        => $file['image_height'],
    'thumb'               => $file['thumb'],
    'thumb_width'         => $file['thumb_width'],
    'thumb_height'        => $file['thumb_height'],
    'timestamp'           => time(),
    'bumped'              => time(),
    'ip'                  => funcs_common_get_client_remote_address(true, $_SERVER),
    'stickied'            => 0,
    'moderated'           => 1,
    'country_code'        => null
  ];
}
