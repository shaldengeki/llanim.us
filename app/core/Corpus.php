<?php
class Corpus {
  private $documents, $tfs, $dfs, $length;
  public function __construct() {
    $this->documents = $this->tfs = $this->dfs = [];
    $this->length = 0;
  }
  public function add(Document $doc) {
    $doc->corpus = $this;
    $this->documents[] = $doc;
    $this->length++;
    foreach ($doc->tfs() as $word=>$count) {
      if (isset($this->tfs[$word])) {
        $this->tfs[$word] += $count;
        $this->dfs[$word]++;
      } else {
        $this->tfs[$word] = $count;
        $this->dfs[$word] = 1;
      }
    }
    return $this;
  }
  public function tf($term) {
    // term frequency of a single term.
    return isset($this->tfs[$term]) ? $this->tfs[$term] : 0;
  }
  public function tfs($tfs=Null) {
    if ($tfs !== Null) {
      $this->tfs = $tfs;
      return $this;
    }
    return $this->tfs;
  }
  public function df($term) {
    // document frequency of a single term.
    return isset($this->dfs[$term]) ? $this->dfs[$term] : 0;
  }
  public function dfs($dfs=Null) {
    if ($dfs !== Null) {
      $this->dfs = $dfs;
      return $this;
    }
    return $this->dfs;
  }
  public function length($length=Null) {
    if ($length !== Null) {
      $this->length = intval($length);
      return $this;
    }
    return $this->length;
  }
}
?>