<?php
// Copyright 2022 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// JSUpload plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2022-10-27
// Name: JS Upload
// Description: JS Upload Plugin
// Version: $Revision: 1.0 $
// License: GPL
//
// Usage: [[JSUpload]]
//

function macro_JSUpload($formatter,$value,$opts=array()) {
    global $DBInfo;

    if (!empty($DBInfo->jsupload_depth) and $DBInfo->jsupload_depth > 2) {
        $depth=$DBInfo->jsupload_depth;
    } else {
        $depth=2;
    }

    if (session_id() == '' || $DBInfo->user->id == 'Anonymous') { // ip based
        $seed = $_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI';
        if (!empty($DBInfo->seed))
            $seed .= $DBInfo->seed;
        if ($DBInfo->user->id != 'Anonymous')
            $seed .= $DBInfo->user->id;
        $myid = md5($seed);
    } else {
        if (!empty($_SESSION['.jsupload']))
            $myid = $_SESSION['.jsupload'];
        else {
            $myid=session_id();
            $_SESSION['.jsupload'] = $myid;
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

    $default_allowed='*.gif;*.jpg;*.jpeg;*.png;*.psd';
    $allowed=$default_allowed;
    $allowed_re = '.*';
    if (!empty($DBInfo->pds_allowed)) {
        $allowed='*.'.str_replace('|',';*.',$DBInfo->pds_allowed);
        $allowed_re = $DBInfo->pds_allowed;
    }

    $jsupload_num=!empty($GLOBALS['jsupload_num']) ? $GLOBALS['jsupload_num']:0;

    // get already uploaded files list
    $uploaded='';
    if (is_dir($DBInfo->upload_dir.'/.myupload/'.$mysubdir)) {
        $mydir=$DBInfo->upload_dir.'/.myupload/'.$mysubdir.'/';
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
                    "<a href=\"javascript:showImgPreview('$f')\">$f</a></li>";
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
                    "<a href=\"javascript:showImgPreview('$f',true)\">$f</a></li>";
            }
        }

    }

    $formatter->register_javascripts(array(
        'JSUpload/preview.js',
        'JSUpload/handlers.js',
    ));

    $upload_css=<<<CSS
<style type="text/css">
@import url("$DBInfo->url_prefix/local/JSUpload/jsupload.css");
</style>
CSS;

    $btn=_("Files...");
    $btn2=_("Upload files");
    $btn3=_("Cancel All files");
    $prefix=qualifiedUrl($DBInfo->url_prefix.'/local');
    $action=$formatter->link_url($formatter->page->urlname);
    $action2=$action.'----jsupload';
    if ($mysubdir) $action2.='----'.$mysubdir;
    $action2=qualifiedUrl($action2);
    $myprefix=qualifiedUrl($DBInfo->url_prefix);
    $jsupload_script = '';

    $upload_js=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
(function(){

function byId(el) {
  return document.getElementById(el);
}

function uploadFile(file) {
    //console.log(file.name+" | "+file.size+" | "+file.type);
    fileQueued(file);

    var ajax = new XMLHttpRequest();
    ajax.upload.addEventListener("progress", function(e) {
        var pie = document.getElementById("fileProgressInfo");
        var proc = Math.ceil((event.loaded / event.total) * 100);

        pie.style.background = "url(" + _url_prefix + "/local/JSUpload/images/progressbar.png) repeat-y -" + (100 - proc) + "px 0";
        pie.innerHTML = proc + " %";

        var progress = byId(file.name + "progress");
        progress.style.background = pie.style.background;
    });
    ajax.upload.addEventListener("load", function(e){
        uploadSuccess(file);
    });
    ajax.upload.addEventListener("error", errorHandler);
    ajax.upload.addEventListener("abort", abortHandler);

    var formdata = new FormData();
    formdata.append('action', 'jsupload');
    formdata.append('Filedata', file);

    ajax.open("POST", "$action");
    ajax.send(formdata);
}

function errorHandler(event) {
    console.log("Upload Failed");
}

function abortHandler(event) {
    console.log("Upload Aborted");
}

$(function() {
$('#jsuploadform :file').on('change', function(){
    for (i=0; i<this.files.length; i++) {
        uploadFile(this.files[i]);
    }
    $('#jsuploadform')[0].reset();
});
});

})();
/*]]>*/
</script>
EOF;
    $formatter->jqReady = true;

    $submit_btn='<span id="spanButtonPlaceHolder"><input type="file" name="upload[]" multiple /></span>';
    $cancel_btn='';

    $form = <<<EOF
	<div id="JSUpload" style='display:none'>
		<form action="" onsubmit="return false;">
			<input type="file" name="upload" />
			<input type="submit" value="Upload" onclick="javascript:alert('disabled...'); return false;" />
		</form>
	</div>
$upload_js
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
            <form id="jsuploadform" target='_blank' action="$action" method="POST" enctype="multipart/form-data">
	        <ul id="mmUploadFileListing">$uploaded</ul>
		<span id="fileButton">
                <input type='hidden' name='action' value='jsupload' />
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
            We're sorry.  JSUpload could not load.  You must have JavaScript enabled to enjoy JSUpload.
        </noscript>
        <div id="divLoadingContent" class="content" style="background-color: #FFFF66; border-top: solid 4px #FF9966; border-bottom: solid 4px #FF9966; margin: 10px 25px; padding: 10px 15px; display: none;">
            JSUpload is loading. Please wait a moment...
        </div>
        <div id="divLongLoading" class="content" style="background-color: #FFFF66; border-top: solid 4px #FF9966; border-bottom: solid 4px #FF9966; margin: 10px 25px; padding: 10px 15px; display: none;">
            JSUpload is taking a long time to load or the load has failed.  Please make sure that the Flash Plugin is enabled and that a working version of the Adobe Flash Player is installed.
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
    return $jsupload_script.$upload_css.$form;
}

