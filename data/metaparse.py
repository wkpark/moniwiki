#!/usr/bin/python
# $Id$
# python version metaparse.py by wkpark@kldp.org

import string,os,sys,re,urllib

local_charset="EUC-KR"
default_charset="UTF-8"

cache_dir="cache/metawiki"
shared_metadb="metadb.cache"
db_type="bsddb"
#db_type="gdbm"

MONIKER = 0
BASE_URL = 1
SOURCE_URL = 2
CUT_REGEX = 3
MATCH_REGEX = 4

class MetaWiki:

    NO_CUT_REGEX = r'(\S.{1,})'

#MOINMOIN_CUT_REGEX_OLD = '.*?\\<a name(.+?)\\<hr\\>.*'
#MOINMOIN_MATCH_REGEX_OLD = 'a href=\\".*[\\/\\?](.+?)\\"'

    USEMOD_CUT_REGEX = r'.*?<hr.*?>(.+?)<hr.*?>.*'
    USEMOD_MATCH_REGEX = r'<[aA]\s+.*?>(.*?)<\/[aA]>'

    TAVI_CUT_REGEX = r'.*?\\<hr\\>(.+?)\\<hr\\>.*'
    TAVI_MATCH_REGEX = r'\\<[aA]\\s+[^\\>]*[\\/\\?](.+?)[\\\'\\"\\>]'

    TWIKI_MATCH_REGEX = r'rdiff[\\?\\/]([^\"]*)'

    ZWIKI_CUT_REGEX = r'(.+?)\\<hr\\>.*'
    ZWIKI_MATCH_REGEX = r'href=\\"(.+?)\\"'

    DEFAULT_MATCH_REGEX = r'(.*)'

    RULES = {
# CloneType: (url,cut_regex,match_regex)
      "usemod":
        ("action=index", USEMOD_CUT_REGEX, USEMOD_MATCH_REGEX),
      "MoinMoin":
        ("?action=titleindex", NO_CUT_REGEX, DEFAULT_MATCH_REGEX),
      "PediaWiki":
        #("Special:Allpages", '<table (.*)</table>', DEFAULT_MATCH_REGEX),
        ("Special:AllPages", '<\!\-\- start content \-\->(.*)<\!\-\- end content \-\->', '"/wiki/([^"]+)"'),
      "foldoc":
        ("http://foldoc.doc.ic.ac.uk/foldoc/contents/all.html",
         NO_CUT_REGEX, r'\?(.*)"'),
      "jargon":
        ("http://www.tuxedo.org/~esr/jargon/html/-$C-.html",
         '.*?<ul>(.*?)</ul>.*','entry/(.*?).html'),
      "Wolfram":
        ('letters/$C',
         NO_CUT_REGEX,'\.\./(.*?).html'),
      "Wiki":
        #("http://sunir.org/apps/meta.pl?list=WikiWiki",
        # NO_CUT_REGEX, DEFAULT_MATCH_REGEX),
        ("search=$",
         USEMOD_CUT_REGEX,USEMOD_MATCH_REGEX),
      "wikiX":
        ("AllPages", NO_CUT_REGEX, '\?display=(.*?)"'),
      "PhpWiki":
        ("AllPages", NO_CUT_REGEX, 'class="wiki">(.*?)<'),
      "tavi":
        ("action=find", "<div id=\"body\">(.*?)</div>", '\?page=([^"]+)"'),
      "TWiki":
        ("?topic=WebIndex",NO_CUT_REGEX,TWIKI_MATCH_REGEX),
      "PediaIndex":
        ("Special:AllPages",
         '<\!\-\- start content -->(.*)<\!\-\- end content \-\->',
         '/w/index.php\?title=(Special:AllPages&amp;from=.*?)"'),
      "GtkDoc":
        ("?", NO_CUT_REGEX, '<dt>\s*([^,]+),\s*<a href="([^"]+)">'),
    };

    def __init__(self):
        if not os.path.isdir(cache_dir):
            os.mkdir(cache_dir, 0777)
	return

    def _fetch(self,wikiname,type,urls,findall=False):
        class MyURLopener(urllib.FancyURLopener):
            version = "App/0.1"

        myurllib = MyURLopener()

        catcmd = 'cat %s'
        num=0
        for url in urls:
            sys.stderr.write("Fetching %s\n" % url)
            try:
                archive, headers = myurllib.retrieve(url, None, progress,None)
                #archive, headers = urllib.urlretrieve(url, None)

            except TypeError:           # 1.5.1 does not accept 3rd argument
                try:
                    archive, headers = myurllib.retrieve(url, None,None,None)
                except IOError:
                    sys.stderr.write(" apparently not found\n")
                    continue
            except IOError:
                sys.stderr.write(" apparently not found\n")
                continue
