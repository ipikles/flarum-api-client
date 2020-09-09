<?php

namespace Maicol07\Flarum\Api\Models;

use ArrayAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Maicol07\Flarum\Api\Client;
use Maicol07\Flarum\Api\Exceptions\InvalidArgumentException;
use Maicol07\Flarum\Api\Fluent;
use Maicol07\Flarum\Api\Resource\Item;

abstract class Model
{
    /**
     * @var Client
     */
    protected static $dispatcher;
    /**
     * @var int|null
     */
    protected $id;
    /**
     * @var array
     */
    protected $attributes = [];
    
    public function __construct(array $attributes = [])
    {
        if (Arr::has($attributes, 'id')) {
            $this->id = Arr::pluck($attributes, 'id');
        }
        
        $this->attributes = $attributes;
    }
    
    public static function fromResource(Item $item)
    {
        $class = sprintf("%s\\%s", __NAMESPACE__, Str::camel(Str::singular($item->type)));
        
        if (class_exists($class)) {
            $response = new $class($item->attributes);
            
            if ($item->id) {
                $response->id = $item->id;
            }
            
            return $response;
        }
        
        throw new InvalidArgumentException("Resource type {$item->type} could not be migrated to Model");
    }
    
    /**
     * @param Client $dispatcher
     */
    public static function setDispatcher(Client $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }
    
    /**
     * @return Client
     */
    public static function getDispatcher(): Client
    {
        return self::$dispatcher;
    }
    
    /**
     * Resource type.
     *
     * @return string
     */
    public function type(): string
    {
        return Str::plural(Str::lower(
            Str::replaceFirst(__NAMESPACE__ . '\\', '', static::class)
        ));
    }
    
    /**
     * Generated resource item.
     *
     * @return Item
     */
    public function item(): Item
    {
        return new Item([
            'type' => $this->type(),
            'attributes' => $this->attributes
        ]);
    }
    
    /**
     * @return array
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
    
    /**
     * @param Model $relation
     */
    public function addRelation(Model $relation): void
    {
    }
    
    /**
     * @return Fluent
     */
    public function baseRequest(): Fluent
    {
        // Set resource type.
        $dispatch = call_user_func_array([
            static::$dispatcher,
            $this->type()
        ], []);
        
        // Set resource Id.
        if ($this->id) {
            $dispatch->id($this->id);
        }
        
        return $dispatch;
    }
    
    /**
     * @return mixed
     */
    public function delete()
    {
        if (!$this->id) {
            throw new InvalidArgumentException("Resource doesn't exist.");
        }
        
        return $this->baseRequest()->delete()->request();
    }
    
    /**
     * Creates or updates a resource.
     *
     * @return mixed
     */
    public function save()
    {
        return $this->baseRequest()
            // Set method and variables.
            ->post(
                $this->item()->toArray()
            )
            // Send request.
            ->request();
    }
    
    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if ($name === 'id') {
            $this->id = $value;
        } else {
            $this->attributes[$name] = $value;
        }
    }
    
    /**
     * @param $name
     * @return array|ArrayAccess|mixed
     */
    public function __get($name)
    {
        return Arr::get($this->attributes, $name);
    }
}
