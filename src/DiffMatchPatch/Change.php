<?php


namespace DiffMatchPatch;


class Change {
    /**
     * @var int
     */
    protected $type;
    /**
     * @var string
     */
    protected $text;

    function __construct($type = null, $text = null)
    {
        $this->type = $type;
        $this->text = $text;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }




}
