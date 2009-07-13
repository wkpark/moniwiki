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

    $use_fake = 1;

    $msg = _("Choose File");
    if ($id==1)
       $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function addRow(id, size) {
    if (size == undefined)
        size = 50;

    var fform = document.getElementById(id);
    var lastRow = fform.rows.length;
    var row = fform.insertRow(lastRow);

    var cell = row.insertCell(0);
    var div = document.createElement('div');
    div.setAttribute('style', 'position:relative');
    var newInput = document.createElement('input');
    newInput.setAttribute('type', 'file');
    newInput.setAttribute('name', 'upfile[]');
    newInput.setAttribute('size', size);
    newInput.setAttribute('style', 'font-size:14px;position:absolute;width:65px;right:0;padding:0;filter:alpha(opacity=0);opacity:0;cursor:pointer;');

EOF;
    if ($id == 1 and $use_fake)
        $script.=<<<EOF
    newInput.className = 'form-file';
    // get basename with replace() for IE
    newInput.onchange = function() { fakeInp.value = this.value.replace(/^.*[\\\\]/g, '');};

    var span = document.createElement('span');
    var fakeInp = document.createElement('input');
    fakeInp.setAttribute('type', 'text');
    fakeInp.setAttribute('size', size);
    fakeInp.className = 'fake-file';
    fakeInp.setAttribute('readonly', 'true');
    if (document.all)
        fakeInp.readOnly = true; // for IE
    fakeInp.onclick = function() {if (this.value) { this.value = ''; newInput.value = ''; } else {delRow(this);} };

    var addbtn = document.createElement('button');
    var span2 = document.createElement('span');
    var txt = document.createTextNode('$msg');
    span2.appendChild(txt);
    addbtn.appendChild(span2);
    addbtn.setAttribute('onclick',"return false;");

    div.appendChild(fakeInp);
    span.appendChild(addbtn);
    span.appendChild(newInput);
    div.appendChild(span);
    cell.appendChild(div);
EOF;
    else if ($id == 1)
        $script .=<<<EOF
    div.appendChild(newInput);
    cell.appendChild(div);
EOF;
    
    if ($id == 1)
        $script .=<<<EOF
}

function delRow(obj) {
    obj.parentNode.parentNode.parentNode.removeChild(obj.parentNode.parentNode);
}

function check_attach(id) {
    // check if the form has attached files.
    var attach = document.getElementById(id);
    inputs = attach.getElementsByTagName('input');
    for (i = 0; i < inputs.length; i++) {
        if (inputs[i].type == 'file' && inputs[i].value != '') {
            return true;
        }
    }
    return false;
}

/*]]>*/
</script>
EOF;
    $msg = _("add files");
    $msg2 = _("add a file");
    $msg3 = _("Upload files");
    $msg4 = _("Reset");
    $attach_msg = _("Attachments");
    $url=$formatter->link_url($formatter->page->urlname);
    $form=<<<EOS
  <form target='_blank' method="post" action="$url" enctype="multipart/form-data">
  <div class='uploadForm'>
  <input type='hidden' name='action' value='UploadFile' />
EOS;
    $icon=$DBInfo->icon['attach'];
    $multiform=<<<EOF
  <table border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td valign='top' rowspan='2'>
        <span onclick="addRow('upload$id')" class='icon-clip' title="$msg">$attach_msg</span>
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
      <div class='buttons'>
  <button type='button' class='add-file' onclick="addRow('upload$id')"><span>$msg2</span></button>
  <input type="hidden" name="uploadid" value="upload$id" />
  <input type="hidden" name="popup" value="1" />
  <button type="submit" class='upload-file save-button' onclick="return check_attach('upload$id')" name="upload"><span>$msg3</span></button>
  <!-- <input type="reset" name="reset" value="$msg4" /> -->
      </div>
      </td>
    </tr>
  </table>
  </div>
  </form>
<script type="text/javascript">
/*<![CDATA[*/
//addRow('upload$id');
/*]]>*/
</script>
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
