<?php
/* Plugin Name: WP-FB-AutoConnect
 * Description: A LoginLogout widget with Facebook Connect button, offering hassle-free login for your readers. Clean and extensible.  Supports BuddyPress. Compatible with Graph API. Derived from Justin Klein http://wordpress.org/extend/plugins/wp-fb-autoconnect/
 * Author: CodeAndMore
 * Version: 1.0
 * Author URI: http://www.codeandmore.com/
 * Plugin URI: http://www.codeandmore.com/wordpress/plugins/wp-facebook-auto-connect-graph
 */


/*
 * Copyright 2010 CodeAndMore, derived work from Justin Klein (email: justin@justin-klein.com)
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


require_once("__inc_opts.php");
require_once("AdminPage.php");
require_once("Widget.php");
require_once("facebook.php");

/**********************************************************************/
/*******************************GENERAL********************************/
/**********************************************************************/

/*
 * custom wp logout url.
 */
function custom_logout_url()
{
    global $opt_jfb_logout_url;
    return get_option($opt_jfb_logout_url);
}

function init_button(){
    global $opt_jfb_app_id, $opt_jfb_api_sec, $opt_jfb_hide_button, $opt_jfb_logout_url, $opt_jfb_bt_size, $opt_jfb_logout_button;
    //If logged in, show "Welcome, User!"
    $userdata = wp_get_current_user();
    // Create our Application instance (replace this with your appId and secret).
    $facebook_graph = new Facebook1(array(
        'appId' => get_option($opt_jfb_app_id),
        'secret' => get_option($opt_jfb_api_sec),
        'cookie' => true,
    ));
    $session = $facebook_graph->getSession();
    $me = null;

    // Session based API call.
    if ($session) {
        try {
            $uid = $facebook_graph->getUser();
            $me = $facebook_graph->api('/me');
        } catch (FacebookApiException $e) {
            error_log($e);
        }
    }

    //Calling users.getinfo legacy api call example
    try {
        $param = array(
            'method' => 'users.getinfo',
            'uids' => $me['id'],
            'fields' => 'name,current_location,profile_url',
            'callback' => ''
        );
        $facebook_graph->api($param);
    } catch (Exception $o) {
        error_log($o);
    }

    // login or logout url will be needed depending on current user state.
    $par = array();
    $par['req_perms'] = "email,user_hometown,user_relationships,read_stream,user_birthday";
    if ($me) {
        if (get_option($opt_jfb_logout_url) != '')
            $logout_wp = get_settings('siteurl') . '/' . get_option($opt_jfb_logout_url);
        else
            $logout_wp = urldecode(str_replace("amp;", "", wp_logout_url($_SERVER['REQUEST_URI'])));
        $logoutUrl = $facebook_graph->getLogoutUrl($logout_wp);
    }else {
        $logoutUrl = wp_logout_url($_SERVER['REQUEST_URI']);
    }
    $loginUrl = $facebook_graph->getLoginUrl($par);
    $after_login_url = plugins_url(dirname(plugin_basename(__FILE__))) . '/_process.php?action=login&return=' . $facebook_graph->getCurrentUrl() . '&_wpnonce=' . wp_create_nonce($jfb_nonce_name);
?>
        <div id="fb-root"></div>
        <script type="text/javascript">
            window.fbAsyncInit = function() {
                FB.init({
                    appId   : '<?php echo $facebook_graph->getAppId(); ?>',
                    session : <?php echo json_encode($session); ?>, // don't refetch the session when PHP already has it
                    status  : true, // check login status
                    cookie  : true, // enable cookies to allow the server to access the session
                    xfbml   : true // parse XFBML
                });

                /* All the events registered */

                FB.Event.subscribe('auth.login', function(response) {
                    // do something with response
                    login();
                });
            <?php if(get_option($opt_jfb_logout_button)): ?>
                FB.Event.subscribe('auth.logout', function(response) {
                    logout();
                });
            <?php endif;?>
            };


            (function() {
                var e = document.createElement('script');
                e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
                e.async = true;
                document.getElementById('fb-root').appendChild(e);
            }());
            function login(){
                var link = "<?php echo $after_login_url; ?>";
                document.location.href = link;

            }
            <?php if(get_option($opt_jfb_logout_button)): ?>
            function logout(){
                var link = "<?php echo $logout_wp; ?>";
                document.location.href = link;
            }
            <?php endif;?>
        </script>
<?php if ($userdata->ID): ?>
            <div style='text-align:center'>
    <?php echo __('Welcome') . ', ' . $userdata->display_name ?>!<br />
            <small>
                <a href="<?php echo get_settings('siteurl') ?>/wp-admin/profile.php"><?php _e("Edit Profile") ?></a>
                <?php if(!get_option($opt_jfb_logout_button) || (!$me)):?>
                     | <a href="<?php echo $logoutUrl; ?>"><?php _e("Logout") ?></a>
                <?php endif;?>
            </small>
        </div>
<?php //Otherwise, show the login form (with Facebook Connect button)
            else: ?>
                <form name='loginform' id='loginform' action='<?php echo get_settings('siteurl') ?>/wp-login.php' method='post'>
                    <label>User:</label><br />
                    <input type='text' name='log' id='user_login' class='input' tabindex='20' /><input type='submit' name='wp-submit' id='wp-submit' value='Login' tabindex='23' /><br />
                    <label>Pass:</label><br />
                    <input type='password' name='pwd' id='user_pass' class='input' tabindex='21' />
                    <span id="forgotText"><a href="<?php echo get_settings('siteurl') ?>/wp-login.php?action=lostpassword"><?php _e('Forgot') ?>?</a></span><br />
    <?php //echo "<input name='rememberme' type='hidden' id='rememberme' value='forever' />"; ?>
    <?php echo wp_register('', ''); ?>
                <input type='hidden' name='redirect_to' value='<?php echo htmlspecialchars($_SERVER['REQUEST_URI']) ?>' />
            </form>
<?php endif; ?>
        
<?php if(!get_option ($opt_jfb_hide_button)):?>        
    <?php if($me && get_option($opt_jfb_logout_button)):?>
        <div>
            <fb:login-button perms="email,user_hometown,user_relationships,read_stream,user_birthday" v="2" size="<?php echo get_option($opt_jfb_bt_size); ?>" length="long" autologoutlink="true"></fb:login-button>
        </div>
    <?php elseif(!$me):?>        
        <div>
            <fb:login-button perms="email,user_hometown,user_relationships,read_stream,user_birthday" v="2" size="<?php echo get_option($opt_jfb_bt_size); ?>" length="long"></fb:login-button>
        </div>
    <?php endif;?>
    <?php endif;?>
<?php
}

