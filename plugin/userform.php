<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a userform action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: userform.php,v 1.34 2010/07/13 14:10:44 wkpark Exp $

function do_userform($formatter,$options) {
  global $DBInfo;

  $user=&$DBInfo->user; # get cookie
  $id=!empty($options['login_id']) ? $options['login_id'] : '';

  $use_any=0;
  if (!empty($DBInfo->use_textbrowsers)) {
    if (is_string($DBInfo->use_textbrowsers))
      $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
    else
      $use_any= preg_match('/Lynx|w3m|links/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
  }

  $options['msg'] = '';
  # e-mail conformation
  if (!empty($options['ticket']) and $id and $id!='Anonymous') {
    $userdb=&$DBInfo->udb;

    $suspended = false;
    if ($userdb->_exists($id)) {
      $user=$userdb->getUser($id);
    } else if ($userdb->_exists($id, 1)) {
      // suspended user
      $suspended = true;
      $user=$userdb->getUser($id, 1);
    }

    if ($user->id == $id) {
       if ($user->info['eticket']==$options['ticket']) {
         list($dummy,$email)=explode('.',$options['ticket'],2);
         $user->info['email']=$email;
         $user->info['eticket']='';
         if ($suspended) {
           if (empty($DBInfo->register_confirm_admin)) {
             $userdb->activateUser($id);
             $userdb->saveUser($user);
           } else {
             $userdb->saveUser($user, array('suspended'=>1));
           }
         } else {
           $userdb->saveUser($user);
         }
         $title=_("Successfully confirmed");
         $options['msg']=_("Your e-mail address is confirmed successfully");
         if (!empty($DBInfo->register_confirm_admin)) {
           $options['msg'].= "<br />"._("Your need to wait until your ID activated by admin");
         }
       } else if ($user->info['nticket']==$options['ticket']) {
         $title=_("Successfully confirmed");
         $user->info['nticket']='';
         $user->info['password']=$user->info['npassword'];
         $user->info['npassword']='';
         $userdb->saveUser($user);
         $options['msg']=_("Your new password is confirmed successfully");
       } else {
         $title=_("Confirmation missmatched !");
         $options['msg']=_("Please try again to register your e-mail address");
       }
    } else {
      if ($suspended)
        $title=_("Please wait until your ID is confirmed by admin!");
      else
        $title=_("ID does not exist !");
      $options['msg']=_("Please try again to register your e-mail address");
    }
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $formatter->send_footer("",$options);
    return '';
  }

  $title='';
  if ($user->id == "Anonymous" and !empty($options['emailreset'])) {
    setcookie('MONI_VERIFIED_EMAIL', '', time() - 3600, get_scriptname());
    $options['msg'].='<br />'._("Verification E-mail removed.");
    $options['verifyemail'] = '';
    $user->verified_email = '';
  } else if ($user->id == "Anonymous" and !empty($options['login']) and
      !empty($options['verify_email'])) {
    $email = base64_decode($options['login']);
    $ticket = base64_encode(getTicket($_SERVER['REMOTE_ADDR'], $email, 10));
    if ($ticket == $options['verify_email']) {
      $options['msg'].='<br />'._("Your email address is successfully verified.");
      $user->verified_email = $email;
      setcookie('MONI_VERIFIED_EMAIL', $email, time() + 60*60*24*30, get_scriptname());
    } else {
      $options['msg'].='<br />'._("Verification missmatched.");
    }
  } else
  if ($user->id == "Anonymous" and $options['verify'] == _("Verify E-mail address") and
      !empty($DBInfo->anonymous_friendly) and !empty($options['verifyemail'])) {
    if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['verifyemail'])) {
      if (($ret = verify_email($options['verifyemail'])) < 0) {
        $ret = -$ret;
        $options['msg'].='<br />'.'ERROR Code: '.$ret;
        $options['msg'].='<br/>'._("Invalid email address or can't verify it.");
      } else {
        if (!empty($DBInfo->verify_email)) {
          if ($DBInfo->verify_email == 1) {
            $options['msg'].='<br/>'._("Your email address is successfully verified.");
            setcookie('MONI_VERIFIED_EMAIL', $options['verifyemail'], time() + 60*60*24*30, get_scriptname());
          } else {
            $opts = array();
            $opts['subject'] = "[$DBInfo->sitename] "._("Verify Email address");
            $opts['email'] = $options['verifyemail'];
            $opts['id'] = 'nobody';
            $ticket = base64_encode(getTicket($_SERVER['REMOTE_ADDR'], $opts['email'], 10));
            $enc = base64_encode($opts['email']);
            $body = qualifiedUrl($formatter->link_url('UserPreferences',"?action=userform&login=$enc&verify_email=$ticket"));

            $body = _("Please confirm your e-mail address")."\n".$body."\n";

            $ret = wiki_sendmail($body, $opts);
            $options['msg'].='<br/>'._("E-mail verification mail sent");
          }
        }
      }
    } else {
      $options['msg'].='<br/>'._("Your email address is not valid");
    }

  } else
  if ($user->id == "Anonymous" and !empty($options['login_id']) and isset($options['password']) and !isset($options['passwordagain'])) {

    if (method_exists($user, 'login')) {
      $user->login($formatter, $options);
      $params = array();
      $params['value'] = $options['page'];
      do_goto($formatter, $params);
      return;
    }
    # login
    $userdb=$DBInfo->udb;
    if ($userdb->_exists($id)) {
      $user=$userdb->getUser($id);
      $login_ok=0;
      if (!empty($DBInfo->use_safelogin)) {
        if (isset($options['challenge']) and
           $options['_chall']==$options['challenge']) {
          #print '<pre>';
          #print $options['password'].'<br />';
          #print hmac($options['challenge'],$user->info['password']);
          #print '</pre>';
          if (hmac($options['challenge'],$user->info['password'])
            == $options['password'])
            $login_ok=1;
        } else { # with no javascript browsers
          $md5pw=md5($options['password']);
          if ($md5pw == $user->info['password'])
            $login_ok=1;
        }
      }
      if ($login_ok or $user->checkPasswd($options['password'])=== true) {
        $options['msg'] = sprintf(_("Successfully login as '%s'"),$id);
        $options['id']=$user->id;
        if ($user->id == 'Anonymous') {
          // special case. login success but ID is not acceptable
          $options['msg'] = _("Invalid user ID. Please register again");
        } else {
          $formatter->header($user->setCookie());
          if (!isset($user->info['login_success']))
            $user->info['login_success'] = 0;
          if (!isset($user->info['login_fail']))
            $user->info['login_fail'] = 0;
          $user->info['login_success']++;
          $user->info['last_login'] = gmdate("Y/m/d H:i:s", time());
          $user->info['login_fail'] = 0; // reset login
          $user->info['remote'] = $_SERVER['REMOTE_ADDR'];
          $userdb->saveUser($user);
          $use_refresh=1;

          if (function_exists('_session_start'))
            _session_start(null, $user->id);
        }

        $DBInfo->user=$user;
      } else {
        $title = sprintf(_("Invalid password !"));
        if (!isset($user->info['login_fail']))
          $user->info['login_fail'] = 0;
        $user->info['login_fail']++;
        $user->info['remote'] = $_SERVER['REMOTE_ADDR'];
        $userdb->saveUser($user);
        $user->setID('Anonymous');
      }
    } else {
      if (isset($options['login_id'][0])) {
        if ($userdb->_exists($id, 1)) {
          // suspended user
          $title = sprintf(_("\"%s\" is waiting for activated by admin !"), $options['login_id']);
        } else {
          $title = sprintf(_("\"%s\" does not exist on this wiki !"),$options['login_id']);
        }
        $options['login_id'] = '';
      } else {
        $title= _("Make new ID on this wiki");
      }
     $form=macro_UserPreferences($formatter,'',$options);
    }
  } else if (!empty($options['logout'])) {
    # logout
    header($user->unsetCookie(), false);
    if (session_name() != '') {
      // for some user plugins
      $params = session_get_cookie_params();
      header('Set-Cookie: '. session_name() .'=dummy; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path='.
        $params['path'].'; Domain='.$params['domain'], false);
    }

    // call logout method
    if (method_exists($user, 'logout')) {
      $user->logout($formatter, $options);
    } else {
      $options['msg']= _("Cookie deleted !");
    }
    $user->id = 'Anonymous';
    $DBInfo->user=$user;
    $use_refresh=1;
  } else if (!empty($DBInfo->use_sendmail) and
    $options['login'] == _("E-mail new password") and
    $user->id=="Anonymous" and !empty($options['email']) and !empty($options['login_id'])) {
    # email new password

    $title='';
    if (!$use_any and $DBInfo->use_ticket) {
      if ($options['__seed'] and $options['check']) {
        $mycheck=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],4);
        if ($mycheck==$options['check'])
          $ok_ticket=1;
        else
          $title= _("Invalid ticket !");
      } else {
        $title= _("You need a ticket !");
      }
    } else {
      $ok_ticket=1;
    }
    $userdb=&$DBInfo->udb;
    if ($userdb->_exists($id)) {
      $user=$userdb->getUser($id);
    }
    if ($ok_ticket and $user->id != "Anonymous") {
      if ($options['email'] == $user->info['email']
        and $user->info['eticket']=='') {

        #make new password
        $mypass=base64_encode(getTicket(time(),$_SERVER['REMOTE_ADDR'],10));
        $mypass=substr($mypass,0,8);
        $options['password']=$mypass;
        $old_passwd=$user->info['password'];
        if ($DBInfo->use_safelogin) {
          $ret=$user->setPasswd(md5($mypass),md5($mypass),1);
        } else {
          $ret=$user->setPasswd($mypass,$mypass);
        }
        $new_passwd=$user->info['password'];
        $user->info['password']=$old_passwd;
        $user->info['npassword']=$new_passwd;

        #make ticket
        $ticket=md5(time().$user->id.$options['email']);
        $user->info['nticket']=$ticket.".".$options['email'];

        // save join agreement
        if (!empty($DBInfo->use_agreement) and !empty($options['joinagreement'])) {
          $user->info['join_agreement'] = 'agree';
          if (!empty($DBInfo->agreement_version))
            $user->info['join_agreement_version'] = $DBInfo->agreement_version;
        }

        $userdb->saveUser($user); # XXX

        $opts['subject']="[$DBInfo->sitename] "._("New password confirmation");
        $opts['email']=$options['email'];
        $opts['id']='nobody';
        $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));

        $body=_("Please confirm your new password")."\n".$body."\n";

        $body.=sprintf(_("Your new password is %s"),$mypass)."\n\n";
        $body.=_("Please change your password later")."\n";

        $ret=wiki_sendmail($body,$opts);
        if (is_array($ret)) {
          $title=_("Fail to e-mail notification !");
          $options['msg']=$ret['msg'];
        } else {
          $title=_("New password is sent to your e-mail !");
          $options['msg']=_("Please check your e-mail");
        }
      } else {
        if ($options['email'] != $user->info['email']) {
          $title=_("Fail to e-mail notification !");
          $options['msg']=_("E-mail mismatch !");
        } else {
          $title=_("Invalid request");
          $options['msg']=_("Please confirm your e-mail address first !");
        }
      }
    } else {
      if (!$ok_ticket) {
        $title=_("Invalid ticket !");
      } else {
        $title=_("ID and e-mail mismatch !");
      }
      $options['msg']=_("Please try again or make a new profile");
    }
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $formatter->send_footer("",$options);
    return;
  } else if ($user->id=="Anonymous" and !empty($options['login_id']) and
    (($options['password'] and $options['passwordagain']) or
     ($DBInfo->use_safelogin and $options['email'])) ) {
    # create profile

    $title='';
    if (!$use_any and !empty($DBInfo->use_ticket)) {
      if ($options['__seed'] and $options['check']) {
        $mycheck=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],4);
        if ($mycheck==$options['check'])
          $ok_ticket=1;
        else
          $title= _("Invalid ticket !");
      } else {
        $title= _("You need a ticket !");
      }
    } else {
      $ok_ticket=1;
    }
    $id=$user->getID($options['login_id']);
    if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i', $id)) {
      if (($ret = verify_email($id)) < 0) {
        $ret = -$ret;
        $options['msg'].='<br />'.'ERROR Code: '.$ret;
        $options['msg'].='<br/>'._("Invalid email address or can't verify it.");
      } else {
        $options['email'] = $id;
        $user->setID($id);
      }
    } else
    if (!preg_match("/\//",$id)) $user->setID($id); // protect http:// style id

    if (!empty($DBInfo->use_agreement) and empty($options['joinagreement'])) {
      $title= _("Please check join agreement.");
    } else
    if ($ok_ticket and $user->id != "Anonymous") {
       if (!empty($DBInfo->use_safelogin)) {
          $mypass=base64_encode(getTicket(time(),$_SERVER['REMOTE_ADDR'],10));
          $mypass=substr($mypass,0,8);
          $options['password']=$mypass;
          $ret=$user->setPasswd(md5($mypass),md5($mypass),1);
       } else {
          $ret=$user->setPasswd($options['password'],$options['passwordagain']);
       }
       if (!empty($DBInfo->password_length) and (strlen($options['password']) < $DBInfo->password_length)) $ret=0;
       if ($ret <= 0) {
           if ($ret==0) $title= _("too short password!");
           else if ($ret==-1) $title= _("mismatch password!");
           else if ($ret==-2) $title= _("not acceptable character found in the password!");
       } else {
           if ($ret < 8 and empty($DBInfo->use_safelogin))
              $options['msg']=_("Your password is too simple to use as a password !");
           $udb=$DBInfo->udb;
           if ($options['email']) {
             if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               if (($ret = verify_email($options['email'])) < 0) {
                 $options['email'] = ''; // reset email address
                 $ret = -$ret;
                 $options['msg'].='<br />'.'ERROR Code: '.$ret;
                 $options['msg'].='<br/>'._("Can't verify E-mail address! Please check your email address.");
               }
             } else
               $options['msg'].='<br/>'._("Your email address is not valid");
           }

           if ($udb->isNotUser($user)) {
             if (!empty($DBInfo->no_register)) {
               $options['msg']=_("Fail to register");
               $options['err']=_("You are not allowed to register on this wiki");
               $options['err'].="\n"._("Please contact WikiMasters");
               do_invalid($formatter,$options);
               return;
             }
             $title= sprintf(_("Successfully added as '%s'"), _html_escape($user->id));
             $options['id']=$user->id;
             $ticket=md5(time().$user->id.$options['email']);
             $user->info['eticket']=$ticket.".".$options['email'];
             if (!empty($DBInfo->use_safelogin)) {
               $options['msg'] =
                 sprintf(_("Successfully added as '%s'"),$user->id);
               $options['msg'].= '<br />'._("Please check your mailbox");
             }
             $args = array();
             if ($options['email'] == $id or !empty($DBInfo->register_confirm_email))
               $args = array('suspended'=>1);
             if (!empty($DBInfo->register_confirm_admin))
               $args = array('suspended'=>1);
             if (!empty($DBInfo->register_confirm_admin)) {
               if (!empty($options['msg']))
                 $options['msg'].= '<br />';
               $options['msg'].= _("Your need to wait until your ID activated by admin");
             }

             // save join agreement
             if (!empty($DBInfo->use_agreement) and !empty($options['joinagreement'])) {
               $user->info['join_agreement'] = 'agree';
               if (!empty($DBInfo->agreement_version))
                 $user->info['join_agreement_version'] = $DBInfo->agreement_version;
             }

             if (empty($DBInfo->use_safelogin) && empty($args['suspended']))
               $formatter->header($user->setCookie());

             $ret = $udb->addUser($user, $args);

             # XXX
             if (!empty($options['email']) and preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
               $body = '';
               if (!empty($DBInfo->email_register_header) and file_exists($DBInfo->email_register_header)) {
                 $body = file_get_contents($DBInfo->email_register_header);
                 $body = str_replace(array('@sitename@'), array($DBInfo->sitename), $body);
               }
               $body.=_("Please confirm your email address")."\n\n";
               $body.=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
               $body.= "\n";
               if (!empty($DBInfo->use_safelogin)) {
                 $body.="\n".sprintf(_("Your initial password is %s"),$mypass)."\n\n";
                 $body.=_("Please change your password later")."\n";
               }
               $ret = wiki_sendmail($body,$options);
               if (is_array($ret)) {
                 $options['msg'].=$ret['msg'];
               } else {
                 $options['msg'].='<br/>'._("Confirmation E-mail sent");
               }
             }
           } else {# already exist user
             $user=$udb->getUser($user->id);
             if ($user->checkPasswd($options['password'])=== true) {
               $options['msg'].= sprintf(_("Successfully login as '%s'"),$id);
               $options['id']=$user->id;
               $formatter->header($user->setCookie());
               $udb->saveUser($user); # XXX

               if (function_exists('_session_start'))
                 _session_start(null, $user->id);
             } else {
               $title = _("Invalid password !");
             }
           }
       }
    } else if (empty($title))
       $title= _("Invalid username !");
  } else if ($user->id != "Anonymous") {
    # save profile
    $udb=&$DBInfo->udb;
    $userinfo=$udb->getUser($user->id);

    if (!empty($options['password']) and !empty($options['passwordagain'])) {
      $chall=0;
      if (!empty($DBInfo->use_safelogin)) {
        if (isset($options['_chall'])) {
          $chall= $options['challenge'];
        } else {
          $chall= rand(100000);
          $options['password']=hmac($chall,$options['password']);
        }
      }
      //echo 'chall=',$chall,' ',$options['password'];
      if ($userinfo->checkPasswd($options['password'],$chall)
         === true) {
        if ($DBInfo->use_safelogin) {
          $mypass=md5($options['passwordagain']); // XXX
          $ret=$userinfo->setPasswd($mypass,$mypass,1);
        } else
          $ret=$userinfo->setPasswd($options['passwordagain']);

        if ($ret <= 0) {
          if ($ret==0) $title= _("too short password!");
          else if ($ret==-1)
            $title= _("mismatch password !");
          else if ($ret==-2)
            $title= _("not acceptable character found in the password!");
          $options['msg']= _("Password is not changed !");
        } else {
          $title= _("Password is changed !");
          if ($ret < 8)
            $options['msg']=_("Password is too simple to use as a password !");
        }
      } else {
        $title= _("Invalid password !");
        $options['msg']=_("Password is not changed !");
      }
    }
    if (isset($options['user_css']))
      $userinfo->info['css_url']=$options['user_css'];
    if (isset($options['timezone'])) {
      list($hour,$min)=explode(':',$options['timezone']);
      $min=$min*60;
      $min=($hour < 0) ? -1*$min:$min;
      $tz_offset=$hour*3600 + $min;
      $userinfo->info['tz_offset']=$tz_offset;
    }
    if (!empty($DBInfo->use_agreement) and !empty($options['joinagreement'])) {
      $userinfo->info['join_agreement'] = 'agree';
      if (!empty($DBInfo->agreement_version))
        $userinfo->info['join_agreement_version'] = $DBInfo->agreement_version;
    }

    $button_check_email_again = !empty($options['button_check_email_again']) ? 1 : 0;
    if ($button_check_email_again and !empty($userinfo->info['eticket'])) {
      list($dummy, $email) = explode('.', $userinfo->info['eticket'], 2);
      if (!empty($email))
        $options['email'] = $email;
    }

    if (!empty($options['email']) and ($options['email'] != $userinfo->info['email'])) {
      if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
        if (($ret = verify_email($options['email'])) < 0) {
          $ret = -$ret;
          $options['msg'].='<br />'.'ERROR Code: '.$ret;
          $options['msg'].='<br />'._("Invalid email address or can't verify it.");
        } else {
          $ticket=md5(time().$userinfo->info['id'].$options['email']);
          $userinfo->info['eticket']=$ticket.".".$options['email'];
          $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
          $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
          $body=_("Please confirm your email address")."\n".$body;
          $ret = wiki_sendmail($body,$options);

          if (is_array($ret)) {
            $options['msg']=$ret['msg'];
          } else {
            $options['msg']=_("E-mail confirmation mail sent");
          }
        }
      } else {
        $options['msg']=_("Your email address is not valid");
      }
    }
    if (!empty($userinfo->info['idtype']) and $userinfo->info['idtype']=='openid' and
      isset($options['nick']) and ($options['nick'] != $userinfo->info['nick'])) {
      $nick = $userinfo->getID($options['nick']);

      // nickname check XXX
      if (!$udb->_exists($nick)) $userinfo->info['nick']=$nick;
      else $options['msg']=_("Your Nickname already used as ID in this wiki");
    }
    $udb->saveUser($userinfo);
    #$options['css_url']=$options['user_css'];
    if (!isset($options['msg']))
      $options['msg']=_("Profiles are saved successfully !");
  } else if ($user->id == "Anonymous" and isset($options['openid_url'])) {
    # login with openid
    include_once('lib/openid.php');      

    $process_url = qualifiedUrl($formatter->link_url("UserPreferences", "?action=userform"));
    $trust_root = qualifiedUrl($formatter->link_url(""));

    $openid = new SimpleOpenID;
	  $openid->SetIdentity($options['openid_url']);
	  $openid->SetTrustRoot($trust_root);
	  $openid->SetRequiredFields(array('nickname','email','fullname'));
	  $openid->SetOptionalFields(array('language','timezone'));

	  if ($openid->GetOpenIDServer()){
		  $openid->SetApprovedURL($process_url);  	// Send Response from OpenID server to this script
      $openid->Redirect(); 	// This will redirect user to OpenID Server
      return;
	  } else {
		  $error = $openid->GetError();
		  #echo "ERROR CODE: " . $error['code'] . "<br>";
		  #echo "ERROR DESCRIPTION: " . $error['description'] . "<br>";
      $options["msg"] = sprintf(_("Authentication request was failed: %s"),$error['description']);
    }
  } else if (!empty($options['openid_mode']) and $options['openid_mode']=='id_res') { // OpenID result
    include_once('lib/openid.php');      

    if ( !preg_match ('/utf-?8/i', $DBInfo->charset) ) {
      $options['openid_sreg_nickname'] =
        iconv ('utf-8', $DBInfo->charset, $options['openid_sreg_nickname']);
      $options['openid_sreg_fullname'] =
        iconv ('utf-8', $DBInfo->charset, $options['openid_sreg_fullname']);
    }

    $openid = new SimpleOpenID;
	  $openid->SetIdentity($options['openid_identity']);
	  $openid_validation_result = $openid->ValidateWithServer();
    if ($openid_validation_result == true) { // OK HERE KEY IS VALID
      $userdb=&$DBInfo->udb;
      // XXX
      $user->setID($options['openid_identity']); // XXX
      if (!empty($options['openid_language'])) $user->info['language']=strtolower($options['openid_sreg_language']);
      //$user->info['tz_offset']=$options['openid_timezone'];

      if ($userdb->_exists($options['openid_identity'])) {
        $user=$userdb->getUser($options['openid_identity']);
        $user->info['idtype']='openid';
        $options['msg'].= sprintf(_("Successfully login as '%s' via OpenID."),$options['openid_identity']);
        $formatter->header($user->setCookie());
        $userdb->saveUser($user); // always save

        if (function_exists('_session_start'))
          _session_start(null, $user->id);
      } else {
        if (!empty($DBInfo->no_register) and $DBInfo->no_register == 1) {
          $options['msg']=_("Fail to register");
          $options['err']=_("You are not allowed to register on this wiki");
          $options['err'].="\n"._("Please contact WikiMasters");
          do_invalid($formatter,$options);
          return;
        }
        if ($options['openid_sreg_nickname']) {
          $nick=$user->getID($options['openid_sreg_nickname']);
          if (!$userdb->_exists($nick)) $user->info['nick']=$nick;
          else $options['msg']=sprintf(_("Your Nickname %s already used as ID in this Wiki."),$nick);
        }
        $user->info['email']=$options['openid_sreg_email'];
        $user->info['idtype']='openid';
        $userdb->addUser($user);
        $formatter->header($user->setCookie());
        $userdb->saveUser($user);
        $options["msg"] .=
          sprintf(_("OpenID Authentication successful and saved as %s."),$options['openid_identity']);
      }
      $options['id']=$user->id;
	  } else if($openid->IsError() == true) { // ON THE WAY, WE GOT SOME ERROR
		  $error = $openid->GetError();
      $options["msg"] = sprintf(_("Authentication request was failed: %s"),$error['description']);
	  } else {											// Signature Verification Failed
      $options["msg"] = _("Invalid OpenID Authentication request");
		  echo "INVALID AUTHORIZATION";
	  }
  } else if (!empty($DBInfo->use_agreement) and $options['login'] == _("Make profile")) {
    $options['agreement'] = 1;
    $form = macro_UserPreferences($formatter, '', $options);
  } else {
    $options["msg"] = _("Invalid request");
  }

  $myrefresh='';
  if (!empty($DBInfo->use_refresh) and !empty($use_refresh)) {
    $sec=$DBInfo->use_refresh - 1;
    if (!empty($options['return_url']))
      $lnk = $options['return_url'];
    else
      $lnk = $formatter->link_url($formatter->page->urlname,'?action=show');
    $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
  }

  $formatter->send_header($myrefresh,$options);
  $formatter->send_title($title,"",$options);
  if (!$title && (empty($DBInfo->control_read) or $DBInfo->security->is_allowed('read',$options)) ) {
    $lnk=$formatter->link_to('?action=show');
    if (empty($form))
      echo sprintf(_("return to %s"), $lnk);
    else
      echo $form;
  } else {
    if (!empty($form)) print $form;
#    else $formatter->send_page("Goto UserPreferences");
  }
  $formatter->send_footer("",$options);
}

?>
