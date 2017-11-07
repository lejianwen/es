<?php
/**
 * Created by PhpStorm.
 * User: Lejianwen
 * Date: 2017/11/4
 * Time: 15:41
 */

namespace Ljw\Es;

/**
 * Class Model
 * @package App\Library\Es
 * @method static Query where($name, $exp = null, $value = null)
 * @method static Query group($field, $_query = null, $alias = '')
 */
class Model
{
    protected $index;
    protected $alias;
    protected $type;

    /**
     * 索引设置
     * @var array
     */
    protected $setting = [
        'number_of_shards'   => 1,
        'number_of_replicas' => 1
    ];
    /**
     * 索引参数
     * @var array
     */
    protected $properties;
    /**
     * @var int 搜索结果总数
     */
    protected $count = 0;
    /**
     * @var array query返回的查询结果
     */
    protected $origin_data;

    public function __construct($config = [])
    {
        $model_name = strtolower(basename(static::class));
        if (!empty($config)) {
            $this->alias or $this->setAlias($config['alias']);
            $this->index or $this->setIndex($config['index']);
            $this->type or $this->setType($config['type']);
        } else {
            $this->alias or $this->setAlias($model_name . '_alias');
            $this->index or $this->setIndex($model_name . '_index');
            $this->type or $this->setType($model_name . '_type');
        }

    }

    public static function single()
    {
        static $model;
        if (!$model) {
            $model = new static();
        }
        return $model;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function setIndex($index)
    {
        $this->index = $index;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getSetting()
    {
        return $this->setting;
    }

    protected function filterData($data)
    {
        foreach ($data as $key => $value) {
            if (!in_array($key, array_keys($this->getProperties()))) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * newQuery
     * @return Query
     * @author Lejianwen
     */
    protected function newQuery()
    {
        return new Query($this);
    }

    /**
     * query
     * @return Query
     * @author Lejianwen
     */
    public static function query()
    {
        return (new static)->newQuery();
    }


    public static function createIndex()
    {
        $model = static::single();
        $params = [
            'index' => $model->getIndex(),
            'body'  => [
                'settings' => $model->getSetting(),
                'aliases'  => [$model->getAlias() => new \stdClass()],
                'mappings' => [
                    $model->getType() => [
                        '_all'       => ['enabled' => false],
                        'properties' => $model->getProperties()
                    ],
                ]
            ]
        ];
        return Es::init()->indices()->create($params);
    }

    /**
     * deleteIndex
     * @return array
     * @author Lejianwen
     */
    public static function deleteIndex()
    {
        $model = static::single();
        $delete_params = [
            'index' => $model->getIndex()
        ];
        return Es::init()->indices()->delete($delete_params);
    }

    public static function updateIndex()
    {
        $model = static::single();
        $params = [
            'index' => $model->getIndex(),
            'type'  => $model->getType(),
            'body'  => ['properties' => $model->getProperties()]

        ];
        return Es::init()->indices()->putMapping($params);
    }

    /**
     * __callStatic
     * @param $func
     * @param $params
     * @return Query
     * @author Lejianwen
     */
    public static function __callStatic($func, $params)
    {
        $query = static::query();
        return $query->$func(...$params);
    }

    /**
     * add
     * @param $id
     * @param $data
     * @return array|bool
     * @author Lejianwen
     */
    public static function add($id, $data)
    {
        $model = static::single();
        $data = $model->filterData($data);
        $re = Es::init()->create([
            'index' => $model->getAlias() ?: $model->getIndex(),
            'type'  => $model->getType(),
            'id'    => $id,
            'body'  => $data
        ]);
        return $re;
    }

    public static function update($id, $data)
    {
        $model = static::single();
        $data = $model->filterData($data);
        $re = Es::init()->update([
            'index' => $model->getAlias() ?: $model->getIndex(),
            'type'  => $model->getType(),
            'id'    => $id,
            'body'  => ['doc' => $data]
        ]);
        return $re;
    }

    public static function exists($id)
    {
        try {
            $model = static::single();
            $data = Es::init()->exists([
                'index' => $model->getAlias() ?: $model->getIndex(),
                'type'  => $model->getType(),
                'id'    => $id
            ]);
            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function delete($id)
    {
        try {
            $model = static::single();
            $data = Es::init()->delete([
                'index' => $model->getAlias() ?: $model->getIndex(),
                'type'  => $model->getType(),
                'id'    => $id
            ]);
            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function find($id)
    {
        $model = static::single();
        $data = Es::init()->get([
            'index' => $model->getAlias() ?: $model->getIndex(),
            'type'  => $model->getType(),
            'id'    => $id
        ]);
        return $data['_source'];
    }

    public function setData($result)
    {
        $this->origin_data = $result['source'];
        if (isset($result['total'])) {
            $this->count = $result['total'];
        }
        return $this;
    }

    public function getData()
    {
        return $this->origin_data;
    }

    public function getCount()
    {
        return $this->count;
    }

    public function __toString()
    {
        return json_encode($this->origin_data);
    }
}