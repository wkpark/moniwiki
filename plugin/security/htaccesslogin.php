<?php
# a htaccesslogin security plugin for the MoniWiki
# by Hans-Juergen Tappe <tappe@hek.uni-karlsruhe.de>
#
# $Id: htaccesslogin.php,v 1.2 2010/06/22 08:06:39 wkpark Exp $
#

class Security_htaccesslogin extends Security_base {
  var $DB;

  function Security_htaccesslogin($DB="") {
    $this->DB=$DB;

    # BEGIN LOGIN
    $id=getenv('REMOTE_USER');
    if ($id != "") {
      $userdb=new UserDB($DB);
      $user=new WikiUser(); # get from COOKIE VARS

      if ($userdb->_exists($id)) {
        # login
        $user=$userdb->getUser($id);
        $options['id']=$user->id;
        $options['login_id']=$user->id;
        $dummy=$user->setCookie();
	$dummy=$userdb->saveUser($user);
      } else {
        # create account
        $user->id=$id;
        $options['id']=$user->id;
        #$ticket=md5(time().$user->id.$options['email']);
        #$user->info['eticket']='';
        $dummy=$user->setCookie();
        $dummy=$userdb->addUser($user);
      }
    }
    # END LOGIN
  }

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function is_allowed($action="read",&$options) {
    if (getenv('REMOTE_USER') == "" || $user->id == "Anonymous") {
      $options['err'].=sprintf(_("You are not allowed to '%s' on this page."),$action);
      $options['err'].="\n"._("Please contact the system administrator for htaccess based logins.");
      return 0;
    }
    return 1;
  }
}

?>
