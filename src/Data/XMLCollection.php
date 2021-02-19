<?php

namespace ACFBentveld\XML\Data;

use ACFBentveld\XML\Casts\Cast;
use ACFBentveld\XML\Casts\PendingCast;
use ACFBentveld\XML\Transformers\PendingTransform;
use ACFBentveld\XML\Transformers\Transformable;
use ACFBentveld\XML\XML;
use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IteratorAggregate;
use JsonSerializable;

class XMLCollection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    use Transformable;

    protected $optimize = false;
    /**
     * @var array
     */
    private $items;

    /**
     * @var XMLElement the loaded xml
     */
    private $raw;

    /**
     * XMLCollection constructor.
     *
     * @param $items
     */
    public function __construct($items)
    {
        $this->raw = $items;
        $this->items = new XMLObject((array) $items);
    }

    /**
     * Returns the raw xml data.
     *
     * @return \ACFBentveld\XML\Data\XMLElement
     * @author Amando Vledder <amando@nugtr.nl>
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Get the xml as a collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collect(): Collection
    {
        return new Collection(json_decode(json_encode($this->items)));
    }

    /**
     * Pass overloaded methods to the items.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->items->{$name}(...$arguments);
    }

    /**
     * Get a item from the xml.
     *
     * @param $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->items->{$key};
    }

    /**
     * Update a value in the XML.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $this->items->{$name} = $value;
    }

    /**
     * Check if an item in the xml isset.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->items->{$name});
    }

    /**
     * Alias for transform.
     *
     * @see transform
     */
    public function expect($key): PendingTransform
    {
        return $this->transform($key);
    }

    /**
     * Start a transform for the given key.
     *
     * @param $key
     *
     * @return \ACFBentveld\XML\Transformers\PendingTransform
     */
    public function transform($key): PendingTransform
    {
        return new PendingTransform($this, function ($transformer) use ($key) {
            $this->items[$key] = is_callable($transformer) ?
                $transformer($this->items[$key])
                : $transformer::apply($this->items[$key]);

            return $this;
        });
    }

    /**
     * Start a cast for the given key.
     *
     * @param $key
     *
     * @return \ACFBentveld\XML\Casts\PendingCast
     */
    public function cast($key): PendingCast
    {
        return new PendingCast($this, function ($cast) use ($key) {
            if (is_array($this->items[$key])) {
                $this->items[$key] = array_map(function ($item) use ($cast) {
                    return Cast::to((array) $item, $cast);
                }, $this->items[$key]);

                return $this;
            }
            $this->items[$key] = Cast::to((array) $this->items[$key], $cast);

            return $this;
        });
    }

    public function optimize($type = XML::OPTIMIZE_UNDERSCORE)
    {
        $this->optimize = $type;

        return $this;
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed $key
     *
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed $key
     * @param  mixed $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            }

            return $value;
        }, (array) $this->get()->toArray());
    }

    /**
     * Get the xml.
     *
     * @return \ACFBentveld\XML\Data\XMLObject
     */
    public function get()
    {
        $items = $this->applyOptimize();

        return $this->applyTransformers($items);
    }

    /**
     * Apply to optimization.
     *
     * @return \ACFBentveld\XML\Data\XMLObject|array
     */
    private function applyOptimize()
    {
        if ($this->optimize === XML::OPTIMIZE_UNDERSCORE) {
            $method = function ($key) {
                $key = strtolower(str_replace('.', '_', $key));
                $key = str_replace(' ', '_', $key);

                return str_replace('-', '_', $key);
            };
        } elseif ($this->optimize === XML::OPTIMIZE_CAMELCASE) {
            $method = function ($key) {
                return Str::camel(str_replace('.', '_', $key));
            };
        } else {
            return $this->items;
        }

        return new XMLObject($this->loopOptimize($this->items->toArray(), $method));
    }

    /**
     * Recursively optimize the xml using the chosen method.
     *
     * @param          $items
     * @param \Closure $callback
     *
     * @return array
     */
    private function loopOptimize($items, \Closure $callback)
    {
        $items = (array) $items;
        $data = [];
        if (! count($items)) {
            return [];
        }
        foreach ($items as $key => $value) {
            if (is_object($value)) {
                if (strpos(get_class($value), 'XMLElement') !== false) {
                    $data[$callback($key)] = new XMLObject($this->loopOptimize($value, $callback));
                }
            } elseif (is_array($value)) {
                $data[$callback($key)] = $this->loopOptimize($value, $callback);
            } else {
                $data[$callback($key)] = $value;
            }
        }

        return $data;
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Arrayable ? $value->toArray() : $value;
        }, (array) $this->get());
    }
}