function fb_comment_button(){
    global $opt_jfb_app_id, $opt_jfb_api_sec, $id, $opt_jfb_logout_url, $opt_jfb_hide_button, $opt_jfb_logout_button, $opt_jfb_bt_size, $jfb_nonce_name;
    $userdata = wp_get_current_user();
    $facebook_graph = new Facebook1(array(
        'appId' => get_option($opt_jfb_app_id),
        'secret' => get_option($opt_jfb_api_sec),
        'cookie' => true,
    ));
    $session = $facebook_graph->getSession();
    $me = null;
    $content = '';
    // Session based API call.
    if ($session) {
        try {
            $uid = $facebook_graph->getUser();
            $me = $facebook_graph->api('/me');
        } catch (FacebookApiException $e) {
            error_log($e);
        }
    }
?>
    <?php
        if(!get_option ($opt_jfb_hide_button) && $userdata->ID && $me){
            if(get_option($opt_jfb_logout_button)){
                $content = '<fb:login-button perms="email,user_hometown,user_relationships,read_stream,user_birthday" v="2" size="'.get_option($opt_jfb_bt_size).'" length="long" autologoutlink="true"></fb:login-button>';
                // login or logout url will be needed depending on current user state.
                $par = array();
                $par['req_perms'] = "email,user_hometown,user_relationships,read_stream,user_birthday";
                $loginUrl = $facebook_graph->getLoginUrl($par);
                $after_login_url = plugins_url(dirname(plugin_basename(__FILE__))) . '/_process.php?action=login&return=' . $facebook_graph->getCurrentUrl() . '&_wpnonce=' . wp_create_nonce($jfb_nonce_name);
    ?>
    <div id="fb-root"></div>
    <script type="text/javascript">
            window.fbAsyncInit = function() {
                FB.init({
                    appId   : '<?php echo $facebook_graph->getAppId(); ?>',
                    session : <?php echo json_encode($session); ?>, // don't refetch the session when PHP already has it
                    status  : true, // check login status
                    cookie  : true, // enable cookies to allow the server to access the session
                    xfbml   : true // parse XFBML
                });

                /* All the events registered */
                
            <?php if(get_option($opt_jfb_logout_button)): ?>
                FB.Event.subscribe('auth.logout', function(response) {
                    logout();
                });
            <?php endif;?>
            };

            (function() {
                var e = document.createElement('script');
                e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
                e.async = true;
                document.getElementById('fb-root').appendChild(e);
            }());            
            <?php if(get_option($opt_jfb_logout_button)): ?>
            function logout(){
                var link = "<?php echo $logout_wp; ?>";
                document.location.href = link;
            }
            <?php endif;?>
        </script>
    <?php
            }else{
                if (get_option($opt_jfb_logout_url) != ''){
                    $logout_wp = get_settings('siteurl') . '/' . get_option($opt_jfb_logout_url);
                }else{
                    $logout_wp = urldecode(str_replace("amp;", "", wp_logout_url($_SERVER['REQUEST_URI'])));
                }
                $content = '<a href="'.$facebook_graph->getLogoutUrl($logout_wp).'" title="Log out of this account">Log out?</a>';
            }
        }
    ?>        
<?php
        return $content;
}
/*
 * Output a Facebook Connect Button.  Note that the button will not function until you've called 
 * jfb_output_facebook_init().  I use document.write() because the button isn't XHTML valid.
 */
