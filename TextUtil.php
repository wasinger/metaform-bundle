<?php
namespace Wasinger\MetaformBundle;


use Soundasleep\Html2Text;

class TextUtil
{

    public static function html2text($string)
    {
        return Html2Text::convert($string, ['ignore_errors' => true]);
    }

    /**
     * @param $string
     * @param string $wrap_plaintext_in
     * @return string|string[]
     */
    public static function asHtml($string, $wrap_plaintext_in = 'div')
    {
        if (self::is_html($string)) {
            return $string;
        } else {
            $string = nl2br(trim($string), false);
            if (strpos($wrap_plaintext_in, '|') !== false) {
                $string = str_replace('|', $string, $wrap_plaintext_in);
            } else {
                if (in_array($wrap_plaintext_in, ['p', 'div', 'span'])) {
                    $string = '<' . $wrap_plaintext_in . '>' . $string . '</' . $wrap_plaintext_in . '>';
                } else {
                    $string = $wrap_plaintext_in . $string;
                }
            }
            if (substr($string, 0, 3) == '<p>') {
                // if the text starts with a paragraph, convert 2 linebreaks to new paragraph
//                $string = str_replace('<br>\n<br>\n', "</p>\n<p>", $string);
                $string = preg_replace('/<br\s?\/?>[\r\n\s]*<br\s?\/?>[\r\n\s]*/', "</p>\n<p>", $string);
            }
            return $string;
        }
    }

    /**
     * Prüfe ob ein String HTML-Code enthält
     *
     * @param $string
     * @return bool
     */
    public static function is_html($string)
    {
        return !(strip_tags($string) === $string);
    }

    public static function asText($string)
    {
        if (self::is_html($string)) {
            return self::html2text($string);
        } else {
            return $string;
        }
    }
}