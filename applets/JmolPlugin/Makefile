ver=11.6.10
install:
	wget -N http://downloads.sourceforge.net/jmol/jmol-$(ver)-binary.zip ; \
	unzip jmol-$(ver)-binary.zip -d jmol
	cp jmol/jmol-$(ver)/JmolApplet.jar .
	rm -r jmol
	#rm jmol-$(ver)-binary.zip
uninstall:
	rm -f JmolApplet.jar