function jfb_output_facebook_btn()
{
    global $jfb_name, $jfb_version, $jfb_js_callbackfunc, $opt_jfb_valid;
    echo "<!-- $jfb_name v$jfb_version -->\n";
    if( !get_option($opt_jfb_valid) )
    {
        echo "<!--WARNING: Invalid or Unset Facebook API Key-->";
        return;
    }
    ?>
    <script type="text/javascript">//<!--
    document.write('<span id="fbLoginButton"><fb:login-button v="2" size="small" onlogin="<?php echo $jfb_js_callbackfunc?>();">Login with Facebook</fb:login-button></span>');
    //--></script>
    <?php
}


/*
 * As an alternative to jfb_output_facebook_btn, this will setup an event to automatically popup the
 * Facebook Connect dialog as soon as the page finishes loading (as if they clicked the button manually) 
 */
function jfb_output_facebook_instapopup( $callbackName=0 )
{
    global $jfb_js_callbackfunc;
    if( !$callbackName ) $callbackName = $jfb_js_callbackfunc;
    ?>
    <script type="text/javascript">//<!--
    function showPopup()
    {
        FB.ensureInit( function(){FB.Connect.requireSession(<?php echo $callbackName?>);}); 
    }
    window.onload = showPopup;
    //--></script>
    <?php
}


/*
 * Output the JS to init the Facebook API, which will also setup a <fb:login-button> if present. 
 */
function jfb_output_facebook_init()
{
    global $opt_jfb_app_id, $opt_jfb_app_id, $opt_jfb_valid;
    if( !get_option($opt_jfb_valid) ) return;
    $xd_receiver = plugins_url(dirname(plugin_basename(__FILE__))) . "/facebook-platform/xd_receiver.htm";
    ?>
    <script type="text/javascript" src="http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php"></script>
    <script type="text/javascript">//<!--
    FB.init("<?php echo get_option($opt_jfb_app_id)?>","<?php echo $xd_receiver?>");
    //--></script>
    <?php  
}



/*
 * Output the JS callback function that'll handle FB logins
 */
