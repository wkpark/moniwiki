<?php
// Copyright 2006-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// SWFUpload plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2006-12-06
// Name: SWF Upload
// Description: SWF Upload Plugin
// URL: http://labb.dev.mammon.se/swfupload/ MoniWikiDev:SWFUpload
// Version: $Revision: 1.22 $
// License: GPL
//
// Usage: [[SWFUpload]]
//
// $Id: SWFUpload.php,v 1.22 2010/08/23 15:14:10 wkpark Exp $

function macro_SWFUpload($formatter,$value,$opts=array()) {
    global $DBInfo;

    $swf_ver = 10;
    if (!empty($DBInfo->swfupload_depth) and $DBInfo->swfupload_depth > 2) {
        $depth=$DBInfo->swfupload_depth;
    } else {
        $depth=2;
    }

    if (session_id() == '') { // ip based
        $seed = $_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI';
        if ($DBInfo->seed)
            $seed .= $DBInfo->seed;
        $myid = md5($seed); // FIXME
    } else {
        if (!empty($_SESSION['_swfupload']))
            $myid = $_SESSION['_swfupload'];
        else {
            $myid=session_id();
            $_SESSION['_swfupload'] = $myid;
        }
    }

    $prefix=substr($myid,0,$depth);
    $mysubdir=$prefix.'/'.$myid.'/';
    $myoptions="<input type='hidden' name='mysubdir' value='$mysubdir' />";

    if (!empty($DBInfo->use_lightbox)) {
        $myoptions.="\n<input type='hidden' name='use_lightbox' value='1' />";
    } else {
        $myoptions.="\n<input type='hidden' name='use_lightbox' value='0' />";
    }

    $jsPreview = '';
    if (!empty($formatter->preview)) {
        $js_tag=1;$jsPreview=' class="previewTag"';
        $uploader='UploadForm';
    } else if (!empty($options['preview'])) {
        $jsPreview=' class="previewTag"';
    }

    $default_allowed='*.gif;*.jpg;*.png;*.psd';
    $allowed=$default_allowed;
    $allowed_re = '.*';
    if (!empty($DBInfo->pds_allowed)) {
        $allowed='*.'.str_replace('|',';*.',$DBInfo->pds_allowed);
        $allowed_re = $DBInfo->pds_allowed;
    }

    $swfupload_num=!empty($GLOBALS['swfupload_num']) ? $GLOBALS['swfupload_num']:0;

    // get already uploaded files list
    $uploaded='';
    if (is_dir($DBInfo->upload_dir.'/.swfupload/'.$mysubdir)) {
        $mydir=$DBInfo->upload_dir.'/.swfupload/'.$mysubdir.'/';
        $handle = @opendir($mydir);
        if ($handle) {
            $files=array();
            while ($file = readdir($handle)) {
                if (is_dir($mydir.$file) or $file[0]=='.') continue;
                if (preg_match('/\.('.$allowed_re.')$/i', $file)) {
                    $files[] = $file;
                } else {
                    @unlink($mydir.$file); // force remove
                }
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

        // support hashed upload dir
        if (!is_dir($mydir) and !empty($DBInfo->use_hashed_upload_dir)) {
            $prefix = get_hashed_prefix($key);
            $mydir = $DBInfo->upload_dir.'/'.$prefix.$key;
        }

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

    if (empty($swfupload_num)) {
        if ($swf_ver == 9) {
            $formatter->register_javascripts(array(
                'js/swfobject.js',
                'SWFUpload/mmSWFUpload.js',
                'SWFUpload/preview.js',
                'SWFUpload/moni.js',
            ));
        } else {
            $formatter->register_javascripts(array(
                'js/swfobject.js',
                'SWFUpload/swfupload.js',
                'SWFUpload/swfupload.swfobject.js',
                'SWFUpload/swfupload.queue.js',
                'SWFUpload/preview.js',
                'SWFUpload/handlers.js',
                #'SWFUpload/fileprogress.js',
            ));
        }
    }

    $swf_css=<<<CSS
<style type="text/css">
@import url("$DBInfo->url_prefix/local/SWFUpload/swfupload.css");
</style>
CSS;

    $btn=_("Files...");
    $btn2=_("Upload files");
    $btn3=_("Cancel All files");
    $prefix=qualifiedUrl($DBInfo->url_prefix.'/local');
    $action=$formatter->link_url($formatter->page->urlname);
    $action2=$action.'----swfupload';
    if ($mysubdir) $action2.='----'.$mysubdir;
    $action2=qualifiedUrl($action2);
    $myprefix=qualifiedUrl($DBInfo->url_prefix);
    $swfupload_script = '';

    if ($swf_ver == 9) {
        $swf_js=<<<EOF
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
EOF;
        $submit_btn="<input type='button' value='$btn' onclick='javascript:mmSWFUpload.callSWF();' />\n";
        $cancel_btn='';
    } else {
        $submit_btn='<span id="spanButtonPlaceHolder"><input type="file" name="upload" /></span>';
        $cancel_btn="<button id='btnCancel' onclick='swfu.cancelQueue();' disabled='disabled' ><span>".$btn3."</span></button>\n";
        $swf_js=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
var swfu;

SWFUpload.onload = function () {
    var settings = {
        flash_url : "$DBInfo->url_prefix/local/SWFUpload/swfupload.swf",
        upload_url: "$action2", // Relative to the SWF file
        file_size_limit : "10 MB",
        file_types : "$allowed",
        file_types_description : "Files",
        file_upload_limit : 100,
        file_queue_limit : 0,
        custom_settings : {
            progressTarget : "fsUploadProgress",
            cancelButtonId : "btnCancel"
        },
        debug: false, // true

        // Button Settings
        button_image_url : "$DBInfo->url_prefix/local/SWFUpload/images/btn0.png",
        button_text : '<span class="button" style="text-align:center">$btn</span>',
        button_text_style : '.button {font-family:Gulim,Sans-serif;text-align:center;}',
        button_text_top_padding : 3,
        button_placeholder_id : "spanButtonPlaceHolder",
        button_width: 61,
        button_height: 22,
        button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
        button_cursor: SWFUpload.CURSOR.HAND,

        // The event handler functions are defined in handlers.js
        swfupload_loaded_handler : swfUploadLoaded,
        file_queued_handler : fileQueued,
        file_queue_error_handler : fileQueueError,
        file_dialog_complete_handler : fileDialogComplete,
        upload_start_handler : uploadStart,
        upload_progress_handler : uploadProgress,
        upload_error_handler : uploadError,
        upload_success_handler : uploadSuccess,
        upload_complete_handler : uploadComplete,
        queue_complete_handler : queueComplete, // Queue plugin event
        
        // SWFObject settings
        minimum_flash_version : "9.0.28",
        swfupload_pre_load_handler : swfUploadPreLoad,
        swfupload_load_failed_handler : swfUploadLoadFailed
    };

    swfu = new SWFUpload(settings);
}
/*]]>*/
</script>

EOF;

    }
    $form=<<<EOF
	<div id="SWFUpload" style='display:none'>
		<form action="" onsubmit="return false;">
			<input type="file" name="upload" />
			<input type="submit" value="Upload" onclick="javascript:alert('disabled...'); return false;" />
		</form>
	</div>
$swf_js
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
            <form id="form1" target='_blanl' action="$action" method="POST" enctype="multipart/form-data">
	        <ul id="mmUploadFileListing">$uploaded</ul>
		<span id="fileButton">
                <input type='hidden' name='action' value='swfupload' />
                <input type='hidden' name='value' value='$mysubdir' />
                <input type='hidden' name='popup' value='1' />
                $myoptions
                $submit_btn
		<button type='submit' onclick='javascript:fileSubmit(this);' ><span>$btn2</span></button>
                $cancel_btn
		</span>
            </form>
        </div>
        <noscript style="background-color: #FFFF66; border-top: solid 4px #FF9966; border-bottom: solid 4px #FF9966; margin: 10px 25px; padding: 10px 15px;">
            We're sorry.  SWFUpload could not load.  You must have JavaScript enabled to enjoy SWFUpload.
        </noscript>
        <div id="divLoadingContent" class="content" style="background-color: #FFFF66; border-top: solid 4px #FF9966; border-bottom: solid 4px #FF9966; margin: 10px 25px; padding: 10px 15px; display: none;">
            SWFUpload is loading. Please wait a moment...
        </div>
        <div id="divLongLoading" class="content" style="background-color: #FFFF66; border-top: solid 4px #FF9966; border-bottom: solid 4px #FF9966; margin: 10px 25px; padding: 10px 15px; display: none;">
            SWFUpload is taking a long time to load or the load has failed.  Please make sure that the Flash Plugin is enabled and that a working version of the Adobe Flash Player is installed.
        </div>
        <div id="divAlternateContent" class="content" style="background-color: #FFFF66; border-top: solid 4px #FF9966; border-bottom: solid 4px #FF9966; margin: 10px 25px; padding: 10px 15px; display: none;">

            We're sorry.  SWFUpload could not load.  You may need to install or upgrade Flash Player.
            Visit the <a href="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash">Adobe website</a> to get the Flash Player.
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

    // check allowed file extensions
    $allowed_re = '.*';
    if (!empty($DBInfo->pds_allowed)) {
        $allowed_re = $DBInfo->pds_allowed;
    }

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
Order deny,allow
deny from all\n
EOF;
            fwrite($fp,$htaccess);
            fclose($fp);
        }
    }

    // check subdir
    if (!empty($DBInfo->swfupload_depth) and $DBInfo->swfupload_depth > 2) {
        $depth=$DBInfo->swfupload_depth;
    } else {
        $depth=2;
    }

    $seed = $_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI';
    if ($DBInfo->seed)
        $seed .= $DBInfo->seed;
    $myid = md5($seed); // FIXME
    if (session_id() != '') { // ip based
        if (0 and $_SESSION['_swfupload']) // XXX flash bug?
            $myid = $_SESSION['_swfupload'];
        else if (!empty($options['value']) and ($p = strpos($options['value'], '/')) !== false) {
            $tmp = explode('/', $options['value']);
            #list($dum,$myid,$dum2)=explode('/',$options['value'],3);
            $myid = $tmp[1];
        }
    }

    $prefix=substr($myid,0,$depth);
    $mysubdir=$prefix.'/'.$myid.'/';

    // debug
    //$options['_mysubdir']=$mysubdir;
    //$fp=fopen($swfupload_dir.'/swflog.txt','a+');
    //foreach ($options as $k=>$v) {
    //    if (is_string($v))
    //         fwrite($fp,sprintf("%s=>%s\n",$k,$v));
    //}
    //foreach ($_SESSION as $k=>$v) {
    //    if (is_string($v))
    //         fwrite($fp,sprintf("%s=>%s\n",$k,$v));
    //}
    //fwrite($fp,"------------------------\n");
    //fclose($fp);
    // set the personal subdir
    if (!empty($options['value']) and preg_match('/^[a-z0-9\/]+$/i',$options['value'])) {
        //if ($mysubdir == $options['value']) // XXX check subdir
        //    $mysubdir = $options['value'];

        list($dum,$myval,$dum2)=explode('/',$options['value'],3); // XXX
        if(!is_dir($swfupload_dir.'/'.$mysubdir)) {
            $om=umask(000);
            _mkdir_p($swfupload_dir.'/'.$mysubdir, 0777);
            umask($om);
        }
    }

    //move the uploaded file
    if (isset($_FILES['Filedata']['tmp_name'])) {
        if (preg_match('/\.('.$allowed_re.')$/i', $_FILES['Filedata']['name'])) {
            move_uploaded_file($_FILES['Filedata']['tmp_name'],
                $swfupload_dir.'/'.$mysubdir.$_FILES['Filedata']['name']);
        }
        echo "Success";
        return;
    } else if (isset($options['MYFILES']) and is_array($options['MYFILES'])) {
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
