DESTDIR=
prefix=/usr
install: moni-install
	mkdir -p $(DESTDIR)$(prefix)/share/moniwiki
	mkdir -p $(DESTDIR)$(prefix)/share/moniwiki/bin
	mkdir -p $(DESTDIR)$(prefix)/share/moniwiki/data
	cp data/*.* $(DESTDIR)$(prefix)/share/moniwiki/data
	cp -ar wikiseed imgs css $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar lib plugin local $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar locale tools $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar local config $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar theme $(DESTDIR)$(prefix)/share/moniwiki
	cp -ar applets $(DESTDIR)$(prefix)/share/moniwiki
	cp wiki.php wikilib.php $(DESTDIR)$(prefix)/share/moniwiki
	cp wikismiley.php $(DESTDIR)$(prefix)/share/moniwiki
	cp config.php.* monisetup.php $(DESTDIR)$(prefix)/share/moniwiki
	cp monisetup.sh secure.sh $(DESTDIR)$(prefix)/share/moniwiki
	cp moni-install $(DESTDIR)$(prefix)/share/moniwiki/bin

moni-install: moni-install.in
	cat moni-install.in | sed 's%@@INSTDIR@@%$(prefix)/share/moniwiki%' >moni-install
	chmod 755 moni-install
