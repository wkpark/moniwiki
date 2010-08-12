" MoniWiki text syntax file
" Filename:	moni.vim
" Language:	MoniWiki text
" Maintainer:	Won-Kyu Park <wkpark@kldp.org>
" Last Change:	Wed, 26 Jun 2002 21:31:09 -0300
" $Id$

" modified version of the moin.vim by Gustavo Niemeyer <niemeyer@conectiva.com>
"
if version < 600
  syntax clear
elseif exists("b:current_syntax")
  finish
endif

syn sync lines=30

syn region  moniSettings    start="\%^@ \(WARNING\|Using\)" end="^[^@]"me=e-1 end="^$" 
syn region  moniItalic      start="''" end="''" oneline
syn region  moniBold        start="'''" end="'''" oneline
syn region  moniSub         start=",," end=",," oneline
syn region  moniSup         start="\^\^" end="\^\^" oneline
syn region  moniSup1        start="\^" end="\^" oneline
syn region  moniDel         start="\~\~" end="\~\~" oneline
syn region  moniDel1        start="\-\-" end="\-\-" oneline
syn match   moniNull        "''''''"
syn match   moniBullet      "[[:space:]]\+\(\*\|[[:digit:]]\+\.\)"
syn region  moniCode        start="{{{" end="}}}"
syn match   moniComment     "^##.*$"
syn match   moniPI          "^#[^#].*$"
syn region  moniMacro       start="\[\[" end="\]\]" oneline
syn region  moniFootNote    start="\[\*" end="\]" oneline
syn region  moniHeader      start="^\s*\z(=\{1,5}\) " end=" \z1$" oneline
syn match   moniTableOpt    "||<[^>]*>"hs=s+2 contained
syn region  moniTable       start="^||" end="||$" oneline contains=moniTableOpt
syn match   moniRule        "^-\{4,}$"
syn match   moniSmileys     "\(\s\|\_^\)\(:)\|B:\|:))\|;)\|:>\|:D\|<:(\|X-(\|:O\|:(\|/!\\\|<!>\|(!)\|\<!\>\|:?\|:\\\|>:>\|%)\|@)\||)\|;))\|(./)\|{OK}\|{X}\|{i}\|{1}\|{2}\|{3}\)\(\s\|\_$\)"
syn match   moniLinkMoin    "\(/\?[[:upper:]][[:lower:][:digit:]]\+\)\{2,}"
syn match   moniLinkEmail   "<\?[[:alnum:]-._+]\+@[[:alnum:]-]\+\.[[:alnum:]-.]\+>\?"
syn match   moniLinkUrl     "\(http\|https\|ftp\|nntp\|news\|mailto\|telnet\|wiki\|file\|attachment\|inline\|drawing\):\([^][:space:]<"'}|:,.)?!]\|[]"'}|:,.)?!][^][:space:]<"'}|:,.)?!]\)\+"
syn match   moniBracketed  "\[\([^]]\)\+\]"
syn match   moniLinkBUrl    "\[\(\(http\|https\|ftp\|nntp\|news\|mailto\|telnet\|wiki\|file\|attachment\|inline\|drawing\):\([^][:space:]<"'}|:,.)?!]\|[]"'}|:,.)?!][^][:space:]<"'}|:,.)?!]\)\+\|#\)[^][:space:]]\+\(\s[^]]\+\)\?\]"
syn match   moniLinkQUrl    "\[\"[^]]\+\"\]"
syn match   moniLinkInter   "[A-Z][a-zA-Z0-9]\+:[^[:space:]'\":<]\([^][:space:]<"'}|:,.)?!]\|[]"'}|:,.)?!][^][:space:]<"'}|:,.)?!]\)\+"

if !exists("did_dic_syntax_inits")
  let did_dic_syntax_inits = 1
  hi link moniComment    Comment
  hi def  moniPI         ctermfg=red guifg=red
  hi def  moniSup        ctermfg=green guifg=green
  hi link moniSup1       moniSup
  hi def  moniDel        ctermfg=red guifg=red
  hi link moniDel1       moniDel
  hi def  moniSub        ctermfg=green guifg=green
  hi link moniFootNote   Comment
  hi def  moniItalic     term=italic cterm=italic gui=italic
  hi def  moniSettings   ctermfg=green guifg=green
  hi link moniMacro      Macro
  hi link moniHeader     Title
  hi def  moniTable      ctermfg=yellow guifg=yellow
  hi def  moniTableOpt   ctermfg=green guifg=green
  hi link moniRule       Title
  hi def  moniCode       ctermfg=cyan guifg=cyan
  hi def  moniLink       ctermfg=green guifg=green
  hi def  moniBullet     ctermfg=blue guifg=blue
  hi def  moniNull       ctermfg=blue guifg=blue
  hi link moniLinkMoin   moniLink
  hi link moniLinkEmail  moniLink
  hi link moniLinkUrl    moniLink
  hi link moniLinkBUrl   moniLinkUrl
  hi link moniLinkQUrl   moniLinkUrl
  hi link moniBracketed  moniLinkUrl
  hi link moniLinkInter  moniLink
  hi def  moniSmileys    ctermfg=yellow guifg=yellow
endif

let b:current_syntax = "moni"

