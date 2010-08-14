/*==============================================================================
This Wikiwyg class provides toolbar support

COPYRIGHT:

    Copyright (c) 2005 Socialtext Corporation 
    655 High Street
    Palo Alto, CA 94301 U.S.A.
    All rights reserved.

Wikiwyg is free software. 

This library is free software; you can redistribute it and/or modify it
under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation; either version 2.1 of the License, or (at
your option) any later version.

This library is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser
General Public License for more details.

    http://www.gnu.org/copyleft/lesser.txt

 =============================================================================*/

proto = new Subclass('Wikiwyg.Toolbar', 'Wikiwyg.Base');
proto.classtype = 'toolbar';

proto.config = {
    divId: null,
    imagesLocation: 'images/',
    imagesExtension: '.gif',
    hideRadio: true,
    controlLayout: [
        'save', 'preview', 'cancel', 'mode_selector', '/',
        // 'selector',
        'h1', 'h2', 'h3', 'h4', 'p', 'pre', '|',
        'bold', 'italic', 'underline', 'strike', '|',
        'link', 'hr', '|',
        'ordered', 'unordered', '|',
        'indent', 'outdent', '|',
        'table', '|',
        'help'
    ],
    styleSelector: [
        'label', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre'
    ],
    controlLabels: {
        save: 'Save',
        preview: 'Preview',
        cancel: 'Cancel',
        bold: 'Bold (Ctrl+b)',
        italic: 'Italic (Ctrl+i)',
        underline: 'Underline (Ctrl+u)',
        strike: 'Strike Through (Ctrl+d)',
        hr: 'Horizontal Rule',
        ordered: 'Numbered List',
        unordered: 'Bulleted List',
        indent: 'More Indented',
        outdent: 'Less Indented',
        help: 'About Wikiwyg',
        label: '[Style]',
        p: 'Normal Text',
        pre: 'Preformatted',
        h1: 'Heading 1',
        h2: 'Heading 2',
        h3: 'Heading 3',
        h4: 'Heading 4',
        h5: 'Heading 5',
        h6: 'Heading 6',
        link: 'Create Link',
        smiley: 'Smiley',
        unlink: 'Remove Linkedness',
        table: 'Create Table'
    }
};

proto.initializeObject = function() {
    if (this.config.divId) {
        this.div = document.getElementById(this.config.divId);
    }
    else {
        this.div = Wikiwyg.createElementWithAttrs(
            'div', {
                'class': 'wikiwyg_toolbar',
                id: 'wikiwyg_toolbar'
            }
        );
    }
    var wrap = Wikiwyg.createElementWithAttrs(
        'div', {
            'class': 'wikiwyg_buttons'
        }
    );
    this.imgdiv = document.createElement('SPAN');

    var config = this.config;
    for (var i = 0; i < config.controlLayout.length; i++) {
        var action = config.controlLayout[i];
        var label = config.controlLabels[action];
        if (action == 'save')
            this.addControlItem(label, 'saveChanges');
        else if (action == 'cancel')
            this.addControlItem(label, 'cancelEdit');
        else if (action == 'preview')
            this.addControlItem(label, 'switchMode','Wikiwyg.Preview');
        else if (action == 'mode_selector')
            this.addModeSelector();
        else if (action == 'selector')
            this.add_styles();
        else if (action == 'help')
            this.add_help_button(action, label);
        else if (action == '|')
            this.add_separator();
        else if (action == '/')
            this.add_break();
        else
            this.add_button(action, label);
    }
    this.div.appendChild(wrap);
    wrap.appendChild(this.imgdiv);
    var clear = Wikiwyg.createElementWithAttrs(
        'div', {
            'style': 'clear:both'
        }
    );
    this.div.appendChild(clear);
}

proto.enableThis = function() {
    this.div.style.display = 'block';
}

proto.disableThis = function() {
    this.div.style.display = 'none';
}

proto.make_button = function(type, label) {
    var base = this.config.imagesLocation;
    var ext = this.config.imagesExtension;
    return Wikiwyg.createElementWithAttrs(
        'img', {
            'class': 'wikiwyg_button',
            alt: label,
            title: label,
            src: base + type + ext
        }
    );
}

proto.add_button = function(type, label) {
    var img = this.make_button(type, label);
    var self = this;
    img.onclick = function() {
        self.wikiwyg.current_mode.process_command(type,this);
    };
    this.imgdiv.appendChild(img);
}

