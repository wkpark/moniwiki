<?php
// Copyright 2005-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a file uploader plugin for the MoniWiki
//
// Usage: [[MultiUpload]]

function macro_MultiUpload($formatter, $value = '') {
    global $Config;

    $GLOBALS['_id_multiupload_'] = empty($GLOBALS['_id_multiupload_']) ? 1 : ++$GLOBALS['_id_multiupload_'];
    $id = $GLOBALS['_id_multiupload_'];

    $formatter->register_javascripts('wikibits.js');
    $formatter->register_javascripts('uploader.js');

    $msg = _("Choose File");
    $msg2 = _("Upload files");
    $url=$formatter->link_url($formatter->page->urlname);

    $form = <<<EOS
  <form target='_blank' id="form-upload$id" method="post" action="$url" enctype="multipart/form-data">
  <div class='uploadForm' id="upload$id">
  <input type='hidden' name='action' value='UploadFile' />
  <input type='hidden' name='uploadid' value='form-upload$id' />
  <span style="position: relative;"><button onclick="return false;" class="add-file"><span>$msg</span></button>
  <input type="file" id="file-upload$id" name="upfile[]" size="50" multiple="multiple" style="position:absolute;left:-5;width:80px" class="form-file" /></span>
EOS;

    $multiform = <<<EOF
  <ul>
  </ul>
  <div>
  <button type="submit" class='upload-file' id='button-upload$id' name="upload"><span>$msg2</span></button>
  </div>
  </div>
  </form>
$js
EOF;

    if (!in_array('UploadedFiles',$formatter->actions))
        $formatter->actions[]='UploadedFiles';

    while (!empty($formatter->preview) and !in_array('UploadFile',$formatter->actions)) {
        if (!empty($Config['use_preview_uploads'])) {
            global $DBInfo;

            $key = $DBInfo->pageToKeyname($formatter->page->name);
            $dir = $Config['upload_dir'].'/'.$key;
            if (!is_dir($dir) and !empty($Config['use_hashed_upload_dir'])) {
                // support hashed upload_dir
                $prefix = get_hashed_prefix($key);
                $dir = $DBInfo->upload_dir.'/'.$prefix.$key;
            }
            if (!is_dir($dir)) break;
            $form = $formatter->macro_repl('UploadedFiles(tag=1)').$form;
        }
        break;
    }
    return $script.$form.$multiform;
}

function do_multiupload($formatter, $params = array()) {
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    $out = macro_MultiUpload($formatter);
    echo $formatter->get_javascripts();
    echo $out;
    if (!in_array('UploadedFiles', $formatter->actions))
        $formatter->actions[] = 'UploadedFiles';
    $formatter->send_footer('', $params);
}

// vim:et:sts=4:sw=4:
