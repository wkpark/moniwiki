<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a userform action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_userform($formatter,$options) {
  global $DBInfo;

  $user=new User(); # get cookie
  if ($user->id != 'Anonymous') { # XXX
    $udb=new UserDB($DBInfo);
    $udb->checkUser($user);
  }
  $id=$options['login_id'];

  $use_any=0;
  if ($DBInfo->use_textbrowsers) {
    if (is_string($DBInfo->use_textbrowsers))
      $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
        $_SERVER['HTTP_USER_AGENT']) ? 0:1;
    else
      $use_any= preg_match('/Lynx|w3m|links/',
        $_SERVER['HTTP_USER_AGENT']) ? 0:1;
  }

  # e-mail conformation
  if ($options['ticket'] and $id and $id!='Anonymous') {
    $userdb=new UserDB($DBInfo);
    if ($userdb->_exists($id)) {
       $user=$userdb->getUser($id);
       if ($user->info['eticket']==$options['ticket']) {
         list($dummy,$email)=explode('.',$options['ticket'],2);
         $user->info['email']=$email;
         $user->info['eticket']='';
         $userdb->saveUser($user);
         $title=_("Successfully confirmed");
         $options['msg']=_("Your e-mail address is confirmed successfully");
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
      $title=_("ID does not exists !");
      $options['msg']=_("Please try again to register your e-mail address");
    }
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $formatter->send_footer("",$options);
    return '';
  }

  if ($user->id == "Anonymous" and !empty($options['login_id']) and isset($options['password']) and !isset($options['passwordagain'])) {
    # login
    $userdb=new UserDB($DBInfo);
    if ($userdb->_exists($id)) {
      $user=$userdb->getUser($id);
      $login_ok=0;
      if ($DBInfo->use_safelogin) {
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
        $formatter->header($user->setCookie());

        $userdb->saveUser($user); # XXX
        $use_refresh=1;
      } else {
        $title = sprintf(_("Invalid password !"));
      }
    } else {
      if ($options['login_id'])
        $title= sprintf(_("\"%s\" does not exists on this wiki !"),$options['login_id']);
      else
        $title= _("Make new ID on this wiki");
     $form=macro_UserPreferences($formatter,'',$options);
    }
  } else if ($options['logout']) {
    # logout
    $formatter->header($user->unsetCookie());
    $options['msg']= _("Cookie deleted !");
    $use_refresh=1;
  } else if ($DBInfo->use_sendmail and
    $options['login'] == _("E-mail new password") and
    $user->id=="Anonymous" and $options['email'] and $options['login_id']) {
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
    $userdb=new UserDB($DBInfo);
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
  } else if ($user->id=="Anonymous" and $options['login_id'] and
    (($options['password'] and $options['passwordagain']) or
     ($DBInfo->use_safelogin and $options['email'])) ) {
    # create profile

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
    $id=$user->getID($options['login_id']);
    $user->setID($id);

    if ($ok_ticket and $user->id != "Anonymous") {
       if ($DBInfo->use_safelogin) {
          $mypass=base64_encode(getTicket(time(),$_SERVER['REMOTE_ADDR'],10));
          $mypass=substr($mypass,0,8);
          $options['password']=$mypass;
          $ret=$user->setPasswd(md5($mypass),md5($mypass),1);
       } else {
          $ret=$user->setPasswd($options['password'],$options['passwordagain']);
       }
       if ($DBInfo->password_length and (strlen($options['password']) < $DBInfo->password_length)) $ret=0;
       if ($ret <= 0) {
           if ($ret==0) $title= _("too short password!");
           else if ($ret==-1) $title= _("mismatch password!");
           else if ($ret==-2) $title= _("not acceptable character found in the password!");
       } else {
           if ($ret < 8 and !$DBInfo->use_safelogin)
              $options['msg']=_("Your password is too simple to use as a password !");
           $udb=new UserDB($DBInfo);
           if ($options['email']) {
             if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               #$user->info['email']=$options['email'];
             } else
               $options['msg'].='<br/>'._("Your email address is not valid");
           }

           if ($udb->isNotUser($user)) {
             if ($DBInfo->no_register) {
               $options['msg']=_("Fail to register");
               $options['err']=_("You are not allowed to register on this wiki");
               $options['err'].="\n"._("Please contact WikiMasters");
               do_invalid($formatter,$options);
               return;
             }
             $title= _("Successfully added!");
             $options['id']=$user->id;
             $ticket=md5(time().$user->id.$options['email']);
             $user->info['eticket']=$ticket.".".$options['email'];
             if ($DBInfo->use_safelogin) {
               $options['msg'] =
                 sprintf(_("Successfully added as '%s'"),$user->id);
               $options['msg'].= '<br />'._("Please check your mailbox");
             } else
               $formatter->header($user->setCookie());
             $ret=$udb->addUser($user);

             # XXX
             if ($options['email'] and preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
               $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
               $body=_("Please confirm your email address")."\n".$body;
               if ($DBInfo->use_safelogin) {
                 $body.="\n".sprintf(_("Your initial password is %s"),$mypass)."\n\n";
                 $body.=_("Please change your password later")."\n";
               }
               wiki_sendmail($body,$options);
               $options['msg'].='<br/>'._("Confirmation E-mail sent");
             }
           } else {# already exist user
             $user=$udb->getUser($user->id);
             if ($user->checkPasswd($options['password'])=== true) {
               $options['msg'].= sprintf(_("Successfully login as '%s'"),$id);
               $options['id']=$user->id;
               $formatter->header($user->setCookie());
               $udb->saveUser($user); # XXX
             } else {
               $title = _("Invalid password !");
             }
           }
       }
    } else if ($title=='')
       $title= _("Invalid username !");
  } else if ($user->id != "Anonymous") {
    # save profile
    $udb=new UserDB($DBInfo);
    $userinfo=$udb->getUser($user->id);

    if ($options['password'] and $options['passwordagain']) {
      $chall=0;
      if ($DBInfo->use_safelogin) {
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
    if ($options['email'] and ($options['email'] != $userinfo->info['email'])) {
      if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
        $ticket=md5(time().$userinfo->info['id'].$options['email']);
        $userinfo->info['eticket']=$ticket.".".$options['email'];
        $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
        $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
        $body=_("Please confirm your email address")."\n".$body;
        wiki_sendmail($body,$options);
        $options['msg']=_("E-mail confirmation mail sent");
      } else {
        $options['msg']=_("Your email address is not valid");
      }
    }
    $udb->saveUser($userinfo);
    #$options['css_url']=$options['user_css'];
    if (!isset($options['msg']))
      $options['msg']=_("Profiles are saved successfully !");
  } else if ($user->id == "Anonymous" and isset($options['openid_url'])) {
    # login with openid
    include_once('lib/openid.php');      
    session_start();

    $process_url = qualifiedUrl($formatter->link_url("UserPreferences", "?action=userform"));
    $trust_root = qualifiedUrl($formatter->link_url(""));

    $openid = new SimpleOpenID;
	  $openid->SetIdentity($options['openid_url']);
	  $openid->SetTrustRoot($trust_root);
	  $openid->SetRequiredFields(array('wikiname','email','fullname'));
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
  } else if ($options['id_res']) { // OpenID result
    include_once('lib/openid.php');      
    session_start();

    $openid = new SimpleOpenID;
	  $openid->SetIdentity($options['openid_identity']);
	  $openid_validation_result = $openid->ValidateWithServer();
    if ($openid_validation_result == true) { // OK HERE KEY IS VALID
      $userdb=new UserDB($DBInfo);
      // XXX
      if ($userdb->_exists($id)) {
              $user=$userdb->getUser($id);
              // check openid
      } else {
         $user->info['tz_offset']=$tz_offset; // XXX
         $udb->addUser($user);
         $udb->saveUser($user);
      }
		  $options['msg'] =  _("");
	  } else if($openid->IsError() == true) { // ON THE WAY, WE GOT SOME ERROR
		  $error = $openid->GetError();
      $options["msg"] = sprintf(_("Authentication request was failed: %s"),$error['description']);
	  } else {											// Signature Verification Failed
      $options["msg"] = _("Invalid OpenID Authentication request");
		  echo "INVALID AUTHORIZATION";
	  }
  }

  $myrefresh='';
  if ($DBInfo->use_refresh and $use_refresh) {
    $sec=$DBInfo->use_refresh - 1;
    $lnk=$formatter->link_url($formatter->page->urlname,'?action=show');
    $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
  }

  $formatter->send_header($myrefresh,$options);
  $formatter->send_title($title,"",$options);
  if (!$title && (!$DBInfo->control_read or $DBInfo->security->is_allowed('read',$options)) ) {
    $formatter->send_page();
  } else {
    if ($form) print $form;
#    else $formatter->send_page("Goto UserPreferences");
  }
  $formatter->send_footer("",$options);
}

?>
