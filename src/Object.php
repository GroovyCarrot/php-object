<?php
/**
 * @file
 * Object.php
 *
 * Created by Jake Wise 16/06/2016.
 *
 * You are permitted to use, modify, and distribute this file in accordance with
 * the terms of the license agreement accompanying it.
 */

namespace GroovyCarrot;

use GroovyCarrot\Debug\ObjectPropertyInfo;

/**
 * Class Object
 *
 * An extended object with support for synthesising getters and setters for
 * properties.
 */
abstract class Object {

  /**
   * Synthesises both a getter and a setter for the property.
   */
  const PROPERTY_PUBLIC = [self::WRITABLE => TRUE, self::READABLE => TRUE];

  /**
   * Synthesises only a getter for the property.
   */
  const PROPERTY_READONLY = [self::WRITABLE => FALSE, self::READABLE => TRUE];

  /**
   * Synthesises only a setter for the property.
   */
  const PROPERTY_WRITEONLY = [self::WRITABLE => TRUE, self::READABLE => FALSE];

  /**
   * Synthesises no getter or setter for the property.
   */
  const PROPERTY_INTERNAL = [self::WRITABLE => FALSE, self::READABLE => FALSE];

  /**
   * Set the default value of the property.
   * <code>
   * protected $property = self::PROPERTY_PUBLIC
   *                     + [self::DEFAULT_VALUE => 'Hello world.'];
   * </code>
   */
  const DEFAULT_VALUE = 'default';

  /**
   * Set the getter method of the property.
   * <code>
   * protected $property = self::PROPERTY_READONLY
   *                     + [self::GETTER => 'getProperty'];
   * </code>
   */
  const GETTER = 'getter';

  /**
   * Set the setter method of the property.
   * <code>
   * protected $property = self::PROPERTY_WRITEONLY
   *                     + [self::SETTER => 'setProperty'];
   * </code>
   */
  const SETTER = 'setter';

  /**
   * State whether the property is readable.
   *
   * If a property is readable, then it has a public getter.
   */
  const READABLE = 'readable';

  /**
   * State whether the property is writable.
   *
   * If a property is writable, then it has a public setter.
   */
  const WRITABLE = 'writable';

  /**
   * Strict typing for property.
   */
  const TYPE = 'type';

  /**
   * Internal property mapping information.
   */
  private $gcObjPropertyMap;

  /**
   * Object constructor.
   */
  public function __construct() {
    $this->gcObjPropertyMap = ['setters' => [], 'getters' => []];

    $vars = array_diff_key(get_object_vars($this), get_class_vars(__CLASS__));
    foreach ($vars as $var => $config) {
      $this->gcObjProcessConfig($var, $config);
    }

    call_user_func_array([$this, 'initialize'], func_get_args());
  }

  /**
   * Exposed initializer method.
   *
   * @return $this
   */
  protected function initialize() {
    return $this;
  }

  /**
   * Process the configuration for a property.
   *
   * @param $var
   * @param array|mixed $config
   *   An array is expected, with information defining how this property
   *   behaves. If
   *
   * @throws \Exception
   */
  private function gcObjProcessConfig($var, $config) {
    if ($config === NULL || $config === []) {
      trigger_error(get_class($this) . "::\${$var} is not configured. No getters and setters have been synthesised for this property, you should use ::PROPERTY_INTERNAL instead.", E_USER_WARNING);
      return;
    }

    if (!is_array($config)) {
      trigger_error(get_class($this) . "::\${$var} has an invalid property definition. When using GroovyCarrot\\Object, all properties must be arrays which describe the property (e.g. ::PROPERTY_PUBLIC).", E_USER_ERROR);
      return;
    }

    $config = $config + self::PROPERTY_PUBLIC;
    $setter = NULL;
    if ($config[self::WRITABLE]) {
      $setter = $this->gcObjCreateSetterForVar($var, $config);
    }
    if ($config[self::READABLE]) {
      $getter = $this->gcObjCreateGetterForVar($var, $config);
    }

    if (isset($config[self::TYPE])) {
      $this->gcObjPropertyMap[self::TYPE][$var] = $config[self::TYPE];
    }

    $this->gcObjSetPropertyDefaultValue($var, $config, $setter);
  }

  /**
   * Create a setter method for a property.
   *
   * @param string $var
   * @param array $config
   *
   * @return string
   *   The setter method name.
   */
  private function gcObjCreateSetterForVar($var, array $config) {
    if (isset($config[self::SETTER])) {
      $setter = $config[self::SETTER];
      if (!is_string($setter) || !preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $setter)) {
        throw new \InvalidArgumentException("Setter for variable {$var} must be a valid PHP method name.");
      }
    }
    else {
      $setter = 'set' . ucfirst($var);
    }

