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

function macro_SWFUpload($formatter,$value) {
    global $DBInfo;

    $swfupload_scrpit=$GLOBALS['swf_script'] ? 1:0;

    if (!$swfupload_script) {
        $swfupload_script=<<<EOS
	<script type="text/javascript" src="$DBInfo->url_prefix/local/SWFUpload/mmSWFUpload.js"></script>
	<script type="text/javascript" src="$DBInfo->url_prefix/local/SWFUpload/moni.js"></script>
EOS;
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
    $action2=qualifiedUrl($action2);
    $form=<<<EOF
	<div id="SWFUpload">
		<form action="" onsubmit="return false;">
			<input type="file" name="upload" />
			<input type="submit" value="Upload" onclick="javascript:alert('disabled...'); return false;" />
		</form>
	</div>
			
	<script type="text/javascript">
		mmSWFUpload.init({
			// debug : true,
			upload_backend : "$action2",
			target : "SWFUpload",
			// cssClass : "myCustomClass",
			_prefix : "$prefix",
			allowed_filesize : "40000",
			allowed_filetypes : "*.gif;*.jpg;*.png;*.psd",
			upload_start_callback : 'uploadStart',
			upload_progress_callback : 'uploadProgress',
			upload_complete_callback : 'uploadComplete',
			// upload_error_callback : 'uploadError',
			upload_cancel_callback : 'uploadCancel'
		});
	</script>

	<div class="fileList">
	<table border='0' cellpadding='0'><tr>
	<td colspan='2'>
		<div id="fileProgressInfo"></div>
	</td>
	</tr><tr>
	<td>
	<div id="filesDisplay">
                <form method='POST' action='$action'>
		<ul id="mmUploadFileListing"></ul>
		<span id="fileButton">
                <input type='hidden' name='action' value='swfupload' />
		<input type='button' value="$btn" onclick='javascript:mmSWFUpload.callSWF();' />
		<input type='submit' value="$btn2" onclick='javascript:fileSubmit(this);' />
		</span>
                </form>
	</div>
	</td>
	<td>
	<div id="filePreview">
	</div>
	</td></tr>
	</table>
	</div>
EOF;
    return $swfupload_script.$swf_css.$form;
}

// do_UploadFile wrapper
function do_SWFUpload($formatter,$options=array()) {
    global $DBInfo;

    $dir=$DBInfo->upload_dir.'/_swfupload'; // XXX
    if(!is_dir($dir)) mkdir($dir, 0755);
    //move the uploaded file
    if (isset($_FILES['Filedata']['tmp_name'])) {
        move_uploaded_file($_FILES['Filedata']['tmp_name'], $dir.'/'.$_FILES['Filedata']['name']);
        return;
    } else if (is_array($options['MYFILES'])) {
        include_once('plugin/UploadFile.php');

        do_UploadFile($formatter,$options);
    } else {
        echo "Error";
    }
}

/*
 * vim:et:sts=4:sw
 */
