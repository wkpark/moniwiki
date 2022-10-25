/* Demo Note:  This demo uses a FileProgress class that handles the UI for displaying the file name and percent complete.
The FileProgress class is not part of SWFUpload.
*/


/* **********************
   Event Handlers
   These are my custom event handlers to make my
   web application behave the way I went when SWFUpload
   completes different tasks.  These aren't part of the SWFUpload
   package.  They are part of my application.  Without these none
   of the actions SWFUpload makes will show up in my application.
   ********************** */

/**
 * remove all SWFUpload stuff and add HTML5 upload callbacks.
 */
function byId(id) {
    return document.getElementById(id);
}

Math.roundf = function(val, precision) {
    var p = this.pow(10, precision);
    return this.round(val * p) / p;
}
/* */

function fileQueued(file) {
        byId("filesDisplay").style.display = "block";

        var el = document.getElementById(file.name);
        if (el) {
            // already have entry
            var chk = el.getElementsByTagName("input")[0];
            if (chk) {
                if (chk.type=='checkbox' && chk.checked == 0) {
                    el.parentNode.removeChild(el);
                } else {
                    return true;
                }
            }
            byId(file.name).className = "uploading";
            return true;
        }

        var li = document.createElement("li");
        var txt = document.createTextNode(file.name);

        li.className = "uploading";
        li.id = file.name;

        var prg = document.createElement("span");
        prg.id = file.name + "progress";
        prg.className = "progressBar";

        li.appendChild(txt);
        li.appendChild(prg);

        byId("mmUploadFileListing").appendChild(li);

        delFiles();
}

function delFiles() {
    var listing = byId("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");

    for (var i=0;i<elem.length;i++) {
        var chk= elem[i].getElementsByTagName("input")[0];
        if (chk !== undefined && chk.type=='checkbox' && chk.checked==0) {
            elem[i].parentNode.removeChild(elem[i]);
        }
    }
}

function fileSubmit(obj) {
    var listing = byId("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");
    var selected = new Array();
    var form = obj.parentNode;

    for (var i=0;i<elem.length;i++) {
        var chk= elem[i].getElementsByTagName("input")[0];
        if (chk.type=='checkbox' && chk.checked==1) {
            var inp = document.createElement('INPUT');
            inp.setAttribute("name",'MYFILES[]');
            inp.setAttribute("type",'hidden');
            inp.setAttribute("value",elem[i].id);
            form.appendChild(inp);

            var a = elem[i].getElementsByTagName("a")[0];
            a.href = "javascript:showImgPreview('" + elem[i].id + "',true)";
        }
    }
}

function uploadSuccess(file) {
    var unt= new Array('Bytes','KB','MB','GB','TB');
    var size=file.size;
    var i;
    for (i=0;i<4;i++) {
        if (size <= 1024) {
            break;
        }
        size=size/1024;
    }
    size= Math.roundf(size,2) + " " + unt[i];

    byId(file.name).className = "uploadDone";
    byId(file.name).innerHTML = "<input type='checkbox' checked='checked' />"
        + "<a href='javascript:showImgPreview(\"" + file.name + "\")'>" + file.name + "</a>" + " (" + size + ")";

    var pie = byId("fileProgressInfo");
    pie.style.background='';
    pie.innerHTML = "";
}

// vim:et:sts=4:sw=4:
