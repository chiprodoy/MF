<?php

namespace MF;

class Licenses {

    static function valid()
    {
        return true;
    }
 
}

interface iModel{
    public static function searchable();
    public static function viewable();
    public static function getFilterable();
    public function getFormFields();
       /*
    *  return (object)[
    *        'belongsTo' => [],
    *        'hasMany' => [],
    *    ];
    *

    */
    public static function getRelationShip();
}

class CField
{
    public $type;

    public function __construct($type, $props = [])
    {
        $this->type = $type;

        foreach ($props as $prop => $value) {
            $this->$prop = $value;
        }
    }

    public function __call($name, $args)
    {
        $this->$name = (is_array($args[0])) ? implode(' ', $args[0]) : $args[0];
        return $this;
    }

    /*public function __set($name, $value)
    {
        $this->$name = $value;
    }*/
}

class FIELD
{
    public static function Text(int $length = 255)
    {
        return new CField('text', ['length' => $length]);
    }

    public static function Password(int $length = 255)
    {
        return new CField('password', ['length' => $length]);
    }

    public static function Email(int $length = 255)
    {
        return new CField('email', ['length' => $length]);
    }

    public static function Tel()
    {
        return new CField('tel');
    }

    public static function Textarea(int $length = 255)
    {
        return new CField('textarea', ['length' => $length]);
    }

    public static function Number(int $min = null, int $max = null)
    {
        if (is_null($min) && is_null($max)) {
            return new CField('number');
        } else {
            if (!is_null($min)) {
                return new CField('number', ['min' => $min]);
            } else {
                if (!is_null($max)) {
                    return new CField('number', ['max' => $max]);
                }
            }

            return new CField('number', ['min' => $min, 'max' => $max]);
        }
    }

    public static function Select(array $list)
    {
        return new CField('select', ['list' => $list]);
    }

    public static function Radio(array $choices = [])
    {
        return new CField('radio', ['list' => $choices]);
    }

    public static function Checkbox(array $choices = [])
    {
        return new CField('checkbox', ['list' => $choices]);
    }

    public static function File()
    {
        return new CField('file');
    }

    public static function Date()
    {
        return new CField('date');
    }

    public static function Time()
    {
        return new CField('time');
    }

    public static function DateTime()
    {
        return new CField('datetime');
    }

    public static function Foreign($props)
    {
        return new CField('foreign', $props);
    }

    public static function Label()
    {
        return new CField('label');
    }

    public static function Hidden()
    {
        return new CField('hidden');
    }
}

trait DeleteApproval{

    public function hasDeleteApproval(){
        $cn=class_basename($this);
        
        if(class_exists("\App\Models\$cn"."Destroy")){
            return true;
        }else{
            return false;
        }

    }
}