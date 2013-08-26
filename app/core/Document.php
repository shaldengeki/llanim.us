<?php
class Document {
  private $text, $corpus, $tfs, $tfidfs;
  public function __construct($text, Corpus $corpus=Null) {
    $this->text = $text;
    $this->tfs = $this->tfidfs = $this->corpus = Null;
    if ($corpus !== Null) {
      $corpus->add($this);
    }
  }
  private function setTfs() {
    // set tfs.
    $this->tfs = [];
    $words = explode(" ", $this->text);
    foreach (array_count_values($words) as $word=>$count) {
      if ($word) {
        $this->tfs[$word] = $count;
      }
    }
  }
  public function tfs($tfs=Null) {
    if ($tfs !== Null) {
      $this->tfs = $tfs;
      return $this;
    }
    if ($this->tfs === Null) {
      $this->setTfs();
    }
    return $this->tfs;
  }
  public function tf($term) {
    return isset($this->tfs()[$term]) ? $this->tfs()[$term] : 0;
  }
  public function setTfIdfs() {
    $this->tfidfs = [];
    foreach ($this->tfs() as $word=>$count) {
      $this->tfidfs[$word] = $this->tf($word) * log($this->corpus->length() / (1 + $this->corpus->df($word)));
    }
  }
  public function tfidfs($tfidfs=Null) {
    if ($tfidfs !== Null) {
      $this->tfidfs = $tfidfs;
      return $this;
    }
    if ($this->tfidfs === Null) {
      $this->setTfIdfs();
    }
    return $this->tfidfs;
  }
  public function tfidf($term) {
    return isset($this->tfidfs()[$term]) ? $this->tfidfs()[$term] : 0;
  }
  public function corpus($corpus=Null) {
    if ($corpus !== Null) {
      $this->corpus = $corpus;
      return $this;
    }
    return $this->corpus;
  }
}
?>