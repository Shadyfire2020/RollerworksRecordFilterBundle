<?php

/**
 * This file is part of the RollerworksRecordFilterBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\Bundle\RecordFilterBundle;

/**
 * FieldSet.
 *
 * Holds the set of filtering fields and there configuration.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class FieldSet implements \Countable, \IteratorAggregate
{
    /**
     * @var FilterField[]
     */
    protected $fields = array();

    /**
     * @var string
     */
    private $name;

    /**
     * Constructor.
     *
     * @param string|null $name Optional fieldSet name (must be legal a class-name).
     *
     * @throws \InvalidArgumentException When the name is invalid
     */
    public function __construct($name = null)
    {
        if (null !== $name && !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid fieldSet name "%s" (must be a legal class-name without a namespace).', $name));
        }

        $this->name = $name;
    }

    /**
     * Returns a new FieldSet instance.
     *
     * @param string|null $name
     *
     * @return FieldSet
     */
    public static function create($name = null)
    {
        return new static($name);
    }

    /**
     * Returns the name of the fieldSet.
     *
     * @return null|string
     */
    public function getSetName()
    {
        return $this->name;
    }

    /**
     * Set an filtering field.
     *
     * @param string      $name
     * @param FilterField $config
     *
     * @return self
     *
     * @throws \InvalidArgumentException When the name is empty or invalid
     */
    public function set($name, FilterField $config)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('FieldName can not be empty.');
        }

        if (false !== strpos($name, "'") || false !== strpos($name, '"')) {
            throw new \InvalidArgumentException(sprintf('FieldName "%s" can not contain quotes.', $name));
        }

        $this->fields[$name] = $config;

        return $this;
    }

    /**
     * Replaces the field.
     *
     * Same as {@see set()}, but throws an exception when there is no field with that name.
     *
     * @param string      $name
     * @param FilterField $config
     *
     * @return self
     *
     * @throws \RuntimeException When there is no field with the given name
     */
    public function replace($name, FilterField $config)
    {
        if (!isset($this->fields[$name])) {
            throw new \RuntimeException(sprintf('Unable to replace none existent field: %s', $name));
        }

        $this->fields[$name] = $config;

        return $this;
    }

    /**
     * Removes the field from the set.
     *
     * @param string $name
     *
     * @return self
     */
    public function remove($name)
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
        }

        return $this;
    }

    /**
     * Returns the configuration of the requested field.
     *
     * @param string $name
     *
     * @return FilterField
     *
     * @throws \RuntimeException When there is no field with the given name
     */
    public function get($name)
    {
        if (!isset($this->fields[$name])) {
            throw new \RuntimeException(sprintf('Unable to find filter field: %s', $name));
        }

        return $this->fields[$name];
    }

    /**
     * Returns all the registered fields.
     *
     * @return FilterField[] [field-name] => {\Rollerworks\Bundle\RecordFilterBundle\FilterField object})
     */
    public function all()
    {
        return $this->fields;
    }

    /**
     * Returns whether there is a field with the given name.
     *
     * @param string $name
     *
     * @return boolean
     */
    public function has($name)
    {
        return isset($this->fields[$name]);
    }

    /**
     * Gets the current FieldSet as an Iterator that includes all Fields.
     *
     * It implements \IteratorAggregate.
     *
     * @see all()
     *
     * @return \ArrayIterator An \ArrayIterator object for iterating over fields
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->fields);
    }

    /**
     * Gets the number of Fields in this set.
     *
     * @return integer The number of fields
     */
    public function count()
    {
        return count($this->fields);
    }
}
