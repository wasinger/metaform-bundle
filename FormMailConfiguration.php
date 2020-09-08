<?php
namespace Wasinger\MetaformBundle;

use Symfony\Component\Mime\Address;

class FormMailConfiguration
{
    public $from;
    public $to;
    public $cc;
    public $bcc;
    public $subject;

    /**
     * Whether the sender should recieve a copy of the mail
     *
     * @var boolean
     */
    public $copy_to_sender;

    /**
     * The name of the form field containing the sender's e-mail address
     * @var string
     */
    public $senderfield;

    public $text_pre;
    public $text_post;
    public $text_pre_sender;
    public $text_post_sender;

    private $required = array('to', 'subject');

    /**
     * @param array $array
     * @return FormMailConfiguration
     */
    static function fromArray($array)
    {
        $o = new static();
        foreach ($o as $name => $value) {
            if (isset($array[$name])) {
                $o->set($name, $array[$name]);
            }
        }
        $o->validate();
        return $o;
    }

    /**
     * @throws \Exception
     */
    public function validate()
    {
        foreach ($this->required as $field) {
            if (!$this->$field) throw new \Exception('Mailconfiguration: required field "' . $field . '" not set.');
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws \Exception
     */
    public function set($name, $value)
    {
        if (!\property_exists($this, $name)) throw new \InvalidArgumentException('Mailconfiguration: Property ' . $name . ' does not exist');
        if (in_array($name, ['from', 'to', 'cc', 'bcc'])) {
            $value = self::parse_addresses($value);
        }
        $this->$name = $value;
    }

    public function get($name)
    {
        return $this->$name;
    }

    public function getAsString($name)
    {
        return self::stringify_addresses($this->$name);
    }

    /**
     * Parse mail addresses from string or array
     * (array may be in SwiftMailer notation [address => realname])
     * to an array of Address objects
     *
     * @param string|array $a
     * @return Address[]
     */
    static function parse_addresses($a): array
    {
        $r = [];
        if (is_array($a)) {
            foreach ($a as $k => $v) {
                if (is_int($k) && is_string($v)) {
                    $r[] = Address::fromString($v);
                } elseif (is_array($v)) {
                    foreach ($v as $k1 => $v1) {
                        $email = filter_var($k1, \FILTER_VALIDATE_EMAIL);
                        if ($email) {
                            $r[] = new Address($email, $v1);
                        }
                    }
                } elseif (is_string($k) && is_string($v)) {
                    $email = filter_var($k, \FILTER_VALIDATE_EMAIL);
                    if ($email) {
                        $r[] = new Address($email, $v);
                    }
                }
            }
        } else if (\is_string($a)) {
            $r[] = Address::fromString($a);
        }
        return $r;
    }

    /**
     * @param Address[] $addresses
     * @return string
     */
    static function stringify_addresses(array $addresses): string
    {
        $r = '';
        foreach ($addresses as $a) {
            /** @var Address $a */
            if ($r != '') {
                $r .= ', ';
            }
            $r .= $a->getAddress();
        }
        return $r;
    }
}