proto.add_help_button = function(type, label) {
    var img = this.make_button(type, label);
    var a = Wikiwyg.createElementWithAttrs(
        'a', {
            target: 'wikiwyg_button',
            href: 'http://www.wikiwyg.net/about/'
        }
    );
    a.appendChild(img);
    this.div.appendChild(a);
}

proto.add_separator = function() {
    var base = this.config.imagesLocation;
    var ext = this.config.imagesExtension;
    this.imgdiv.appendChild(
        Wikiwyg.createElementWithAttrs(
            'img', {
                'class': 'wikiwyg_separator',
                alt: ' | ',
                title: '',
                src: base + 'separator' + ext
            }
        )
    );
}

proto.addControlItem = function(text, method,arg) {
    var span = Wikiwyg.createElementWithAttrs(
        'span', { 'class': 'wikiwyg_control_link' }
    );

    var link = Wikiwyg.createElementWithAttrs(
        'a', { href: '#' }
    );
    link.appendChild(document.createTextNode(text));
    span.appendChild(link);
    
    var self = this;
    if (arg) {
        method=method+'("'+arg+'")';
        this.controls=this.controls ? ','+arg:arg;
    } else method=method+'()';
    link.onclick = function() { eval('self.wikiwyg.' + method); return false };

    this.div.appendChild(span);
}

proto.resetModeSelector = function() {
    if (this.firstModeRadio) {
        var temp = this.firstModeRadio.onclick;
        this.firstModeRadio.onclick = null;
        this.firstModeRadio.click();
        this.firstModeRadio.onclick = temp;
    }
}

proto.addModeSelector = function() {
    var cntrl = Wikiwyg.createElementWithAttrs(
        'ul', {
            'class': 'wikiwyg_mode_selector'
        }
    );

    var control_buttons=[];
    if (this.controls) {
        var btns=this.controls.split(',');
        for (var i=0;i < btns.length;i++) {
            control_buttons[btns[i]]=1;
        }
    }

    var btn_name = Wikiwyg.createUniqueId();
    for (var i = 0; i < this.wikiwyg.config.modeClasses.length; i++) {
        var class_name = this.wikiwyg.config.modeClasses[i];
        if (control_buttons[class_name]) continue;
        var mode_object = this.wikiwyg.mode_objects[class_name];
 
        var btn_id = Wikiwyg.createUniqueId();
 
        var checked = i == 0 ? 'checked' : '';
        var btn = Wikiwyg.createElementWithAttrs(
            'li', {
                id: btn_id,
                'class': mode_object.classname
                /* 'checked': checked */
            }
        );
        /*
        if (!this.firstModeRadio)
            this.firstModeRadio = btn;
        */
 
        var self = this;
        btn.onclick = function() { 
            self.wikiwyg.switchMode(this.className);
        };
 
        var label = Wikiwyg.createElementWithAttrs(
            'span', { 'for': btn_id }
        );
        label.appendChild(document.createTextNode(mode_object.modeDescription));

        cntrl.appendChild(btn);
        btn.appendChild(label);
    }
    this.div.appendChild(cntrl);
}

proto.add_break = function() {
    if (this.imgdiv.childNodes.length) {
        this.div.appendChild(this.imgdiv);
        this.imgdiv = Wikiwyg.createElementWithAttrs(
            'div', {
                'class': 'wikiwyg_buttons'
            }
        );
    } else {
        var clear = Wikiwyg.createElementWithAttrs(
            'div', {
                'style': 'clear:both'
            }
        );
        //this.div.appendChild(clear);
	// XXX float right !
    }
}

proto.add_styles = function() {
    var options = this.config.styleSelector;
    var labels = this.config.controlLabels;

    this.styleSelect = Wikiwyg.createElementWithAttrs(
        'select', {
            'class': 'wikiwyg_selector'
        }
    );

    for (var i = 0; i < options.length; i++) {
        value = options[i];
        var option = Wikiwyg.createElementWithAttrs(
            'option', { 'value': value }
        );
        option.appendChild(document.createTextNode(labels[value]));
        this.styleSelect.appendChild(option);
    }
    var self = this;
    this.styleSelect.onchange = function() { 
        self.set_style(this.value) 
    };
    this.div.appendChild(this.styleSelect);
}

proto.set_style = function(style_name) {
    var idx = this.styleSelect.selectedIndex;
    // First one is always a label
    if (idx != 0)
        this.wikiwyg.current_mode.process_command(style_name);
    this.styleSelect.selectedIndex = 0;
}
