
                </div><!-- .entry-content -->
                <footer class="entry-meta">
                    <!-- AddThis Button BEGIN -->
                    <div class="entry-meta-social">
                        <div class="addthis_toolbox addthis_default_style addthis_16x16_style">
                            <a class="addthis_button_facebook"></a>
                            <a class="addthis_button_twitter"></a>
                            <a class="addthis_button_tumblr"></a>
                            <a class="addthis_button_pinterest_share"></a>
                            <a class="addthis_button_google_plusone_share"></a>
                            <a class="addthis_button_email"></a>
                        </div>
                        <script type="text/javascript">var addthis_config = {"data_track_addressbar":true};</script>
                        <script type="text/javascript" src="//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-527e0d8163589d74"></script>
                    </div>
                    <!-- AddThis Button END -->
                    <div class="entry-meta-detail">
                        <?php 
                            if (!empty($lastedit)){
                                $lastedit = date("g:i a, F j, Y", strtotime($lastedit. " " . $lasttime));
                                print "Modified on $lastedit. ";
                            }
                            ?>
                    </div>
                    <div class="moni">
                        Theme Publish by <a href="http://kovshenin.com/" rel="designer">Konstantin Kovshenin</a> Converted by <a href="http://haruair.com/" target="_blank">Haruair</a><div><a target="_blank" href="http://moniwiki.sourceforge.net/" rel="generator"><img src="<?php echo $this->themeurl;?>/imgs/moniwiki-powered-thin.png" alt="Powered By MoniWiki" /></a></div>
                        
                    </div>
                </footer>
            </article>
        </div>
    </div>
  </div>
  <div id="secondary" class="widget-area" role="complementary">

    <aside id="search" class="widget widget_search">
        <form id='searchform' action='' method='get' onsubmit="return moin_submit();" role="search">
            <label for="s" class="assistive-text">Search</label>
            <input type="text" class="field" name="value" value="" id="s" placeholder="Search …">
            <input type="submit" class="submit" name="status" id="searchsubmit" value="Search">
        </form>
    </aside>
    <aside id="meta-nav" class="widget widget_metanav">
        <h1 class="widget-title">Meta</h1>

        <?php
            $menu = str_replace("<div id='wikiAction'>", "", $menu);
            $menu = str_replace("</div>", "", $menu);
            echo $menu;
        ?>
    </aside>
  </div>
</div>

<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
<script type='text/javascript' src='<?php echo $this->themeurl;?>/js/small-menu.js'></script>
<script type='text/javascript' src='<?php echo $this->themeurl;?>/js/jquery.cookie.js'></script>
<script type='text/javascript' src='<?php echo $this->themeurl;?>/js/script.js'></script>
