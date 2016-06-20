<?php
/**
 * @file
 * communicator
 * ObjectPropertyInfo.php
 *
 * Created by Jake Wise 20/06/2016.
 *
 * You are permitted to use, modify, and distribute this file in accordance with
 * the terms of the license agreement accompanying it.
 */

namespace GroovyCarrot\Debug;

use GroovyCarrot\Object;

/**
 * Class ObjectPropertyInfo
 * @package GroovyCarrot\Object
 */
class ObjectPropertyInfo {

  /**
   * The name of the property being described.
   *
   * @var string
   */
  protected $name;

  /**
   * The type of the property.
   *
   * @var string
   */
  protected $type;

  /**
   * The synthesised getter for the property.
   *
   * @var string
   */
  protected $getter;

  /**
   * The synthesised setter for the property.
   *
   * @var string
   */
  protected $setter;

  /**
   * The current value of the property.
   *
   * @var mixed
   */
  protected $value;

  /**
   * ObjectPropertyInfo constructor.
   *
   * @param string $var
   * @param mixed $value
   * @param array $objPropertyMap
   *   The object's property mapping information.
   */
  public function __construct($var, $value, $objPropertyMap) {
    $this->name = $var;
    $this->value = $value;
    $this->type = isset($objPropertyMap[Object::TYPE][$var]) ? $objPropertyMap[Object::TYPE][$var] : 'mixed';
    $this->getter = array_search($var, $objPropertyMap[Object::GETTER]);
    $this->setter = array_search($var, $objPropertyMap[Object::SETTER]);
  }

}
