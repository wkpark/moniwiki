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

/* */
function byId(id) {
    return document.getElementById(id);
}

Math.roundf = function(val, precision) {
    var p = this.pow(10, precision);
    return this.round(val * p) / p;
}
/* */

function swfUploadPreLoad() {
    var self = this;
    var loading = function () {
        //document.getElementById("divSWFUploadUI").style.display = "none";
        document.getElementById("divLoadingContent").style.display = "";

        var longLoad = function () {
            document.getElementById("divLoadingContent").style.display = "none";
            document.getElementById("divLongLoading").style.display = "";
        };
        this.customSettings.loadingTimeout = setTimeout(function () {
                longLoad.call(self)
            },
            15 * 1000
        );
    };
    
    this.customSettings.loadingTimeout = setTimeout(function () {
            loading.call(self);
        },
        1*1000
    );
}
function swfUploadLoaded() {
    var self = this;
    clearTimeout(this.customSettings.loadingTimeout);
    //document.getElementById("divSWFUploadUI").style.visibility = "visible";
    //document.getElementById("divSWFUploadUI").style.display = "block";
    document.getElementById("divLoadingContent").style.display = "none";
    document.getElementById("divLongLoading").style.display = "none";
    document.getElementById("divAlternateContent").style.display = "none";
    
    //document.getElementById("btnBrowse").onclick = function () { self.selectFiles(); };
    document.getElementById("btnCancel").onclick = function () { self.cancelQueue(); };
}
   
function swfUploadLoadFailed() {
    clearTimeout(this.customSettings.loadingTimeout);
    //document.getElementById("divSWFUploadUI").style.display = "none";
    document.getElementById("divLoadingContent").style.display = "none";
    document.getElementById("divLongLoading").style.display = "none";
    document.getElementById("divAlternateContent").style.display = "";
}


