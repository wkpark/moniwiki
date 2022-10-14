# the latest JmolApplet.jar
verap=14.31.18
AP=$(shell echo $(verap) | cut -d. -f1)
BP=$(shell echo $(verap) | cut -d. -f2)

applet:
	wget -N https://downloads.sourceforge.net/project/jmol/Jmol/Version%20$(AP).$(BP)/Jmol%20$(verap)/Jmol-$(verap)-binary.zip ; \
	unzip Jmol-$(verap)-binary.zip -d jmol
	cp jmol/jmol-$(verap)/applet/JmolApplet.jar .
	rm -r jmol
	#rm jmol-$(ver)-binary.zip

uninstall:
	rm -f JmolApplet.jar
