<?php
/**
 * Created by PhpStorm.
 * User: tianyi
 * Date: 3/9/2017
 * Time: 10:26
 */

namespace Goenitz\SimpleModel;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;

class Model implements Jsonable, Arrayable, \ArrayAccess
{
    public static $connection;

    protected $table;
    protected $attributes = [];
    protected $fillable = [];
    protected $identifier = 'id';
    protected $exist = false;
    protected $hidden = [];

    private $builder;

    function __construct(array $attributes = [], $exist = false)
    {
        if (is_null($this->table)) {
            $this->table = str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));
        }
        $this->attributes = $attributes;
        $this->exist = $exist;
        $this->builder = new QueryBuilder(self::$connection, get_called_class());
    }

    public static function connect(array $config)
    {
        if (!isset($config['port'])) {
            $config['port'] = 3306;
        }
        $connection = new \PDO("mysql:host={$config['host']};dbname={$config['database']};port={$config['port']}",
            $config['username'],
            $config['password']);
        self::$connection = $connection;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    protected function all()
    {
        return $this->builder->get();
    }

    protected function create(array $attributes, $fillable = true)
    {
        if ($fillable) {
            $attributes = collect($attributes)->only($this->fillable);
        }
        return $this->builder->newQuery()->create($attributes);
    }

    protected function createMany(array $rows)
    {
        return collect($rows)->map(function ($row) {
            return $this->create($row);
        });
    }

    public function save()
    {
        if (!$this->exist) {
            return $this->create($this->attributes, false);
        } else {
            $attributes = collect($this->attributes)->except([$this->identifier])->toArray();
            return $this->builder->update($attributes, [
                $this->identifier, $this->{$this->identifier}
            ]);
        }
    }

    public function update(array $attributes = [], $fillable = true)
    {
        $diffAttribute = collect($attributes)->diff($this->attributes);
        if ($diffAttribute->count() == 0) {
            return true;
        }
        if ($fillable) {
            $diffAttribute = $diffAttribute->only($this->fillable);
        }
        return $this->builder->update($diffAttribute->toArray(), [
            $this->identifier , $this->{$this->identifier}
        ]);
    }

    protected function find($value)
    {
        return $this->builder->where($this->identifier, $value)->first();
    }

    protected function destroy($ids)
    {
        $ids = is_array($ids) ? $ids : func_get_args();
        $count = 0;
        foreach ($ids as $id) {
            $result = $this->builder->newQuery()->where($this->identifier, $id)->delete();
            if ($result) {
                $count ++;
            }
        }
        return $count;
    }

    public function delete()
    {
        if ($this->exist = true) {
            $identifier = $this->identifier;
            $result = $this->builder->where([
                $identifier, $this->$identifier
            ])->delete();
            if ($result) {
                $this->exist = false;
            }
            return $result;
        }
        return false;
    }

    public static function __callStatic($name, $args)
    {
        if (in_array($name, ['create', 'createMany', 'find', 'all', 'destroy'])) {
            return (new static)->$name(...$args);
        } else {
            return (new static)->builder->$name(...$args);
        }
    }

    function __get($name)
    {
        $newName = studly_case($name);
        if (method_exists($this, "get{$newName}Attribute")) {
            return $this->{"get{$newName}Attribute"}();
        }
        return $this->attributes[$name];
    }

    function __set($name, $value)
    {
        if ($name == $this->identifier) {
            throw new \Exception("The {$name} attribute is read-only.");
        }
        $newName = studly_case($name);
        if(method_exists($this, "set{$newName}Attribute")) {
            $this->{"set{$newName}Attribute"}($value);
        } else {
            $this->attributes[$name] = $value;
        }
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return collect($this->attributes)->except($this->hidden)->toJson();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return collect($this->attributes)->except($this->hidden)->toArray();
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    function __toString()
    {
        return $this->toJson();
    }
}