DESTDIR=
prefix=/usr
install: moni-install
	mkdir -p $(DESTDIR)$(prefix)/share/moniwiki
	mkdir -p $(DESTDIR)$(prefix)/share/moniwiki/bin
	cp -ar data wikiseed imgs css $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar lib plugin $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar locale tools $(DESTDIR)$(prefix)/share/moniwiki
	cp wiki.php wikilib.php $(DESTDIR)$(prefix)/share/moniwiki
	cp wikismiley.php $(DESTDIR)$(prefix)/share/moniwiki
	cp config.php.default monisetup.php $(DESTDIR)$(prefix)/share/moniwiki
	cp monisetup.sh secure.sh $(DESTDIR)$(prefix)/share/moniwiki
	cp moni-install $(DESTDIR)$(prefix)/share/moniwiki/bin

moni-install: moni-install.in
	cat moni-install.in | sed 's%@@INSTDIR@@%$(prefix)/share/moniwiki%' >moni-install
	chmod 755 moni-install
