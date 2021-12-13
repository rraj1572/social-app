<?php
class Push
{
    private $id;
    private $title;
    private $type;
    private $data;
    private $isBackground;
    private $isCustom = FALSE;

    function __construct()
    {

    }

    public function setId($id) {
      $this->id = $id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setIsBackground($isBackground) {
      $this->isBackground = $isBackground;
    }

    public function setIsCustom($isCustom) {
      $this->isCustom = $isCustom;
    }

    public function getPush()
    {
        $res                  = array();
        $res["id"] = $this->id;
        $res['title']         = $this->title;
        $res['type']          = $this->type;
        $res['data']          = $this->data;
        $res['isBackground']  = $this->isBackground;
        $res['isCustom']  = $this->isCustom;
        return $res;
    }
}