function jfb_output_facebook_callback($redirectTo=0, $callbackName=0)
{
     //Make sure the plugin is setup properly before doing anything
     global $opt_jfb_ask_perms, $opt_jfb_req_perms, $opt_jfb_valid, $jfb_nonce_name, $jfb_js_callbackfunc, $opt_jfb_ask_stream;
     if( !get_option($opt_jfb_valid) ) return;
     
     //Get out our params
     if( !$redirectTo )  $redirectTo = htmlspecialchars($_SERVER['REQUEST_URI']);
     if( !$callbackName )$callbackName = $jfb_js_callbackfunc;
     
     //Output an html form that we'll submit via JS once the FB login is complete; it redirects us to the PHP script that logs us into WP.  
  ?><form name="<?php echo $callbackName ?>_form" method="post" action="<?php echo plugins_url(dirname(plugin_basename(__FILE__))) . "/_process_login.php"?>" >
      <input type="hidden" name="redirectTo" value="<?php echo $redirectTo?>" />
      <?php wp_nonce_field ($jfb_nonce_name) ?>   
    </form><?php

    //Output the JS callback function, which Facebook will automatically call once it's been logged in.
    ?><script type="text/javascript">//<!--
    function <?php echo $callbackName ?>()
    {
        //Make sure we have a valid session
        if (!FB.Facebook.apiClient.get_session())
        { alert('Facebook failed to log you in!'); return; }

        <?php 
        //Optionally request permissions to get their real email and to publish to their wall before redirecting to the logon script.
        $ask_for_email_permission = get_option($opt_jfb_ask_perms) || get_option($opt_jfb_req_perms);
        if( $ask_for_email_permission && get_option($opt_jfb_ask_stream) )                     //Ask for both
            echo "FB.Connect.showPermissionDialog('email,publish_stream', function(reply)\n        {\n";
        else if( $ask_for_email_permission )                                                   //Ask for email only
            echo "FB.Connect.showPermissionDialog('email,publish_stream', function(reply)\n        {\n";
        else if( get_option($opt_jfb_ask_stream) )                                             //Ask for publish only
            echo "FB.Connect.showPermissionDialog('email,publish_stream', function(reply)\n        {\n";
        
        //If we're not requiring their email, just redirect them (no matter if they approve or not)
        if( !get_option($opt_jfb_req_perms) )
        {
            echo "            document." . $callbackName . "_form.submit();\n";
            if( $ask_for_email_permission || get_option($opt_jfb_ask_stream) )
               echo "        });\n";
        }        
        
        
        //If we REQUIRE their email address, make sure they accept the extended permissions before redirecting to the logon script            
        else
        {
            echo "            FB.Facebook.apiClient.users_hasAppPermission('email', function (emailCheck)\n".
                 "            {\n". 
		         "                 if(emailCheck) document." . $callbackName . "_form.submit();\n" .
                 "                 else           alert('Sorry, this site requires an e-mail address to log you in.');\n" .
                 "            });\n";
            echo "        });\n";
        }
        ?>
    }
    //--></script><?php
}



/**
  * Include the FB class in the <html> tag (only when not already logged in)
  * So stupid IE will render the button correctly
  */
add_filter('language_attributes', 'jfb_output_fb_namespace');
function jfb_output_fb_namespace()
{
    global $current_user;
    if( isset($current_user) && $current_user->ID != 0 ) return;
    echo 'xmlns:fb="http://www.facebook.com/2008/fbml"';
}


/**********************************************************************/
/*******************************AVATARS********************************/
/**********************************************************************/


/**
  * If enabled, hook into get_avatar() and replace it with a WORDPRESS profile thumbnail.
  * NOTE: BuddyPress avatars are handled below.
  */
