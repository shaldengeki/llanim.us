      <hr>

      <footer>
          <p id='gen_time' class='pull-left'>Page generation took <?php echo round(microtime(True) - $this->start, 3); ?> seconds.</p>
          <p class='pull-right'>&copy; Seasonal Anime Topic 2013</p>
      </footer>
    </div>
<?php
  foreach ($this->js as $js) {
?>
    <script type="text/javascript"<?php echo isset($js['url']) ? 'src="'.$js['url'].'"' : ""; ?>><?php echo isset($js['src']) ? $js['src'] : ""; ?></script>

<?php
  }
?>
    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(["_setAccount", "UA-21665738-1"]);
      _gaq.push(["_trackPageview"]);
      (function() {
        var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true;
        ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js";
        var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);
      })();
    </script>
    </body>
</html>