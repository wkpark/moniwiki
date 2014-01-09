<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadForm plugin for the MoniWiki
//
// Usage: [[UploadForm]]
//
// $Id: UploadForm.php,v 1.21 2010/09/17 10:47:00 wkpark Exp $

function macro_UploadForm($formatter,$value) {
    global $DBInfo;
    static $id=1;

    $use_fake = 1;
    $hide_btn = 1;
    $name = 'upfile';
    $show = true;

    $msg2 = _("Successfully Uploaded");
    $msg = _("Choose File");
    $formatter->register_javascripts("wikibits.js");
    $script = '';
    if ($id==1)
       $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function addRow(id, name, size) {
    if (size == undefined)
        size = 50;
    if((tmpbutton = document.getElementById(id).getElementsByTagName('button').item(0)) != undefined)
	tmpbutton = tmpbutton.clientWidth;

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
            if (document.all) {
                // magic for IE6
                /*@cc_on
                if (@_jscript_version==5.6 ||
                    (@_jscript_version==5.7 && navigator.userAgent.toLowerCase().indexOf("msie 6.") != -1)) {
                    iframe.src = 'javascript:document.write("' + "<script>document.domain='" + document.domain + "';</" + "script>" + '");';
                }
                @*/
            }
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
    newInput.setAttribute('name', name+'[]');
    newInput.setAttribute('size', size);

    var tmpstyle = "width:80px";
    if(tmpbutton != undefined)
	tmpstyle = "width:" + tmpbutton + "px;";
    else
	tmpbutton = 80; // set 80px for IE

    newInput.style.position = 'absolute'; // IE
    newInput.style.left = -8; // IE
    newInput.style.width = tmpbutton+3; // IE
    newInput.setAttribute('style', 'position:absolute;left:-5;'+tmpstyle);

    var btn = document.getElementById('button-' + id);
    if (btn) {
        btn.setAttribute('style','display:none;');
        btn.style.display = 'none';
    }

EOF;
    if ($id == 1 and $hide_btn)
        $script .=<<<EOF

EOF;
    if ($id == 1 and $use_fake)
        $script.=<<<EOF
    newInput.className = 'form-file';
    // get basename with replace() for IE
    newInput.onchange = function() {
        // add new row if and only if this input element is in the bottom row of upload table
        this_row = this.parentNode.parentNode.parentNode.parentNode;
        this_table = this_row.parentNode;
        if (this_table.lastChild == this_row) {
                addRow(id, name, size);
        }

        fakeInp.style.display='inline-block';
        fakeInp.value = this.value.replace(/^.*[\\\\]/g, '');

        var btn = document.getElementById('button-' + id);
        if (btn) {
            btn.setAttribute('style','display:inline-block;');
            btn.style.display = 'inline-block';
        }
    };

    var span = document.createElement('span');
    span.style.position='relative';
    var fakeInp = document.createElement('input');
    fakeInp.setAttribute('type', 'text');
    fakeInp.setAttribute('size', size);
    fakeInp.className = 'fake-file';
    fakeInp.setAttribute('readonly', 'true');
    fakeInp.style.display = 'none';
    if (document.all)
        fakeInp.readOnly = true; // for IE
    fakeInp.onclick = function() {if (this.value) { this.value = ''; newInput.value = ''; } else {delRow(id,this);} };

    var addbtn = document.createElement('button');
    var span2 = document.createElement('span');
    var txt = document.createTextNode('$msg');
    span2.appendChild(txt);
    addbtn.appendChild(span2);
    addbtn.setAttribute('onclick',"return false;");
    addbtn.className = 'add-file';

    div.appendChild(fakeInp);
    span.appendChild(addbtn);
    span.appendChild(newInput);
    div.appendChild(span);
    cell.appendChild(div);
    /* newInput.click(); /* */
EOF;
    else if ($id == 1)
        $script .=<<<EOF
    div.appendChild(newInput);
    cell.appendChild(div);
    /* newInput.click(); /* */
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
        if (inputs[i].type == 'file' && inputs[i].value != '') {
            return;
        }
    }
    var btn = document.getElementById('button-' + id);
    if (btn) btn.style.display = 'none';

EOF;
    if ($id == 1)
        $script .=<<<EOF
    
}

