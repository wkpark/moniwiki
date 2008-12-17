/**
 * mmSWFUpload 0.7: Flash upload dialog - http://profandesign.se/swfupload/
 *
 * SWFUpload is (c) 2006 Lars Huring and Mammon Media and is released under the MIT License:
 * http://www.opensource.org/licenses/mit-license.php
 *
 * VERSION HISTORY
 * 0.5 - First release
 *
 * 0.6 - 2006-11-24
 * - Got rid of flash overlay
 * - SWF size reduced to 840b
 * - CSS-only styling of button
 * - Add upload to links etc.
 *
 * 0.7 - 2006-11-27
 * - Added filesize param and check in SWF
 */

mmSWFUpload = {

	init: function(settings) {
	
		this.settings = settings;
		if (this.settings["_prefix"]) {
                        this._prefix=this.settings["_prefix"] + "/local";
		} else if (_url_prefix) { // for MoniWiki
			this._prefix=_url_prefix + "/local";
		} else {
			this._prefix= "./jscripts";
		}

		// Remove background flicker in IE
		try {
		  document.execCommand('BackgroundImageCache', false, true);
		} catch(e) {}

		// Create SWFObject
			
		if(swfobject.getFlashPlayerVersion().major >= 8) {
			
			var param={
				"wmode": "transparent",
				"menu":"false"
			};
			// Add all settings to flash
			var vars={
				"uploadBackend": this.addSetting("upload_backend", ""),
				"uploadStartCallback": this.addSetting("upload_start_callback", ""),
				"uploadProgressCallback": this.addSetting("upload_progress_callback", ""),
				"uploadCompleteCallback": this.addSetting("upload_complete_callback", ""),
				"uploadCancelCallback": this.addSetting("upload_cancel_callback", ""),
				"uploadErrorCallback": this.addSetting("upload_error_callback", "mmSWFUpload.handleErrors"),
				"allowedFiletypes": this.addSetting("allowed_filetypes", "*.gif;*.jpg;*.png"),
				"allowedFilesize": this.addSetting("allowed_filesize", "1000")
			};
			var attr={
				"id": "_mmSWFUploadField"
			};
			swfobject.embedSWF(this._prefix + "/SWFUpload/upload.swf", this.addSetting("target", "flashUpload"),
				"1px", "1px", this.addSetting("flash_version", "8"), "",vars, param, attr);
						
			// Output the flash
			//so.write(this.addSetting("target", "flashUpload"));
	
			// Set up button and styles
			var swfc = document.getElementById(this.settings["target"]);
			
			var link = document.createElement("a");
			link.id = "_mmSWFUploadLink";
			link.href = "javascript:mmSWFUpload.callSWF()";
			link.className = this.addSetting("cssClass", "SWFUploadLink");
			
			link.style.display = "block";
			swfc.appendChild(link);
		}
		
		if(this.settings["debug"] == true) {
			mmSWFUpload.debug();
		}
		
	},
	
	// Make sure that we get a few default values
	addSetting: function(setting, defval) {
		
		if(!this.settings[setting]) {
			this.settings[setting] = defval;
		}
	
		return this.settings[setting];

	},
	
	// Default error handling.
	handleErrors: function(errcode, file, msg) {
		
		switch(errcode) {
			
			case -10:	// HTTP error
				// alert(errcode + ", " + file + ", " + msg);
				break;
			
			case -20:	// No backend file specified
				alert(errcode + ", " + file + ", " + msg);
				break;
			
			case -30:	// IOError
				alert(errcode + ", " + file + ", " + msg);
				break;
			
			case -40:	// Security error
				alert(errcode + ", " + file + ", " + msg);
				break;

			case -50:	// Filesize too big
				alert(errcode + ", " + file.name + ", " + msg);
				break;
		
		}
		
	},
	
	getMovie: function(movieName) {
		if (navigator.appName.indexOf("Microsoft") != -1) {
		return window[movieName];
		}	else {
			return document[movieName];
		}
    },
    
	callSWF: function() {
		mmSWFUpload.getMovie("_mmSWFUploadField").uploadImage();
    },
	
	debug: function() {
			document.write("<strong>Target:</strong> " + this.settings["target"] + "<br />");
			document.write("<strong>Upload start callback:</strong> " + this.settings["upload_start_callback"] + "<br />");
			document.write("<strong>Upload progress callback:</strong> " + this.settings["upload_progress_callback"] + "<br />");
			document.write("<strong>Upload complete callback:</strong> " + this.settings["upload_complete_callback"] + "<br />");
			document.write("<strong>Upload filetypes:</strong> " + this.settings["allowed_filetypes"] + "<br />");
			document.write("<strong>Max filesize:</strong> " + this.settings["allowed_filesize"] + "kb <br />");
			document.write("<strong>Upload backend file:</strong> " + this.settings["upload_backend"] + "<br />");
			document.write("<strong>Upload error callback:</strong> " + this.settings["upload_error_callback"] + "<br />");
			document.write("<strong>Upload cancel callback:</strong> " + this.settings["upload_cancel_callback"] + "<br />");
	}

}