function fileQueued(file) {
    try {
        //var progress = new FileProgress(file, this.customSettings.progressTarget);
        //progress.setStatus("Pending...");
        //progress.toggleCancel(true, this);

        byId("filesDisplay").style.display = "block";

        if (document.getElementById(file.name)) {
            byId(file.name).className = "uploading";
            return true; // XXX
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
    } catch (ex) {
        this.debug(ex);
    }

}

function fileQueueError(file, errorCode, message) {
    try {
        if (errorCode === SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
            alert("You have attempted to queue too many files.\n" + (message === 0 ? "You have reached the upload limit." : "You may select " + (message > 1 ? "up to " + message + " files." : "one file.")));
            return;
        }

        var progress = new FileProgress(file, this.customSettings.progressTarget);
        progress.setError();
        progress.toggleCancel(false);

        switch (errorCode) {
        case SWFUpload.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT:
            progress.setStatus("File is too big.");
            this.debug("Error Code: File too big, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.QUEUE_ERROR.ZERO_BYTE_FILE:
            progress.setStatus("Cannot upload Zero Byte files.");
            this.debug("Error Code: Zero byte file, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.QUEUE_ERROR.INVALID_FILETYPE:
            progress.setStatus("Invalid File Type.");
            this.debug("Error Code: Invalid File Type, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        default:
            if (file !== null) {
                progress.setStatus("Unhandled Error");
            }
            this.debug("Error Code: " + errorCode + ", File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        }
    } catch (ex) {
        this.debug(ex);
    }
}

function fileDialogComplete(numFilesSelected, numFilesQueued) {
    try {
        if (numFilesSelected > 0) {
            document.getElementById(this.customSettings.cancelButtonId).disabled = false;
        }
        
        /* I want auto start the upload and I can do that here */
        this.startUpload();
    } catch (ex)  {
        this.debug(ex);
    }
}

function uploadStart(file) {
    try {
        /* I don't want to do any file validation or anything,  I'll just update the UI and
           return true to indicate that the upload should start.
           It's important to update the UI here because in Linux no uploadProgress events are called. The best
           we can do is say we are uploading.
           */
        //var progress = new FileProgress(file, this.customSettings.progressTarget);
        //progress.setStatus("Uploading...");
        //progress.toggleCancel(true, this);
        //

    }
    catch (ex) {}

    return true;
}

function delFiles() {
    var listing = byId("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");

    for (var i=0;i<elem.length;i++) {
        var chk= elem[i].getElementsByTagName("input")[0];
        if (chk.type=='checkbox' && chk.checked==0) {
            elem[i].parentNode.removeChild(elem[i]);
        }
    }
}

function fileSubmit(obj) {

    var listing = byId("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");
    var selected = new Array();
    var form = obj.parentNode;
    var j=0;

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

function uploadProgress(file, bytesLoaded, bytesTotal) {
    try {
        //var percent = Math.ceil((bytesLoaded / bytesTotal) * 100);
        //var progress = new FileProgress(file, this.customSettings.progressTarget);
        //progress.setProgress(percent);
        //progress.setStatus("Uploading...");

        var pie = document.getElementById("fileProgressInfo");
        var proc = Math.ceil((bytesLoaded / bytesTotal) * 100);

        pie.style.background = "url(" + _url_prefix + "/local/SWFUpload/images/progressbar.png) repeat-y -" + (100 - proc) + "px 0";
        pie.innerHTML = proc + " %";

        var progress = byId(file.name + "progress");
        progress.style.background = pie.style.background;
    } catch (ex) {
        this.debug(ex);
    }
}

function uploadSuccess(file, serverData) {
    try {
        //var progress = new FileProgress(file, this.customSettings.progressTarget);
        //progress.setComplete();
        //progress.setStatus("Complete.");
        //progress.toggleCancel(false);

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
    } catch (ex) {
        this.debug(ex);
    }
}

function uploadError(file, errorCode, message) {
    try {
        //var progress = new FileProgress(file, this.customSettings.progressTarget);
        //progress.setError();
        //progress.toggleCancel(false);

        switch (errorCode) {
        case SWFUpload.UPLOAD_ERROR.HTTP_ERROR:
            progress.setStatus("Upload Error: " + message);
            this.debug("Error Code: HTTP Error, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.UPLOAD_FAILED:
            progress.setStatus("Upload Failed.");
            this.debug("Error Code: Upload Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.IO_ERROR:
            progress.setStatus("Server (IO) Error");
            this.debug("Error Code: IO Error, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.SECURITY_ERROR:
            progress.setStatus("Security Error");
            this.debug("Error Code: Security Error, File name: " + file.name + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.UPLOAD_LIMIT_EXCEEDED:
            progress.setStatus("Upload limit exceeded.");
            this.debug("Error Code: Upload Limit Exceeded, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.FILE_VALIDATION_FAILED:
            progress.setStatus("Failed Validation.  Upload skipped.");
            this.debug("Error Code: File Validation Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        case SWFUpload.UPLOAD_ERROR.FILE_CANCELLED:
            // If there aren't any files left (they were all cancelled) disable the cancel button
            if (this.getStats().files_queued === 0) {
                document.getElementById(this.customSettings.cancelButtonId).disabled = true;
            }
            progress.setStatus("Cancelled");
            progress.setCancelled();
            break;
        case SWFUpload.UPLOAD_ERROR.UPLOAD_STOPPED:
            progress.setStatus("Stopped");
            break;
        default:
            progress.setStatus("Unhandled Error: " + errorCode);
            this.debug("Error Code: " + errorCode + ", File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
            break;
        }
    } catch (ex) {
        this.debug(ex);
    }
}

function uploadComplete(file) {
    if (this.getStats().files_queued === 0) {
        document.getElementById(this.customSettings.cancelButtonId).disabled = true;
    }
}

// This event comes from the Queue Plugin
function queueComplete(numFilesUploaded) {
    //var status = document.getElementById("divStatus");
    //status.innerHTML = numFilesUploaded + " file" + (numFilesUploaded === 1 ? "" : "s") + " uploaded.";
}

// vim:et:sts=4:sw=4:
