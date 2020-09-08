<?php


namespace Wasinger\MetaformBundle;


class RenderedTextdataDto
{
    /** @var string  */
    private $text = '';
    /** @var string  */
    private $html = '';

    public function addText(string $text) {
        $this->text .= $text;
    }

    public function addHtml(string $html) {
        $this->html .= $html;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }


}