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
    $udb->checkUser(&$user);
  }
  $id=$options['login_id'];

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

  if ($user->id == "Anonymous" and isset($options['id']) and isset($options['password']) and !isset($options['passwordagain'])) {
    # login
    $userdb=new UserDB($DBInfo);
    if ($userdb->_exists($id)) {
      $user=$userdb->getUser($id);
      if ($user->checkPasswd($options['password'])=== true) {
        $options['msg'] = sprintf(_("Successfully login as '%s'"),$id);
        $options['id']=$user->id;
        $formatter->header($user->setCookie());

        $userdb->saveUser($user); # XXX
      } else {
        $title = sprintf(_("Invalid password !"));
      }
    } else {
      if ($options['login_id'])
        $title= sprintf(_("\"%s\" is not exists on this wiki !"),$options['login_id']);
      else
        $title= _("Make new ID on this wiki");
     $form=macro_UserPreferences($formatter,'',$options);
    }
  } else if ($options['logout']) {
    # logout
    $formatter->header($user->unsetCookie());
    $title= _("Cookie deleted !");
  } else if ($user->id=="Anonymous" and $options['login_id'] and $options['password'] and $options['passwordagain']) {
    # create profile

    $id=$user->getID($options['login_id']);
    $user->setID($id);

    if ($user->id != "Anonymous") {
       $ret=$user->setPasswd($options['password'],$options['passwordagain']);
       if ($DBInfo->password_length and (strlen($options['password']) < $DBInfo->password_length)) $ret=0;
       if ($ret <= 0) {
           if ($ret==0) $title= _("too short password!");
           else if ($ret==-1) $title= _("mismatch password!");
           else if ($ret==-2) $title= _("not acceptable character found in the password!");
       } else {
           if ($ret < 8)
              $options['msg']=_("Your password is too simple to use as a password !");
           $udb=new UserDB($DBInfo);
           if ($options['email']) {
             if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               #$user->info['email']=$options['email'];
             } else
               $options['msg'].='<br/>'._("Your email address is not valid");
           }

           if ($udb->isNotUser($user)) {
             $title= _("Successfully added!");
             $options['id']=$user->id;
             $ticket=md5(time().$user->id.$options['email']);
             $user->info['eticket']=$ticket.".".$options['email'];
             $formatter->header($user->setCookie());
             $ret=$udb->addUser($user);

             # XXX
             if ($options['email'] and preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
               $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
               $body=_("Please confirm your email address")."\n".$body;
               wiki_sendmail($body,$options);
               $options['msg'].='<br/>'._("E-mail confirmation mail sent");
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
    } else
       $title= _("Invalid username !");
  } else if ($user->id != "Anonymous") {
    # save profile
    $udb=new UserDB($DBInfo);
    $userinfo=$udb->getUser($user->id);

    if ($options['password'] and $options['passwordagain']) {
      if ($userinfo->checkPasswd($options['password'])=== true) {
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
  }

  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  if (!$title)
    $formatter->send_page();
  else {
    if ($form) print $form;
#    else $formatter->send_page("Goto UserPreferences");
  }
  $formatter->send_footer("",$options);
}

?>
