<?php

namespace App;

// вспомагательный   класс  для   вывода  простых  списков
class DataItem implements \Zippy\Interfaces\DataItem
{

    public $id;
    protected $fields = array();

    function __construct($row = null) {

        if (is_array($row)) {
            $this->fields = array_merge($this->fields, $row);
        }
    }

    public final function __set($name, $value) {
        $this->fields[$name] = $value;
    }

    public final function __get($name) {
        return $this->fields[$name];
    }

    public function getID() {
        return $this->id;
    }

    public function getData() {
        return $this->fields;
    }

}