// do_UploadFile wrapper
function do_post_JSUpload($formatter,$options=array()) {
    global $DBInfo;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter, $options);
    }

    // check allowed file extensions
    $allowed_re = '.*';
    if (!empty($DBInfo->pds_allowed)) {
        $allowed_re = $DBInfo->pds_allowed;
    }

    $jsupload_dir=$DBInfo->upload_dir.'/.myupload';
    $mysubdir='';
    if(!is_dir($jsupload_dir)) {
        $om=umask(000);
        mkdir($jsupload_dir, 0777);
        umask($om);

        $fp=fopen($jsupload_dir.'/.htaccess','w');
        if ($fp) {
            $htaccess=<<<EOF
# FCGI or CGI user can use .user.ini
Options -Indexes
AddType text/plain .php5 .php4 .php3 .phtml .php .html .map .mm
<Files ~ "\.php">
#ForceType text/plain
SetHandler text/plain
</Files>
Order deny,allow
deny from all\n
EOF;
            fwrite($fp,$htaccess);
            fclose($fp);
        }
    }

    // check subdir
    if (!empty($DBInfo->jsupload_depth) and $DBInfo->jsupload_depth > 2) {
        $depth=$DBInfo->jsupload_depth;
    } else {
        $depth=2;
    }

    $seed = $_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI';
    if ($DBInfo->seed)
        $seed .= $DBInfo->seed;
    $myid = md5($seed); // FIXME
    if (session_id() != '') { // ip based
        if (0 and $_SESSION['.jsupload']) // XXX flash bug?
            $myid = $_SESSION['.jsupload'];
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
    //$fp=fopen($jsupload_dir.'/swflog.txt','a+');
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
        if(!is_dir($jsupload_dir.'/'.$mysubdir)) {
            $om=umask(000);
            _mkdir_p($jsupload_dir.'/'.$mysubdir, 0777);
            umask($om);
        }
    }

    //move the uploaded file
    if (isset($_FILES['Filedata']['tmp_name'])) {
        if (preg_match('/\.('.$allowed_re.')$/i', $_FILES['Filedata']['name'])) {
            move_uploaded_file($_FILES['Filedata']['tmp_name'],
                $jsupload_dir.'/'.$mysubdir.$_FILES['Filedata']['name']);
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
        $out= macro_JSUpload($formatter,'');
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
