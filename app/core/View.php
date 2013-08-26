<?php

class View {
  public $filename, $html, $attrs, $css, $preJs, $js, $start;

  public function __construct($filename=Null, $attrs=Null) {
    $this->start = microtime(True);
    $this->filename = $filename;
    $this->html = Null;
    $this->attrs = [
      'title' => Config::APP_NAME,
      'subtitle' => Null,
      'encoding' => 'UTF-8'
    ];
    array_merge($this->attrs, $attrs ? $attrs : []);

    // set default css and js.
    $this->css = [
      ['url' => "//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css"],
      ['url' => "/css/timePicker.css"],
      ['url' => "/css/jquery.countdown.css"],
      ['url' => "/css/jquery.jqplot.min.css"],
      ['url' => "/css/bootstrap-dataTables.css"],
      ['url' => "/css/token-input.css"],
      ['url' => "/css/llanimu.css"],
    ];

    $this->preJs = [
      ['url' => "//cdnjs.cloudflare.com/ajax/libs/modernizr/2.6.2/modernizr.min.js"]
    ];

    $this->js = [
      ['url' => "//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"],
      ['url' => "//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"],
      ['url' => "//www.google.com/jsapi"],
      ['url' => "/js/vendor/bootstrap.min.js"],
      ['url' => "/js/vendor/jquery.jqplot.min.js"],
      ['url' => "/js/vendor/jqplot.highlighter.min.js"],
      ['url' => "/js/vendor/jqplot.trendline.min.js"],
      ['url' => "/js/vendor/jqplot.barRenderer.min.js"],
      ['url' => "/js/vendor/jqplot.pieRenderer.min.js"],
      ['url' => "/js/vendor/jqplot.categoryAxisRenderer.min.js"],
      ['url' => "//cdnjs.cloudflare.com/ajax/libs/datatables/1.9.4/jquery.dataTables.min.js"],
      ['url' => "/js/vendor/jquery.timePicker.min.js"],
      ['url' => "/js/vendor/jquery.columnmanager.min.js"],
      ['url' => "/js/vendor/jquery.countdown.min.js"],
      ['url' => "/js/vendor/jquery.tokeninput.min.js"],
      ['url' => "//cdnjs.cloudflare.com/ajax/libs/swfobject/2.2/swfobject.min.js"],
      ['url' => "/js/vendor/jwplayer.js"],
      ['url' => "/js/vendor/sigma.min.js"],
      ['url' => "/js/vendor/sigma.forceatlas2.js"],
      ['url' => "/js/vendor/sigma.parseGexf.js"],
      ['url' => "/js/vendor/holder.js"],
      ['url' => "/js/llanimu.js"],
      ['url' => "/js/postFeed.js"]
    ];

    // if ($video_directory != '') {
    //   $foo = [
    //     "/js/jquery.jcarousel.pack.js",
    //     "/js/jquery.sync.js",
    //     "/js/effects.core.js",
    //     "/js/effects.blind.js",
    //     "/js/effects.slide.js",
    //     "/js/effects.slide.js",
    //     "/js/contentPanel.js",
    //     "'.$video_directory.'.js"
    //   ];
    // }
  }

  public static function CopyAttrs(View $view, $filename=Null) {
    $newView = new View($filename, $view->attrs);
    return $view->copyAttrsTo($newView);
  }
  public function copyAttrsTo(View $view) {
    $view->css = $this->css;
    $view->preJs = $this->preJs;
    $view->js = $this->js;
    return $view;
  }

  public function escape($input) {
    if ($input === '' || $input === 'NULL') {
      return '';
    }
    return htmlspecialchars(html_entity_decode($input, ENT_QUOTES, $this->attrs['encoding']), ENT_QUOTES, $this->attrs['encoding']);
  }

  public function urlencode($x) {
    $out = '';
    for ($i = 0; isset($x[$i]); $i++) {
      $c = $x[$i];
      if (!ctype_alnum($c)) $c = '%' . sprintf('%02X', ord($c));
      $out .= $c;
    }
    return $out;
  }