#        if os.system(catcmd % archive) != 0:
#            raise "Fetch failed"
            # 
	    fp = open(archive)
            all = fp.readlines()
            fp.close()
            content = "".join(all);

            p = re.compile(self.RULES[type][1],re.M | re.S)

            dummy=p.search(content)
            if dummy:
                cache=open(archive,'w')
                cache.write(dummy.group(1))
                cache.close()

            if num==0:
                cache=open(os.path.join(cache_dir,wikiname),'w')
            else:
                cache=open(os.path.join(cache_dir,wikiname),'a')
            num=num+1
            for line in os.popen(catcmd % archive).readlines():
                if self.RULES.has_key(type):
                    if findall:
                        dummy=re.findall(self.RULES[type][2],line)
                        if dummy:
                            sa = set(dummy)
                            cache.write("\n".join(sa) + "\n")
                    else:
                        dummy=re.search(self.RULES[type][2],line)
                        if dummy:
                            try:
                                cache.write(dummy.group(1)+"\t"+dummy.group(2)+"\n")
                    	    except IndexError:
                                cache.write(dummy.group(1)+"\n")
                else:
                    cache.write(line)
            cache.close()

    def _get_url(self,wikiname,type,url):
        if self.RULES.has_key(type):
            # absolute URL
            if re.match(r'^http://',self.RULES[type][0]):
                indexurl=self.RULES[type][0]
            elif re.search(r'\$PAGE',url):
                indexurl=re.sub(r'\$PAGE',self.RULES[type][0],url)
            else:
                indexurl=url+self.RULES[type][0]
#            print "    " + indexurl
        elif type != '':
            if re.search(r'\$PAGE',url):
                indexurl=re.sub(r'\$PAGE',type,url)
            else:
                indexurl=url+type

	return indexurl

    def _GtkDoc_rule(self,wikiname,type,url):
        urls=[]
        urls.append(self._get_url(wikiname,type,url))
        self._fetch(wikiname,type,urls)

    def _MoinMoin_rule(self,wikiname,type,url):
        urls=[]
        urls.append(self._get_url(wikiname,type,url))
        self._fetch(wikiname,type,urls)

    def _usemod_rule(self,wikiname,type,url):
        urls=[]
        urls.append(self._get_url(wikiname,type,url))
        self._fetch(wikiname,type,urls)

    def _PediaWiki_rule(self,wikiname,type,url):
        urls=[]
        urls.append(self._get_url(wikiname,type,url))
        #urls.append("http://www.wikipedia.org/w/wiki.phtml?title=Special:Allpages&from=!")
        #self._fetch(wikiname,type,urls)
        self._fetch(wikiname,"PediaIndex",urls,True)
	fp=open("cache/metawiki/" + wikiname)
        all=fp.readlines()
        fp.close()
        urls=[]
        for idx in all:
          urls.append(url + string.replace(idx,'&amp;','&'))
        self._fetch("PediaIndex","PediaIndex",urls,True)

	fp=open("cache/metawiki/PediaIndex")
        all=fp.readlines()
        fp.close()
        urls=[]
        for idx in all:
          urls.append(url + string.replace(idx,'&amp;','&'))
        self._fetch("PediaIndex","PediaIndex",urls,True)

	fp=open("cache/metawiki/PediaIndex")
        all=fp.readlines()
        fp.close()
        urls=[]
        for idx in all:
          urls.append(url + string.replace(idx,'&amp;','&'))
        self._fetch(wikiname,type,urls,True)

    def _foldoc_rule(self,wikiname,type,url):
        urls=[]
        urls.append(self._get_url(wikiname,type,url))
        self._fetch(wikiname,type,urls)

    def _jargon_rule(self,wikiname,type,url):
        urls=[]
        temp=self._get_url(wikiname,type,url)
        for l in list("0ABCDEFGHIJKLMNOPQRSTUVWXYZ"):
            urls.append(re.sub(r'\$C',l,temp))

        self._fetch(wikiname,type,urls)

    def _Wolfram_rule(self,wikiname,type,url):
        urls=[]
        temp=self._get_url(wikiname,type,url)
        for l in list("0ABCDEFGHIJKLMNOPQRSTUVWXYZ"):
            urls.append(re.sub(r'\$C',l,temp))

        self._fetch(wikiname,type,urls)

    def _Wiki_rule(self,wikiname,type,url):
        urls=[]
        urls.append(self._get_url(wikiname,type,url))
        self._fetch(wikiname,type,urls)

    def getMetaCache(self,wikiname,type,url):
        if hasattr(self, '_' + type + '_rule'):
            getattr(self, '_' + type + '_rule')(wikiname,type,url)
        else:
            urls=[]
            urls.append(self._get_url(wikiname,type,url))
            self._fetch(wikiname,type,urls)
	return
	
