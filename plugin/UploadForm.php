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
    $hide_btn = 1;

    $msg2 = _("Successfully Uploaded");
    $msg = _("Choose File");
    $formatter->register_javascripts("wikibits.js");
    if ($id==1)
       $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function addRow(id, size) {
    if (size == undefined)
        size = 50;

    // check editform
    var editform = document.getElementById('editform');
    if (editform) {
        var iframe = document.getElementById('upload-iframe');
        if (!iframe) {
            if (document.all)
                iframe = document.createElement('<iframe frameBorder="0" name="upload-iframe" width="1px" height="1px">');
            else
                iframe = document.createElement('iframe');
            iframe.setAttribute('id','upload-iframe');
            iframe.setAttribute('name','upload-iframe');
            iframe.setAttribute('style','display:none;border:0;');
            //iframe.setAttribute('style','border:0;');
            var body = document.getElementsByTagName('body')[0];
            body.appendChild(iframe);
        }
    }
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
    newInput.style.position = 'absolute'; // IE
    newInput.style.left = -8; // IE
    newInput.setAttribute('style', 'position:absolute;left:-5;');

EOF;
    if ($id == 1 and $hide_btn)
        $script .=<<<EOF
    var btn = document.getElementById('button-' + id);
    btn.setAttribute('style','display:inline-block;');
    btn.style.display = 'inline-block';

EOF;
    if ($id == 1 and $use_fake)
        $script.=<<<EOF
    newInput.className = 'form-file';
    // get basename with replace() for IE
    newInput.onchange = function() { fakeInp.value = this.value.replace(/^.*[\\\\]/g, '');};

    var span = document.createElement('span');
    span.style.position='relative';
    var fakeInp = document.createElement('input');
    fakeInp.setAttribute('type', 'text');
    fakeInp.setAttribute('size', size);
    fakeInp.className = 'fake-file';
    fakeInp.setAttribute('readonly', 'true');
    if (document.all)
        fakeInp.readOnly = true; // for IE
    fakeInp.onclick = function() {if (this.value) { this.value = ''; newInput.value = ''; } else {delRow(id,this);} };

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

function delRow(id,obj) {
    obj.parentNode.parentNode.parentNode.parentNode.removeChild(obj.parentNode.parentNode.parentNode);

EOF;
    if ($id == 1 and $hide_btn)
        $script .=<<<EOF
    var form = document.getElementById("form-" + id);
    var inputs = form.getElementsByTagName('input');
    var mysubmit = null;
    for (i = 0; i < inputs.length; i++) {
        if (inputs[i].type == 'file') {
            return;
        }
    }
    var btn = document.getElementById('button-' + id);
    btn.style.display = 'none';

EOF;
    if ($id == 1)
        $script .=<<<EOF
    
}

function check_attach(id) {
    // check if the form has attached files.
    attach = document.getElementById(id);
    var ok = false;
    files = '';
    js = '';
    var tmp = '';
    inputs = attach.getElementsByTagName('input');
    for (i = 0; i < inputs.length; i++) {
        if (inputs[i].type == 'file' && inputs[i].value != '') {
            ok = true;
            tmp = inputs[i].value.replace(/^.*[\\\\]/g, '');
            files += 'attachment:'+tmp + "\\n";
            js += "insertTags('attachment:',' ','" + tmp + "',3);";
        }
    }
    if (ok == false)
        return false;
    // check editform
    var editform = document.getElementById('editform');
    if (editform) {
        // iframe upload
        iframe = document.getElementById('upload-iframe');
        var attachform = document.getElementById('form-'+id);
        if (attachform) {
            attachform.setAttribute('target', 'upload-iframe');
        }

        // TODO check success or fail
        setTimeout("iframe.parentNode.removeChild(iframe);alert(files + '$msg2');"+js+"resetForm(attach)", 1500);
        return ok;
    }
    return ok;
}

function resetForm(form) {
    if (form && form.rows.length) { // for UploadForm
        for (var i=form.rows.length;i>0;i--) {
            form.deleteRow(i-1);
        }
    }
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
  <form target='_blank' id="form-upload$id" method="post" action="$url" enctype="multipart/form-data">
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
  <button type="submit" class='upload-file' id='button-upload$id' onclick="check_attach('upload$id')" name="upload"><span>$msg3</span></button>
  <!-- <input type="reset" name="reset" value="$msg4" /> -->
      </div>
      </td>
    </tr>
  </table>
  </div>
  </form>
<script type="text/javascript">
/*<![CDATA[*/
(function () {
    var btn = document.getElementById('button-upload$id'); btn.style.display = 'none';
})();
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
