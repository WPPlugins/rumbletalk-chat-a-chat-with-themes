<?php
/*
  Plugin Name: RumbleTalk Chat
  Plugin URI: https://wordpress.org/plugins/rumbletalk-chat-a-chat-with-themes/
  Description: Group chat room for wordpress and budypress websites. Use one or many advanced stylish chat rooms for your community.
  Tags: buddypress
  Version: 5.2.9
  Author: RumbleTalk Ltd
  Author URI: https://www.rumbletalk.com
  License: GPL2

  Copyright 2012-2017 RumbleTalk Ltd (email : support@rumbletalk.com)

  This program is free trial software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

# Include RumbleTalk PHP SDK
require 'rumbletalk-sdk.php';
use RumbleTalk\RumbleTalkSDK;

require 'rumbletalk-chat-ajax.php';

class RumbleTalkChat {

    protected $options;
    protected $cdn = 'https://d1pfint8izqszg.cloudfront.net/';

    public function __construct()
    {
        $this->options = array(
            'rumbletalk_chat_code',
            'rumbletalk_chat_names',
            'rumbletalk_chat_hashes',
            'rumbletalk_chat_ids',
            'rumbletalk_chat_chats', // [{hash: {width, height, floating, membersOnly}}, ...] as JSON string
            'rumbletalk_chat_width', // deprecated
            'rumbletalk_chat_height', // deprecated
            'rumbletalk_chat_floating', // deprecated
			'rumbletalk_chat_member', // deprecated
    		'rumbletalk_chat_token_key',
    		'rumbletalk_chat_token_secret',
    		'rumbletalk_chat_chatId',
        );

        register_activation_hook(__FILE__, array(&$this, 'install'));
        register_deactivation_hook(__FILE__, array(&$this, 'unInstall'));

        if (is_admin()) {
			add_thickbox();
            add_action('admin_menu', array(&$this, 'adminMenu'));
            add_action('admin_init', array(&$this, 'adminInit'));
			add_action('wp_ajax_rumbletalk_apply_new_token', array(&$this, 'ajaxApplyNewTokenCallback'));
			add_action('wp_ajax_rumbletalk_get_access_token', array(&$this, 'ajaxGetAccessTokenCallback'));
			add_action('wp_ajax_rumbletalk_create_new_chatroom', array(&$this, 'ajaxCreateNewChatRoomCallback'));
			add_action('wp_ajax_rumbletalk_update_chatrooms', array(&$this, 'ajaxUpdateChatRooms'));
			add_action('wp_ajax_rumbletalk_select_chatroom', array(&$this, 'ajaxSelectChatRoom'));
			add_action('wp_ajax_rumbletalk_delete_chatroom', array(&$this, 'ajaxDeleteChatRoom'));
			add_action('wp_ajax_rumbletalk_update_chatroom_options', array(&$this, 'ajaxUpdateChatRoomOptions'));
        } else {
            add_shortcode('rumbletalk-chat', array(&$this, 'embed'));
			add_action('wp_head', array(&$this, 'hook_javascript'));
        }
    }

	public function adminInit() {
		if (current_user_can( 'edit_posts' ) && current_user_can('edit_pages')) {
			add_filter('mce_buttons', array(&$this, 'registerTinyMceButton'));
			add_filter('mce_external_plugins', array(&$this, 'addTinyMceButton'));
		}
	}

	public function registerTinyMceButton($buttons) {
		//array_push($buttons, "button_rumbletalk_chat", "button_rumbletalk_chat2");
		array_push($buttons, "button_rumbletalk_chat2");
		return $buttons;
	}

	public function addTinyMceButton($plugin_array) {
		$plugin_array['rumbletalk_mce_buttons'] = plugins_url('/add-mce-buttons.js', __FILE__ ) ;
		return $plugin_array;
	}

    /**
     * add the Login SDK script to the <head>
     */
    public function hook_javascript() {
        $code = get_option('rumbletalk_chat_code');
        $current_user = wp_get_current_user();
     
        if( !empty($code)&& !empty($current_user->display_name)) {
            ?>
<script type="text/javascript">
(function(g,v,w,d,s,a,b){w['rumbleTalkMessageQueueName']=g;w[g]=w[g]||
function(){(w[g].q=w[g].q||[]).push(arguments)};a=d.createElement(s);
b=d.getElementsByTagName(s)[0];a.async=1;
a.src='<?= $this->cdn ?>api/'+v+'/sdk.js';
b.parentNode.insertBefore(a,b);})('rtmq','v0.34',window,document,'script');
</script>
            <?php
        }
    }

    public function adminMenu() {
        add_options_page(
            'RumbleTalk Chat',
            'RumbleTalk Chat',
            'administrator',
            'rumbletalk-chat',
            array(&$this, 'drawAdminPage')
        );
    }

	public function ajaxApplyNewTokenCallback() {
		$rumbleTalkChatAjax = new RumbleTalkChatAjax();
		$rumbleTalkChatAjax->processAjaxRequest('apply_new_token', $_POST);
	}

	public function ajaxGetAccessTokenCallback() {
		$rumbleTalkChatAjax = new RumbleTalkChatAjax();
		$rumbleTalkChatAjax->processAjaxRequest('get_access_token', $_POST);
	}

	public function ajaxCreateNewChatRoomCallback() {
		$rumbleTalkChatAjax = new RumbleTalkChatAjax();
		$rumbleTalkChatAjax->processAjaxRequest('create_new_chatroom', $_POST);
	}

	public function ajaxUpdateChatRooms() {
		$rumbleTalkChatAjax = new RumbleTalkChatAjax();
		$rumbleTalkChatAjax->processAjaxRequest('update_chatrooms', $_POST);
	}

	public function ajaxSelectChatRoom() {
		update_option('rumbletalk_chat_code', $_POST['hash']);
		$result = array('status' => true);
		die(json_encode((array)$result));
	}

	public function ajaxDeleteChatRoom() {
		$rumbleTalkChatAjax = new RumbleTalkChatAjax();
		$rumbleTalkChatAjax->processAjaxRequest('delete_chatroom', $_POST);
	}

	public function ajaxUpdateChatRoomOptions() {
		$chatHash = $_POST['hash'];
		$options = $_POST['options'];

		$currentOptions = array();
		$chats = json_decode(get_option('rumbletalk_chat_chats'), true);
		if (!isset($chats[$chatHash])) {
			$chats[$chatHash] = array();
		}
		foreach ($options as $key => &$value) {
			if ('false' == $value)
				$value = false;
			else if ('true' == $value)
				$value = true;
			$chats[$chatHash][$key] = $value;
		}
		update_option('rumbletalk_chat_chats', json_encode($chats));
        
        if (isset($options['membersOnly'])) {
            $_POST['membersOnly'] = $options['membersOnly'];
            $rumbleTalkChatAjax = new RumbleTalkChatAjax();
            $rumbleTalkChatAjax->processAjaxRequest('update_chat', $_POST);
        }
        
		die('ok');
	}

    public function embed($attr = null) {
    	$hash = isset($attr["hash"]) ? $attr["hash"] : null;
    	if (!$hash) {
    		$hash = explode(',', get_option('rumbletalk_chat_hashes'));
    		if ($hash)
    			$hash = $hash[0];
    	}
    	if (empty($hash))
    		return '';

    	# default options
    	$chatOptions = array(
	    	'height' => '',
	    	'width' => '',
	    	'floating' => false,
	    	'membersOnly' => false
    	);

    	$options = json_decode(get_option('rumbletalk_chat_chats'), true);
    	if (isset($options[$hash])) {
			$chatOptions = $options[$hash];
    	} elseif (get_option('rumbletalk_chat_member')) {
            $chatOptions['membersOnly'] = true;
        }

        $isw = ( preg_match('/^\d{1,4}%?$/', $chatOptions['width']) == 1 );
        
        if (preg_match('/^\d{1,4}%?$/', $chatOptions['height']) != 1) {
            $chatOptions['height'] = '500';
        }
        
        $style = "height: {$chatOptions['height']}px;" . ($isw ? " max-width: {$chatOptions['width']}px;" : '');
        
        $str = '<div style="' . $style . '">';

        if ($chatOptions['membersOnly'] && (!$attr || !$attr['display_only'])) {
            $current_user = wp_get_current_user();
            if ($current_user->display_name) {
                $loginInfo = array(
                    'username' => $current_user->display_name,
                    'hash' => $hash
                );
            ?>
            <script type="text/javascript">rtmq('login', <?= json_encode($loginInfo) ?>);</script>
            <?php
            }
        }

        $code = $hash;
        if (!empty($chatOptions['floating'])) {
            $code .= '&1';
        }
        
        $divId = 'rt-' . md5($code);
        $str .= '<div id="' . $divId . '"></div>';
        $url = "https://www.rumbletalk.com/client/?" . $code;
        $str .= '<script type="text/javascript" src="'. $url . '"></script>';
        $str .= '</div>';

        return $str;
    }

    public function install() {
        foreach ($this->options as $opt) {
            add_option($opt);
        }
    }

    public function unInstall() {
        foreach ($this->options as $opt) {
            delete_option($opt);
        }
    }

    public function drawAdminPage() {
    	$showCreateAccountForm = false;
    	$createAccountError = "";
    	$createAccountNotes = "";
    	if ($_REQUEST['account_creation_submitted']) {
    		# Initialize key and secret with default values
			$appKey = 'key';
			$appSecret = 'secret';

			# create the RumbleTalk SDK instance using the key and secret
			$rumbletalk = new RumbleTalkSDK($appKey, $appSecret);

			$email = $_REQUEST['email'];
			$password = strval($_REQUEST['password']);
			$data = array(
			   'email' => $email,
			   'password' => $password,
               'referrer' => 'WordPress'
			);
			$result = $rumbletalk->createAccount($data);
    		if (!$result['status']) {
    			$showCreateAccountForm = true;
    			$createAccountError = $result['message'];
    			if ($result['message'] == 'Email address already in use') {
    				$createAccountNotes = 'Please retrieve your token key and secret ';
                    $createAccountNotes .= '<a href="https://www.rumbletalk.com/admin/" target="_blank">here</a>.';
    			}
    		} else {
    			update_option('rumbletalk_chat_token_key', $result['token']['key']);
    			update_option('rumbletalk_chat_token_secret', $result['token']['secret']);
    			update_option('rumbletalk_chat_chatId', $result['chatId']);
    			update_option('rumbletalk_chat_code', $result['hash']);
    			update_option('rumbletalk_chat_hashes', $result['hash']);
    			update_option('rumbletalk_chat_ids', $result['id']);
    			update_option('rumbletalk_chat_names', 'New Chat');
				delete_option('rumbletalk_accesstoken');
    		}
    	}

    	# upgrade from previous versions
    	if (
            !get_option('rumbletalk_chat_chats') &&
            (get_option('rumbletalk_chat_width', null) !== null) &&
            (get_option('rumbletalk_chat_height', null) !== null) &&
            (get_option('rumbletalk_chat_floating', null) !== null) &&
            (get_option('rumbletalk_chat_member', null) !== null)
        ) {
    		$hash = get_option('rumbletalk_chat_code');
    		$options = array(
    			$hash => array(
    				'width' => get_option('rumbletalk_chat_width'),
    				'height' => get_option('rumbletalk_chat_height'),
    				'floating' => get_option('rumbletalk_chat_floating') ? true : false,
    				'membersOnly' => get_option('rumbletalk_chat_member') ? true : false,
    			));
			update_option('rumbletalk_chat_chats', json_encode($options));
			delete_option('rumbletalk_chat_width');
			delete_option('rumbletalk_chat_height');
			delete_option('rumbletalk_chat_floating');
			delete_option('rumbletalk_chat_member');
    	}
        
        ?>
    <style>
    .upgrade_button {
        display:inline-block;
        border-radius: 3px;

        background-color: #da2424;
        text-decoration: none;
        color: #fff;
        font: bold 15px arial;
        font-weight: 700;
        margin-left: 0px;
        padding: 7px;
    }
        .upgrade_button:hover {
            background-color: #b31414;
            color: #fff;
        }
    
    .anchor {
        cursor: pointer;
        text-decoration: underline;
        border: none;
        background: transparent;
        color: #0073aa;
    }
        .anchor:hover {
            text-decoration: none;
            color: #00a0d2;
        }
        .anchor:focus { outline: none; }

    #TB_ajaxContent {
        overflow-y: hidden !important;
        position: relative;
    }
    #TB_window { display: none !important; }
    #TB_window.visibleImportant { display: block !important; }
    
    .modal-buttons {
        position: absolute;
        bottom: 20px;
        width: 90%;
        text-align: center;
    }
    .modal-buttons button { margin: 0 10px; }

    #chatrooms_refresh {
        background-image: url('<?php echo plugins_url('ico-refresh.png', __FILE__); ?>');
        background-position: center center;
        width: 29px;
        height: 29px;
        margin: 0 10px;
        background-repeat: no-repeat;
        cursor: pointer;
        display: inline-block;
        vertical-align: middle;     		
    }
    #update_chatrooms_loading {
        display: none;
        width: 16px;
        height: 16px;
        margin: -4px 10px;
    }
    
    #delete_chatroom_loading {
        width: 20px;
        height: 20px;
        margin-left: 30px;
        margin-bottom: -5px;
    }
    
    #update_chatroom_loading {
        width: 20px;
        height: 20px;
        margin-left: 140px;
    }
    
    #create_new_chatroom_loading {
        width: 20px;
        height: 20px;
        margin-left: 40px;
    }
    </style>
    <div id="fb-root"></div>
    <div id="modal-window-error" style="display:none;">
        <p>.</p>
    </div>						
    <div id="modal-window-confirmation" style="display:none;">
        <p>.</p>
    </div>						
    <div id="modal-window-prompt" style="display:none;">
        <p>.</p>
    </div>						
    <div style="width:820px;">
        <h2>RumbleTalk Chat Options</h2>
        <table>
            <tr>
                <td width="500" valign="top">
                    <div style="width:500px;position;relative;">
                        <form method="post" action="<?= admin_url('options-general.php?page=rumbletalk-chat') ?>"
                              onsubmit="return validateAccountCreation( this );" id="createFormReference"
                              <?= (get_option("rumbletalk_chat_hashes") == '' || $showCreateAccountForm) ? '' : ' style="display:none;"' ?>>
                            <input type="hidden" name="account_creation_submitted" value="1" />
                            <table valign="top">
                                <tr>
                                    <td colspan="2" align="left" style="padding-bottom:30px;"><img width="490" src="<?= $this->cdn ?>emails/Mailxa-01.png" /></td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-bottom:15px;">
                                        <button type="button" class="anchor" onclick="toggleCreateAccount(1);">I already have an account</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-bottom:15px;">
                                        Add RumbleTalk chat-room to your community or live event in one minute.<br/><br/>
                                        1 - Enter your email address and choose a password.<br/>
                                        2 - Click on the create button. It takes up to 20 seconds for the automated account creation.<br/>
                                        3 - Now, add the exact text <b style="font:arial 8px none; color:#68A500">[rumbletalk-chat]</b>
										to your visual editor where you want your chat to show.
                                    </td>
                                </tr>
                                <?php if ($createAccountError): ?>
                                <tr>
                                    <td colspan="2">
                                        <br/>
                                        <span style="color:red;font-weight:bold;">Info: <?= $createAccountError ?></span>
                                    </td>
                                </tr>
                                <?php endif ?>
                                <?php if ($createAccountNotes): ?>
                                <tr>
                                    <td colspan="2">
                                        <span><?= $createAccountNotes ?></span>
                                        <br/><br/>
                                    </td>
                                </tr>
                                <?php endif ?>
                                <tr>
                                    <td width="20"><b>Email:</b></td>
                                    <td width="60"><input type="text" name="email" /></td>
                                </tr>
                                <tr>
                                    <td width="20"><b>Password:</b></td>
                                    <td width="60"><input type="password" name="password" /></td>
                                </tr>
                                <tr>
                                    <td width="20"><b>Confirm Password:</b></td>
                                    <td width="60"><input type="password" name="password_c" /></td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input id="create_chat_button" type="submit" value="Create a Chatroom" />
                                        <img id="loading_gif" style="display:none;" src="<?= $this->cdn ?>images/mainpage/loading.gif" alt="loading" />
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                    <br/>
                                    <?php if ( get_option( 'rumbletalk_chat_code' ) != '' ) { ?>
                                        <span style="color:red;font-weight:bold;">Note! your current chat will be deleted if you enter a new email and password.</span>
                                    <?php } ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" align="left"  style="padding-top:30px;"><img width="490" src="<?= $this->cdn ?>emails/Mailxa-04.png" /></td>
                                </tr>
                            </table>
                        </form>
                        <form method="post" action="options.php" class="chatOptionsReference"<?= (get_option("rumbletalk_chat_code") == '' || $showCreateAccountForm) ? ' style="display:none;"' : '' ?>>
                            <input type="hidden" name="action" value="update"/>
                            <input type="hidden" name="page_options" value="rumbletalk_chat_token_key,rumbletalk_chat_token_secret"/>
                            <?php
                                wp_nonce_field("update-options");
                                $hideToken = get_option('rumbletalk_chat_token_key') && get_option('rumbletalk_chat_token_secret') && get_option("rumbletalk_chat_code");
                            ?>
                            <table valign="top">
                                <tr>
                                    <td colspan="2" align="left"  style="padding-bottom:30px;"><img width="490" src="<?= $this->cdn ?>emails/Mailxa-01.png" /></td>
                                </tr>
                                <tr>
                                    <td width="200" align="left">
                                        <button type="button" class="anchor" onclick="toggleCreateAccount();">Create a new account</button>
                                    </td>
                                    <td width="180"  align="left">
                                        <button type="button" class="anchor" id="display_token"><?= $hideToken ? 'Token is set: change' : 'Hide' ?> token</button>
                                    </td>										
                                </tr>
                                
                                <tr class="tokens_row"<?= $hideToken ? ' style="display: none"' : '' ?>>
                                    <td colspan="2" style="padding-bottom:15px;">
                                     <span style="font:arial 8px none; color:#AAACAD">
                                      Token means a two text keys that are unique to your chat room account. Keys are created automatically when one creates his first chat.<br><br>
                                      In case you wish to change\update your keys, then go to your <a href="https://www.rumbletalk.com/admin/groups.php" target="_blank">admin panel</a> and find it under the "<a href="https://rumbletalk-images-upload.s3.amazonaws.com/cbc4b58e0cc1741689eb1d8c80959989/1474448961-keys-location.png" target="_blank">Account</a>" info.
                                     </span>
                                    </td>                                    
                                </tr>									
                                <tr class="tokens_row"<?= $hideToken ? ' style="display: none"' : '' ?>>
                                    <td colspan="2">
                                        <b>Token Key:</b>
                                        <input type="text" name="rumbletalk_chat_token_key" id="rumbletalk_chat_token_key" style="width: 300px; float: right;"
                                            value="<?= htmlspecialchars(get_option("rumbletalk_chat_token_key")) ?>" maxlength="32">
                                    </td>
                                </tr>
                                <tr class="tokens_row"<?= $hideToken ? ' style="display: none"' : '' ?>>
                                    <td colspan="2">
                                        <b>Token Secret:</b>
                                        <input type="text" name="rumbletalk_chat_token_secret" id="rumbletalk_chat_token_secret" style="width: 300px; float: right;"
                                            value="<?= htmlspecialchars(get_option("rumbletalk_chat_token_secret")) ?>" maxlength="64">
                                    </td>
                                </tr>
                                <tr class="tokens_row"<?= $hideToken ? ' style="display: none"' : '' ?>>
                                    <td width="180" colspan="2">
                                        <button id="update-chatroom" style="width: 300px">Update chatroom with new Token</button>
                                        <br><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-bottom:5px;padding-top:20px;">
                                       <table width="100%">
                                         <tr>
                                           <td align="left"  style="padding-left:10px;">
                                               <img width="95px" src="<?= $this->cdn ?>blog/floatembed/180x120-01.jpg">
                                           </td>
                                           <td align="left" style="padding-top:10px;padding-left:20px;">
                                               <img width="95px" src="<?= $this->cdn ?>blog/floatembed/180x120-02.jpg">
                                           </td>
                                         </tr>
                                         <tr>
                                           <td align="left" style="padding-left:20px;">Chat in a page</td>
                                           <td align="left" style="padding-left:10px;">Floating chat (toolbar)</td>
                                         </tr>
                                       </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <table id="selected_rumbletalk_chat_code" style="margin: 20px 0;">
                                            <thead>
                                                <tr>
                                                   <th>Chat HASH</th>
                                                   <th>Width</th>
                                                   <th></th>
                                                   <th>Height</th>
                                                   <th>Floating</th>
                                                   <th>Members</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top:20px;">
                                        <button type="submit" title="save changes when you add rooms or change chat dimention"><?php _e("Save Changes") ?></button>
                                        <button type="button" id="create_new_chatroom" title="created a new chat room">Add New Chat Room</button>
                                        <span id="chatrooms_refresh" title="Refresh data"></span>
                                        <img id="update_chatrooms_loading" src="<?= plugins_url('rolling.gif', __FILE__); ?>" />
                                    </td>
                                </tr>										
                                <tr>
                                  <td colspan="2" style="padding-bottom:5px;padding-top:20px;">
                                      <table>
                                          <tr>
                                              <td  colspan="2" align="left" valign="top">
                                                  <b><u>How to set your chat?</u></b>
                                              </td>
                                          </tr>
                                          <tr>
                                              <td align="left" valign="top" style="padding-top:15px;">
                                                  <img  width="32px" src="<?= $this->cdn ?>admin/images/SQ-about.png" />
                                              </td>
                                              <td style="padding-left:5px;">
                                                  Add the exact text
                                                  <b style="font:arial 8px none; color:#68A500">&#91;rumbletalk-chat&#93;</b>
                                                  <br/>
                                                  to your visual editor where you want your chat to show.....and you are done.
                                              </td>
                                          </tr>
                                          <tr>
                                              <td style="padding-top:15px;" align="left" valign="top">
                                                  <img width="32px" src="<?= $this->cdn ?>admin/images/SQ-contact.png" />
                                              </td>
                                              <td style="padding-left:5px;">
                                                  In case you have more than one chat, you can add the text with an exact chat HASH <b style="font:arial 8px none; color:#68A500">&#91;rumbletalk-chat hash="insert here your chat hash"&#93;</b>
                                              </td>
                                          </tr>
                                     </table>
                                   </td>
                                  </tr>
                
                                <tr>
                                    <td></td>
                                    <td>
                                        <span style="font:arial 8px none; color:#AAACAD">
                                            
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top:10px;">
                                        <ul class="ul-disc">
                                            <li><b>Chatroom Hash</b>: This is a unique 8 characters chat room code. It is populated automatically once you register with RumbleTalk.</li>
                                            <li><b>Chatroom width</b>: The width in pixels of your chat room.<br/>
                                            You can use percentages (e.g. 40%) or leave blank.</li>
                                            <li><b>Chatroom height (size)</b>: The height of your chat room.<br/>
                                            You can use percentages (e.g. 40%) or leave blank.</li>
                                            <li><b>Floating</b>: A floating toolbar chat. it will appear on your right bottom corner (you can change it to left of the screen).</li>
                                            <li><b>Members</b>: Let members of your community automatically login to the chat with no need to supply user and password.
                                            If you wish to allow registered users and guests to automatically login the chat. You should uncheck the<a href="https://www.rumbletalk.com/support/API_Auto_Login/" target="_blank">"Force SDK" checkbox</a>. 
                                            </li>
                                            <li><a href="https://www.rumbletalk.com/about_us/contact_us/" target="_blank"> CONTACT US</a> for any question, we are friendly !!!</li>
                                            
                                        </ul>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2" style="padding-top:20px;"><span style="font:arial 8px none; color:green">&#42; In some wordpress themes, there are two known issues, please <br/>see below the way to handle it.</span></td>
                                </tr>
                                <tr>
                                    <td colspan="2" align="left"  style="padding-top:30px;"><img width="490" src="<?= $this->cdn ?>emails/Mailxa-04.png" /></td>
                                </tr>
                            </table>
                        </form>
                    </div>

                    <div id="modal-chat-settings" style="display:none;">
                        <div id="modal-chat-settings-status"></div>
                        <form id="modalSettingsForm" target="settingsIframe" action="https://iframe.rumbletalk.com/wp/index.php" method="post" style="display: none">
                            <input type="text" name="token" value="" />
                            <input type="text" name="chat_hash" value="" />
                            <input type="submit" />
                        </form>
                        
                        <iFrame id="settingsIframe" src="" name="settingsIframe" style="width: 100%; height: 100%; border: 0;"></iFrame>
                    </div>
                </td>
                
                <td valign="top">
                    <style>
                    .rate-us { text-align: center; }
                    .anchor-img,
                    .anchor-img:hover { text-decoration: none; }
                    </style>
                    <div style="float:right; width:290px; border:1px #DEDEDD dashed; background-color:#FEFAE7; padding:10px 10px 10px 10px">
                        <div class="rate-us">
                            <a class="anchor-img" href="https://wordpress.org/support/view/plugin-reviews/rumbletalk-chat-a-chat-with-themes#postform">
                                <img src="<?= $this->cdn ?>blog/5stars.png" />
                            </a>
                            <br>
                            <a href="https://wordpress.org/support/view/plugin-reviews/rumbletalk-chat-a-chat-with-themes#postform">
                                Rate us
                            </a>
                        </div>
                        <br><br>
                        <b>Description:</b> The <a href="https://www.rumbletalk.com/?utm_source=wordpress&utm_medium=plugin&utm_campaign=fromplugin" target="_blank">RumbleTalk</a> Plugin is a boutique chat room Platform for websites, facebook pages and real-time events. Perfect for Communities, radios and live stream. It is available for all Wordpress installed versions.<br />
                        <br />

                        <br />
						
						<b>HOW TO ADD A CHAT TO YOUR WEBSITE?</b> 
						<iframe src="https://www.youtube.com/embed/r_qsn-2tZ5Y" frameborder="1" allowfullscreen></iframe>
                        <br />
                        <br />						
						<br />
						<b>HOW TO DELETE SINGLE OR ALL MESSAGES?</b> 
						<iframe src="https://www.youtube.com/embed/CBgK7MKZgKY" frameborder="1" allowfullscreen></iframe>
						<br />
                        <br />						
						<br />
						<b>HOW TO CHANGE THE CHAT DESIGN?</b> 						
						<iframe src="https://www.youtube.com/embed/u0l82eK04mc" frameborder="1" allowfullscreen></iframe>
						<br />
                        <br />						
						<br />
						<b>HOW TO EXPROT YOUR CHAT TRANSCRIPT?</b> 						
						<iframe src="https://www.youtube.com/embed/bKjauxUqlXc" frameborder="1" allowfullscreen></iframe>												
						
                        <br />
						<br />
						<br />
						For more information check our <a href="https://www.rumbletalk.com/faq/?utm_source=wordpress&utm_medium=plugin&utm_campaign=fromplugin" target="_blank">F.A.Q</a>
						
                    </div>
                 </td>
            </tr>
        </table>
        <table>
            <tr>
                <td width="500" valign="top">

                    <div class="chatOptionsReference"<?= get_option("rumbletalk_chat_code") == '' ? ' style="display:none;"' : '' ?>>
                        <div style="width:500px;">
							<table>
                                <tr>
                                    <td align="left" valign="top" width="20">
                                        <img  width="32px" src="<?= $this->cdn ?>admin/images/SQ-faq.png" />
                                    </td>
                                    <td style="padding-left:5px;">
                                        <span style="font:arial; font-size:14px;color:#73AC00">Troubleshooting</span> <br/>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-left:5px;padding-top:10px;">
                                        RumbleTalk chat room is elastic and can expand to any size. In some themes you might run into 2 possible issues.<br/>
                                        1 - The height is harder to adjust.<br/>
                                        2 - Some elements in the page are missing (not shown).<br/><br/>

                                        The solution: remove RumbleTalk plugin. Than get the full chat code (see below) via the admin panel. add the chatroom code below directly into the html of the page.<br/><br/>
                                        <span style="font:arial 8px none; color:green">&#42; Copy and paste the code below into your html, make sure you replaced the <b>chatcode HASH</b> with your own chatroom code.</span><br/><br/>

                                        <div>
                                            <code>
                                                <?= htmlspecialchars($this->embed(array('display_only' => true))) ?>
                                            </code>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div style="width:500px;">
                            <table style="padding-top:20px;">
                                <tr>
                                    <td align="left" valign="top" width="20">
                                        <img  width="32px" src="<?= $this->cdn ?>admin/images/SQ-faq.png" />
                                    </td>
                                    <td style="padding-left:5px;">
                                        <span style="font:arial; font-size:14px;color:#73AC00">Worpress Hosted</span> <br/>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-left:5px;padding-top:10px;">
                                        If your website is hosted by wordpress, you will not be able to use RumbleTalk :-(.
                                        <br>
                                        Reason: Wordpress prevent 3rd party widgets to be included in their hosted version.
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
                <td valign="top">
                    <div style="float:right; width:290px; border:1px #DEDEDD dashed; background-color:#FEFAE7; padding:10px 10px 10px 10px">
							<table style="padding-top:15px;padding-bottom:15px;">
								<tr>
									<td align="left" valign="top" style="padding-top:15px;">
									  <span style="font-size:15px;"><b>Get more with our <u>Premium</u> plans</b></span>
										<ul type="circle">
											<li>* Allow more seats in your chat </li>
											<li>* Create more chat rooms</li>
											<li>* Create private/public rooms </li>
											<li>* Live one on eone video/Audio calls </li>
											<li>* Share Docs, Excel, PowerPoint, PDF</li>
											<li>* Upload Images from your own PC</li>
											<li>* Take pictures from your PC camera</li>
											<li>* Integrate your users base (members)</li>
										</ul>		
									</td>
								</tr>
								<tr>
									<td align="center" valign="top" style="padding-top:15px;">
										 <a href="https://www.rumbletalk.com/upgrade/?hash=<?= $hash ?>" class="upgrade_button" target="_blank" title="Upgrade your account, create more rooms and get more chat seats">Upgrade your chat, Now!</a>
									</td>
								</tr>
							</table>           
							
                        With RumbleTalk you may create your own chat design (theme), share images and videos, talk from your mobile and even add the same chat installed on your website to your facebook page.
                        <br>
                        <br>
                        <a target="_blank" href="<?= $this->cdn ?>blog/dana1_ppt_godepression.png">
                            <img width="100" src="<?= $this->cdn ?>blog/dana1_ppt_godepression.png" />
                        </a>
                        <a target="_blank" href="<?= $this->cdn ?>images/donotuseyet.png">
                            <img width="100" src="<?= $this->cdn ?>images/donotuseyet.png" />
                        </a>
                        <br>
                        <a target="_blank" href="<?= $this->cdn ?>images/blog/DeleteMessages.png">
                            <img width="100" src="<?= $this->cdn ?>images/blog/DeleteMessages.png" />
                        </a>
                        <a target="_blank" href="<?= $this->cdn ?>images/blog/DeleteAllMessages2.png">
                            <img width="100" src="<?= $this->cdn ?>images/blog/DeleteAllMessages2.png" />
                        </a>
                        <br>
                        <br>
                        <b>Thanks:</b> Thank you for using RumbleTalk plugin. If you have any issues, suggestions or praises send us an email to support@rumbletalk.com
						<br />
						<br />
						<b>Like the plugin? "Like" RumbleTalk Chat!</b>
                        <div class="fb-like" data-href="https://www.facebook.com/rumbletalk" data-width="280" data-layout="standard" data-action="like" data-show-faces="true" data-share="true"></div>

                    </div>
                </td>
            </tr>
        </table>
    </div>
    <script type="text/javascript">
        var jQuery = jQuery || $;
        
        jQuery(function ($) {
            var rumbleTalkOptions = {},
                $keyInput = $('#rumbletalk_chat_token_key'),
                $secretInput = $('#rumbletalk_chat_token_secret');
            
            function buildModalButtonBox(text) {
                var $modalButtons = $('<div>').addClass('modal-buttons'),
                    $button = $('<button>');
                
                $button.text(text);
                $button.click(function(){
                    $('#TB_closeWindowButton').click();
                });
                $modalButtons.append($button);
                
                return $modalButtons;
            }

            function showErrorMessage(error) {
                var $p = $('<p>');
                $p.text(error);
                $('#modal-window-error').empty()
                    .append($p, buildModalButtonBox('Close'));
                
                tb_show('Information', '#TB_inline?width=300&height=250&inlineId=modal-window-error');
                
                setTimeout(
                    function() {
                        $('#TB_ajaxContent').css('height', '220px');
                        $('#TB_window').addClass('visibleImportant');
                    }, 
                    100
                );
            }

            function showModalPrompt(title, text, callback) {
                var $modalButtons = buildModalButtonBox('Cancel'),
                    $button = $('<button>');
                
                $button.click(function(){
                    var value = $('#modal-prompt-value').val();
                    if (typeof callback == 'function') {
                        callback(value);
                    }
                    $('#TB_closeWindowButton').click();
                }).text('Continue');
                $modalButtons.append($button);
                
                $('#modal-window-prompt').empty();
                $('#modal-window-prompt').append($('<p>'));
                $('#modal-window-prompt p').html(text + ' <input type="text" size="30" id="modal-prompt-value" />');
                $('#modal-window-prompt').append($modalButtons);
                
                tb_show(title, '#TB_inline?width=300&height=250&inlineId=modal-window-prompt');
                setTimeout(
                    function() {
                        $('#TB_ajaxContent').css('height', '120px');
                        $('#TB_window').addClass('visibleImportant');
                    }, 
                    100
                );
            }

            window.getErrorMessage = function (id) {
                var message;

                switch (parseInt(id)) {
                    case -1:
                        message = "Please enter a valid email address";
                        break;

                    case -2:
                        message = "The password must be at least 6 characters long (spaces are ignored!)";
                        break;

                    case -3:
                        message = "The email address already exists";
                        break;

                    case -7:
                        message = "Please retype the same password";
                        break;

                    case -11:
                        message = "The automatic creation has failed. Please create the account manually.";
                        message += "You can find more details in the 'Troubleshooting' section";
                        break;

                    default:
                        message = "Oops, could not complete the operation, please try again later";
                }

                return message;
            }

            window.toggleCreateAccount = function (which) {
                if (which == 1) {
                    $('#createFormReference').hide();
                    $('.chatOptionsReference').show();
                } else {
                    $('#createFormReference').show();
                    $('.chatOptionsReference').hide();
                }
            }

            window.validateAccountCreation = function (form) {
                var email = form.elements["email"],
                    password = form.elements["password"],
                    password_c = form.elements["password_c"];

                if (!(/^[-0-9A-Za-z!#$%&'*+\/=?^_`{|}~.]+@[-0-9A-Za-z!#$%&'*+\/=?^_`{|}~.]+/).test(email.value)) {
                    showErrorMessage(getErrorMessage(-1));
                    email.focus();
                    return false;
                }

                if (password.value.length < 6) {
                    showErrorMessage(getErrorMessage(-2));
                    password.focus();
                    return false;
                }

                if (password.value != password_c.value) {
                    showErrorMessage(getErrorMessage(-7));
                    password_c.focus();
                    return false;
                }

                document.getElementById("create_chat_button").style.display = "none";
                document.getElementById("loading_gif").style.display = "inline";

                return true;

            }

            function addChatRoomUI(hash, id, width, height, floating, membersOnly) {
                var $tr = $('<tr>'),
                    $element;
                
                $tr.data('id', id)
                    .addClass('chat-room-row');
                
                $element = $('<input>').attr({
                        type: 'text',
                        size: 7,
                        'class': 'chatHashReference'
                    })
                    .prop('readonly', true)
                    .val(hash);
                $tr.append($('<td>').append($element));
                
                $element = $('<input>').attr({
                        name: 'width',
                        type: 'text',
                        size: 2
                    })
                    .val(width)
                    .change(onOptionChange);
                $tr.append($('<td>').append($element), $('<td>').text('x'));
                
                $element = $('<input>').attr({
                        name: 'height',
                        type: 'text',
                        size: 2
                    })
                    .val(height)
                    .change(onOptionChange);
                $tr.append($('<td>').append($element));
                
                $element = $('<input>').attr({
                        name: 'floating',
                        type: 'checkbox'
                    })
                    .prop('checked', floating)
                    .change(onOptionChange);
                $tr.append($('<td>').css('text-align', 'center').append($element));
                
                $element = $('<input>').attr({
                        title: 'Automatically logs in members',
                        name: 'membersOnly',
                        type: 'checkbox'
                    })
                    .prop('checked', membersOnly)
                    .change(onOptionChange);
                $tr.append($('<td>').css('text-align', 'center').append($element));
                
                $element = $('<a>')
                    .attr({href: '#'})
                    .data('hash', hash)
                    .text('Settings')
                    .click(onModalChatSettingsOpen);
                $tr.append($('<td>').append($element));
                
                $element = $('<a>').attr({
                        name: 'upgrade_chatroom',
                        href: 'https://www.rumbletalk.com/upgrade/?hash=' + hash,
                        title: 'Upgrade your account, get more chat seats and create more rooms',
                        target: '_blank'
                    })
                    .text('Upgrade')
                    .css('color', 'red');
                $tr.append($('<td>').append($element));
                
                $element = $('<a>').attr({
                        name: 'delete_room',
                        href: '#',
                        title: 'Delete chat room',
                        'class': 'delete_chatroom'
                    })
                    .text('Delete').
                    click(onDeleteChatRoom);
                $tr.append($('<td>').append($element));
                                        
                $('#selected_rumbletalk_chat_code tbody').append($tr);
            }

            function validateUpdateChatroomBtn() {
                $('#update-chatroom').prop(
                    'disabled',
                    $keyInput.val().length == 0 ||
                    $secretInput.val().length == 0
                );
            }
            
            validateUpdateChatroomBtn();

            function updateChatRooms() {
                var data = {
                    action: 'rumbletalk_update_chatrooms',
                    key: $keyInput.val(),
                    secret: $secretInput.val()
                };

                $('#chatrooms_refresh').hide();
                $('#update_chatrooms_loading').show();

                $.ajax({
                    url: ajaxurl,
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status) {
                            $('#selected_rumbletalk_chat_code tbody').empty();
                            var hashes = data.hashes.split(",").map(String),
                                ids = data.ids.split(",").map(String),
                                options = JSON.parse(data.options);
                            
                            $.each(hashes, function(i, hash) {
                                var option = {width: '', height: '', floating: false, membersOnly: false};
                                $.extend(option, options[hash]);
                                addChatRoomUI(hash, ids[i], option.width, option.height, option.floating, option.membersOnly);
                            });
                        } else {
                            showErrorMessage('Info: ' + data.message);
                        }
                    },
                    complete: function() {
                        $('#update_chatrooms_loading').hide();
                        $('#chatrooms_refresh').show();
                    }
                });
            }

            if (<?= get_option('rumbletalk_chat_names') ? 1 : 0 ?>) {
                updateChatRooms();
            }

            function initModalSettingsFrame(accessToken, chatHash) {
                $('#modal-chat-settings-status').html('');

                $('#modalSettingsForm [name=token]').val(accessToken);
                $('#modalSettingsForm [name=chat_hash]').val(chatHash);

                $('#settingsIframe').show();
                $('#modalSettingsForm').submit();

                setTimeout(
                    function() {
                        $('#TB_window').css({
                            width: '1030px',
                            'margin-left': '-515px'
                        }).addClass('visibleImportant');
                        $('#TB_ajaxContent').css('width', '1000px');
                    }, 
                    100
                );
            }

            function doDeleteChatRoom($row) {
                var deleteBtn = $row.find('.delete_chatroom'),
                    $img = $('<img>'),
                    data = {
                        action: 'rumbletalk_delete_chatroom',
                        key: $keyInput.val(),
                        secret: $secretInput.val(),
                        id: $row.data('id')
                    };

                $img.attr({id: 'delete_chatroom_loading', src: '<?php echo plugins_url('rolling.gif', __FILE__); ?>'})
                deleteBtn.hide();
                deleteBtn.after($img);

                $.ajax({
                    url: ajaxurl,
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status) {
                            $row.remove();
                        } else {
                            showErrorMessage('Info: ' + data.message);
                        }
                    },
                    complete: function() {
                        $img.remove();
                        deleteBtn.show();
                    }
                });
            }

            function showModalConfirmation(title, text, callback) {
                var $modalButtons = buildModalButtonBox('Cancel'),
                    $button = $('<button>');
                
                $button.click(function(){
                    if (typeof callback == 'function') {
                        callback();
                    }
                    $('#TB_closeWindowButton').click();
                }).text('Confirm');
                $modalButtons.append($button);
                
                $('#modal-window-confirmation').empty();
                $('#modal-window-confirmation').append($('<p>'));
                $('#modal-window-confirmation p').text(text);
                $('#modal-window-confirmation').append($modalButtons);

                tb_show(title, '#TB_inline?width=300&height=250&inlineId=modal-window-confirmation');
                setTimeout(
                    function() {
                        $('#TB_ajaxContent').css('height', '100px');
                        $('#TB_window').addClass('visibleImportant');
                    }, 
                    100
                );
            }

            function onDeleteChatRoom(e) {
                e.preventDefault();

                showModalConfirmation(
                    'Please confirm ChatRoom deletion',
                    'Are you sure you want to delete this ChatRoom?',
                    function () {
                        doDeleteChatRoom($(e.target).parents('.chat-room-row'));
                    }
                );
            }

            function onModalChatSettingsOpen(e) {
                e.preventDefault();
                tb_show('', '#TB_inline?width=1000&height=700&inlineId=modal-chat-settings');

                $('#settingsIframe').hide();
                $('#modal-chat-settings-status').html('<div style="padding: 20px">Loading...</div>');
                
                var hash = $(e.target).data('hash'),
                    data = {
                        action: 'rumbletalk_get_access_token',
                        key: $keyInput.val(),
                        secret: $secretInput.val()
                    };

                if (rumbleTalkOptions.accessToken) {
                    /* use existing access tokens */
                    setTimeout(
                        function() {
                            initModalSettingsFrame(rumbleTalkOptions.accessToken, hash);
                        },
                        100
                    );
                } else {
                    /* get access token */
                    $.ajax({
                        url: ajaxurl, 
                        data: data,
                        type: 'POST',
                        dataType: 'json',
                        success: function(data) {
                            if (data.status) {
                                /* load the data into the iframe */
                                rumbleTalkOptions['accessToken'] = data.accessToken;
                                initModalSettingsFrame(data.accessToken, hash);
                            } else {
                                $('#TB_ajaxContent').empty();
                                showErrorMessage('Info: ' + data.message);
                            }
                        }
                    });
                }
            }

            $keyInput.on('change keyup', validateUpdateChatroomBtn);
            $secretInput.on('change keyup', validateUpdateChatroomBtn);

            var $displayToken = $('#display_token');
            $displayToken.click(function(e) {
                e.preventDefault();
                
                if ($('.tokens_row').css('display') == 'none') {
                    $displayToken.html('Hide token');
                } else {
                    $displayToken.html('Token is set: change token');
                }
                
                $('.tokens_row').toggle();
            });

            var $updateChatroom = $('#update-chatroom');
            $updateChatroom.click(function(e) {
                var $img = $('<img>'),
                    data = {
                        action: 'rumbletalk_apply_new_token',
                        key: $keyInput.val(),
                        secret: $secretInput.val()
                    };
                
                $img.attr({id: 'update_chatroom_loading', src: '<?php echo plugins_url('rolling.gif', __FILE__); ?>'});

                $updateChatroom.hide();
                $updateChatroom.after($img);

                $.ajax({
                    url: ajaxurl, 
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status) {
                            window.location.reload();
                        } else {
                            showErrorMessage('Info: ' + data.message);
                        }
                    },
                    complete: function() {
                        $('#update_chatroom_loading').remove();
                        $updateChatroom.show();
                    }
                });
                e.preventDefault();
            });

            function doCreateChatRoom(chatName) {
                if (!chatName) {
                    return true;
                }

                var $img = $('<img>'),
                    data = {
                        action: 'rumbletalk_create_new_chatroom',
                        key: $keyInput.val(),
                        secret: $secretInput.val(),
                        chatName: chatName
                    };

                $img.attr({id: 'create_new_chatroom_loading', src: '<?php echo plugins_url('rolling.gif', __FILE__); ?>'});

                $('#create_new_chatroom').hide();
                $('#create_new_chatroom').after($img);

                $.ajax({
                    url: ajaxurl, 
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        if (data.status) {
                            addChatRoomUI(data.hash, data.id, '', '', false, false);
                        } else {
                            showErrorMessage('Info: ' + data.message);
                        }
                    },
                    complete: function() {
                        $('#create_new_chatroom_loading').remove();
                        $('#create_new_chatroom').show();
                    }
                });
            }

            $('#create_new_chatroom').click(function(e) {
                e.preventDefault();
                showModalPrompt('New ChatRoom', 'Please enter new ChatRoom name', doCreateChatRoom);
            });

            $('#chatrooms_refresh').click(updateChatRooms);

            function setChatRoomOption(id, chatHash, name, value) {
                var data = {
                    action: 'rumbletalk_update_chatroom_options',
                    key: $keyInput.val(),
                    secret: $secretInput.val(),
                    id: id,
                    hash: chatHash,
                    options: {}
                };
                data.options[name] = value;
                
                $.ajax({
                    url: ajaxurl,
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        if (!data.status) {
                            showErrorMessage('Error');
                        }
                    }
                });
            }

            function onOptionChange(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $target = $(e.target),
                    $parent = $target.parents('.chat-room-row');
                
                setChatRoomOption(
                    $parent.data('id'),
                    $parent.find('.chatHashReference').val(),
                    $target.attr('name'),
                    $target.attr('type') == 'checkbox'
                        ? $target.prop('checked')
                        : $target.val()
                );
                
                return false;
            }

            /* prevent from submitting on ENTER key */
            $("form").keypress(function (e) {
                if (e.keyCode == 13) {
                    if ($(e.target).parents('.chat-room-row').data('id')) {
                        e.preventDefault();
                        $(':focus').blur();
                    }
                }
            });

        }(jQuery));
        
        (function(d, s, id) {
          var js, fjs = d.getElementsByTagName(s)[0];
          if (d.getElementById(id)) return;
          js = d.createElement(s); js.id = id;
          js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.4&appId=181184391902159";
          fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
    </script>
        <?php
    }
}

new RumbleTalkChat();
?>