def getMetaIndex(metamap,fetch=1):
    wikis=[]
    lines=[]

    if os.path.isfile(metamap):
      map=open(metamap,'r')
      lines.extend(map.readlines())
      map.close()
    else:
      lines.append(metamap)
    # read other maps hear

    metaInfo=MetaWiki()

    for wiki in lines:
        if wiki[0] == '#': continue
        try:
            dummy=string.split(string.strip(wiki),None)
            if len(dummy) == 2:
                wikiname=dummy[0]
                url=dummy[1]
                type="MoinMoin"
            elif len(dummy) == 3:
                wikiname=dummy[0]
	        url=dummy[1]
                type=dummy[2]
            sys.stderr.write("\nProcessing %s:%s\n" % (wikiname,type))
        except:
            continue

        type=string.strip(type)
        # save all wikinames
	wikis.append(wikiname)

        if fetch: metaInfo.getMetaCache(wikiname,type,url)
    return wikis

def normalize(name):
    temp=re.sub(r'[!\'\"\?^\$\#~&*,_<>]','',name)
    temp=re.sub(r'(\-|%20|_|\+)',' ',temp)
    temp=re.sub(r'([a-z])([A-Z])',r'\1 \2',temp)
    temp=re.sub(r'([/\.])([A-Z])',r'\1 \2',temp)

    if not re.sub(r'[0-9%]','',temp): return ''
    words=string.split(temp)
    if len(words) >3:
        return ''
    elif len(words) != 1:
        for i in range(len(words)):
            words[i]=string.upper(words[i][0:1]) + words[i][1:]
#            words[i]=string.capitalize(words[i])
    else:
        if words[0][0]!=string.upper(words[0][0]):
            words[0]=string.lower(words[0])
    wikiname=string.join(words,'')
#    print wikiname
    return wikiname

def updateMetaCache(wikis,dbfile,dbtype):
    import os,sys,string
    if dbtype=='bsddb':
        mydb = __import__(dbtype)
        db = mydb.hashopen(dbfile,'c')
    elif dbtype=='gdbm':
        mydb = __import__(dbtype)
        db = mydb.open(dbfile,'c')

#   import bsddb,os,sys,string
#   db = bsddb.hashopen(dbfile,'c')
#   import gdbm,os,sys,string
#   db = gdbm.open(dbfile,'c')

    for wiki in wikis:
        f=open(os.path.join(cache_dir,wiki),'r')
        indices=f.readlines()
        f.close()
        for index in indices:
            key=string.strip(index)
            try:
                key=unicode(key,default_charset).encode(local_charset)
            except:
                pass

            if re.search("\t",key):
                temp=string.split(key)
                nkey=temp[0]
                key=temp[1]
            else:
                nkey=normalize(key)
                key=string.replace(key," ","%20")
#
#            print nkey+":"+key 
            if not nkey: continue
            if nkey != key:
                val=wiki+':'+key
            else:
                val=wiki
            if db.has_key(nkey):
                temp=db[nkey]
                temp=re.sub(r':\S+','',temp)
                sister_wikis=string.split(temp)
                if not wiki in sister_wikis:
                    db[nkey]=db[nkey]+" "+val
            else:
                db[nkey]=val
            #print val

# util. function from the fetch-po
def progress(block_count, block_size, total_size):
    if total_size == -1:
        sys.stderr.write('.')
        return

    if block_count == 0:
        sys.stderr.write("  getting %s bytes:" % total_size)
        return
    previous_count = block_count - 1
    if previous_count % 10 == 0:
        if previous_count % 50 == 0:
            if previous_count == 0:
                sys.stderr.write('\n%5dK -> ' % 0)
            else:
                previous_size = previous_count * block_size
                sys.stderr.write(' [%3d%%]\n%5dK -> '
                                 % ((100L * previous_size / total_size),
                                    previous_size / 1000))
        else:
            sys.stderr.write(' ')
    sys.stderr.write('.')
    if block_count * block_size >= total_size:
        while block_count % 50 != 0:
            if block_count % 10 == 0:
                sys.stderr.write(' ')
            sys.stderr.write(' ')
            block_count = block_count + 1
        sys.stderr.write(' [100%]\n')

#########################
if __name__ == "__main__":
    import getopt

    fetch=1
    sistermap='sistermap.txt'
    fetchonly=0

    arguments= tuple(sys.argv[1:])
    options, arguments = getopt.getopt(arguments, 'rfm:ghs:')
    for (option, value) in options:
       if option == '-r': fetch=0
       elif option == '-m': sistermap=value
       elif option == '-f': fetchonly=1
       elif option == '-g': db_type='gdbm'
       elif option == '-s': sistermap=value
       elif option == '-h':
         print "usage) python metaparse.py [options]"
         print " -r : refresh only"
         print " -m mymap.txt: use mymap.txt as a sistermap.txt"
         print " -f : fetch only - no metadb.cache generated"
         print " -g : set db_type as 'gdbm'"
         print " -s 'MySite http://to.my.site/': specify a wiki site"
         print ""
         sys.exit(0)

    wikis=getMetaIndex(sistermap,fetch)

    if not fetchonly:
       updateMetaCache(wikis,shared_metadb,db_type)
    sys.stderr.write("\nOK!\n")

# vim:et:sts=4:sw=4:
