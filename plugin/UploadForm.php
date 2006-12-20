<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadForm plugin for the MoniWiki
//
// Usage: [[UploadForm]]
//
// $Id$

function macro_UploadForm($formatter,$value) {
    global $DBInfo;
    static $id=1;

    if ($id==1)
       $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function addRow(id) {
    var fform = document.getElementById(id);
    var lastRow = fform.rows.length;
    var row = fform.insertRow(lastRow);

    var cell = row.insertCell(0);
    var span = document.createElement('span');
    var rmbtn = document.createElement('input');
    var newInput = document.createElement('input');
    newInput.setAttribute('type', 'file');
    newInput.setAttribute('name', 'upfile[]');
    newInput.setAttribute('size', '50');

    rmbtn.setAttribute('type','button');
    rmbtn.setAttribute('value','x');
    rmbtn.setAttribute('onclick',"delRow(this)");
    rmbtn.onclick=Function("delRow(this)"); // for IE

    span.appendChild(newInput);
    span.appendChild(rmbtn);
    cell.appendChild(span);
}

function delRow(obj) {
    obj.parentNode.parentNode.parentNode.removeChild(obj.parentNode.parentNode);
}

/*]]>*/
</script>
EOF;
    $url=$formatter->link_url($formatter->page->urlname);
    $form=<<<EOS
  <form target='_blank' method="post" action="$url" enctype="multipart/form-data">
  <input type='hidden' name='action' value='UploadFile' />
EOS;
    $icon=$DBInfo->icon['attach'];
    $multiform=<<<EOF
  <table border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td valign='top' rowspan='2'>
        <span onclick="addRow('upload$id')" title="add files">$icon</span>
      </td>
      <td>
        <table cellspacing="0" cellpadding="0" border="0">
          <tbody id="upload$id">
            <tr>
              <td></td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
    <tr>
      <td>
  <input type='button' onclick="addRow('upload$id')" value="add a file" />
  <input type="hidden" name="uploadid" value="upload$id" />
  <input type="hidden" name="popup" value="1" />
  <input type="submit" name="upload" value="Upload files" />
  <input type="reset" name="reset" value="Reset" />
      </td>
    </tr>
  </table>
  </form>
EOF;

    if (!in_array('UploadedFiles',$formatter->actions))
        $formatter->actions[]='UploadedFiles';
    $id++;
    if ($formatter->preview and !in_array('UploadFile',$formatter->actions)) {
        $keyname=$DBInfo->pageToKeyname($formatter->page->name);
        if (is_dir($DBInfo->upload_dir.'/'.$keyname))
        $form=$formatter->macro_repl('UploadedFiles(tag=1)').$form;
    }
    return $script.$form.$multiform;
}

// vim:et:sts=4:
?>
