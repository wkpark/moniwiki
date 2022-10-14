# the latest JmolApplet.jar
verap=14.31.18
AP=$(shell echo $(verap) | cut -d. -f1)
BP=$(shell echo $(verap) | cut -d. -f2)

# the latest JSmol/Jmol
verjs=14.32.77
AJ=$(shell echo $(verjs) | cut -d. -f1)
BJ=$(shell echo $(verjs) | cut -d. -f2)

applet:
	wget -N https://downloads.sourceforge.net/project/jmol/Jmol/Version%20$(AP).$(BP)/Jmol%20$(verap)/Jmol-$(verap)-binary.zip ; \
	unzip Jmol-$(verap)-binary.zip -d jmol
	cp jmol/jmol-$(verap)/applet/JmolApplet.jar .
	rm -r jmol
	#rm jmol-$(ver)-binary.zip

jsmol.js:
	wget -N https://downloads.sourceforge.net/project/jmol/Jmol/Version%20$(AJ).$(BJ)/Jmol%20$(verjs)/Jmol-$(verjs)-binary.zip ; \
	unzip Jmol-$(verjs)-binary.zip -d jmol
	unzip jmol/jmol-$(verjs)/jsmol.zip
	(cd jsmol/js; cat JSmoljQueryExt.js JSmolCore.js j2sjmol.js JSmol.js JSmolApi.js JSmolApplet.js > ../JSmol.all.js)
	rm -r jmol
	#rm Jmol-$(verjs)-binary.zip

uninstall:
	rm -f JmolApplet.jar