if( get_option($opt_jfb_wp_avatars) ) add_filter('get_avatar', 'jfb_wp_avatar', 10, 5);
function jfb_wp_avatar($avatar, $id_or_email, $size, $default, $alt)
{
    //First, get the userid
	if (is_numeric($id_or_email))	    
	    $user_id = $id_or_email;
	else if(is_object($id_or_email) && !empty($id_or_email->user_id))
	   $user_id = $id_or_email->user_id;

	//If we couldn't get the userID, just return default behavior (email-based gravatar, etc)
	if(!isset($user_id) || !$user_id) return $avatar;

	//Now that we have a userID, let's see if we have their facebook profile pic stored in usermeta
	$fb_img = get_usermeta($user_id, 'facebook_avatar_thumb');
	
	//If so, replace the avatar! Otherwise, fallback on what WP core already gave us.
	if($fb_img) $avatar = "<img alt='fb_avatar' src='$fb_img' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
    return $avatar;
}



/**********************************************************************/
/*******************BUDDYPRESS (previously in BuddyPress.php)**********/
/**********************************************************************/

/*
 * If this is BuddyPress, switch ON the option to include bp filters by default
 */
global $opt_jfb_buddypress;
add_action( 'bp_init', 'jfb_turn_on_bp' );
function jfb_turn_on_bp()
{
    add_option($opt_jfb_buddypress, 1);
    add_option($opt_jfb_bp_avatars, 1);
}


if( get_option($opt_jfb_buddypress) )
{
    /*
     * If enabled, hook into bp_core_fetch_avatar and replace the BuddyPress avatar with the Facebook profile picture.
     * For WORDPRESS avatar handling, see above. 
     */
    if( get_option($opt_jfb_bp_avatars) ) add_filter( 'bp_core_fetch_avatar', 'jfb_get_facebook_avatar', 10, 4 );    
    function jfb_get_facebook_avatar($avatar, $params='')
    {
        //First, get the userid
    	global $comment;
    	if (is_object($comment))	$user_id = $comment->user_id;
    	if (is_object($params)) 	$user_id = $params->user_id;
    	if (is_array($params))
    	{
    		if ($params['object']=='user')
    			$user_id = $params['item_id'];
    	}
    
    	//Then see if we have a Facebook avatar for that user
    	if( $params['type'] == 'full' && get_usermeta($user_id, 'facebook_avatar_full'))
    		return '<img alt="avatar" src="' . get_usermeta($user_id, 'facebook_avatar_full') . '" class="avatar" />';
        else if( get_usermeta($user_id, 'facebook_avatar_thumb') )
    	    return '<img alt="avatar" src="' . get_usermeta($user_id, 'facebook_avatar_thumb') . '" class="avatar" />';
    	else
            return $avatar;
    }
    
    
    /*
     * Add a Facebook Login button to the Buddypress sidebar login widget
     * NOTE: If you use this, you mustn't also use the built-in widget - just one or the other!
     */
    add_action( 'bp_after_sidebar_login_form', 'jfb_bp_add_fb_login_button' );
    function jfb_bp_add_fb_login_button()
    {
      if ( !is_user_logged_in() )
      {
          echo "<p></p>";
          jfb_output_facebook_btn();
          jfb_output_facebook_init();
          jfb_output_facebook_callback();
      }
    }
    
    
    /*
     * Modify the userdata for BuddyPress by changing login names from the default FB_xxxxxx
     * to something prettier for BP's social link system
     */
    add_filter( 'wpfb_insert_user', 'jfp_bp_modify_userdata', 10, 2 );
    function jfp_bp_modify_userdata( $wp_userdata, $fb_userdata )
    {
        $counter = 1;
        $name = str_replace( ' ', '', $fb_userdata['first_name'] . $fb_userdata['last_name'] );
        if ( username_exists( $name ) )
        {
            do
            {
                $username = $name;
                $counter++;
                $username = $username . $counter;
            } while ( username_exists( $username ) );
        }
        else
        {
            $username = $name;
        }
        $username = strtolower( sanitize_user($username) );
    
        $wp_userdata['user_login']   = $username;
        $wp_userdata['user_nicename']= $username;
        return $wp_userdata;
    }
}


/**********************************************************************/
/***************************Error Reporting****************************/
/**********************************************************************/

register_activation_hook(__FILE__, 'jfb_activate');
register_deactivation_hook(__FILE__, 'jfb_deactivate');

?>