    $this->gcObjPropertyMap[self::SETTER][$setter] = $var;
    return $setter;
  }

  /**
   * Create a getter method for a property.
   *
   * @param string $var
   * @param array $config
   *
   * @return string
   *   The getter method name.
   */
  private function gcObjCreateGetterForVar($var, array $config) {
    if (isset($config[self::GETTER])) {
      $getter = $config[self::GETTER];
      if (!is_string($getter) || !preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $getter)) {
        throw new \InvalidArgumentException("Getter for variable {$var} must be a valid PHP method name.");
      }
    }
    else {
      $getter = 'get' . ucfirst($var);
    }

    $this->gcObjPropertyMap[self::GETTER][$getter] = $var;
    return $getter;
  }

  /**
   * Get the default value for a property.
   *
   * If the property definition has self::DEFAULT_VALUE, then the
   * property is set to this value. Otherwise the property will be set to NULL.
   *
   * @param string $var
   * @param array $config
   * @param string|null $setter
   *   If a setter is specified then the setter will be used, rather than direct
   *   assignment. This will invoke any type checks.
   */
  private function gcObjSetPropertyDefaultValue($var, array $config, $setter = NULL) {
    $default = NULL;
    if (isset($config[self::DEFAULT_VALUE])) {
      $default = $config[self::DEFAULT_VALUE];
    }

    if ($setter) {
      call_user_func_array([$this, $setter], [$default]);
    }
    else {
      $this->{$var} = $default;
    }
  }

  /**
   * Warn the user that a method was called with a missing argument.
   *
   * @param string $method
   * @param int $n
   *   Offset of argument missing.
   */
  private function gcWarnMissingArgument($method, $n = 1) {
    $last_call = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
    trigger_error('Missing argument ' . $n . ' for ' . $method . ', called in ' . $last_call['file'] . ' on line ' . $last_call['line'] . ' and defined in ' . $last_call['class'] . '->' . $last_call['function'] . '().', E_USER_ERROR);
  }

  /**
   * Magic call method, invoked when the method does not exist on the object.
   *
   * This method is used to synthesise getters and setters on the object.
   *
   * @param string $name
   * @param array $arguments
   *
   * @return mixed|void
   */
  public function __call($name, array $arguments) {
    if (isset($this->gcObjPropertyMap[self::SETTER][$name])) {
      if (empty($arguments)) {
        $this->gcWarnMissingArgument($name);
      }

      $var = $this->gcObjPropertyMap[self::SETTER][$name];
      $value = reset($arguments);
      $this->gcObjCheckType($value, $var);

      $this->{$var} = $value;
      return $this;
    }
    elseif (isset($this->gcObjPropertyMap[self::GETTER][$name])) {
      $var = $this->gcObjPropertyMap[self::GETTER][$name];
      return $this->{$var};
    }

    trigger_error('Call to undefined method ' . $this->className() . '::' . $name .'()', E_USER_ERROR);
  }

  /**
   * Check that a provided value matches a required type for a property.
   *
   * @param mixed $value
   * @param string $var
   */
  private function gcObjCheckType($value, $var) {
    if (!isset($this->gcObjPropertyMap[self::TYPE][$var])) {
      return;
    }

    $type = $this->gcObjPropertyMap[self::TYPE][$var];

    if (!$value instanceof $type) {
      throw new \InvalidArgumentException("Value for property {$var} must be of type {$type}.");
    }
  }

  /**
   * Determine if this class implements a method.
   *
   * @param string $method
   *
   * @return bool
   */
  public static function hasClassMethod($method) {
    return method_exists(get_called_class(), $method);
  }

  /**
   * Determine if this object implements a method.
   *
   * @param string $method
   *
   * @return bool
   */
  public function hasMethod($method) {
    return $this->hasClassMethod($method)
           || isset($this->gcObjPropertyMap[self::GETTER][$method])
           || isset($this->gcObjPropertyMap[self::SETTER][$method]);
  }

  /**
   * The name of the class.
   *
   * @return string
   */
  public static function className() {
    return get_called_class();
  }

  /**
   * The name of the parent class.
   *
   * @return string
   */
  public static function parentClass() {
    return get_parent_class(get_called_class());
  }

  /**
   * Get the properties for this object.
   *
   * @return array
   */
  public function objectVars() {
    $vars = get_object_vars($this);
    unset($vars['gcObjPropertyMap']);
    return $vars;
  }

  /**
   * Get the properties for this class.
   *
   * @return array
   */
  public static function classVars() {
    return get_class_vars(get_called_class());
  }

  /**
   * Debug information magic method.
   *
   * @return array
   */
  public function __debugInfo() {
    $data = [];
    foreach ($this->objectVars() as $var => $value) {
      $data[$var] = new ObjectPropertyInfo($var, $value, $this->gcObjPropertyMap);
    }
    return $data;
  }

}

