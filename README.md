#### Readable code, is reading less code.

This object is a proof-of-concept, and looks to minimise the amount of code that you need to write in order to achieve standard functionality that is expected of objects. This class takes advantage of array literals in PHP to describe how a property behaves, rather than simply what it's default value is.

```php
/**
 * An example property.
 *
 * The following is the default configuration assumed for a property. This
 * will automatically synthesize a getter method (getService) and a setter
 * (setService).
 */
protected $service = [
  self::READABLE => TRUE, // Has a public getter.
  self::WRITABLE => TRUE, // Has a public setter.
  self::DEFAULT_VALUE => NULL, // Default value is NULL.
  self::GETTER => 'getService', // Getter method is ->getService().
  self::SETTER => 'setService', // Setter method is ->setService().
];

// Is equivalent to writing:
protected $service = self::PROPERTY_PUBLIC;
```

This means that in order to write a simple value object with one property, only a single line of code is required to describe the properties behaviour.

```php
class ValueObject extends \GroovyCarrot\Object {
  protected $value = self::PROPERTY_PUBLIC + [self::TYPE => 'string'];
}
```

In contrast to:
```php
class ValueObject {
  /**
   * @var string
   */
  protected $value;
  
  /**
   * Getter for $value.
   *
   * @return string
   */
  public function getValue() {
    return $this->Value;
  }
  
  /**
   * Setter for $value.
   *
   * @param string $value
   * @return $this
   */
  public function setValue($value) {
    if (gettype($value) !== 'string') {
      throw new \InvalidArgumentException();
    }

    $this->value = $value;
    return $this;
  }
}
```

Both of these classes behave the same, and reading them conveys exactly the same amount of information: This class has one property, with both a setter and a getter, that accepts a string.

```php
$value = new \ValueObject();
$value->setValue('Hello world.');
echo $value->getValue(); // Hello world.
$value->setValue(['some' => 'value']); // InvalidArgumentException: expected instance of string, array given.
```

##### Huge performance regression
Unfortunately, while it will take you considerably less time to write your classes, since PHP has to determine whether or not to synthesize getters and setters at runtime (since there is no compile-time execution in PHP) there is a massive x12-16 performance regression when constructing objects, and a x2+ regression when calling methods. In addition to the performance implications, your IDE  performs autocomplete based on actual methods, and not methods computed at runtime, so you would not have autocomplete for any of your synthesized methods.
