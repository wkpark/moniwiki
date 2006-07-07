<?php
# A sample ACL file for Moniwiki
# $Id$
*	@ALL		allow	*
*	Anonymous	deny	*
*	Anonymous	allow	read,userform,rss_rc,aclinfo
MoniWiki	@ALL	deny	uploadfile,diff
ACL	@ALL		deny    edit,diff,info # a shared ACL file (not supported yet)
# set group members
#@KLDP	Hello,foobar,moniwiki
