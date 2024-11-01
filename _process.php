<?php    
    require_once("__inc_wp.php");
    if($_GET['action'] == 'login'){        
        $jfb_log = "Starting login process (Client: " . $_SERVER['REMOTE_ADDR'] . ", Version: $jfb_version)\n";
        $user_login_id = null;
        $me = null;

        if( !get_option($opt_jfb_disablenonce) ){
            $jfb_log .= "WP: Verifying nonce (expected '" . wp_create_nonce( $jfb_nonce_name ) . "', received '" . $_REQUEST['_wpnonce'] . "')\n";        
            $jfb_log .= "WP: nonce check passed\n";
        } else
            $jfb_log .= "WP: nonce check DISABLED\n";
        if( !isset($_GET['return']) || !$_GET['return'] )
            j_die("Error: Missing POST Data (redirect)");
        $redirectTo = $_GET['return'];
        $jfb_log .= "WP: Found redirect URL ($redirectTo)\n";        

        require_once("facebook.php");
        // Create our Application instance (replace this with your appId and secret).
        $facebook_graph = new Facebook1(array(
          'appId'  => get_option($opt_jfb_app_id),
          'secret' => get_option($opt_jfb_api_sec),
          'cookie' => true,
        ));
                        
        $session = $facebook_graph->getSession();
        // Session based API call.
        if ($session) {
          try {
            $uid = $facebook_graph->getUser();
            $me = $facebook_graph->api('/me');
          } catch (FacebookApiException $e) {
            error_log($e);
          }
        }                
        
        $fbuserarray =  array();
        $fbuserarray[0]['first_name']       = $me['first_name'];
        $fbuserarray[0]['last_name']        = $me['last_name'];
        $fbuserarray[0]['name']             = $me['name'];
        $fbuserarray[0]['pic_big']          = "http://graph.facebook.com/$uid/picture?type=large";
        $fbuserarray[0]['uid'] = $fb_uid    = $me['id'];
        $fbuserarray[0]['pic_square']       = "https://graph.facebook.com/$uid/picture";
        $fbuserarray[0]['email_hashes']     = '';
        $fbuserarray[0]['profile_url']      = $me['link'];
        $fbuserarray[0]['contact_email']    = $me['email'];
        $fbuserarray[0]['email']            = $me['email'];

        if(!$fb_uid) j_die("Error: Failed to get the Facebook session. Please verify your Application ID and Secret.");
        $jfb_log .= "FB: Connected to session (uid $fb_uid)\n";
        
        $fbuser = $fbuserarray[0];
        if( !$fbuser ) j_die("Error: Could not access the Facebook API client (failed on users_getInfo($fb_uid)): " . print_r($fbuserarray, true) );
        $jfb_log .= "FB: Got user info (".$fbuser['name'].")\n";
        if( $fbuser['contact_email'] )
            $jfb_log .= "FB: Email privilege granted (" .$fbuser['email'] . ")\n";
        else if( $fbuser['email'] ) {
            $jfb_log .= "FB: Email privilege granted, but only for an anonymous proxy address (" . $fbuser['email'] . ")\n";
        } else {
            $fbuser['email'] = "FB_" . $fb_uid . $jfb_default_email;
            $jfb_log .= "FB: Email privilege denied\n";
        }
        //login: get all user to compare
        $wp_users = get_users_of_blog();
        $wp_user_hashes = array();
        $jfb_log .= "WP: Searching for user by meta...\n";
        foreach ($wp_users as $wp_user)
        {
            $user_data = get_userdata($wp_user->ID);
            $meta_uid  = get_user_meta($wp_user->ID, $jfb_uid_meta_name);
            if( $meta_uid && $meta_uid == $fb_uid )
            {
                $user_login_id   = $wp_user->ID;
                $user_login_name = $user_data->user_login;
                $jfb_log .= "WP: Found existing user by meta (" . $user_login_name . ")\n";
                break;
            }


            //In case we don't find them by meta, we'll need to search for them by email below.
            //Precalculate each non-FB-connected user's mail-hash (http://wiki.developers.facebook.com/index.php/Connect.registerUsers)
            if( !$meta_uid )
            {
                $email= strtolower(trim($user_data->user_email));
                $hash = sprintf('%u_%s', crc32($email), md5($email));
                $wp_user_hashes[$wp_user->ID] = array('email_hash' => $hash);
            }
        }

        if ( !$user_login_id && $fbuser['contact_email'] )
        {
            $jfb_log .= "WP: Searching for user by email address...\n";
            if ( $wp_user = get_user_by('email', $fbuser['email']) )
            {
                $user_login_id = $wp_user->ID;
                $user_data = get_userdata($wp_user->ID);
                $user_login_name = $user_data->user_login;
                $jfb_log .= "WP: Found existing user (" . $user_login_name . ") by email (" . $fbuser['email'] . ")\n";
            }
        }

//        //If we still haven't found the user, and if they've denied direct access to their email address (so we can't search for them with get_user_by()),
//        //we can still use FB email hashes to see if they've registered an address that matches any of our existing WP users.
//        //Note that we ONLY do this if the user denied the email extended_permission - otherwise, the check above would've already found them.
//        if( !$user_login_id && !$fbuser['contact_email'] && count($wp_user_hashes) > 0 )
//        {
//            if(version_compare(PHP_VERSION, '5', "<"))
//            {
//                $jfb_log .= "FP: CANNOT search for users by email in PHP4\n";
//            }
//            else
//            {
//                //Search for users via their email hashes.  Facebook can handle 1000 at a time.
//                $insert_limit = 1000;
//                $hash_chunks = array_chunk( $wp_user_hashes, $insert_limit );
//                $jfb_log .= "FP: Searching for user by email hashes (" . count($wp_user_hashes) . " candidates of " . count($wp_users) . " total users)...\n";
//                foreach( $hash_chunks as $num => $hashes )
//                {
//                    //First we send Facebook a list of email hashes we want to check against this FB user.
//                    $jfb_log .= "    Checking Users #" . ($num*$insert_limit) . "-" . ($num*$insert_limit+count($hashes)-1) . "\n";
//                    $ret = $facebook->api_client->connect_registerUsers(json_encode($hashes));
//                    if( !$ret )
//                    {
//                        $jfb_log .= "    WARNING: Could not register hashes with Facebook (connect_registerUsers).  Hash lookup will cease here.\n";
//                        break;
//                    }
//
//                    //Next we get the hashes for the current FB user; This will only return hashes we
//                    //registered above, so if we get back nothing we know the current FB user is not in this group of WP users.
//                    $facebook_graph_fbuser_hashes = $facebook->api_client->users_getInfo($fb_uid, array('email_hashes'));
//                    $facebook_graph_fbuser_hashes = $facebook_graph_fbuser_hashes[0]['email_hashes'];
//
//                    //If we did get back a hash, all we need to do is find which WP user it came from - and that's who's logging in!
//                    if(!empty($facebook_graph_fbuser_hashes))
//                    {
//                        foreach( $facebook_graph_fbuser_hashes as $facebook_graph_fbuser_hash )
//                        {
//                            foreach( $wp_user_hashes as $facebook_graph_wpuser_id => $facebook_graph_wpuser_hash )
//                            {
//                                if( $facebook_graph_fbuser_hash == $facebook_graph_wpuser_hash['email_hash'] )
//                                {
//                                    $user_login_id   = $facebook_graph_wpuser_id;
//                                    $user_data       = get_userdata($user_login_id);
//                                    $user_login_name = $user_data->user_login;
//                                    $jfb_log .= "FB: Found existing user by email hash (" . $user_login_name . ")\n";
//                                    break;
//                                }
//                            }
//                        }
//                    }
//                    if( $user_login_id ) break;
//                }  //Try the next group of hashes
//            }
//        }


        //If we found an existing user, check if they'd previously denied access to their email but have now allowed it.
        //If so, we'll want to update their WP account with their *real* email.
        if( $user_login_id )
        {
            //Check 1: It was previously denied, but is now allowed
            $updateEmail = false;
            if( strpos($user_data->user_email, $jfb_default_email) !== FALSE && strpos($fbuser['email'], $jfb_default_email) === FALSE )
            {
                $jfb_log .= "WP: Previously DENIED email has now been allowed; updating to (".$fbuser['email'].")\n";
                $updateEmail = true;
            }
            //Check 2: It was previously allowed, but only as an anonymous proxy.  They've now revealed their "true" email.
            if( strpos($user_data->user_email, "@proxymail.facebook.com") !== FALSE && strpos($fbuser['email'], "@proxymail.facebook.com") === FALSE )
            {
                $jfb_log .= "WP: Previously PROXIED email has now been allowed; updating to (".$fbuser['email'].")\n";
                $updateEmail = true;
            }
            if( $updateEmail )
            {
                $user_upd = array();
                $user_upd['ID']         = $user_login_id;
                $user_upd['user_email'] = $fbuser['email'];
                wp_update_user($user_upd);
            }
        }


        //If we STILL don't have a user_login_id, the FB user who's logging in has never been to this blog.
        //We'll auto-register them a new account.  Note that if they haven't allowed email permissions, the
        //account we register will have a bogus email address (but that's OK, since we still know their Facebook ID)
        if( !$user_login_id )
        {            
            $jfb_log .= "WP: No user found. Automatically registering (FB_". $fb_uid . ")\n";
            $user_data = array();
            $user_data['user_login']    = "FB_" . $fb_uid;
            $user_data['user_pass']     = wp_generate_password();
            $user_data['first_name']    = $fbuser['first_name'];
            $user_data['last_name']     = $fbuser['last_name'];
            $user_data['user_nicename'] = $fbuser['name'];
            $user_data['display_name']  = $fbuser['first_name'];
            $user_data['user_url']      = $fbuser["profile_url"];
            $user_data['user_email']    = $fbuser["email"];

            //Run a filter so the user can be modified to something different before registration
            $user_data = apply_filters('wpfb_insert_user', $user_data, $fbuser );

            //Insert a new user to our database and make sure it worked
            $user_login_id   = wp_insert_user($user_data);
            if( is_wp_error($user_login_id) )
            {
                $jfb_log .= "WP: Error creating user: " . $user_login_id->get_error_message() . "\n";
                j_die("Error: wp_insert_user failed!  This should never happen; if you see this bug, please report it to the plugin author at $jfb_homepage.");
            }

            //Success! Notify the site admin.
            $user_login_name = $user_data['user_login'];
            wp_new_user_notification($user_login_name);

            //Run an action so i.e. usermeta can be added to a user after registration
            do_action('wpfb_inserted_user', array('WP_ID' => $user_login_id, 'FB_ID' => $fb_uid, 'facebook' => $facebook) );

            //If the option was selected and permission exists, publish an announcement about the user's registration to their wall
            if( get_option($opt_jfb_ask_stream) ) {
                try {
                    $statusUpdate = $facebook_graph->api('/me/feed', 'post', array('message'=> get_option($opt_jfb_stream_content), 'cb' => ''));
                    $jfb_log .= "FB: Publishing registration news to user's wall.\n";
                } catch (FacebookApiException $e) {
                    $jfb_log .= "FB: User has DENIED permission to publish to their wall.\n";
                    error_log($e);
                }
            }
        }

        //Tag the user with our meta so we can recognize them next time, without resorting to email hashes
        update_user_meta($user_login_id, $jfb_uid_meta_name, $fb_uid);
        $jfb_log .= "WP: Updated usermeta ($jfb_uid_meta_name)\n";

        //Also store the user's facebook avatar(s), in case the user wants to use them later
        if( $fbuser['pic_square'] )
        {
            update_user_meta($user_login_id, 'facebook_avatar_thumb', $fbuser['pic_square']);
            update_user_meta($user_login_id, 'facebook_avatar_full', $fbuser['pic_big']);
            $jfb_log .= "WP: Updated avatars (" . $fbuser['pic_square'] . ")\n";
        }
        else
        {
            update_user_meta($user_login_id, 'facebook_avatar_thumb', '');
            update_user_meta($user_login_id, 'facebook_avatar_full', '');
            $jfb_log .= "FB: User does not have a profile picture; clearing cached avatar (if present).\n";
        }

        //Log them in
        wp_set_auth_cookie($user_login_id);

        //Run a custom action.  You can use this to modify a logging-in user however you like,
        //i.e. add them to a "Recent FB Visitors" log, assign a role if they're friends with you on Facebook, etc.

        do_action('wp_login', $user_login_name);


        //Email logs if requested
        $jfb_log .= "Login complete!\n";
        $jfb_log .= "   WP User : $user_login_name (" . admin_url("user-edit.php?user_id=$user_login_id") . ")\n";
        $jfb_log .= "   FB User : " . $fbuser['name'] . " (" . $fbuser["profile_url"] . ")\n";
        $jfb_log .= "   Redirect: " . $redirectTo . "\n";
        j_mail("Facebook Login: " . $user_login_name);
        //Redirect the user back to where they were
        $delay_redirect = get_option($opt_jfb_delay_redir);
        if( !isset($delay_redirect) || !$delay_redirect )
        {            
            header("Location: " . $_GET['return']);
            exit;
        }
?>
    <!doctype html public "-//w3c//dtd html 4.0 transitional//en">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>Logging In...</title>
    </head>
    <body>
        <img alt="" src="<?php $fbuser['pic_big'];?>" />
        <?php                     
            echo "<pre>".$jfb_log."</pre>";
        ?>

        <?php echo '<a href="'.$_GET['return'].'">Continue</a>'?>
    </body>
</html>
<?php
    }        
?>
