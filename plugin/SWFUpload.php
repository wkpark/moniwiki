<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// SWFUpload plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2006-12-06
// Name: SWF Upload
// Description: SWF Upload Plugin
// URL: http://labb.dev.mammon.se/swfupload/ MoniWikiDev:SWFUpload
// Version: $Revision$
// License: GPL
//
// Usage: [[SWFUpload]]
//
// $Id$

function macro_SWFUpload($formatter,$value,$opts=array()) {
    global $DBInfo;

    if ($DBInfo->swfupload_depth > 2) {
        $depth=$DBInfo->swfupload_depth;
    } else {
        $depth=2;
    }

    if ($DBInfo->nosession) { // ip based
        $myid=md5($_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI'); // FIXME
    } else {
        $myid=session_id();
    }

    $prefix=substr($myid,0,$depth);
    $mysubdir=$prefix.'/'.$myid.'/';
    $myoptions="<input type='hidden' name='mysubdir' value='$mysubdir' />";

    if ($DBInfo->use_lightbox) {
        $myoptions.="\n<input type='hidden' name='use_lightbox' value='1' />";
    } else {
        $myoptions.="\n<input type='hidden' name='use_lightbox' value='0' />";
    }

    if ($formatter->preview) {
        $js_tag=1;$jsPreview=' class="previewTag"';
        $uploader='UploadForm';
    } else if ($options['preview']) {
        $jsPreview=' class="previewTag"';
    }

    $default_allowed='*.gif;*.jpg;*.png;*.psd';
    $allowed=$default_allowed;
    if ($DBInfo->pds_allowed) {
        $allowed='*.'.str_replace('|',';*.',$DBInfo->pds_allowed);
    }

    $swfupload_num=$GLOBALS['swfupload_num'] ? $GLOBALS['swfupload_num']:0;

    // get already uploaded files list
    $uploaded='';
    if (is_dir($DBInfo->upload_dir.'/.swfupload/'.$mysubdir)) {
        $mydir=$DBInfo->upload_dir.'/.swfupload/'.$mysubdir;
        $handle = @opendir($mydir);
        if ($handle) {
            $files=array();
            while ($file = readdir($handle)) {
                if (is_dir($mydir.$file) or $file[0]=='.') continue;
                $files[] = $file;
            }
            closedir($handle);

            foreach ($files as $f) {
                $uploaded.="<li id='$f'><input checked=\"checked\" type=\"checkbox\">".
                    "<a href='javascript:showImgPreview(\"$f\")'>$f</a></li>";
            }
        }
    }

    //
    // check already uploaed files
    //
    if (1) {
        $value=$formatter->page->urlname;
        $key=$DBInfo->pageToKeyname($formatter->page->name);
        $mydir=$DBInfo->upload_dir."/$key";

        $handle = @opendir($mydir);
        if ($handle) {
            $files=array();
            while ($file = readdir($handle)) {
                if (is_dir($mydir.$file) or $file[0]=='.') continue;
                $files[] = $file;
            }
            closedir($handle);

            foreach ($files as $f) {
                $uploaded.="<li><input checked=\"checked\" disabled=\"disabled\" type=\"checkbox\">".
                    "<a href='javascript:showImgPreview(\"$f\",true)'>$f</a></li>";
            }
        }

    }

    if (!$swfupload_num) {
        $formatter->register_javascripts(array(
            'js/swfobject.js',
            'SWFUpload/mmSWFUpload.js',
            'SWFUpload/moni.js',
        ));
    }

    $swf_css=<<<CSS
<style type="text/css">
@import url("$DBInfo->url_prefix/local/SWFUpload/swfupload.css");
</style>
CSS;

    $btn=_("Files...");
    $btn2=_("Upload");
    $prefix=qualifiedUrl($DBInfo->url_prefix.'/local');
    $action=$formatter->link_url($formatter->page->urlname);
    $action2=$action.'----swfupload';
    if ($mysubdir) $action2.='----'.$mysubdir;
    $action2=qualifiedUrl($action2);
    $myprefix=qualifiedUrl($DBInfo->url_prefix);
    $form=<<<EOF
	<div id="SWFUpload">
		<form action="" onsubmit="return false;">
			<input type="file" name="upload" />
			<input type="submit" value="Upload" onclick="javascript:alert('disabled...'); return false;" />
		</form>
	</div>

        <script type="text/javascript">
        /*<![CDATA[*/
		mmSWFUpload.init({
			//debug : true,
			upload_backend : "$action2",
			target : "SWFUpload",
			// cssClass : "myCustomClass",
			_prefix : "$myprefix",
			allowed_filesize : "40000",
			allowed_filetypes : "$allowed",
			upload_start_callback : 'uploadStart',
			upload_progress_callback : 'uploadProgress',
			upload_complete_callback : 'uploadComplete',
			// upload_error_callback : 'uploadError',
                        upload_cancel_callback : 'uploadCancel'
                });
        /*]]>*/
	</script>

	<div class="fileList">
	<table border='0' cellpadding='0'>
	<tr>
	<td>
	<div id="previewAlign">
	</div>
	<div id="filePreview"$jsPreview>
	</div>
	</td>
	<td>
	<div id="filesDisplay">
                <form target='_blank' method='POST' action='$action'>
		<ul id="mmUploadFileListing">$uploaded</ul>
		<span id="fileButton">
                <input type='hidden' name='action' value='swfupload' />
                <input type='hidden' name='popup' value='1' />
                $myoptions
		<input type='button' value="$btn" onclick='javascript:mmSWFUpload.callSWF();' />
		<input type='submit' value="$btn2" onclick='javascript:fileSubmit(this);' />
		</span>
                </form>
	</div>
	</td>
        </tr>
	<tr>
	<td colspan='2'>
		<div id="fileProgressInfo"></div>
	</td>
	</tr>
	</table>
	</div>
EOF;
    return $swfupload_script.$swf_css.$form;
}

// do_UploadFile wrapper
function do_SWFUpload($formatter,$options=array()) {
    global $DBInfo;

    $swfupload_dir=$DBInfo->upload_dir.'/.swfupload';
    $mysubdir='';
    if(!is_dir($swfupload_dir)) {
        $om=umask(000);
        mkdir($swfupload_dir, 0777);
        umask($om);

        $fp=fopen($swfupload_dir.'/.htaccess','w');
        if ($fp) {
            $htaccess=<<<EOF
Options -Indexes
Order deny,allow\n
EOF;
            fwrite($fp,$htaccess);
            fclose($fp);
        }
    }

    // debug
    //$fp=fopen($swfupload_dir.'/swflog.txt','w+');
    //foreach ($options as $k=>$v) {
    //    if (is_string($v))
    //         fwrite($fp,sprintf("%s=>%s\n",$k,$v));
    //}
    // set the personal subdir
    if ($options['value'] and preg_match('/^[a-z0-9\/]+$/i',$options['value'])) {
        $mysubdir=$options['value'];

        list($dum,$myval,$dum2)=explode('/',$options['value'],3); // XXX
        if(!is_dir($swfupload_dir.'/'.$mysubdir)) {
            $om=umask(000);
            _mkdir_p($swfupload_dir.'/'.$mysubdir, 0777);
            umask($om);
        }
    }
    //fclose($fp);


    //move the uploaded file
    if (isset($_FILES['Filedata']['tmp_name'])) {
        move_uploaded_file($_FILES['Filedata']['tmp_name'],
            $swfupload_dir.'/'.$mysubdir.$_FILES['Filedata']['name']);
        return;
    } else if (is_array($options['MYFILES'])) {
        include_once('plugin/UploadFile.php');

        $options['_pds_subdir']=$mysubdir; // a temporary pds dir
        $options['_pds_remove']=1; // remove all files in pds dir
        do_UploadFile($formatter,$options);
    } else {
        $formatter->send_header("",$options);
        $formatter->send_title("","",$options);
        $out= macro_SWFUpload($formatter,'');
        print $formatter->get_javascripts();
        print $out;
        if (!in_array('UploadedFiles',$formatter->actions))
            $formatter->actions[]='UploadedFiles';
        $formatter->send_footer("",$options);
    }
}

/*
 * vim:et:sts=4:sw
 */
