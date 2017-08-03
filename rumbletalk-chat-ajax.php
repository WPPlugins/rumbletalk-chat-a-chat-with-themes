<?php

use RumbleTalk\RumbleTalkSDK;

class RumbleTalkChatAjax
{
    private $accessToken;
    private $renewalNeeded = true;

    public function __construct()
    {
        $this->accessToken = get_option('rumbletalk_accesstoken');
        
        if ($this->accessToken) {
            $expiration = RumbleTalkSDK::getTokenExpiration($this->accessToken);
            $this->renewalNeeded = RumbleTalkSDK::renewalNeeded($expiration);
        }
    }
    
    private function retrieveAccessToken($rumbletalk, $forceNewToken = false)
    {
        if ($this->renewalNeeded || $forceNewToken) {
            $this->accessToken = $rumbletalk->fetchAccessToken();
            update_option('rumbletalk_accesstoken', $this->accessToken);
            $this->renewalNeeded = false;
        } else {
            $rumbletalk->setAccessToken($this->accessToken);
        }
        
        return $this->accessToken;
    }

    public function processAjaxRequest($method, $args) {
    	if ($method == 'apply_new_token')
    		$this->applyNewToken($args);
    	else if ($method == 'get_access_token')
    		$this->getAccessToken($args);
    	else if ($method == 'create_new_chatroom')
    		$this->createNewChatRoom($args);
    	else if ($method == 'update_chatrooms')
    		$this->updateChatRooms($args);
    	else if ($method == 'delete_chatroom')
    		$this->deleteChatRoom($args);
    	else if ($method == 'update_chat')
    		$this->updateChat($args);
    }