function check_attach(id) {
    // check if the form has attached files.
    attach = document.getElementById(id);
    var ok = false;
    files = '';
    var tmp = '';
    inputs = attach.getElementsByTagName('input');
    for (i = 0; i < inputs.length; i++) {
        if (inputs[i].type == 'file' && inputs[i].value != '') {
            ok = true;
            break;
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
            // set domain name.
            if (location.host != document.domain) {
                if (document.all) {
                    var mydomain = document.createElement('<input name="domain">');
                } else {
                    var mydomain = document.createElement('input');
                    mydomain.setAttribute('name', 'domain');
                }

                mydomain.setAttribute('type', 'hidden');
                mydomain.setAttribute('value', document.domain + '');
                attachform.appendChild(mydomain);
            }

            attachform.setAttribute('target', 'upload-iframe');
            attachform.elements['action'].value='UploadFile/ajax';
        }

        var timer = setInterval(function() {check_upload_result(iframe, attach, timer);}, 1500);
        return ok;
    }
    return ok;
}

function check_upload_result (iframe,attach, timer) {
    if (!iframe) return;

    try {
        var doc = iframe.contentDocument || iframe.contentWindow.document;
    } catch(e) {
        // silently ignore
        alert('Error: '+ e + ' - Security restriction detected !\\nPlease check your "document.domain=' + document.domain + '"');
        return;
    }
    if (!doc || !doc.body) return;

    var p = doc.body.firstChild;
    if (p && p.nodeType == 3 && p.nodeValue) { // text node
        eval("var ret = " + p.nodeValue);
        // remove iframe;
        iframe.parentNode.removeChild(iframe);
        alert(ret['title'] + "\\n" + ret['msg']);
        for (var i = 0; i < ret['files'].length; i++) {
            if (ret['files'][i] == '') continue;
            insertTags('attachment:',' ', ret['files'][i], 3);
        }
        clearInterval(timer);
        resetForm(attach);
    }
}

function resetForm(form) {
    inputs = form.getElementsByTagName('input');
    var name = "$name", size = 50; // default
    for (i = 0; i < inputs.length; i++) {
        if (inputs[i].type == 'file') {
            name = inputs[i].getAttribute('name');
            size = inputs[i].getAttribute('size');
            break;
        }
    }

    if (form && form.rows.length) { // for UploadForm
        for (var i=form.rows.length;i>0;i--) {
            form.deleteRow(i-1);
        }
    }
    name = name.replace(/\[\]$/g, '');
    addRow(form.getAttribute('id'), name, size);
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
        <span onclick="addRow('upload$id','$name')" class='icon-clip' title="$msg">$attach_msg</span>
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
  <!-- button type='button' class='add-file' onclick="addRow('upload$id','$name')"><span>$msg2</span></button -->
  <input type="hidden" name="upload$id" value="upload$id" />
  <input type="hidden" name="popup" value="1" />
EOF;
    if (!empty($show))
        $multiform.=<<<EOF
  <button type="submit" class='upload-file' id='button-upload$id' onclick="check_attach('upload$id')" name="upload"><span>$msg3</span></button>
  <!-- <input type="reset" name="reset" value="$msg4" /> -->
EOF;
    $multiform.=<<<EOF
      </div>
      </td>
    </tr>
  </table>
  </div>
  </form>
<script type="text/javascript">
/*<![CDATA[*/
(function () {
    var btn = document.getElementById('button-upload$id');
    if (btn) btn.style.display = 'none';
})();

function init_uploadForm() {
        addRow('upload$id','$name');
}

if (window.addEventListener) {
	window.addEventListener("load", init_uploadForm, false);
} else if (window.attachEvent) {
	window.attachEvent("onload", init_uploadForm);
} else {
	window.onload = init_uploadForm;
}

/*]]>*/
</script>
EOF;

    if (!in_array('UploadedFiles',$formatter->actions))
        $formatter->actions[]='UploadedFiles';
    $id++;
    if (!empty($formatter->preview) and !in_array('UploadFile',$formatter->actions)) {
        if (!empty($DBInfo->use_preview_uploads)) {
            $keyname=$DBInfo->pageToKeyname($formatter->page->name);
            $dir = $DBInfo->upload_dir.'/'.$keyname;
            if (!is_dir($dir) and !empty($DBInfo->use_hashed_upload_dir)) {
                // support hashed upload_dir
                $prefix = get_hashed_prefix($keyname);
                $dir = $DBInfo->upload_dir.'/'.$prefix.$keyname;
            }
            if (is_dir($dir))
            $form=$formatter->macro_repl('UploadedFiles(tag=1)').$form;
        }
    }
    return $script.$form.$multiform;
}

// vim:et:sts=4:
?>
