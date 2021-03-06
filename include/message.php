<?php
	// send a private message
	



function send_message($recipient=0, $body='', $subject='', $replyto=''){ 
	$a = get_app();

	if(! $recipient) return -1;
	
	if(! strlen($subject))
		$subject = t('[no subject]');

	$me = q("SELECT * FROM `contact` WHERE `uid` = %d AND `self` = 1 LIMIT 1",
		intval(local_user())
	);
	$contact = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($recipient),
			intval(local_user())
	);

	if(! (count($me) && (count($contact)))) {
		return -2;
	}

	$hash = random_string();
 	$uri = 'urn:X-dfrn:' . $a->get_baseurl() . ':' . local_user() . ':' . $hash ;

	if(! strlen($replyto))
		$replyto = $uri;

	$r = q("INSERT INTO `mail` ( `uid`, `from-name`, `from-photo`, `from-url`, 
		`contact-id`, `title`, `body`, `seen`, `replied`, `uri`, `parent-uri`, `created`)
		VALUES ( %d, '%s', '%s', '%s', %d, '%s', '%s', %d, %d, '%s', '%s', '%s' )",
		intval(local_user()),
		dbesc($me[0]['name']),
		dbesc($me[0]['thumb']),
		dbesc($me[0]['url']),
		intval($recipient),
		dbesc($subject),
		dbesc($body),
		1,
		0,
		dbesc($uri),
		dbesc($replyto),
		datetime_convert()
	);
	$r = q("SELECT * FROM `mail` WHERE `uri` = '%s' and `uid` = %d LIMIT 1",
		dbesc($uri),
		intval(local_user())
	);
	if(count($r))
		$post_id = $r[0]['id'];

	/**
	 *
	 * When a photo was uploaded into the message using the (profile wall) ajax 
	 * uploader, The permissions are initially set to disallow anybody but the
	 * owner from seeing it. This is because the permissions may not yet have been
	 * set for the post. If it's private, the photo permissions should be set
	 * appropriately. But we didn't know the final permissions on the post until
	 * now. So now we'll look for links of uploaded messages that are in the
	 * post and set them to the same permissions as the post itself.
	 *
	 */

	$match = null;

	if(preg_match_all("/\[img\](.*?)\[\/img\]/",$body,$match)) {
		$images = $match[1];
		if(count($images)) {
			foreach($images as $image) {
				if(! stristr($image,$a->get_baseurl() . '/photo/'))
					continue;
				$image_uri = substr($image,strrpos($image,'/') + 1);
				$image_uri = substr($image_uri,0, strpos($image_uri,'-'));
				$r = q("UPDATE `photo` SET `allow_cid` = '%s'
					WHERE `resource-id` = '%s' AND `album` = '%s' AND `uid` = %d ",
					dbesc('<' . $recipient . '>'),
					dbesc($image_uri),
					dbesc( t('Wall Photos')),
					intval(local_user())
				); 
			}
		}
	}
	
	if($post_id) {
		proc_run('php',"include/notifier.php","mail","$post_id");
		return intval($post_id);
	} else {
		return -3;
	}

}