    private function validateTokenArgs($args) {
    	$valid = isset($args['key']) && $args['key'] && isset($args['secret']) && $args['secret'];
    	if (!$valid) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = "Please provide token";
    		die(json_encode((array)$error));
    	}
    }

    private function updateOptions(&$options, $hashes) {
		foreach ($hashes as $hash) {
			if (!isset($options[$hash]))
				$options[$hash] = array();
			if (!isset($options[$hash]['width']))
				$options[$hash]['width'] = '';
			if (!isset($options[$hash]['height']))
				$options[$hash]['height'] = '';
			if (!isset($options[$hash]['floating']))
				$options[$hash]['floating'] = false;
			if (!isset($options[$hash]['membersOnly']))
				$options[$hash]['membersOnly'] = false;
		}
    }

    public function applyNewToken($args) {
    	$this->validateTokenArgs($args);

		# Initialize key and secret with default values
		$appKey = $args['key'];
		$appSecret = $args['secret'];

		# create the RumbleTalk SDK instance using the key and secret
		$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

		try {
			# fetch (and set) the access token for the account (tokens lasts for 30 days)
			$this->retrieveAccessToken($rumbletalk, true);
            update_option('rumbletalk_chat_token_key', $appKey);
            update_option('rumbletalk_chat_token_secret', $appSecret);
		} catch(Exception $e) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = $e->getMessage();
    		die(json_encode((array)$error));
		}

		$result = $rumbletalk->get('chats');

		if ($result['status']) {
			# Get hash of the first chat for the account
			$chats = $result['data'];
			$hash = $chats[0]['hash'];
			$result = array('status' => true, 'hash' => $hash);
			
			$hashes = implode(',', array_map(create_function('$chat', 'return $chat["hash"];'), $chats));
			$names = implode(',', array_map(create_function('$chat', 'return $chat["name"];'), $chats));
			# Update chats
    		update_option('rumbletalk_chat_hashes', $hashes);
    		update_option('rumbletalk_chat_names', $names);

			# Update option
			update_option('rumbletalk_chat_code', $hash);

			die(json_encode((array)$result));
		}
		else {
			$error = new stdClass();
			$error->status = false;
			$error->message = "Cannot retrieve chat";
			$error->details = $result;
			die(json_encode((array)$error));
		}
	}

    public function getAccessToken($args) {
    	$this->validateTokenArgs($args);

		# Initialize key and secret with default values
		$appKey = $args['key'];
		$appSecret = $args['secret'];

		# create the RumbleTalk SDK instance using the key and secret
		$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

		try {
			# fetch (and set) the access token for the account (tokens lasts for 30 days)
			$accessToken = $this->retrieveAccessToken($rumbletalk);
		}
		catch(Exception $e) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = $e->getMessage();
    		die(json_encode((array)$error));
		}

		$result = array('status' => true, 'accessToken' => $accessToken);
		die(json_encode((array)$result));
	}

    public function createNewChatRoom($args) {
    	$this->validateTokenArgs($args);

		# Initialize key and secret with default values
		$appKey = $args['key'];
		$appSecret = $args['secret'];
		$chatName = $args['chatName'];

		# create the RumbleTalk SDK instance using the key and secret
		$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

		try {
			# fetch (and set) the access token for the account (tokens lasts for 30 days)
			$this->retrieveAccessToken($rumbletalk);
			$result = $rumbletalk->post('chats', array('name' => $chatName));

			if (!$result['status']) {
				$error = new stdClass();
				$error->status = false;
				$error->message = "Please upgrade your account to create more chat rooms";
				$error->details = $result;
				die(json_encode((array)$error));
			}
		}
		catch(Exception $e) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = $e->getMessage();
    		die(json_encode((array)$error));
		}

		$hash = $result['hash'];
		$id = $result['chatId'];
		$names = explode(',', get_option('rumbletalk_chat_names'));
		$hashes = explode(',', get_option('rumbletalk_chat_hashes'));
		$ids = explode(',', get_option('rumbletalk_chat_ids'));

		$names[] = $chatName;
		$hashes[] = $hash;
		$ids[] = $id;

		update_option('rumbletalk_chat_hashes', implode(',', $hashes));
		update_option('rumbletalk_chat_names', implode(',', $names));
		update_option('rumbletalk_chat_ids', implode(',', $ids));
		update_option('rumbletalk_chat_code', $hash);

		// set default options for this chat
		$options = json_decode(get_option('rumbletalk_chat_chats'), true);
		$this->updateOptions($options, $hashes);
		update_option('rumbletalk_chat_chats', json_encode($options));

		$result = array('status' => true, 'hash' => $hash, 'id' => $id, 'options' => json_encode($options[$hash]));
		die(json_encode((array)$result));
	}

    public function updateChatRooms($args) {
    	$this->validateTokenArgs($args);

		# Initialize key and secret with default values
		$appKey = $args['key'];
		$appSecret = $args['secret'];

		# create the RumbleTalk SDK instance using the key and secret
		$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

		try {
			# fetch (and set) the access token for the account (tokens lasts for 30 days)
			$this->retrieveAccessToken($rumbletalk);
		}
		catch(Exception $e) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = $e->getMessage();
    		die(json_encode((array)$error));
		}

		$result = $rumbletalk->get('chats');

		if ($result['status']) {
			# Get hash of the first chat for the account
			$chats = $result['data'];

			$hashes = implode(',', array_map(create_function('$chat', 'return $chat["hash"];'), $chats));
			$names = implode(',', array_map(create_function('$chat', 'return $chat["name"];'), $chats));
			$ids = implode(',', array_map(create_function('$chat', 'return $chat["id"];'), $chats));
			# Update chats
    		update_option('rumbletalk_chat_hashes', $hashes);
    		update_option('rumbletalk_chat_names', $names);
    		update_option('rumbletalk_chat_ids', $ids);

    		$options = json_decode(get_option('rumbletalk_chat_chats'), true);
    		$this->updateOptions($options, explode(',', $hashes));
    		update_option('rumbletalk_chat_chats', json_encode($options));

			$result = array(
                'status' => true,
                'hashes' => $hashes,
                'names' => $names,
                'ids' => $ids,
                'options' => json_encode($options)
            );
			die(json_encode((array)$result));
		}
		else {
			$error = new stdClass();
			$error->status = false;
			$error->message = "Cannot retrieve chats, please try again in a few seconds";
			$error->details = $result;
			die(json_encode((array)$error));
		}
    }

    public function deleteChatRoom($args) {
    	$this->validateTokenArgs($args);

		# Initialize key and secret with default values
		$appKey = $args['key'];
		$appSecret = $args['secret'];
		$id = $args['id'];

		# create the RumbleTalk SDK instance using the key and secret
		$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

		try {
			# fetch (and set) the access token for the account (tokens lasts for 30 days)
			$this->retrieveAccessToken($rumbletalk);
		}
		catch(Exception $e) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = $e->getMessage();
    		die(json_encode((array)$error));
		}

		$result = $rumbletalk->delete('chats/' . $id);

		if (!$result['status']) {
			$error = new stdClass();
			$error->status = false;
			$error->message = "Cannot delete chat";
			$error->details = $result;
			die(json_encode((array)$error));
		}

		$names = explode(',', get_option('rumbletalk_chat_names'));
		$hashes = explode(',', get_option('rumbletalk_chat_hashes'));
		$ids = explode(',', get_option('rumbletalk_chat_ids'));
		$i = array_search($id, $ids);
		if ($i !== false) {
			unset($names[$i]);
			unset($hashes[$i]);
			unset($ids[$i]);
		}
		update_option('rumbletalk_chat_hashes', implode(',', $hashes));
		update_option('rumbletalk_chat_names', implode(',', $names));
		update_option('rumbletalk_chat_ids', implode(',', $ids));

		$result = array('status' => true);
		die(json_encode((array)$result));
	}
    
    public function updateChat($args)
    {
        $this->validateTokenArgs($args);

		# Initialize key and secret with default values
		$appKey = $args['key'];
		$appSecret = $args['secret'];
		$id = $args['id'];

		# create the RumbleTalk SDK instance using the key and secret
		$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

		try {
			# fetch (and set) the access token for the account (tokens lasts for 30 days)
			$this->retrieveAccessToken($rumbletalk);
		}
		catch(Exception $e) {
    		$error = new stdClass();
    		$error->status = false;
    		$error->message = $e->getMessage();
    		die(json_encode((array)$error));
		}
		
		$postData = array('forceSDKLogin' => !!$args['membersOnly']);
		if ($postData['forceSDKLogin']) {
			$postData['allowListeners'] = false;
			$postData['autoInvite'] = false;
			$postData['inviteFriends'] = false;
		}

		$result = $rumbletalk->put("chats/{$id}", $postData);

		if (!$result['status']) {
			$error = new stdClass();
			$error->status = false;
			$error->message = "Cannot update the chat";
			$error->details = $result;
			die(json_encode((array)$error));
		}

		$result = array('status' => true);
		die(json_encode((array)$result));
    }
}

?>