  public function googleChart(array $chartProperties, array $seriesPoints, array $seriesProperties) {
    $src = 'google.load("visualization", "1", {packages:["corechart"]});
google.setOnLoadCallback(drawChart);
function drawChart() {
  var data = new google.visualization.DataTable();
';
    // output series definitions and properties.
    foreach ($seriesProperties as $seriesKey => $series) {
      if (!isset($series['type'])) {
        // infer series type from data.
        if ($seriesPoints[$seriesKey]) {
          switch(gettype($seriesPoints[0][$seriesKey])) {
            case "boolean":
              $seriesProperties[$seriesKey]['type'] = 'boolean';
              break;
            case "string":
              $seriesProperties[$seriesKey]['type'] = 'string';
              break;
            case "object":
              if (get_class($seriesPoints[0][$seriesKey]) == "DateTime") {
                $seriesProperties[$seriesKey]['type'] = "date";
              } else {
                $seriesProperties[$seriesKey]['type'] = "number";
              }
              break;
            default:
            case "integer":
            case "double":
              $seriesProperties[$seriesKey]['type'] = 'number';
              break;
          }
        } else {
          $seriesProperties[$seriesKey]['type'] = 'number';
        }
      }
      $seriesTitle = isset($seriesProperties[$seriesKey]['title']) ? $seriesProperties[$seriesKey]['title'] : "";
      unset($seriesProperties[$seriesKey]['title']);
      $src .= "        data.addColumn({";
      $seriesAttrStrings = [];
      foreach ($seriesProperties[$seriesKey] as $attr=>$val) {
        $seriesAttrStrings[] = $attr.": '".addslashes($val)."'";
      }
      $src .= implode(", ", $seriesAttrStrings);
      $src .= "}, '".addslashes($seriesTitle)."');\n";
    }


    //output data points.
    $src .= "data.addRows(";
    $dataStrings = [];
    foreach ($seriesPoints as $pointArray) {
      // output each series in the correct format.
      $seriesStrings = [];
      foreach ($pointArray as $seriesKey=>$value) {
        if ($seriesProperties[$seriesKey]['type'] == 'string') {
          $seriesStrings[] = "'".$value."'";
        } elseif  ($seriesProperties[$seriesKey]['type'] == 'boolean') {
          $seriesStrings[] = boolean($value);
        } elseif ($seriesProperties[$seriesKey]['type'] == 'date') {
          $seriesStrings[] = "new Date(".$value->format('Y, ').(intval($value->format('n')) - 1).', '.$value->format('j, G, i').")";
        } else {
          if (is_integer($value)) {
            $seriesStrings[] = intval($value);
          } else {
            $seriesStrings[] = floatval($value);
          }
        }
      }
      $dataStrings[] = "[".implode(",", $seriesStrings)."]";
    }
    $src .= "[".implode(",\n", $dataStrings)."]);\n";
    $src .= "        var chart = new google.visualization.".addslashes($chartProperties['type'])."(document.getElementById('".addslashes($chartProperties['id'])."'));
          chart.draw(data, {";

    unset($chartProperties['id']);
    unset($chartProperties['type']);
    foreach ($chartProperties as $key => $value) {
      if (is_array($value)) {
        $src .= $key.": {";
        $valueArray = [];
        foreach ($value as $valKey => $valVal) {
          if (is_integer($valVal)) {
            $valueArray[] = $valKey.": ".intval($valVal);
          } elseif (is_string($valVal)) {
            $valueArray[] = $valKey.": '".$valVal."'";
          } else {
            $valueArray[] = $valKey.": ".floatval($valVal);
          }
        }
        $src .= implode(", ", $valueArray)."}, ";
      } else {
          if (is_integer($value)) {
            $src .= $key.": ".intval($value).", ";
          } elseif (is_string($value)) {
            $src .= $key.": '".addslashes($value)."', ";
          } else {
            $src .= $key.": ".floatval($value).", ";
          }
      }
    }
    $src .= "});
        }\n";
    $this->js[] = ['src' => $src];
  }

  public function html($html=Null) {
    if ($html === Null) {
      // getter.
      if ($this->html === Null) {
        if ($this->filename !== Null && file_exists($this->filename)) {
          ob_start();
          include($this->filename);
          $this->html = ob_get_clean();
        } else {
          throw new Exception("Could not find view: ".$this->filename);
        }
      }
      return $this->html;
    }
    // setter.
    $this->html = $html;
    return $this;
  }

  public function render() {
    echo $this->html();
  }
}

?>