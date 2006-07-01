#### WARN
#### WARN Some functions maybe incompatible with this modified scripts.
#### WARN
ver=0.12
tarball=Wikiwyg-$(ver).tar.gz
files=lib COPYRIGHT LICENSE Changes
install:
	wget -c http://openjsan.org/dist/i/in/ingy/$(tarball)
	if [ -f $(tarball) ]; then \
		mkdir -p tmp;\
		tar --directory tmp -xzvf $(tarball);\
		(cd tmp/Wikiwyg-$(ver);tar cvf - $(files) |\
		(cd ../..;tar xf - )); rm -r tmp;\
	fi
