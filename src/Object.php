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

    if ($args = func_get_args()) {
      call_user_func_array([$this, 'initialize'], $args);
    }
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
    if ($config[self::WRITABLE]) {
      $this->gcObjCreateSetterForVar($var, $config);
    }
    if ($config[self::READABLE]) {
      $this->gcObjCreateGetterForVar($var, $config);
    }

    $this->gcObjSetPropertyDefaultValue($var, $config);
  }

  /**
   * Create a setter method for a property.
   *
   * @param string $var
   * @param array $config
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

    $this->gcObjPropertyMap['setters'][$setter] = $var;
  }

  /**
   * Create a getter method for a property.
   *
   * @param string $var
   * @param array $config
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

    $this->gcObjPropertyMap['getters'][$getter] = $var;
  }

  /**
   * Get the default value for a property.
   *
   * If the property definition has self::DEFAULT_VALUE, then the
   * property is set to this value. Otherwise the property will be set to NULL.
   *
   * @param string $var
   * @param array $config
   */
  private function gcObjSetPropertyDefaultValue($var, array $config) {
    $default = NULL;
    if (isset($config[self::DEFAULT_VALUE])) {
      $default = $config[self::DEFAULT_VALUE];
    }
    $this->{$var} = $default;
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
    if (isset($this->gcObjPropertyMap['setters'][$name])) {
      if (empty($arguments)) {
        $this->gcWarnMissingArgument($name);
      }

      $var = $this->gcObjPropertyMap['setters'][$name];
      $this->{$var} = reset($arguments);
      return $this;
    }
    elseif (isset($this->gcObjPropertyMap['getters'][$name])) {
      $var = $this->gcObjPropertyMap['getters'][$name];
      return $this->{$var};
    }

    trigger_error('Call to undefined method ' . $this->className() . '::' . $name .'()', E_USER_ERROR);
  }

  /**
   * Determine if this object implements a method.
   *
   * @param string $method
   *
   * @return bool
   */
  public static function hasMethod($method) {
    return method_exists(get_called_class(), $method);
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
    return get_object_vars($this);
  }

  /**
   * Get the properties for this class.
   *
   * @return array
   */
  public static function classVars() {
    return get_class_vars(get_called_class());
  }

}

